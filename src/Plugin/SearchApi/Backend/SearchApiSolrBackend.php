<?php

/**
 * @file
 * Contains \Drupal\search_api_solr\Plugin\SearchApi\Backend\SearchApiSolrBackend.
 */

namespace Drupal\search_api_solr\Plugin\SearchApi\Backend;

use Drupal\Core\Config\Config;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\FilterInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\Utility;
use Solarium\Client;
use Solarium\Core\Client\Request;
use Solarium\Core\Query\Helper;
use Solarium\QueryType\Select\Query\Query;
use Solarium\Exception\HttpException;
use Solarium\QueryType\Select\Result\Result;
use Solarium\QueryType\Update\Query\Document\Document;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @SearchApiBackend(
 *   id = "search_api_solr",
 *   label = @Translation("Solr"),
 *   description = @Translation("Index items using an Apache Solr search server.")
 * )
 */
class SearchApiSolrBackend extends BackendPluginBase {

  /**
   * The date format that Solr uses, in PHP date() syntax.
   */
  const SOLR_DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

  /**
   * A connection to the Solr server.
   *
   * @var \Solarium\Client
   */
  protected $solr;

  /**
   * Static cache for getFieldNames().
   *
   * @var array
   */
  protected $fieldNames = array();

  /**
   * Metadata describing fields on the Solr/Lucene index.
   *
   * @see SearchApiSolrBackend::getFields().
   *
   * @var array
   */
  protected $fields;

  /**
   * A Solarium Update query.
   *
   * @var \Solarium\QueryType\Update\Query\Query
   */
  protected static $updateQuery;

  /**
   * A Solarium query helper.
   *
   * @var \Solarium\Core\Query\Helper
   */
  protected static $queryHelper;

  /**
   * Saves whether a commit operation was already scheduled for this server.
   *
   * @var bool
   */
  protected $commitScheduled = FALSE;

  /**
   * Request handler to use for this search query.
   *
   * @var string
   */
  protected $request_handler = NULL;

  /**
   * Stores Solr system information.
   *
   * @var array
   */
  protected $systemInfo;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * A config object for 'search_api_solr.settings'.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $searchApiSolrSettings;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, FormBuilderInterface $form_builder, ModuleHandlerInterface $module_handler, Config $search_api_solr_settings) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->formBuilder = $form_builder;
    $this->moduleHandler = $module_handler;
    $this->searchApiSolrSettings = $search_api_solr_settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('module_handler'),
      $container->get('config.factory')->get('search_api_solr.settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'scheme' => 'http',
      'host' => 'localhost',
      'port' => '8983',
      'path' => '/solr',
      'http_user' => '',
      'http_pass' => '',
      'excerpt' => FALSE,
      'retrieve_data' => FALSE,
      'highlight_data' => FALSE,
      'skip_schema_check' => FALSE,
      'solr_version' => '',
      'http_method' => 'AUTO',
      // Default to TRUE for new servers, but to FALSE for existing ones.
      'clean_ids' => $this->configuration ? FALSE : TRUE,
      'site_hash' => $this->configuration ? FALSE : TRUE,
      'autocorrect_spell' => TRUE,
      'autocorrect_suggest_words' => TRUE,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if (!$this->server->isNew()) {
      // Editing this server
      $form['server_description'] = array(
        '#type' => 'item',
        '#title' => $this->t('Solr server URI'),
        '#description' => $this->getServerLink(),
      );
    }

    if (!$this->configuration['clean_ids']) {
      if (\Drupal::moduleHandler()->moduleExists('advanced_help')) {
        $variables['@url'] = url('help/search_api_solr/README.txt');
      }
      else {
        $variables['@url'] = url(drupal_get_path('module', 'search_api_solr') . '/README.txt');
      }
      $description = $this->t('Change Solr field names to be more compatible with advanced features. Doing this leads to re-indexing of all indexes on this server. See <a href="@url">README.txt</a> for details.', $variables);
      $form['clean_ids_form'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Clean field identifiers'),
        '#description' => $description,
        '#collapsible' => TRUE,
      );
      $form['clean_ids_form']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Switch to clean field identifiers'),
        '#submit' => array('_search_api_solr_switch_to_clean_ids'),
      );
    }
    $form['clean_ids'] = array(
      '#type' => 'value',
      '#value' => $this->configuration['clean_ids'],
    );

    if (!$this->configuration['site_hash']) {
      $description = $this->t('If you want to index content from multiple sites on a single Solr server, you should enable the multi-site compatibility here. Note, however, that this will completely clear all search indexes (from this site) lying on this server. All content will have to be re-indexed.');
      $form['site_hash_form'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Multi-site compatibility'),
        '#description' => $description,
        '#collapsible' => TRUE,
      );
      $form['site_hash_form']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Turn on multi-site compatibility and clear all indexes'),
        '#submit' => array('_search_api_solr_switch_to_site_hash'),
      );
    }
    $form['site_hash'] = array(
      '#type' => 'value',
      '#value' => $this->configuration['site_hash'],
    );

    $form['scheme'] = array(
      '#type' => 'select',
      '#title' => $this->t('HTTP protocol'),
      '#description' => $this->t('The HTTP protocol to use for sending queries.'),
      '#default_value' => $this->configuration['scheme'],
      '#options' => array(
        'http' => 'http',
        'https' => 'https',
      ),
    );

    $form['host'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Solr host'),
      '#description' => $this->t('The host name or IP of your Solr server, e.g. <code>localhost</code> or <code>www.example.com</code>.'),
      '#default_value' => $this->configuration['host'],
      '#required' => TRUE,
    );
    $form['port'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Solr port'),
      '#description' => $this->t('The Jetty example server is at port 8983, while Tomcat uses 8080 by default.'),
      '#default_value' => $this->configuration['port'],
      '#required' => TRUE,
    );
    $form['path'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Solr path'),
      '#description' => $this->t('The path that identifies the Solr instance to use on the server.'),
      '#default_value' => $this->configuration['path'],
    );

    $form['http'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Basic HTTP authentication'),
      '#description' => $this->t('If your Solr server is protected by basic HTTP authentication, enter the login data here.'),
      '#collapsible' => TRUE,
      '#collapsed' => empty($this->configuration['http_user']),
    );
    $form['http']['http_user'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $this->configuration['http_user'],
    );
    $form['http']['http_pass'] = array(
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t('If this field is left blank and the HTTP username is filled out, the current password will not be changed.'),
    );

    $form['advanced'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['advanced']['excerpt'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Return an excerpt for all results'),
      '#description' => $this->t("If search keywords are given, use Solr's capabilities to create a highlighted search excerpt for each result. " .
          'Whether the excerpts will actually be displayed depends on the settings of the search, though.'),
      '#default_value' => $this->configuration['excerpt'],
    );
    $form['advanced']['retrieve_data'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Retrieve result data from Solr'),
      '#description' => $this->t('When checked, result data will be retrieved directly from the Solr server. ' .
          'This might make item loads unnecessary. Only indexed fields can be retrieved. ' .
          'Note also that the returned field data might not always be correct, due to preprocessing and caching issues.'),
      '#default_value' => $this->configuration['retrieve_data'],
    );
    $form['advanced']['highlight_data'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Highlight retrieved data'),
      '#description' => $this->t('When retrieving result data from the Solr server, try to highlight the search terms in the returned fulltext fields.'),
      '#default_value' => $this->configuration['highlight_data'],
    );
    $form['advanced']['skip_schema_check'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Skip schema verification'),
      '#description' => $this->t('Skip the automatic check for schema-compatibillity. Use this override if you are seeing an error-message about an incompatible schema.xml configuration file, and you are sure the configuration is compatible.'),
      '#default_value' => $this->configuration['skip_schema_check'],
    );
    $form['advanced']['solr_version'] = array(
      '#type' => 'select',
      '#title' => $this->t('Solr version override'),
      '#description' => $this->t('Specify the Solr version manually in case it cannot be retrived automatically. The version can be found in the Solr admin interface under "Solr Specification Version" or "solr-spec"'),
      '#options' => array(
        '' => $this->t('Determine automatically'),
        '1' => '1.4',
        '3' => '3.x',
        '4' => '4.x',
      ),
      '#default_value' => $this->configuration['solr_version'],
    );
    // Highlighting retrieved data only makes sense when we retrieve data.
    // (Actually, internally it doesn't really matter. However, from a user's
    // perspective, having to check both probably makes sense.)
    $form['advanced']['highlight_data']['#states']['invisible']
        [':input[name="options[form][advanced][retrieve_data]"]']['checked'] = FALSE;

    $form['advanced']['http_method'] = array(
      '#type' => 'select',
      '#title' => $this->t('HTTP method'),
      '#description' => $this->t('The HTTP method to use for sending queries. GET will often fail with larger queries, while POST should not be cached. AUTO will use GET when possible, and POST for queries that are too large.'),
      '#default_value' => $this->configuration['http_method'],
      '#options' => array(
        'AUTO' => $this->t('AUTO'),
        'POST' => 'POST',
        'GET' => 'GET',
      ),
    );

    if (\Drupal::moduleHandler()->moduleExists('search_api_autocomplete')) {
      $form['advanced']['autocomplete'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Autocomplete'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      );
      $form['advanced']['autocomplete']['autocorrect_spell'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Use spellcheck for autocomplete suggestions'),
        '#description' => $this->t('If activated, spellcheck suggestions ("Did you mean") will be included in the autocomplete suggestions. Since the used dictionary contains words from all indexes, this might lead to leaking of sensitive data, depending on your setup.'),
        '#default_value' => $this->configuration['autocorrect_spell'],
      );
      $form['advanced']['autocomplete']['autocorrect_suggest_words'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Suggest additional words'),
        '#description' => $this->t('If activated and the user enters a complete word, Solr will suggest additional words the user wants to search, which are often found (not searched!) together. This has been known to lead to strange results in some configurations – if you see inappropriate additional-word suggestions, you might want to deactivate this option.'),
        '#default_value' => $this->configuration['autocorrect_suggest_words'],
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (isset($values['port']) && (!is_numeric($values['port']) || $values['port'] < 0 || $values['port'] > 65535)) {
      $this->formBuilder->setError($form['port'], $form_state, $this->t('The port has to be an integer between 0 and 65535.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // Since the form is nested into another, we can't simply use #parents for
    // doing this array restructuring magic. (At least not without creating an
    // unnecessary dependency on internal implementation.)
    $values += $values['http'];
    $values += $values['advanced'];
    $values += !empty($values['autocomplete']) ? $values['autocomplete'] : array();
    unset($values['http'], $values['advanced'], $values['autocomplete']);

    // Highlighting retrieved data only makes sense when we retrieve data.
    $values['highlight_data'] &= $values['retrieve_data'];

    // For password fields, there is no default value, they're empty by default.
    // Therefore we ignore empty submissions if the user didn't change either.
    if ($values['http_pass'] === ''
        && isset($this->configuration['http_user'])
        && $values['http_user'] === $this->configuration['http_user']) {
      $values['http_pass'] = $this->configuration['http_pass'];
    }

    foreach ($values as $key => $value) {
      $form_state->setValue($key, $value);
    }

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    // First, check the features we always support.
    $supported = array(
      'search_api_autocomplete',
      'search_api_facets',
      'search_api_facets_operator_or',
      'search_api_grouping',
      'search_api_mlt',
      'search_api_multi',
      'search_api_service_extra',
      'search_api_spellcheck',
      'search_api_data_type_location',
      'search_api_data_type_geohash',
    );
    $supported = array_combine($supported, $supported);
    if (isset($supported[$feature])) {
      return TRUE;
    }

    // If it is a custom data type, maybe we support it automatically via
    // search_api_solr_hook_search_api_data_type_info().
    if (substr($feature, 0, 21) != 'search_api_data_type_') {
      return FALSE;
    }
    $type = substr($feature, 21);
    $type = Utility::getDataTypeInfo($type);
    // We only support it if the "prefix" key is set.
    return $type && !empty($type['prefix']);
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    $info = array();

    $info[] = array(
      'label' => $this->t('Solr server URI'),
      'info' => $this->getServerLink(),
    );

    if ($this->configuration['http_user']) {
      $vars = array(
        '@user' => $this->configuration['http_user'],
        '@pass' => str_repeat('*', strlen($this->configuration['http_pass'])),
      );
      $http = $this->t('Username: @user; Password: @pass', $vars);
      $info[] = array(
        'label' => $this->t('Basic HTTP authentication'),
        'info' => $http,
      );
    }

    if ($this->server->status()) {
      // If the server is enabled, check whether Solr can be reached.
      $ping = $this->ping();
      if ($ping) {
        $msg = $this->t('The Solr server could be reached (latency: @millisecs ms).', array('@millisecs' => $ping * 1000));
      }
      else {
        $msg = $this->t('The Solr server could not be reached. Further data is therefore unavailable.');
      }
      $info[] = array(
        'label' => $this->t('Connection'),
        'info' => $msg,
        'status' => $ping ? 'ok' : 'error',
      );

      if ($ping) {
        try {
          // If Solr can be reached, provide more information. This isn't done
          // often (only when an admin views the server details), so we clear the
          // cache to get the current data.
          $this->connect();
          $data = $this->getLuke();
          if (isset($data['index']['numDocs'])) {
            // Collect the stats
            $stats_summary = $this->getStatsSummary();

            $pending_msg = $stats_summary['@pending_docs'] ? $this->t('(@pending_docs sent but not yet processed)', $stats_summary) : '';
            $index_msg = $stats_summary['@index_size'] ? $this->t('(@index_size on disk)', $stats_summary) : '';
            $indexed_message = $this->t('@num items !pending !index_msg', array(
              '@num' => $data['index']['numDocs'],
              '!pending' => $pending_msg,
              '!index_msg' => $index_msg,
            ));
            $info[] = array(
              'label' => $this->t('Indexed'),
              'info' => $indexed_message,
            );

            if (!empty($stats_summary['@deletes_total'])) {
              $info[] = array(
                'label' => $this->t('Pending Deletions'),
                'info' => $stats_summary['@deletes_total'],
              );
            }

            $info[] = array(
              'label' => $this->t('Delay'),
              'info' => $this->t('@autocommit_time before updates are processed.', $stats_summary),
            );

            $status = 'ok';
            if (empty($this->configuration['skip_schema_check'])) {
              if (substr($stats_summary['@schema_version'], 0, 10) == 'search-api') {
                drupal_set_message($this->t('Your schema.xml version is too old. Please replace all configuration files with the ones packaged with this module and re-index you data.'), 'error');
                $status = 'error';
              }
              elseif (substr($stats_summary['@schema_version'], 0, 9) != 'drupal-4.') {
                $variables['@url'] = url(drupal_get_path('module', 'search_api_solr') . '/INSTALL.txt');
                $message = $this->t('You are using an incompatible schema.xml configuration file. Please follow the instructions in the <a href="@url">INSTALL.txt</a> file for setting up Solr.', $variables);
                drupal_set_message($message, 'error');
                $status = 'error';
              }
            }
            $info[] = array(
              'label' => $this->t('Schema'),
              'info' => $stats_summary['@schema_version'],
              'status' => $status,
            );

            if (!empty($stats_summary['@core_name'])) {
              $info[] = array(
                'label' => $this->t('Solr Core Name'),
                'info' => $stats_summary['@core_name'],
              );
            }
          }
        }
        catch (SearchApiException $e) {
          $info[] = array(
            'label' => $this->t('Additional information'),
            'info' => $this->t('An error occurred while trying to retrieve additional information from the Solr server: @msg.', array('@msg' => $e->getMessage())),
            'status' => 'error',
          );
        }
      }
    }

    return $info;
  }

  /**
   * Returns a link to the Solr server, if the necessary options are set.
   */
  public function getServerLink() {
    if (!$this->configuration) {
      return '';
    }
    $host = $this->configuration['host'];
    if ($host == 'localhost' && !empty($_SERVER['SERVER_NAME'])) {
      $host = $_SERVER['SERVER_NAME'];
    }
    $url = $this->configuration['scheme'] . '://' . $host . ':' . $this->configuration['port'] . $this->configuration['path'];
    return l($url, $url);
  }

  /**
   * Creates a connection to the Solr server as configured in $this->configuration.
   */
  protected function connect() {
    if (!$this->solr) {
      $this->solr = new Client();
      $this->solr->createEndpoint($this->configuration + array('key' => $this->server->id()), TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index) {
    // Only delete the index's data if the index isn't read-only.
    if (!is_object($index) || empty($index->read_only)) {
      $this->deleteAllIndexItems($index);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items) {
    $documents = array();
    $ret = array();
    $index_id = $this->getIndexId($index->id());
    $fields = $this->getFieldNames($index);
    $fields_single_value = $this->getFieldNames($index, TRUE);
    $languages = language_list();
    $base_urls = array();

    // Make sure that we have a Solr connection.
    $this->connect();

    /** @var \Drupal\search_api\Item\ItemInterface[] $items */
    foreach ($items as $id => $item) {
      $doc = $this->getUpdateQuery()->createDocument();
      $doc->setField('id', $this->createId($index_id, $id));
      $doc->setField('index_id', $index_id);
      $doc->setField('item_id', $id);

      // If multi-site compatibility is enabled, add the site hash and
      // language-specific base URL.
      if (!empty($this->configuration['site_hash'])) {
        $doc->setField('hash', search_api_solr_site_hash());
        $lang = $item->getField('search_api_language')->getValues();
        $lang = reset($lang);
        if (empty($base_urls[$lang])) {
          $url_options = array('absolute' => TRUE);
          if (isset($languages[$lang])) {
            $url_options['language'] = $languages[$lang];
          }
          $base_urls[$lang] = url(NULL, $url_options);
        }
        $doc->setField('site', $base_urls[$lang]);
      }

      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($item as $name => $field) {
        // If the field is not known for the index, something weird has
        // happened. We refuse to index the items and hope that the others are
        // OK.
        if (!isset($fields[$name])) {
          $vars = array(
            '%field' => $name,
            '@id' => $id,
          );
          watchdog('search_api_solr', 'Error while indexing: Unknown field %field on the item with ID @id.', $vars, WATCHDOG_WARNING);
          $doc = NULL;
          break;
        }
        $this->addIndexField($doc, $fields[$name], $fields_single_value[$name], $field->getValues(), $field->getType());
      }

      if ($doc) {
        $documents[] = $doc;
        $ret[] = $id;
      }
    }

    // Let other modules alter documents before sending them to solr.
    $this->moduleHandler->alter('search_api_solr_documents', $documents, $index, $items);
    $this->alterSolrDocuments($documents, $index, $items);

    if (!$documents) {
      return array();
    }
    try {
      $this->getUpdateQuery()->addDocuments($documents);
      if ($index->getOption('index_directly')) {
        $this->getUpdateQuery()->addCommit();
        $this->solr->update(static::$updateQuery);

        // Reset the Update query for further calls.
        static::$updateQuery = NULL;
      }
      else {
        $this->scheduleCommit();
      }
      return $ret;
    }
    catch (SearchApiException $e) {
      watchdog_exception('search_api_solr', $e, "%type while indexing: !message in %function (line %line of %file).");
    }
    return array();
  }

  /**
   * Creates an ID used as the unique identifier at the Solr server.
   *
   * This has to consist of both index and item ID. Optionally, the site hash is
   * also included.
   *
   * @see search_api_solr_site_hash()
   */
  protected function createId($index_id, $item_id) {
    $site_hash = !empty($this->configuration['site_hash']) ? search_api_solr_site_hash() . '-' : '';
    return "$site_hash$index_id-$item_id";
  }

  /**
   * Creates a list of all indexed field names mapped to their Solr field names.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The Search Api index.
   * @param bool $single_value_name
   *   (optional) Whether to return names for fields which store only the first
   *   value of the field. Defaults to FALSE.
   * @param bool $reset
   *   (optional) Whether to reset the static cache.
   *
   * The special fields "search_api_id" and "search_api_relevance" are also
   * included. Any Solr fields that exist on search results are mapped back to
   * to their local field names in the final result set.
   *
   * @see SearchApiSolrBackend::search()
   */
  public function getFieldNames(IndexInterface $index, $single_value_name = FALSE, $reset = FALSE) {
    $subkey = (int) $single_value_name;
    if (!isset($this->fieldNames[$index->id()][$subkey]) || $reset) {
      // This array maps "local property name" => "solr doc property name".
      $ret = array(
        'search_api_id' => 'item_id',
        'search_api_relevance' => 'score',
      );

      // Add the names of any fields configured on the index.
      $fields = (isset($index->options['fields']) ? $index->options['fields'] : array());
      foreach ($fields as $key => $field) {
        // Generate a field name; this corresponds with naming conventions in
        // our schema.xml
        $type = $field['type'];

        // Use the real type of the field if the server supports this type.
        if (isset($field['real_type'])) {
          if ($this->supportsFeature('search_api_data_type_' . $field['real_type'])) {
            $type = $field['real_type'];
          }
        }

        $type_info = search_api_solr_get_data_type_info($type);
        $pref = isset($type_info['prefix']) ? $type_info['prefix'] : '';
        $pref .= ($single_value_name) ? 's' : 'm';
        if (!empty($this->configuration['clean_ids'])) {
          $name = $pref . '_' . str_replace(':', '$', $key);
        }
        else {
          $name = $pref . '_' . $key;
        }

        $ret[$key] = $name;
      }

      // Let modules adjust the field mappings.
      $hook_name = $single_value_name ? 'search_api_solr_single_value_field_mapping' : 'search_api_solr_field_mapping';
      $this->moduleHandler->alter($hook_name, $index, $ret);

      $this->fieldNames[$index->id()][$subkey] = $ret;
    }

    return $this->fieldNames[$index->id()][$subkey];
  }

  /**
   * Helper method for indexing.
   *
   * Adds $value with field name $key to the document $doc. The format of $value
   * is the same as specified in
   * \Drupal\search_api\Backend\BackendSpecificInterface::indexItems().
   */
  protected function addIndexField(Document $doc, $key, $key_single, $values, $type) {
    // Don't index empty values (i.e., when field is missing).
    if (!isset($values)) {
      return;
    }

    // All fields.
    foreach ($values as $value) {
      switch ($type) {
        case 'boolean':
          $value = $value ? 'true' : 'false';
          break;

        case 'date':
          $value = is_numeric($value) ? (int) $value : strtotime($value);
          if ($value === FALSE) {
            return;
          }
          $value = format_date($value, 'custom', self::SOLR_DATE_FORMAT, 'UTC');
          break;

        case 'integer':
          $value = (int) $value;
          break;

        case 'decimal':
          $value = (float) $value;
          break;
      }

      // For tokenized text, add each word separately.
      if ($type == 'tokenized_text' && is_array($value)) {
        foreach ($value as $tokenizd_value) {
          // @todo Score is tracked by key, not for each value, how to handle
          //   this?
          $doc->addField($key, $tokenizd_value['value'], $tokenizd_value['score']);
        }
      }
      else {
        $doc->addField($key, $value);
      }
    }

    $field_value = $doc->{$key};
    $first_value = (is_array($field_value)) ? reset($field_value) : $field_value;
    if ($type == 'tokenized_text' && is_array($first_value) && isset($first_value['value'])) {
      $first_value = $first_value['value'];
    }
    $doc->setField($key_single, $first_value);
  }

  /**
   * Applies custom modifications to indexed Solr documents.
   *
   * This method allows subclasses to easily apply custom changes before the
   * documents are sent to Solr. The method is empty by default.
   *
   * @param \Solarium\QueryType\Update\Query\Document\Document[] $documents
   *   An array of \Solarium\QueryType\Update\Query\Document\Document objects
   *   ready to be indexed, generated from $items array.
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The search index for which items are being indexed.
   * @param array $items
   *   An array of items being indexed.
   *
   * @see hook_search_api_solr_documents_alter()
   */
  protected function alterSolrDocuments(array &$documents, IndexInterface $index, array $items) {
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $ids) {
    $index_id = $this->getIndexId($index->id());
    $solr_ids = array();
    foreach ($ids as $id) {
      $solr_ids[] = $this->createId($index_id, $id);
    }
    $this->getUpdateQuery()->addDeleteByIds($solr_ids);
    $this->scheduleCommit();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index = NULL) {
    if ($index) {
      // Since the index ID we use for indexing can contain arbitrary
      // prefixes, we have to escape it for use in the query.
      $index_id = $this->getQueryHelper()->escapePhrase($index->id());
      $index_id = $this->getIndexId($index_id);
      $query = '(index_id:' . $index_id . ')';
      if (!empty($this->configuration['site_hash'])) {
        // We don't need to escape the site hash, as that consists only of
        // alphanumeric characters.
        $query .= ' AND (hash:' . search_api_solr_site_hash() . ')';
      }
      $this->getUpdateQuery()->addDeleteQuery($query);
    }
    else {
      $this->getUpdateQuery()->addDeleteQuery('*:*');
    }
    $this->scheduleCommit();
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    // Reset request handler.
    $this->request_handler = NULL;
    // Get field information.
    $index = $query->getIndex();
    $index_id = $this->getIndexId($index->id());
    $fields = $this->getFieldNames($index);
    $fields_single_value = $this->getFieldNames($index, TRUE);
    // Get Solr connection.
    $this->connect();
    $version = $this->getSolrVersion();

    // Instantiate a Solarium select query.
    $solarium_query = $this->solr->createSelect();

    // Extract keys.
    $keys = $query->getKeys();
    if (is_array($keys)) {
      $keys = $this->flattenKeys($keys);
    }

    // Set searched fields.
    $options = $query->getOptions();
    $search_fields = $query->getFields();
    // Get the index fields to be able to retrieve boosts.
    $index_fields = $index->getFields();
    $qf = array();
    foreach ($search_fields as $f) {
      $boost = $index_fields[$f]->getBoost() ? '^' . $index_fields[$f]->getBoost() : '';
      $qf[] = $fields[$f] . $boost;
    }

    // Extract filters.
    $filter = $query->getFilter();
    $fq = $this->createFilterQueries($filter, $fields, $index->options['fields']);
    $fq[] = 'index_id:' . static::getQueryHelper($solarium_query)->escapePhrase($index_id);
    if (!empty($this->configuration['site_hash'])) {
      // We don't need to escape the site hash, as that consists only of
      // alphanumeric characters.
      $fq[] = 'hash:' . search_api_solr_site_hash();
    }

    // Extract sorts.
    foreach ($query->getSort() as $field => $order) {
      $f = $fields_single_value[$field];
      if (substr($f, 0, 3) == 'ss_') {
        $f = 'sort_' . substr($f, 3);
      }
      $solarium_query->addSort($f, strtolower($order));
    }

    // Get facet fields.
    $facets = $query->getOption('search_api_facets', array());
    $facet_params = $this->setFacets($facets, $fields, $fq, $solarium_query);

    // Handle highlighting.
    $this->setHighlighting($solarium_query, $query);

    // Handle More Like This query.
    $mlt = $query->getOption('search_api_mlt');
    if ($mlt) {
      $mlt_params['qt'] = 'mlt';
      // The fields to look for similarities in.
      $mlt_fl = array();
      foreach($mlt['fields'] as $f) {
        // Solr 4 has a bug which results in numeric fields not being supported
        // in MLT queries.
        // Date fields don't seem to be supported at all.
        if ($fields[$f][0] === 'd' || ($version == 4 && in_array($fields[$f][0], array('i', 'f')))) {
          continue;
        }
        $mlt_fl[] = $fields[$f];
        // For non-text fields, set minimum word length to 0.
        if (isset($index->options['fields'][$f]['type']) && !search_api_is_text_type($index->options['fields'][$f]['type'])) {
          $mlt_params['f.' . $fields[$f] . '.mlt.minwl'] = 0;
        }
      }
      $mlt_params['mlt.fl'] = implode(',', $mlt_fl);
      $id = $this->createId($index_id, $mlt['id']);
      $id = static::getQueryHelper()->escapePhrase($id);
      $keys = 'id:' . $id;
    }

    // Handle spatial filters.
    if ($spatials = $query->getOption('search_api_location')) {
      foreach ($spatials as $i => $spatial) {
        if (empty($spatial['field']) || empty($spatial['lat']) || empty($spatial['lon'])) {
          continue;
        }

        unset($radius);
        $field = $fields[$spatial['field']];
        $escaped_field = static::escapeFieldName($field);
        $point = ((float) $spatial['lat']) . ',' . ((float) $spatial['lon']);

        // Prepare the filter settings.
        if (isset($spatial['radius'])) {
          $radius = (float) $spatial['radius'];
        }
        $spatial_method = 'geofilt';
        if (isset($spatial['method']) && in_array($spatial['method'], array('geofilt', 'bbox'))) {
          $spatial_method = $spatial['method'];
        }

        // Change the fq facet ranges to the correct fq.
        foreach ($fq as $key => $value) {
          // If the fq consists only of a filter on this field, replace it with
          // a range.
          $preg_field = preg_quote($escaped_field, '/');
          if (preg_match('/^' . $preg_field . ':\["?(\*|\d+(?:\.\d+)?)"? TO "?(\*|\d+(?:\.\d+)?)"?\]$/', $value, $m)) {
            unset($fq[$key]);
            if ($m[1] && is_numeric($m[1])) {
              $min_radius = isset($min_radius) ? max($min_radius, $m[1]) : $m[1];
            }
            if (is_numeric($m[2])) {
              // Make the radius tighter accordingly.
              $radius = isset($radius) ? min($radius, $m[2]) : $m[2];
            }
          }
        }

        // If either a radius was given in the option, or a filter was
        // encountered, set a filter for the lowest value. If a lower boundary
        // was set (too), we can only set a filter for that if the field name
        // doesn't contains any colons.
        if (isset($min_radius) && strpos($field, ':') === FALSE) {
          $upper = isset($radius) ? " u=$radius" : '';
          $fq[] = "{!frange l=$min_radius$upper}geodist($field,$point)";
        }
        elseif (isset($radius)) {
          $fq[] = "{!$spatial_method pt=$point sfield=$field d=$radius}";
        }

        // Change sort on the field, if set (and not already changed).
        if (isset($sort[$spatial['field']]) && substr($sort[$spatial['field']], 0, strlen($field)) === $field) {
          if (strpos($field, ':') === FALSE) {
            $sort[$spatial['field']] = str_replace($field, "geodist($field,$point)", $sort[$spatial['field']]);
          }
          else {
            $link = l(t('edit server'), 'admin/config/search/search_api/server/' . $this->server->machine_name . '/edit');
            watchdog('search_api_solr', 'Location sort on field @field had to be ignored because unclean field identifiers are used.', array('@field' => $spatial['field']), WATCHDOG_WARNING, $link);
          }
        }

        // Change the facet parameters for spatial fields to return distance
        // facets.
        if (!empty($facets)) {
          if (!empty($facet_params['facet.field'])) {
            $facet_params['facet.field'] = array_diff($facet_params['facet.field'], array($field));
          }
          foreach ($facets as $delta => $facet) {
            if ($facet['field'] != $spatial['field']) {
              continue;
            }
            $steps = $facet['limit'] > 0 ? $facet['limit'] : 5;
            $step = (isset($radius) ? $radius : 100) / $steps;
            for ($k = $steps - 1; $k > 0; --$k) {
              $distance = $step * $k;
              $key = "spatial-$delta-$distance";
              $facet_params['facet.query'][] = "{!$spatial_method pt=$point sfield=$field d=$distance key=$key}";
            }
            foreach (array('limit', 'mincount', 'missing') as $setting) {
              unset($facet_params["f.$field.facet.$setting"]);
            }
          }
        }
      }
    }
    // Normal sorting on location fields isn't possible.
    foreach (array_keys($solarium_query->getSorts()) as $sort) {
      if (substr($sort, 0, 3) === 'loc') {
        $solarium_query->removeSort($sort);
      }
    }

    // Handle field collapsing / grouping.
    $grouping = $query->getOption('search_api_grouping');
    if (!empty($grouping['use_grouping'])) {
      $group_params['group'] = 'true';
      // We always want the number of groups returned so that we get pagers done
      // right.
      $group_params['group.ngroups'] = 'true';
      if (!empty($grouping['truncate'])) {
        $group_params['group.truncate'] = 'true';
      }
      if (!empty($grouping['group_facet'])) {
        $group_params['group.facet'] = 'true';
      }
      foreach ($grouping['fields'] as $collapse_field) {
        $type = $index_fields[$collapse_field]['type'];
        // Only single-valued fields are supported.
        if ($version < 4) {
          // For Solr 3.x, only string and boolean fields are supported.
          if (search_api_is_list_type($type) || !search_api_is_text_type($type, array('string', 'boolean', 'uri'))) {
            $warnings[] = $this->t('Grouping is not supported for field @field. ' .
                'Only single-valued fields of type "String", "Boolean" or "URI" are supported.',
                array('@field' => $index_fields[$collapse_field]['name']));
            continue;
          }
        }
        else {
          if (search_api_is_list_type($type) || search_api_is_text_type($type)) {
            $warnings[] = $this->t('Grouping is not supported for field @field. ' .
                'Only single-valued fields not indexed as "Fulltext" are supported.',
                array('@field' => $index_fields[$collapse_field]['name']));
            continue;
          }
        }
        $group_params['group.field'][] = $fields[$collapse_field];
      }
      if (empty($group_params['group.field'])) {
        unset($group_params);
      }
      else {
        if (!empty($grouping['group_sort'])) {
          foreach ($grouping['group_sort'] as $group_sort_field => $order) {
            if (isset($fields[$group_sort_field])) {
              $f = $fields[$group_sort_field];
              if (substr($f, 0, 3) == 'ss_') {
                $f = 'sort_' . substr($f, 3);
              }
              $order = strtolower($order);
              $group_params['group.sort'][] = $f . ' ' . $order;
            }
          }
          if (!empty($group_params['group.sort'])) {
            $group_params['group.sort'] = implode(', ', $group_params['group.sort']);
          }
        }
        if (!empty($grouping['group_limit']) && ($grouping['group_limit'] != 1)) {
          $group_params['group.limit'] = $grouping['group_limit'];
        }
      }
    }

    // Set defaults.
    if ($keys) {
      $solarium_query->setQuery($keys);
    }

    // Collect parameters.
    $solarium_query->setFields('item_id,score');
    $solarium_query->getEDisMax()->setQueryFields($qf);
    foreach ($fq as $key => $filter_query) {
      $solarium_query->createFilterQuery('fq' . $key)->setQuery($filter_query);
    }

    if (isset($options['offset'])) {
      $solarium_query->setStart($options['offset']);
    }
    $rows = isset($options['limit']) ? $options['limit'] : 1000000;
    $solarium_query->setRows($rows);

    if (!empty($options['search_api_spellcheck'])) {
      $solarium_query->getSpellcheck();
    }
    /*
    if (!empty($mlt_params['mlt.fl'])) {
      $params += $mlt_params;
    }
    if (!empty($group_params)) {
      $params += $group_params;
    }
    */
    if (!empty($this->configuration['retrieve_data'])) {
      $solarium_query->setFields('*,score');
    }

    try {
      $this->moduleHandler->alter('search_api_solr_query', $solarium_query, $query);
      $this->preQuery($solarium_query, $query);

      // Use the manual method of creating a Solarium request so we can control
      // the HTTP method.
      $request = $this->solr->createRequest($solarium_query);

      // Set the HTTP method or use the 'postbigrequest' plugin if no specific
      // method is configured.
      if ($this->configuration['http_method'] == 'AUTO') {
        $this->solr->getPlugin('postbigrequest');
      }
      elseif ($this->configuration['http_method'] == 'POST') {
        $request->setMethod(Request::METHOD_POST);
      }

      // Set HTTP Basic Authentication parameter, if login data was set.
      if (strlen($this->configuration['http_user']) && strlen($this->configuration['http_pass'])) {
        $request->setAuthentication($this->configuration['http_user'], $this->configuration['http_pass']);
      }

      // Send search request.
      $response = $this->solr->executeRequest($request);
      $resultset = $this->solr->createResult($solarium_query, $response);

      // Extract results.
      $results = $this->extractResults($query, $resultset);

      // Add warnings, if present.
      if (!empty($warnings)) {
        foreach ($warnings as $warning) {
          $results->addWarning($warning);
        }
      }

      // Extract facets.
      if ($facets = $this->extractFacets($query, $resultset)) {
        $results->setExtraData('search_api_facets', $facets);
      }

      $this->moduleHandler->alter('search_api_solr_search_results', $results, $query, $resultset);
      $this->postQuery($results, $query, $resultset);

      return $results;
    }
    catch (SearchApiException $e) {
      throw new SearchApiException(t('An error occurred while trying to search with Solr: @msg.', array('@msg' => $e->getMessage())));
    }
  }

  /**
   * Extract results from a Solr response.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query object.
   * @param \Solarium\QueryType\Select\Result\Result $resultset
   *   A Solarium select response object.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   A result set object.
   */
  protected function extractResults(QueryInterface $query, Result $resultset) {
    $index = $query->getIndex();
    $fields = $this->getFieldNames($index);
    $field_options = $index->options['fields'];

    // Set up the results array.
    $results = Utility::createSearchResultSet($query);
    $results->setExtraData('search_api_solr_response', $resultset->getData());

    // In some rare cases (e.g., MLT query with nonexistent ID) the response
    // will be NULL.
    if (!$resultset->getResponse() && !$resultset->getGrouping()) {
      $results->setResultCount(0);
      return $results;
    }

    // If field collapsing has been enabled for this query, we need to process
    // the results differently.
    $grouping = $query->getOption('search_api_grouping');
    if (!empty($grouping['use_grouping']) && $resultset->getGrouping()) {
//      $docs = array();
//      $results['result count'] = 0;
//      foreach ($grouping['fields'] as $field) {
//        if (!empty($response->grouped->{$fields[$field]})) {
//          $results['result count'] += $response->grouped->{$fields[$field]}->ngroups;
//          foreach ($response->grouped->{$fields[$field]}->groups as $group) {
//            foreach ($group->doclist->docs as $doc) {
//              $docs[] = $doc;
//            }
//          }
//        }
//      }
    }
    else {
      $results->setResultCount($resultset->getNumFound());
      $docs = $resultset->getDocuments();
    }

    // Add each search result to the results array.
    foreach ($docs as $doc) {
      $doc_fields = $doc->getFields();

      // We can find the item ID and the score in the special 'search_api_*'
      // properties. Mappings are provided for these properties in
      // SearchApiSolrBackend::getFieldNames().
      $result_item = Utility::createItem($index, $doc_fields[$fields['search_api_id']]);
      $result_item->setScore($doc_fields[$fields['search_api_id']]);
      unset($doc_fields[$fields['search_api_id']], $doc_fields[$fields['search_api_relevance']]);

      // Extract properties from the Solr document, translating from Solr to
      // Search API property names. This reverses the mapping in
      // SearchApiSolrBackend::getFieldNames().
      foreach ($fields as $search_api_property => $solr_property) {
        if (isset($doc_fields[$solr_property])) {
          // Date fields need some special treatment to become valid date values
          // (i.e., timestamps) again.
          if (isset($field_options[$search_api_property]['type'])
              && $field_options[$search_api_property]['type'] == 'date'
              && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $doc_fields[$solr_property])) {
            $doc_fields[$solr_property] = strtotime($doc_fields[$solr_property]);
          }

          $field = Utility::createField($index, $search_api_property);
          $field->setValues($doc_fields[$solr_property]);
          $result_item->setField($search_api_property, $field);
        }
      }

      $index_id = $this->getIndexId($index->id());
      $solr_id = $this->createId($index_id, $result_item->getId());
      $excerpt = $this->getExcerpt($resultset->getData(), $solr_id, $result_item->getFields(), $fields);
      if ($excerpt) {
        $result_item->setExcerpt($excerpt);
      }

      $results->addResultItem($result_item);
    }

    // Check for spellcheck suggestions.
//    if (module_exists('search_api_spellcheck') && $query->getOption('search_api_spellcheck')) {
//      $results->setExtraData('search_api_spellcheck', new SearchApiSpellcheckSolr($resultset));
//    }

    return $results;
  }

  /**
   * Extract and format highlighting information for a specific item from a Solr response.
   *
   * Will also use highlighted fields to replace retrieved field data, if the
   * corresponding option is set.
   */
  protected function getExcerpt($response, $id, array $fields, array $field_mapping) {
    if (!isset($response->highlighting->$id)) {
      return FALSE;
    }
    $output = '';

    if (!empty($this->configuration['excerpt']) && !empty($response->highlighting->$id->spell)) {
      foreach ($response->highlighting->$id->spell as $snippet) {
        $snippet = strip_tags($snippet);
        $snippet = preg_replace('/^.*>|<.*$/', '', $snippet);
        $snippet = $this->formatHighlighting($snippet);
        // The created fragments sometimes have leading or trailing punctuation.
        // We remove that here for all common cases, but take care not to remove
        // < or > (so HTML tags stay valid).
        $snippet = trim($snippet, "\00..\x2F:;=\x3F..\x40\x5B..\x60");
        $output .= $snippet . ' … ';
      }
    }
    if (!empty($this->configuration['highlight_data'])) {
      foreach ($field_mapping as $search_api_property => $solr_property) {
        if (substr($solr_property, 0, 3) == 'tm_' && !empty($response->highlighting->$id->$solr_property)) {
          // Contrary to above, we here want to preserve HTML, so we just
          // replace the [HIGHLIGHT] tags with the appropriate format.
          $fields[$search_api_property] = $this->formatHighlighting($response->highlighting->$id->$solr_property);
        }
      }
    }

    return $output;
  }

  /**
   * Changes highlighting tags from our custom, HTML-safe ones to HTML.
   *
   * @param string|array $snippet
   *   The snippet(s) to format.
   *
   * @return string|array
   *   The snippet(s), properly formatted as HTML.
   */
  protected function formatHighlighting($snippet) {
    return preg_replace('#\[(/?)HIGHLIGHT\]#', '<$1strong>', $snippet);
  }

  /**
   * Extracts facets from a Solarium result set.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query.
   * @param \Solarium\QueryType\Select\Result\Result $resultset
   *   A Solarium select response object.
   *
   * @return array
   *   An array describing facets that apply to the current results.
   */
  protected function extractFacets(QueryInterface $query, Result $resultset) {
    $facets = array();

    if (!$resultset->getFacetSet()) {
      return $facets;
    }

    $index = $query->getIndex();
    $fields = $this->getFieldNames($index);

    $extract_facets = $query->getOption('search_api_facets', array());

    if ($facet_fields = $resultset->getFacetSet()->getFacets()) {
      foreach ($extract_facets as $delta => $info) {
        $field = $fields[$info['field']];
        if (!empty($facet_fields[$field])) {
          $min_count = $info['min_count'];
          $terms = $facet_fields[$field]->getValues();
          if ($info['missing']) {
            // We have to correctly incorporate the "_empty_" term.
            // This will ensure that the term with the least results is dropped,
            // if the limit would be exceeded.
            if (isset($terms[''])) {
              if ($terms[''] < $min_count) {
                unset($terms['']);
              }
              else {
                arsort($terms);
                if ($info['limit'] > 0 && count($terms) > $info['limit']) {
                  array_pop($terms);
                }
              }
            }
          }
          elseif (isset($terms[''])) {
            unset($terms['']);
          }
          $type = isset($index->options['fields'][$info['field']]['type']) ? $index->options['fields'][$info['field']]['type'] : 'string';
          foreach ($terms as $term => $count) {
            if ($count >= $min_count) {
              if ($term === '') {
                $term = '!';
              }
              elseif ($type == 'boolean') {
                if ($term == 'true') {
                  $term = '"1"';
                }
                elseif ($term == 'false') {
                  $term = '"0"';
                }
              }
              elseif ($type == 'date') {
                $term = $term ? '"' . strtotime($term) . '"' : NULL;
              }
              else {
                $term = "\"$term\"";
              }
              if ($term) {
                $facets[$delta][] = array(
                  'filter' => $term,
                  'count' => $count,
                );
              }
            }
          }
          if (empty($facets[$delta])) {
            unset($facets[$delta]);
          }
        }
      }
    }

    $result_data = $resultset->getData();
    if (isset($result_data['facet_counts']['facet_queries'])) {
      if ($spatials = $query->getOption('search_api_location')) {
        foreach ($result_data['facet_counts']['facet_queries'] as $key => $count) {
          if (!preg_match('/^spatial-(.*)-(\d+(?:\.\d+)?)$/', $key, $m)) {
            continue;
          }
          if (empty($extract_facets[$m[1]])) {
            continue;
          }
          $facet = $extract_facets[$m[1]];
          if ($count >= $facet['min_count']) {
            $facets[$m[1]][] = array(
              'filter' => "[* {$m[2]}]",
              'count' => $count,
            );
          }
        }
      }
    }

    return $facets;
  }

  /**
   * Flatten a keys array into a single search string.
   *
   * @param array $keys
   *   The keys array to flatten, formatted as specified by
   *   \Drupal\search_api\Query\QueryInterface::getKeys().
   * @param bool $is_nested
   *   (optional) Whether the function is called for a nested condition.
   *   Defaults to FALSE.
   *
   * @return string
   *   A Solr query string representing the same keys.
   */
  protected function flattenKeys(array $keys, $is_nested = FALSE) {
    $k = array();
    $or = $keys['#conjunction'] == 'OR';
    $neg = !empty($keys['#negation']);
    foreach ($keys as $key_nr => $key) {
      // We cannot use \Drupal\Core\Render\Element::children() anymore because
      // $keys is not a valid render array.
      if ($key_nr[0] === '#' || !$key) {
        continue;
      }
      if (is_array($key)) {
        $subkeys = $this->flattenKeys($key, TRUE);
        if ($subkeys) {
          $nested_expressions = TRUE;
          // If this is a negated OR expression, we can't just use nested keys
          // as-is, but have to put them into parantheses.
          if ($or && $neg) {
            $subkeys = "($subkeys)";
          }
          $k[] = $subkeys;
        }
      }
      else {
        $key = static::getQueryHelper()->escapePhrase(trim($key));
        $k[] = $key;
      }
    }
    if (!$k) {
      return '';
    }

    // Formatting the keys into a Solr query can be a bit complex. The following
    // code will produce filters that look like this:
    //
    // #conjunction | #negation | return value
    // ----------------------------------------------------------------
    // AND          | FALSE     | A B C
    // AND          | TRUE      | -(A AND B AND C)
    // OR           | FALSE     | ((A) OR (B) OR (C))
    // OR           | TRUE      | -A -B -C

    // If there was just a single, unnested key, we can ignore all this.
    if (count($k) == 1 && empty($nested_expressions)) {
      $k = reset($k);
      return $neg ? "*:* AND -$k" : $k;
    }

    if ($or) {
      if ($neg) {
        return '*:* AND -' . implode(' AND -', $k);
      }
      return '((' . implode(') OR (', $k) . '))';
    }
    $k = implode($neg || $is_nested ? ' AND ' : ' ', $k);
    return $neg ? "*:* AND -($k)" : $k;
  }

  /**
   * Transforms a query filter into a flat array of Solr filter queries, using
   * the field names in $fields.
   */
  protected function createFilterQueries(FilterInterface $filter, array $solr_fields, array $fields) {
    $or = $filter->getConjunction() == 'OR';
    $fq = array();
    foreach ($filter->getFilters() as $f) {
      if (is_array($f)) {
        if (!isset($fields[$f[0]])) {
          throw new SearchApiException(t('Filter term on unknown or unindexed field @field.', array('@field' => $f[0])));
        }
        if ($f[1] !== '') {
          $fq[] = $this->createFilterQuery($solr_fields[$f[0]], $f[1], $f[2], $fields[$f[0]]);
        }
      }
      else {
        $q = $this->createFilterQueries($f, $solr_fields, $fields);
        if ($filter->getConjunction() != $f->getConjunction()) {
          // $or == TRUE means the nested filter has conjunction AND, and vice versa
          $sep = $or ? ' ' : ' OR ';
          $fq[] = count($q) == 1 ? reset($q) : '((' . implode(')' . $sep . '(', $q) . '))';
        }
        else {
          $fq = array_merge($fq, $q);
        }
      }
    }
    return ($or && count($fq) > 1) ? array('((' . implode(') OR (', $fq) . '))') : $fq;
  }

  /**
   * Create a single search query string according to the given field, value
   * and operator.
   */
  protected function createFilterQuery($field, $value, $operator, $field_info) {
    $field = static::escapeFieldName($field);
    if ($value === NULL) {
      return ($operator == '=' ? '*:* AND -' : '') . "$field:[* TO *]";
    }
    $value = trim($value);
    $value = $this->formatFilterValue($value, $field_info['type']);
    switch ($operator) {
      case '<>':
        return "*:* AND -($field:$value)";
      case '<':
        return "$field:{* TO $value}";
      case '<=':
        return "$field:[* TO $value]";
      case '>=':
        return "$field:[$value TO *]";
      case '>':
        return "$field:{{$value} TO *}";

      default:
        return "$field:$value";
    }
  }

  /**
   * Format a value for filtering on a field of a specific type.
   */
  protected function formatFilterValue($value, $type) {
    switch ($type) {
      case 'boolean':
        $value = $value ? 'true' : 'false';
        break;
      case 'date':
        $value = is_numeric($value) ? (int) $value : strtotime($value);
        if ($value === FALSE) {
          return 0;
        }
        $value = format_date($value, 'custom', self::SOLR_DATE_FORMAT, 'UTC');
        break;
    }
    return $this->getQueryHelper()->escapePhrase($value);
  }

  /**
   * Helper method for creating the facet field parameters.
   */
  protected function setFacets(array $facets, array $fields, array &$fq = array(), Query $solarium_query) {
    if (!$facets) {
      return array();
    }
    $facet_set = $solarium_query->getFacetSet();
    $facet_set->setSort('count');
    $facet_set->setLimit(10);
    $facet_set->setMinCount(1);
    $facet_set->setMissing(FALSE);

    $taggedFields = array();
    foreach ($facets as $info) {
      if (empty($fields[$info['field']])) {
        continue;
      }
      // String fields have their own corresponding facet fields.
      $field = $fields[$info['field']];
      // Check for the "or" operator.
      if (isset($info['operator']) && $info['operator'] === 'or') {
        // Remember that filters for this field should be tagged.
        $escaped = static::escapeFieldName($fields[$info['field']]);
        $taggedFields[$escaped] = "{!tag=$escaped}";
        // Add the facet field.
        $facet_field = $facet_set->createFacetField($field)->setField("{!ex=$escaped}$field");
      }
      else {
        // Add the facet field.
        $facet_field = $facet_set->createFacetField($field)->setField($field);
      }
      // Set limit, unless it's the default.
      if ($info['limit'] != 10) {
        $limit = $info['limit'] ? $info['limit'] : -1;
        $facet_field->setLimit($limit);
      }
      // Set mincount, unless it's the default.
      if ($info['min_count'] != 1) {
        $facet_field->setMinCount($info['min_count']);
      }
      // Set missing, if specified.
      if ($info['missing']) {
        $facet_field->setMissing(TRUE);
      }
    }
    // Tag filters of fields with "OR" facets.
    foreach ($taggedFields as $field => $tag) {
      $regex = '#(?<![^( ])' . preg_quote($field, '#') . ':#';
      foreach ($fq as $i => $filter) {
        // Solr can't handle two tags on the same filter, so we don't add two.
        // Another option here would even be to remove the other tag, too,
        // since we can be pretty sure that this filter does not originate from
        // a facet – however, wrong results would still be possible, and this is
        // definitely an edge case, so don't bother.
        if (preg_match($regex, $filter) && substr($filter, 0, 6) != '{!tag=') {
          $fq[$i] = $tag . $filter;
        }
      }
    }
  }

  /**
   * Sets the highlighting parameters.
   *
   * (The $query parameter currently isn't used and only here for the potential
   * sake of subclasses.)
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query object.
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The Solarium select query object.
   */
  protected function setHighlighting(Query $solarium_query, QueryInterface $query) {
    if (!empty($this->configuration['excerpt']) || !empty($this->configuration['highlight_data'])) {
      $hl = $solarium_query->getHighlighting();
      $hl->setFields('spell');
      $hl->setSimplePrefix('[HIGHLIGHT]');
      $hl->setSimplePostfix('[/HIGHLIGHT]');
      $hl->setSnippets(3);
      $hl->setFragSize(70);
      $hl->setMergeContiguous(TRUE);
    }

    if (!empty($this->configuration['highlight_data'])) {
      $hl = $solarium_query->getHighlighting();
      $hl->setFields('tm_*');
      $hl->setSnippets(1);
      $hl->setFragSize(0);
      if (!empty($this->configuration['excerpt'])) {
        // If we also generate a "normal" excerpt, set the settings for the
        // "spell" field (which we use to generate the excerpt) back to the
        // above values.
        $hl->getField('spell')->setSnippets(3);
        $hl->getField('spell')->setFragSize(70);
        // It regrettably doesn't seem to be possible to set hl.fl to several
        // values, if one contains wild cards (i.e., "t_*,spell" wouldn't work).
        $hl->setFields('*');
      }
    }
  }

  /**
   * Sets the request handler.
   *
   * This should also make the needed adjustments to the request parameters.
   *
   * @param $handler
   *   Name of the handler to set.
   * @param array $call_args
   *   An associative array containing all three arguments to the
   *   SearchApiSolrConnectionInterface::search() call ("query", "params" and
   *   "method") as references.
   *
   * @return bool
   *   TRUE iff this method invocation handled the given handler. This allows
   *   subclasses to recognize whether the request handler was already set by
   *   this method.
   */
  protected function setRequestHandler($handler, array &$call_args) {
    if ($handler == 'pinkPony') {
      $call_args['params']['qt'] = $handler;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Empty method called before sending a search query to Solr.
   *
   * This allows subclasses to apply custom changes before the query is sent to
   * Solr. Works exactly like hook_search_api_solr_query_alter().
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The Solarium select query object.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   */
  protected function preQuery(Query $solarium_query, QueryInterface $query) {
  }

  /**
   * Empty method to allow subclasses to apply custom changes before search results are returned.
   *
   * Works exactly like hook_search_api_solr_search_results_alter().
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The results array that will be returned for the search.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   * @param object $response
   *   The response object returned by Solr.
   */
  protected function postQuery(ResultSetInterface $results, QueryInterface $query, $response) {
  }

  //
  // Autocompletion feature
  //

  /**
   * Implements SearchApiAutocompleteInterface::getAutocompleteSuggestions().
   */
  // Largely copied from the apachesolr_autocomplete module.
  public function getAutocompleteSuggestions(QueryInterface $query, SearchApiAutocompleteSearch $search, $incomplete_key, $user_input) {
    $suggestions = array();
    // Reset request handler
    $this->request_handler = NULL;
    // Turn inputs to lower case, otherwise we get case sensivity problems.
    $incomp = drupal_strtolower($incomplete_key);

    $index = $query->getIndex();
    $fields = $this->getFieldNames($index);
    $complete = $query->getOriginalKeys();

    // Extract keys
    $keys = $query->getKeys();
    if (is_array($keys)) {
      $keys_array = array();
      while ($keys) {
        reset($keys);
        if (!element_child(key($keys))) {
          array_shift($keys);
          continue;
        }
        $key = array_shift($keys);
        if (is_array($key)) {
          $keys = array_merge($keys, $key);
        }
        else {
          $keys_array[$key] = $key;
        }
      }
      $keys = $this->flattenKeys($query->getKeys());
    }
    else {
      $keys_array = drupal_map_assoc(preg_split('/[-\s():{}\[\]\\\\"]+/', $keys, -1, PREG_SPLIT_NO_EMPTY));
    }
    if (!$keys) {
      $keys = NULL;
    }

    // Set searched fields
    $options = $query->getOptions();
    $search_fields = $query->getFields();
    $qf = array();
    foreach ($search_fields as $f) {
      $qf[] = $fields[$f];
    }

    // Extract filters
    $fq = $this->createFilterQueries($query->getFilter(), $fields, $index->options['fields']);
    $index_id = $this->getIndexId($index->id());
    $fq[] = 'index_id:' . $this->getQueryHelper()->escapePhrase($index_id);
    if (!empty($this->configuration['site_hash'])) {
      // We don't need to escape the site hash, as that consists only of
      // alphanumeric characters.
      $fq[] = 'hash:' . search_api_solr_site_hash();
    }

    // Autocomplete magic
    $facet_fields = array();
    foreach ($search_fields as $f) {
      $facet_fields[] = $fields[$f];
    }

    $limit = $query->getOption('limit', 10);

    $params = array(
      'qf' => $qf,
      'fq' => $fq,
      'rows' => 0,
      'facet' => 'true',
      'facet.field' => $facet_fields,
      'facet.prefix' => $incomp,
      'facet.limit' => $limit * 5,
      'facet.mincount' => 1,
      'spellcheck' => (!isset($this->configuration['autocorrect_spell']) || $this->configuration['autocorrect_spell']) ? 'true' : 'false',
      'spellcheck.count' => 1,
    );
    // Retrieve http method from server options.
    $http_method = !empty($this->configuration['http_method']) ? $this->configuration['http_method'] : 'AUTO';

    $call_args = array(
      'query'       => &$keys,
      'params'      => &$params,
      'http_method' => &$http_method,
    );
    if ($this->request_handler) {
      $this->setRequestHandler($this->request_handler, $call_args);
    }
    $second_pass = !isset($this->configuration['autocorrect_suggest_words']) || $this->configuration['autocorrect_suggest_words'];
    for ($i = 0; $i < ($second_pass ? 2 : 1); ++$i) {
      try {
        // Send search request
        $this->connect();
        $this->moduleHandler->alter('search_api_solr_query', $call_args, $query);
        $this->preQuery($call_args, $query);
        $response = $this->solr->search($keys, $params, $http_method);

        if (!empty($response->spellcheck->suggestions)) {
          $replace = array();
          foreach ($response->spellcheck->suggestions as $word => $data) {
            $replace[$word] = $data->suggestion[0];
          }
          $corrected = str_ireplace(array_keys($replace), array_values($replace), $user_input);
          if ($corrected != $user_input) {
            array_unshift($suggestions, array(
              'prefix' => $this->t('Did you mean') . ':',
              'user_input' => $corrected,
            ));
          }
        }

        $matches = array();
        if (isset($response->facet_counts->facet_fields)) {
          foreach ($response->facet_counts->facet_fields as $terms) {
            foreach ($terms as $term => $count) {
              if (isset($matches[$term])) {
                // If we just add the result counts, we can easily get over the
                // total number of results if terms appear in multiple fields.
                // Therefore, we just take the highest value from any field.
                $matches[$term] = max($matches[$term], $count);
              }
              else {
                $matches[$term] = $count;
              }
            }
          }

          if ($matches) {
            // Eliminate suggestions that are too short or already in the query.
            foreach ($matches as $term => $count) {
              if (strlen($term) < 3 || isset($keys_array[$term])) {
                unset($matches[$term]);
              }
            }

            // Don't suggest terms that are too frequent (by default in more
            // than 90% of results).
            $result_count = $response->response->numFound;
            $max_occurrences = $result_count * $this->searchApiSolrSettings->get('autocomplete_max_occurrences');
            if (($max_occurrences >= 1 || $i > 0) && $max_occurrences < $result_count) {
              foreach ($matches as $match => $count) {
                if ($count > $max_occurrences) {
                  unset($matches[$match]);
                }
              }
            }

            // The $count in this array is actually a score. We want the
            // highest ones first.
            arsort($matches);

            // Shorten the array to the right ones.
            $additional_matches = array_slice($matches, $limit - count($suggestions), NULL, TRUE);
            $matches = array_slice($matches, 0, $limit, TRUE);

            // Build suggestions using returned facets
            $incomp_length = strlen($incomp);
            foreach ($matches as $term => $count) {
              if (drupal_strtolower(substr($term, 0, $incomp_length)) == $incomp) {
                $suggestions[] = array(
                  'suggestion_suffix' => substr($term, $incomp_length),
                  'term' => $term,
                  'results' => $count,
                );
              }
              else {
                $suggestions[] = array(
                  'suggestion_suffix' => ' ' . $term,
                  'term' => $term,
                  'results' => $count,
                );
              }
            }
          }
        }
      }
      catch (SearchApiException $e) {
        watchdog_exception('search_api_solr', $e, "%type during autocomplete Solr query: !message in %function (line %line of %file).", array(), WATCHDOG_WARNING);
      }

      if (count($suggestions) >= $limit) {
        break;
      }
      // Change parameters for second query.
      unset($params['facet.prefix']);
      $keys = trim ($keys . ' ' . $incomplete_key);
    }

    return $suggestions;
  }

  //
  // Additional methods that might be used when knowing the service class.
  //

  /**
   * Ping the Solr server to tell whether it can be accessed.
   *
   * Uses the admin/ping request handler.
   */
  public function ping() {
    $this->connect();
    $query = $this->solr->createPing();

    try {
      $start = microtime(TRUE);
      $result = $this->solr->ping($query);
      if ($result->getResponse()->getStatusCode() == 200) {
        // Add 1 µs to the ping time so we never return 0.
        return (microtime(TRUE) - $start) + 1E-6;
      }
    }
    catch (HttpException $e) {
      // @todo Show a message with the exception?
    }
    return FALSE;
  }

  /**
   * Sends a commit command to the Solr server.
   */
  public function commit() {
    try {
      if (static::$updateQuery) {
        $this->connect();
        $this->getUpdateQuery()->addCommit();
        $this->solr->update($this->getUpdateQuery());
      }
    }
    catch (\Exception $e) {
      watchdog_exception('search_api_solr', $e,
          '%type while trying to commit on server @server: !message in %function (line %line of %file).',
          array('@server' => $this->server->label()), WATCHDOG_WARNING);
    }
  }

  /**
   * Schedules a commit operation for this server.
   *
   * The commit will be sent at the end of the current page request. Multiple
   * calls to this method will still only result in one commit operation.
   */
  public function scheduleCommit() {
    if (!$this->commitScheduled) {
      $this->commitScheduled = TRUE;
      drupal_register_shutdown_function(array($this, 'commit'));
    }
  }

  /**
   * Gets the currently used Solr connection object.
   *
   * @return \Solarium\Client
   *   The solr connection object used by this server.
   */
  public function getSolrConnection() {
    $this->connect();
    return $this->solr;
  }

  /**
   * Get metadata about fields in the Solr/Lucene index.
   *
   * @param int $num_terms
   *   Number of 'top terms' to return.
   *
   * @return array
   *   An array of SearchApiSolrField objects.
   *
   * @see SearchApiSolrConnectionInterface::getFields()
   */
  public function getFields($num_terms = 0) {
    $this->connect();
    return $this->solr->getFields($num_terms);
  }

  /**
   * Retrieves a config file or file list from the Solr server.
   *
   * Uses the admin/file request handler.
   *
   * @param string|null $file
   *   (optional) The name of the file to retrieve. If the file is a directory,
   *   the directory contents are instead listed and returned. NULL represents
   *   the root config directory.
   *
   * @return \Solarium\Core\Client\Response
   *   A Solarium response object containing either the file contents or a file
   *   list.
   */
  public function getFile($file = NULL) {
    $this->connect();

    $query = $this->solr->createPing();
    $query->setHandler('admin/file');
    $query->addParam('contentType', 'text/xml;charset=utf-8');
    if ($file) {
      $query->addParam('file', $file);
    }

    return $this->solr->ping($query)->getResponse();
  }

  /**
   * Prefixes an index ID as configured.
   *
   * The resulting ID will be a concatenation of the following strings:
   * - If set, the "search_api_solr.settings.index_prefix" configuration.
   * - If set, the index-specific "search_api_solr.settings.index_prefix_INDEX"
   *   configuration.
   * - The index's machine name.
   *
   * @param string $machine_name
   *   The index's machine name.
   *
   * @return string
   *   The prefixed machine name.
   */
  protected function getIndexId($machine_name) {
    // Prepend per-index prefix.
    $id = $this->searchApiSolrSettings->get('index_prefix_' . $machine_name) . $machine_name;
    // Prepend environment prefix.
    $id = $this->searchApiSolrSettings->get('index_prefix') . $id;
    return $id;
  }

  /**
   * Gets the current Solarium update query, creating one if necessary.
   *
   * @return \Solarium\QueryType\Update\Query\Query
   *   The Update query.
   */
  protected function getUpdateQuery() {
    if (!static::$updateQuery) {
      $this->connect();
      static::$updateQuery = $this->solr->createUpdate();
    }
    return static::$updateQuery;
  }

  /**
   * Returns a Solarium query helper object.
   *
   * @param \Solarium\Core\Query\Query|null $query
   *   (optional) A Solarium query object.
   *
   * @return \Solarium\Core\Query\Helper
   *   A Solarium query helper.
   */
  protected function getQueryHelper(Query $query = NULL) {
    if (!static::$queryHelper) {
      if ($query) {
        static::$queryHelper = $query->getHelper();
      }
      else {
        static::$queryHelper = new Helper();
      }
    }

    return static::$queryHelper;
  }

  /**
   * Gets the current Solr version.
   *
   * @return int
   *   1, 3 or 4. Does not give a more detailed version, for that you need to
   *   use getSystemInfo().
   */
  protected function getSolrVersion() {
    // Allow for overrides by the user.
    if (!empty($this->configuration['solr_version'])) {
      return $this->configuration['solr_version'];
    }

    $system_info = $this->getSystemInfo();
    // Get our solr version number
    if (isset($system_info['lucene']['solr-spec-version'])) {
      return $system_info['lucene']['solr-spec-version'];
    }
    return 0;
  }

  /**
   * Gets information about the Solr Core.
   *
   * @return object
   *   A response object with system information.
   */
  protected function getSystemInfo() {
    // @todo Add back persistent cache?
    if (!isset($this->systemInfo)) {
      // @todo Finish https://github.com/basdenooijer/solarium/pull/155 and stop
      // abusing the ping query for this.
      $query = $this->solr->createPing();
      $query->setHandler('admin/system');
      $this->systemInfo = $this->solr->ping($query)->getData();
    }

    return $this->systemInfo;
  }

  /**
   * Gets meta-data about the index.
   *
   * @return object
   *   A response object filled with data from Solr's Luke.
   */
  protected function getLuke() {
    // @todo Write a patch for Solarium to have a separate Luke query and stop
    // abusing the ping query for this.
    $query = $this->solr->createPing();
    $query->setHandler('admin/luke');
    return $this->solr->ping($query)->getData();
  }

  /**
   * Gets summary information about the Solr Core.
   *
   * @return array
   */
  protected function getStatsSummary() {
    $summary = array(
      '@pending_docs' => '',
      '@autocommit_time_seconds' => '',
      '@autocommit_time' => '',
      '@deletes_by_id' => '',
      '@deletes_by_query' => '',
      '@deletes_total' => '',
      '@schema_version' => '',
      '@core_name' => '',
      '@index_size' => '',
    );

    $solr_version = $this->getSolrVersion();
    $query = $this->solr->createPing();
    $query->setResponseWriter(Query::WT_PHPS);
    if (version_compare($solr_version, '4', '>=')) {
      $query->setHandler('admin/mbeans?stats=true');
    }
    else {
      $query->setHandler('admin/stats.jsp');
    }
    $stats = $this->solr->ping($query)->getData();
    if (!empty($stats)) {
      if (version_compare($solr_version, '3', '<=')) {
        // @todo Needs to be updated by someone who has a Solr 3.x setup.
        /*
        $docs_pending_xpath = $stats->xpath('//stat[@name="docsPending"]');
        $summary['@pending_docs'] = (int) trim(current($docs_pending_xpath));
        $max_time_xpath = $stats->xpath('//stat[@name="autocommit maxTime"]');
        $max_time = (int) trim(current($max_time_xpath));
        // Convert to seconds.
        $summary['@autocommit_time_seconds'] = $max_time / 1000;
        $summary['@autocommit_time'] = \Drupal::service('date')->formatInterval($max_time / 1000);
        $deletes_id_xpath = $stats->xpath('//stat[@name="deletesById"]');
        $summary['@deletes_by_id'] = (int) trim(current($deletes_id_xpath));
        $deletes_query_xpath = $stats->xpath('//stat[@name="deletesByQuery"]');
        $summary['@deletes_by_query'] = (int) trim(current($deletes_query_xpath));
        $summary['@deletes_total'] = $summary['@deletes_by_id'] + $summary['@deletes_by_query'];
        $schema = $stats->xpath('/solr/schema[1]');
        $summary['@schema_version'] = trim($schema[0]);
        $core = $stats->xpath('/solr/core[1]');
        $summary['@core_name'] = trim($core[0]);
        $size_xpath = $stats->xpath('//stat[@name="indexSize"]');
        $summary['@index_size'] = trim(current($size_xpath));
        */
      }
      else {
        $update_handler_stats = $stats['solr-mbeans']['UPDATEHANDLER']['updateHandler']['stats'];
        $summary['@pending_docs'] = (int) $update_handler_stats['docsPending'];
        $max_time = (int) $update_handler_stats['autocommit maxTime'];
        // Convert to seconds.
        $summary['@autocommit_time_seconds'] = $max_time / 1000;
        $summary['@autocommit_time'] = \Drupal::service('date.formatter')->formatInterval($max_time / 1000);
        $summary['@deletes_by_id'] = (int) $update_handler_stats['deletesById'];
        $summary['@deletes_by_query'] = (int) $update_handler_stats['deletesByQuery'];
        $summary['@deletes_total'] = $summary['@deletes_by_id'] + $summary['@deletes_by_query'];
        $summary['@schema_version'] = $this->getSystemInfo()['core']['schema'];
        $summary['@core_name'] = $stats['solr-mbeans']['CORE']['core']['stats']['coreName'];
        $summary['@index_size'] = $stats['solr-mbeans']['QUERYHANDLER']['/replication']['stats']['indexSize'];
      }
    }
    return $summary;
  }

  /**
   * Escapes a Search API field name for passing to Solr.
   *
   * Since field names can only contain one special character, ":", there is no
   * need to use the complete escape() method.
   *
   * @param string $value
   *   The field name to escape.
   *
   * @return string
   *   An escaped string suitable for passing to Solr.
   */
  public static function escapeFieldName($value) {
    $value = str_replace(':', '\:', $value);
    return $value;
  }

}
