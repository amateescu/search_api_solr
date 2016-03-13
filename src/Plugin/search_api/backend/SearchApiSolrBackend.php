<?php

/**
 * @file
 * Contains \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend.
 */

namespace Drupal\search_api_solr\Plugin\search_api\backend;

use Drupal\Core\Config\Config;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Query\ConditionInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility as SearchApiUtility;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\Utility\Utility as SearchApiSolrUtility;
use Drupal\search_api_solr\Solr\SolrHelper;
use Solarium\Client;
use Solarium\Core\Client\Request;
use Solarium\Core\Query\Helper;
use Solarium\Core\Query\Result\ResultInterface;
use Solarium\QueryType\Select\Query\Query;
use Solarium\Exception\ExceptionInterface;
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
class SearchApiSolrBackend extends BackendPluginBase implements SolrBackendInterface {

  /**
   * The date format that Solr uses, in PHP date() syntax.
   */
  const SOLR_DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

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
  protected $field_names;

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
   * A connection to the Solr server.
   *
   * @var \Solarium\Client
   */
  protected $solr;

  /**
   * SolrHelper object with helper functions to work with Solr.
   *
   * @var \Drupal\search_api_solr\Solr\SolrHelper
   */
  protected $solrHelper;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ModuleHandlerInterface $module_handler, Config $search_api_solr_settings, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->moduleHandler = $module_handler;
    $this->searchApiSolrSettings = $search_api_solr_settings;
    $this->languageManager = $language_manager;
    $solr_helper = new SolrHelper($this->configuration + array('key' => $this->server->id()));
    $this->setSolrHelper($solr_helper);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('config.factory')->get('search_api_solr.settings'),
      $container->get('language_manager')
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
      'site_hash' => TRUE,
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
        '#description' => $this->getSolrHelper()->getServerLink(),
      );
    }

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
        '5' => '5.x',
        '6' => '6.x',
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


    if ($this->moduleHandler->moduleExists('search_api_autocomplete')) {
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

    $form['multisite'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Multi-site compatibility'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $description = $this->t('Automatically filter all searches to only retrieve results from this Drupal site. By default a Solr server (and core) is able to index the data of multiple sites. Disable if you want to retrieve results from multiple sites at once.');
    $form['multisite']['site_hash'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Retrieve results for this site only'),
      '#description' => $description,
      '#default_value' => $this->configuration['site_hash'],
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (isset($values['port']) && (!is_numeric($values['port']) || $values['port'] < 0 || $values['port'] > 65535)) {
      $form_state->setError($form['port'], $this->t('The port has to be an integer between 0 and 65535.'));
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
    $values += $values['multisite'];
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

    // Clean-up the form to avoid redundant entries in the stored configuration.
    $form_state->unsetValue('http');
    $form_state->unsetValue('advanced');
    $form_state->unsetValue('multisite');
    // The server description is a #type item element, which means it has a
    // value, do not save it.
    $form_state->unsetValue('server_description');

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    // First, check the features we always support.
    $supported = array(
      //'search_api_autocomplete',
      //'search_api_facets',
      //'search_api_facets_operator_or',
      //'search_api_grouping',
      'search_api_mlt',
      'search_api_random_sort',
      'search_api_spellcheck',
      //'search_api_data_type_location',
      //'search_api_data_type_geohash',
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
    $type = SearchApiSolrUtility::getDataTypeInfo($type);
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
      'info' => $this->getSolrHelper()->getServerLink(),
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
          $data = $this->getSolrHelper()->getLuke();
          if (isset($data['index']['numDocs'])) {
            // Collect the stats
            $stats_summary = $this->getSolrHelper()->getStatsSummary();

            $pending_msg = $stats_summary['@pending_docs'] ? $this->t('(@pending_docs sent but not yet processed)', $stats_summary) : '';
            $index_msg = $stats_summary['@index_size'] ? $this->t('(@index_size on disk)', $stats_summary) : '';
            $indexed_message = $this->t('@num items @pending @index_msg', array(
              '@num' => $data['index']['numDocs'],
              '@pending' => $pending_msg,
              '@index_msg' => $index_msg,
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
                $variables['@url'] = Url::fromUri('internal:/' . drupal_get_path('module', 'search_api_solr') . '/INSTALL.txt')->toString();
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
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {
    if ($this->indexFieldsUpdated($index)) {
      $index->reindex();
    }
  }

  /**
   * Checks if the recently updated index had any fields changed.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return bool
   */
  protected function indexFieldsUpdated(IndexInterface $index) {
    // Get the original index, before the update. If it cannot be found, err on
    // the side of caution.
    if (!isset($index->original)) {
      return TRUE;
    }
    /** @var \Drupal\search_api\IndexInterface $original */
    $original = $index->original;

    $old_fields = $original->getFields();
    $new_fields = $index->getFields();
    if (!$old_fields && !$new_fields) {
      return FALSE;
    }
    if (array_diff_key($old_fields, $new_fields) || array_diff_key($new_fields, $old_fields)) {
      return TRUE;
    }
    $old_field_names = $this->getFieldNames($original, FALSE, TRUE);
    $new_field_names = $this->getFieldNames($index, FALSE, TRUE);
    return $old_field_names != $new_field_names;
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
    $field_names = $this->getFieldNames($index);
    $field_names_single_value = $this->getFieldNames($index, TRUE);
    $languages = $this->languageManager->getLanguages();
    $base_urls = array();

    // Make sure that we have a Solr connection.
    $this->connect();

    /** @var \Drupal\search_api\Item\ItemInterface[] $items */
    foreach ($items as $id => $item) {
      /** @var \Solarium\QueryType\Update\Query\Document\Document $doc */
      $doc = $this->getUpdateQuery()->createDocument();
      $doc->setField('id', $this->createId($index_id, $id));
      $doc->setField('index_id', $index_id);
      $doc->setField('item_id', $id);

      // Add the site hash and language-specific base URL.
      $doc->setField('hash', SearchApiSolrUtility::getSiteHash());
      $lang = $item->getField('search_api_language')->getValues();
      $lang = reset($lang);
      if (empty($base_urls[$lang])) {
        $url_options = array('absolute' => TRUE);
        if (isset($languages[$lang])) {
          $url_options['language'] = $languages[$lang];
        }
        // An exception is thrown if this is called during a non-HTML response
        // like REST or a redirect without collecting metadata. Avoid that by
        // collecting and discarding it.
        // See https://www.drupal.org/node/2638686.
        $base_urls[$lang] = Url::fromRoute('<front>', array(), $url_options)->toString(TRUE)->getGeneratedUrl();
      }
      $doc->setField('site', $base_urls[$lang]);

      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($item as $name => $field) {
        // If the field is not known for the index, something weird has
        // happened. We refuse to index the items and hope that the others are
        // OK.
        if (!isset($field_names[$name])) {
          $vars = array(
            '%field' => $name,
            '@id' => $id,
          );
          \Drupal::logger('search_api_solr')->warning('Error while indexing: Unknown field %field on the item with ID @id.', $vars);
          $doc = NULL;
          break;
        }
        $this->addIndexField($doc, $field_names[$name], $field_names_single_value[$name], $field->getValues(), $field->getType());
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
      // Do a commitWithin since that is automatically a softCommit with Solr 4
      // and a delayed hard commit with Solr 3.4+.
      // We wait 1 second after the request arrived for solr to parse the commit.
      // This allows us to return to Drupal and let Solr handle what it
      // needs to handle
      // @see http://wiki.apache.org/solr/NearRealtimeSearch
      /** @var \Solarium\Plugin\CustomizeRequest\CustomizeRequest $customizer */
      $customizer = $this->solr->getPlugin('customizerequest');
      $customizer->createCustomization('id')
        ->setType('param')
        ->setName('commitWithin')
        ->setValue('1000');

      $this->solr->execute($this->getUpdateQuery());

      // Reset the Update query for further calls.
      static::$updateQuery = NULL;
      return $ret;
    }
    catch (\Exception $e) {
      watchdog_exception('search_api_solr', $e, "%type while indexing: @message in %function (line %line of %file).");
    }
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $ids) {
    try {
      $this->connect();
      $index_id = $this->getIndexId($index->id());
      $solr_ids = array();
      foreach ($ids as $id) {
        $solr_ids[] = $this->createId($index_id, $id);
      }
      $this->getUpdateQuery()->addDeleteByIds($solr_ids);

      // Do a commitWithin since that is automatically a softCommit with Solr 4
      // and a delayed hard commit with Solr 3.4+.
      // We wait 1 second after the request arrived for solr to parse the commit.
      // This allows us to return to Drupal and let Solr handle what it
      // needs to handle
      // @see http://wiki.apache.org/solr/NearRealtimeSearch
      /** @var \Solarium\Plugin\CustomizeRequest\CustomizeRequest $customizer */
      $customizer = $this->solr->getPlugin('customizerequest');
      $customizer->createCustomization('id')
        ->setType('param')
        ->setName('commitWithin')
        ->setValue('1000');

      $this->solr->execute($this->getUpdateQuery());
    }
    catch (ExceptionInterface $e) {
      throw new SearchApiException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index = NULL) {
    $this->connect();
    if ($index) {
      // Since the index ID we use for indexing can contain arbitrary
      // prefixes, we have to escape it for use in the query.
      $index_id = $this->getQueryHelper()->escapePhrase($index->id());
      $index_id = $this->getIndexId($index_id);
      $query = '(index_id:' . $index_id . ')';
      $site_hash = $this->getQueryHelper()->escapePhrase(SearchApiSolrUtility::getSiteHash());
      $query .= ' AND (hash:' . $site_hash . ')';
      $this->getUpdateQuery()->addDeleteQuery($query);
    }
    else {
      $this->getUpdateQuery()->addDeleteQuery('*:*');
    }

    // Do a commitWithin since that is automatically a softCommit with Solr 4
    // and a delayed hard commit with Solr 3.4+.
    // We wait 1 second after the request arrived for solr to parse the commit.
    // This allows us to return to Drupal and let Solr handle what it
    // needs to handle
    // @see http://wiki.apache.org/solr/NearRealtimeSearch
    /** @var \Solarium\Plugin\CustomizeRequest\CustomizeRequest $customizer */
    $customizer = $this->solr->getPlugin('customizerequest');
    $customizer->createCustomization('id')
      ->setType('param')
      ->setName('commitWithin')
      ->setValue('1000');

    $this->solr->update($this->getUpdateQuery());
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    // Reset request handler.
    $this->request_handler = NULL;
    // Get field information.
    /** @var \Drupal\search_api\Entity\Index $index */
    $index = $query->getIndex();
    $index_id = $this->getIndexId($index->id());
    $field_names = $this->getFieldNames($index);
    $field_names_single_value = $this->getFieldNames($index, TRUE);

    // Get Solr connection.
    $this->connect();

    // Instantiate a Solarium select query.
    $solarium_query = $this->solr->createSelect();

    // Extract keys.
    $keys = $query->getKeys();
    if (is_array($keys)) {
      $keys = $this->getSolrHelper()->flattenKeys($keys);
    }
    // Set them
    $solarium_query->setQuery($keys);
    unset($keys);

    $returned_fields = array('item_id', 'score');
    if (!$this->configuration['site_hash']) {
      $returned_fields[] = 'hash';
    }
    $solarium_query->setFields($returned_fields);

    // Set searched fields.
    $options = $query->getOptions();
    $search_fields = $this->getQueryFulltextFields($query);
    // Get the index fields to be able to retrieve boosts.
    $index_fields = $index->getFields();
    $query_fields = array();
    foreach ($search_fields as $search_field) {
      /** @var \Solarium\QueryType\Update\Query\Document\Document $document */
      $document = $index_fields[$search_field];
      $boost = $document->getBoost() ? '^' . $document->getBoost() : '';
      $query_fields[] = $field_names[$search_field] . $boost;
    }
    $solarium_query->getEDisMax()->setQueryFields(implode(' ', $query_fields));

    // Handle More Like This requests
    $mlt_options = $query->getOption('search_api_mlt');
    if ($mlt_options) {
      $index_fields = $index->getFields();
      $this->getSolrHelper()->setMoreLikeThis($solarium_query, $query, $mlt_options, $index_fields, $field_names);

      // Override the search key by setting it to the solr document id
      // we want to compare it with
      // @todo. Figure out how we can set MLT earlier in the process
      // so we do not do unnecessary function calls
      $id = $this->createId($index_id, $mlt_options['id']);
      $id = static::getQueryHelper()->escapePhrase($id);
      $solarium_query->setQuery('id:' . $id);
    }

    // Set basic filters.
    $conditions_queries = $this->createFilterQueries($query->getConditionGroup(), $field_names, $index->getFields());
    foreach ($conditions_queries as $id => $conditions_query) {
      $solarium_query->createFilterQuery('filters_' . $id)->setQuery($conditions_query);
    }

    // Set the Index filter
    $solarium_query->createFilterQuery('index_id')->setQuery('index_id:' . static::getQueryHelper($solarium_query)->escapePhrase($index_id));

    // Set the site hash filter, if enabled.
    if ($this->configuration['site_hash']) {
      $site_hash = $this->getQueryHelper()->escapePhrase(SearchApiSolrUtility::getSiteHash());
      $solarium_query->createFilterQuery('site_hash')->setQuery('hash:' . $site_hash);
    }

    // Set sorts.
    $this->solrHelper->setSorts($solarium_query, $query, $field_names_single_value);

    // Set facet fields.
    $facets = $query->getOption('search_api_facets', array());
    $this->setFacets($facets, $field_names, $solarium_query);

    // Set highlighting.
    $excerpt = !empty($this->configuration['excerpt']) ? true : false;
    $highlight_data = !empty($this->configuration['highlight_data']) ? true : false;
    $this->getSolrHelper()->setHighlighting($solarium_query, $query, $excerpt, $highlight_data);

    // Handle spatial filters.
    $spatial_options = $query->getOption('search_api_location');
    if ($spatial_options) {
      $this->solrHelper->setSpatial($solarium_query, $query, $spatial_options, $field_names);
    }

    // Handle field collapsing / grouping.
    $grouping_options = $query->getOption('search_api_grouping');
    if (!empty($grouping_options['use_grouping'])) {
      $this->solrHelper->setGrouping($solarium_query, $query, $grouping_options, $index_fields, $field_names);
    }

    if (isset($options['offset'])) {
      $solarium_query->setStart($options['offset']);
    }
    $rows = isset($options['limit']) ? $options['limit'] : 1000000;
    $solarium_query->setRows($rows);

    if (!empty($options['search_api_spellcheck'])) {
      $solarium_query->getSpellcheck();
    }

    /**
     * @todo Make this more configurable so that views can choose which fields
     * it wants to fetch
     */
    if (!empty($this->configuration['retrieve_data'])) {
      $solarium_query->setFields(array('*', 'score'));
    }

    // Allow modules to alter the query
    try {
      $this->moduleHandler->alter('search_api_solr_query', $solarium_query, $query);
      $this->preQuery($solarium_query, $query);

      // Use the 'postbigrequest' plugin if no specific http method is
      // configured. The plugin needs to be loaded before the request is
      // created.
      if ($this->configuration['http_method'] == 'AUTO') {
        $this->solr->getPlugin('postbigrequest');
      }

      // Use the manual method of creating a Solarium request so we can control
      // the HTTP method.
      $request = $this->solr->createRequest($solarium_query);

      // Set the configured HTTP method.
      if ($this->configuration['http_method'] == 'POST') {
        $request->setMethod(Request::METHOD_POST);
      }
      elseif ($this->configuration['http_method'] == 'GET') {
        $request->setMethod(Request::METHOD_GET);
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
      if ($resultset instanceof Result) {
        if ($facets = $this->extractFacets($query, $resultset)) {
          $results->setExtraData('search_api_facets', $facets);
        }
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
   * {@inheritdoc}
   */
  public function getSolrHelper() {
    // Make sure that the SolrHelper has a connection which is required for some
    // of its functions.
    $this->connect();

    return $this->solrHelper;
  }

  /**
   * {@inheritdoc}
   */
  public function setSolrHelper($solrHelper) {
    $this->solrHelper = $solrHelper;
  }

  /**
   * Creates a connection to the Solr server as configured in $this->configuration.
   */
  protected function connect() {
    if (!$this->solr) {
      $this->solr = new Client();
      $this->solr->createEndpoint($this->configuration + array('key' => $this->server->id()), TRUE);
      $this->getSolrHelper()->setSolr($this->solr);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSolr() {
    return $this->solr;
  }

  /**
   * Creates an ID used as the unique identifier at the Solr server.
   *
   * This has to consist of both index and item ID. Optionally, the site hash is
   * also included.
   *
   * @see \Drupal\search_api_solr\Utility\Utility::getSiteHash()
   */
  protected function createId($index_id, $item_id) {
    return SearchApiSolrUtility::getSiteHash() . "-$index_id-$item_id";
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldNames(IndexInterface $index, $single_value_name = FALSE, $reset = FALSE) {
    // @todo The field name mapping should be cached per index because custom
    // queries needs to access it on every query.
    $subkey = (int) $single_value_name;
    if (!isset($this->fieldNames[$index->id()][$subkey]) || $reset) {
      // This array maps "local property name" => "solr doc property name".
      $ret = array(
        'search_api_id' => 'item_id',
        'search_api_relevance' => 'score',
        'search_api_random' => 'random',
      );

      // Add the names of any fields configured on the index.
      $fields = $index->getFields();
      foreach ($fields as $key => $field) {
        // Generate a field name; this corresponds with naming conventions in
        // our schema.xml
        $type = $field->getType();
        $type_info = SearchApiSolrUtility::getDataTypeInfo($type);
        $pref = isset($type_info['prefix']) ? $type_info['prefix'] : '';
        $pref .= ($single_value_name) ? 's' : 'm';
        $name = $pref . '_' . $key;
        $ret[$key] = SearchApiSolrUtility::encodeSolrDynamicFieldName($name);
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
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index for which items are being indexed.
   * @param array $items
   *   An array of items being indexed.
   *
   * @see hook_search_api_solr_documents_alter()
   */
  protected function alterSolrDocuments(array &$documents, IndexInterface $index, array $items) {
  }

  /**
   * Extract results from a Solr response.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query object.
   * @param \Solarium\Core\Query\Result\ResultInterface $result
   *   A Solarium select response object.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   A result set object.
   */
  protected function extractResults(QueryInterface $query, ResultInterface $result) {
    $index = $query->getIndex();
    $field_names = $this->getFieldNames($index);
    $fields = $index->getFields();
    $site_hash = SearchApiSolrUtility::getSiteHash();
    // We can find the item ID and the score in the special 'search_api_*'
    // properties. Mappings are provided for these properties in
    // SearchApiSolrBackend::getFieldNames().
    $id_field = $field_names['search_api_id'];
    $score_field = $field_names['search_api_relevance'];

    // Set up the results array.
    $result_set = SearchApiUtility::createSearchResultSet($query);
    $result_set->setExtraData('search_api_solr_response', $result->getData());

    // In some rare cases (e.g., MLT query with nonexistent ID) the response
    // will be NULL.
    $is_grouping = $result instanceof Result && $result->getGrouping();
    if (!$result->getResponse() && !$is_grouping) {
      $result_set->setResultCount(0);
      return $result_set;
    }

    // If field collapsing has been enabled for this query, we need to process
    // the results differently.
    $grouping = $query->getOption('search_api_grouping');
    $docs = array();
    if (!empty($grouping['use_grouping']) && $is_grouping) {
//      $docs = array();
//      $result_set['result count'] = 0;
//      foreach ($grouping['fields'] as $field) {
//        if (!empty($response->grouped->{$fields[$field]})) {
//          $result_set['result count'] += $response->grouped->{$fields[$field]}->ngroups;
//          foreach ($response->grouped->{$fields[$field]}->groups as $group) {
//            foreach ($group->doclist->docs as $doc) {
//              $docs[] = $doc;
//            }
//          }
//        }
//      }
    }
    else {
      $result_set->setResultCount($result->getNumFound());
      $docs = $result->getDocuments();
    }

    // Add each search result to the results array.
    /** @var \Solarium\QueryType\Select\Result\Document $doc */
    foreach ($docs as $doc) {
      $doc_fields = $doc->getFields();

      $item_id = $doc_fields[$id_field];
      // For items coming from a different site, we need to adapt the item ID.
      if (!$this->configuration['site_hash'] && $doc_fields['hash'] != $site_hash) {
        $item_id = $doc_fields['hash'] . '--' . $item_id;
      }
      $result_item = SearchApiUtility::createItem($index, $item_id);
      $result_item->setScore($doc_fields[$score_field]);
      unset($doc_fields[$id_field], $doc_fields[$score_field]);

      // Extract properties from the Solr document, translating from Solr to
      // Search API property names. This reverses the mapping in
      // SearchApiSolrBackend::getFieldNames().
      foreach ($field_names as $search_api_property => $solr_property) {
        if (isset($doc_fields[$solr_property]) && isset($fields[$search_api_property])) {
          $field = clone $fields[$search_api_property];

          // Date fields need some special treatment to become valid date values
          // (i.e., timestamps) again.
          if ($field->getType() == 'date'
              && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $doc_fields[$solr_property][0])) {
            $doc_fields[$solr_property][0] = strtotime($doc_fields[$solr_property][0]);
          }

          $field->setValues($doc_fields[$solr_property]);
          $result_item->setField($search_api_property, $field);
        }
      }

      $index_id = $this->getIndexId($index->id());
      $solr_id = $this->createId($index_id, $result_item->getId());
      $item_fields = $result_item->getFields();
      $excerpt = $this->getSolrHelper()->getExcerpt($result->getData(), $solr_id, $item_fields, $field_names);
      if ($excerpt) {
        $result_item->setExcerpt($excerpt);
      }

      $result_set->addResultItem($result_item);
    }

    // Check for spellcheck suggestions.
    /*if (module_exists('search_api_spellcheck') && $query->getOption('search_api_spellcheck')) {
       $result_set->setExtraData('search_api_spellcheck', new SearchApiSpellcheckSolr($result));
    }*/

    return $result_set;
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
    if (!$resultset->getFacetSet()) {
      return array();
    }

    $facets = array();
    $index = $query->getIndex();
    $field_names = $this->getFieldNames($index);
    $fields = $index->getFields();

    $extract_facets = $query->getOption('search_api_facets', array());

    if ($facet_fields = $resultset->getFacetSet()->getFacets()) {
      foreach ($extract_facets as $delta => $info) {
        $field = $field_names[$info['field']];
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
          $type = isset($fields[$info['field']]) ? $fields[$info['field']]->getType() : 'string';
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
   * Transforms a query filter into a flat array of Solr filter queries, using
   * the field names in $field_names.
   */
  protected function createFilterQueries(ConditionGroupInterface $conditions, array $solr_fields, array $index_fields) {
    $or = $conditions->getConjunction() == 'OR';
    $fq = array();
    $prefix = '';
    foreach ($conditions->getConditions() as $condition) {
      if ($condition instanceof ConditionInterface) {
        $field = $condition->getField();
        if (!isset($index_fields[$field])) {
          throw new SearchApiException(t('Filter term on unknown or unindexed field @field.', array('@field' => $field)));
        }
        $value = $condition->getValue();
        if ($value !== '') {
          $fq[] = $this->createFilterQuery($solr_fields[$field], $value, $condition->getOperator(), $index_fields[$field]);
        }
      }
      else {
        $q = $this->createFilterQueries($condition, $solr_fields, $index_fields);
        if ($conditions->getConjunction() != $condition->getConjunction() && count($q) > 1) {
          // $or == TRUE means the nested filter has conjunction AND, and vice versa
          $sep = $or ? ' ' : ' OR ';
          $fq[] = '((' . implode(')' . $sep . '(', $q) . '))';
        }
        else {
          $fq = array_merge($fq, $q);
        }
      }
    }
    foreach ($conditions->getTags() as $tag) {
      $prefix = "{!tag=$tag}";
      // We can only apply one tag per filter.
      break;
    }
    if ($or && count($fq) > 1) {
      $fq = array('((' . implode(') OR (', $fq) . '))');
    }
    if ($prefix) {
      foreach ($fq as $i => $filters) {
        $fq[$i] = $prefix . $filters;
      }
    }
    return $fq;
  }

  /**
   * Create a single search query string according to the given field, value
   * and operator.
   */
  protected function createFilterQuery($field, $value, $operator, FieldInterface $index_field) {
    $field = SearchApiSolrUtility::escapeFieldName($field);
    if ($value === NULL) {
      return ($operator == '=' ? '*:* AND -' : '') . "$field:[* TO *]";
    }
    $value = trim($value);
    $value = $this->formatFilterValue($value, $index_field->getType());
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
  protected function setFacets(array $facets, array $field_names, Query $solarium_query) {
    if (empty($facets)) {
      return;
    }

    $facet_set = $solarium_query->getFacetSet();
    $facet_set->setSort('count');
    $facet_set->setLimit(10);
    $facet_set->setMinCount(1);
    $facet_set->setMissing(FALSE);

    foreach ($facets as $info) {
      if (empty($field_names[$info['field']])) {
        continue;
      }
      $field = $field_names[$info['field']];
      // Create the Solarium facet field object.
      $facet_field = $facet_set->createFacetField($field)->setField($field);

      // For "OR" facets, add the expected tag for exclusion.
      if (isset($info['operator']) && strtolower($info['operator']) === 'or') {
        $facet_field->setExcludes(array('facet:' . $info['field']));
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
      else {
        $facet_field->setMissing(FALSE);
      }
    }
  }

  /**
   * Sets the request handler.
   *
   * This should also make the needed adjustments to the request parameters.
   * @todo SearchApiSolrConnectionInterface doesn't exist!
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
    $incomp = Unicode::strtolower($incomplete_key);

    $index = $query->getIndex();
    $field_names = $this->getFieldNames($index);
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
      $keys = $this->getSolrHelper()->flattenKeys($query->getKeys());
    }
    else {
      $keys_array = preg_split('/[-\s():{}\[\]\\\\"]+/', $keys, -1, PREG_SPLIT_NO_EMPTY);
      $keys_array = array_combine($keys_array, $keys_array);
    }
    if (!$keys) {
      $keys = NULL;
    }

    // Set searched fields
    $options = $query->getOptions();
    $search_fields = $query->getFulltextFields();
    $qf = array();
    foreach ($search_fields as $f) {
      $qf[] = $field_names[$f];
    }

    // Extract filters
    $fq = $this->createFilterQueries($query->getFilter(), $field_names, $index->getOption('fields', array()));
    $index_id = $this->getIndexId($index->id());
    $fq[] = 'index_id:' . $this->getQueryHelper()->escapePhrase($index_id);
    if ($this->configuration['site_hash']) {
      $site_hash = $this->getQueryHelper()->escapePhrase(SearchApiSolrUtility::getSiteHash());
      $fq[] = 'hash:' . $site_hash;
    }

    // Autocomplete magic
    $facet_fields = array();
    foreach ($search_fields as $f) {
      $facet_fields[] = $field_names[$f];
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
              if (Unicode::strtolower(substr($term, 0, $incomp_length)) == $incomp) {
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

  /**
   * {@inheritdoc}
   */
  public function ping() {
    $this->connect();
    $query = $this->solr->createPing();

    try {
      $start = microtime(TRUE);
      $result = $this->solr->execute($query);
      if ($result->getResponse()->getStatusCode() == 200) {
        // Add 1 µs to the ping time so we never return 0.
        return (microtime(TRUE) - $start) + 1E-6;
      }
    }
    catch (HttpException $e) {
      watchdog_exception('search_api_solr', $e);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrConnection() {
    $this->connect();
    return $this->solr;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($num_terms = 0) {
    $this->connect();
    // @todo getFields() is not defined.
    return $this->solr->getFields($num_terms);
  }

  /**
   * {@inheritdoc}
   */
  public function getFile($file = NULL) {
    $this->connect();

    $query = $this->solr->createPing();
    $query->setHandler('admin/file');
    $query->addParam('contentType', 'text/xml;charset=utf-8');
    if ($file) {
      $query->addParam('file', $file);
    }

    return $this->solr->execute($query)->getResponse();
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
   * @param \Solarium\QueryType\Select\Query\Query|null $query
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
}
