<?php

namespace Drupal\search_api_solr\Plugin\search_api\backend;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Url;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Query\ConditionGroup;
use Drupal\search_api\Query\ConditionInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\Utility\DataTypeHelperInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api\Utility\Utility as SearchApiUtility;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;
use Drupal\search_api_solr\Entity\SolrFieldType;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\Solarium\Autocomplete\Query as AutocompleteQuery;
use Drupal\search_api_solr\SolrAutocompleteInterface;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\SolrCloudConnectorInterface;
use Drupal\search_api_solr\SolrConnector\SolrConnectorPluginManager;
use Drupal\search_api_solr\SolrProcessorInterface;
use Drupal\search_api_solr\Utility\SolrCommitTrait;
use Drupal\search_api_solr\Utility\Utility;
use Solarium\Component\ComponentAwareQueryInterface;
use Solarium\Core\Client\Response;
use Solarium\Core\Query\Helper;
use Solarium\Core\Query\QueryInterface as SolariumQueryInterface;
use Solarium\Core\Query\Result\ResultInterface;
use Solarium\Exception\ExceptionInterface;
use Solarium\Exception\StreamException;
use Solarium\QueryType\Stream\Expression;
use Solarium\QueryType\Update\Query\Query as UpdateQuery;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Result;
use Solarium\QueryType\Update\Query\Document\Document;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Apache Solr backend for search api.
 *
 * @SearchApiBackend(
 *   id = "search_api_solr",
 *   label = @Translation("Solr"),
 *   description = @Translation("Index items using an Apache Solr search server.")
 * )
 */
class SearchApiSolrBackend extends BackendPluginBase implements SolrBackendInterface, SolrAutocompleteInterface, PluginFormInterface {

  use PluginFormTrait {
    submitConfigurationForm as traitSubmitConfigurationForm;
  }

  use SolrCommitTrait;

  /**
   * Metadata describing fields on the Solr/Lucene index.
   *
   * @var string[][]
   */
  protected $fieldNames = [];

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
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The backend plugin manager.
   *
   * @var \Drupal\search_api_solr\SolrConnector\SolrConnectorPluginManager
   */
  protected $solrConnectorPluginManager;

  /**
   * @var \Drupal\search_api_solr\SolrConnectorInterface
   */
  protected $solrConnector;

  /**
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * The data type helper.
   *
   * @var \Drupal\search_api\Utility\DataTypeHelper|null
   */
  protected $dataTypeHelper;

  /**
   * The Solarium query helper.
   *
   * @var Helper
   */
  protected $queryHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ModuleHandlerInterface $module_handler, Config $search_api_solr_settings, LanguageManagerInterface $language_manager, SolrConnectorPluginManager $solr_connector_plugin_manager, FieldsHelperInterface $fields_helper, DataTypeHelperInterface $dataTypeHelper, Helper $query_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->moduleHandler = $module_handler;
    $this->searchApiSolrSettings = $search_api_solr_settings;
    $this->languageManager = $language_manager;
    $this->solrConnectorPluginManager = $solr_connector_plugin_manager;
    $this->fieldsHelper = $fields_helper;
    $this->dataTypeHelper = $dataTypeHelper;
    $this->queryHelper = $query_helper;
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
      $container->get('language_manager'),
      $container->get('plugin.manager.search_api_solr.connector'),
      $container->get('search_api.fields_helper'),
      $container->get('search_api.data_type_helper'),
      $container->get('solarium.query_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'retrieve_data' => FALSE,
      'highlight_data' => FALSE,
      'skip_schema_check' => FALSE,
      'site_hash' => FALSE,
      'server_prefix' => '',
      'domain' => 'generic',
      // Set the default for new servers to NULL to force "safe" un-selected
      // radios. @see https://www.drupal.org/node/2820244
      'connector' => NULL,
      'connector_config' => [],
      'sasm_limit_search_page_to_content_language' => FALSE,
      'sasm_search_page_include_language_independent' => FALSE,
      'optimize' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if (!$this->server->isNew()) {
      // Editing this server.
      $form['server_description'] = [
        '#type' => 'item',
        '#title' => $this->t('Solr server URI'),
        '#description' => $this->getSolrConnector()->getServerLink(),
      ];
    }

    $solr_connector_options = $this->getSolrConnectorOptions();
    $form['connector'] = [
      '#type' => 'radios',
      '#title' => $this->t('Solr Connector'),
      '#description' => $this->t('Choose a connector to use for this Solr server.'),
      '#options' => $solr_connector_options,
      '#default_value' => $this->configuration['connector'],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxSolrConnectorConfigForm'],
        'wrapper' => 'search-api-solr-connector-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $this->buildConnectorConfigForm($form, $form_state);

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
    ];
    $form['advanced']['retrieve_data'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Retrieve result data from Solr'),
      '#description' => $this->t('When checked, result data will be retrieved directly from the Solr server. This might make item loads unnecessary. Only indexed fields can be retrieved. Note also that the returned field data might not always be correct, due to preprocessing and caching issues.'),
      '#default_value' => $this->configuration['retrieve_data'],
    ];
    $form['advanced']['highlight_data'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Retrieve highlighted snippets'),
      '#description' => $this->t('Return a highlighted version of the indexed fulltext fields. These will be used by the "Highlighting Processor" directly instead of applying its own PHP algorithm.'),
      '#default_value' => $this->configuration['highlight_data'],
    ];
    $form['advanced']['skip_schema_check'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip schema verification'),
      '#description' => $this->t('Skip the automatic check for schema-compatibillity. Use this override if you are seeing an error-message about an incompatible schema.xml configuration file, and you are sure the configuration is compatible.'),
      '#default_value' => $this->configuration['skip_schema_check'],
    ];

    $form['advanced']['server_prefix'] = [
      '#type' => 'textfield',
      '#title' => t('All index prefix'),
      '#description' => t("By default, the index ID in the Solr server is the same as the index's machine name in Drupal. This setting will let you specify an additional prefix. Only use alphanumeric characters and underscores. Since changing the prefix makes the currently indexed data inaccessible, you should not change this variable when no data is indexed."),
      '#default_value' => $this->configuration['server_prefix'],
    ];

    $domains = SolrFieldType::getAvailableDomains();
    $form['advanced']['domain'] = [
      '#type' => 'select',
      '#options' => array_combine($domains, $domains),
      '#title' => $this->t('Targeted content domain'),
      '#description' => $this->t('For example "UltraBot3000" would be indexed as "Ultra" "Bot" "3000" in a generic domain, "CYP2D6" has to stay like it is in a scientific domain.'),
      '#default_value' => isset($this->configuration['domain']) ? $this->configuration['domain'] : 'generic',
    ];

    $form['advanced']['i_know_what_i_do'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Optimize the Solr index'),
      '#description' => $this->t('Optimize the Solr index once a day. Even if this option "sounds good", think twice before activating it! For most Solr setups it\'s recommended to NOT enable this feature!'),
      '#default_value' => $this->configuration['optimize'],
    ];

    $form['advanced']['optimize'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Yes, I know what I\'m doing and want to enable a daily optimization!'),
      '#default_value' => $this->configuration['optimize'],
      '#states' => [
        'invisible' => [':input[name="advanced][i_know_what_i_do"]' => ['checked' => FALSE]]
      ],
    ];

    $form['multisite'] = [
      '#type' => 'details',
      '#title' => $this->t('Multi-site compatibility'),
      '#description' => $this->t("By default a single Solr backend based Search API server is able to index the data of multiple Drupal sites. But this is an expert-only and dangerous feature that mainly exists for backward compatibility. If you really index multiple sites in one index and don't activate 'Retrieve results for this site only' below you have to ensure that you enable 'Retrieve result data from Solr'! Otherwise it could lead to any kind of errors!"),
    ];
    $description = $this->t("Automatically filter all searches to only retrieve results from this Drupal site. The default and intended behavior is to display results from all sites. WARNING: Enabling this filter might break features like autocomplete, spell checking or suggesters!");
    $form['multisite']['site_hash'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Retrieve results for this site only'),
      '#description' => $description,
      '#default_value' => $this->configuration['site_hash'],
    ];

    $form['multilingual'] = [
      '#type' => 'details',
      '#title' => $this->t('Multilingual'),
    ];
    $form['multilingual']['sasm_limit_search_page_to_content_language'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit to current content language.'),
      '#description' => $this->t('Limit all search results for custom queries or search pages not managed by Views to current content language if no language is specified in the query.'),
      '#default_value' => isset($this->configuration['sasm_limit_search_page_to_content_language']) ? $this->configuration['sasm_limit_search_page_to_content_language'] : FALSE,
    ];
    $form['multilingual']['sasm_search_page_include_language_independent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include language independent content in search results.'),
      '#description' => $this->t('This option will include content without a language assigned in the results of custom queries or search pages not managed by Views. For example, if you search for English content, but have an article with languague of "undefined", you will see those results as well. If you disable this option, you will only see content that matches the language.'),
      '#default_value' => isset($this->configuration['sasm_search_page_include_language_independent']) ? $this->configuration['sasm_search_page_include_language_independent'] : FALSE,
    ];

    return $form;
  }

  /**
   * Returns all available backend plugins, as an options list.
   *
   * @return string[]
   *   An associative array mapping backend plugin IDs to their (HTML-escaped)
   *   labels.
   */
  protected function getSolrConnectorOptions() {
    $options = [];
    foreach ($this->solrConnectorPluginManager->getDefinitions() as $plugin_id => $plugin_definition) {
      $options[$plugin_id] = Html::escape($plugin_definition['label']);
    }
    return $options;
  }

  /**
   * Builds the backend-specific configuration form.
   *
   * @param \Drupal\search_api_solr\SolrConnectorInterface $connector
   *   The server that is being created or edited.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function buildConnectorConfigForm(array &$form, FormStateInterface $form_state) {
    $form['connector_config'] = [];

    $connector_id = $this->configuration['connector'];
    if ($connector_id) {
      $connector = $this->solrConnectorPluginManager->createInstance($connector_id, $this->configuration['connector_config']);
      if ($connector instanceof PluginFormInterface) {
        $form_state->set('connector', $connector_id);
        if ($form_state->isRebuilding()) {
          \Drupal::messenger()->addWarning($this->t('Please configure the selected Solr connector.'));
        }
        // Attach the Solr connector plugin configuration form.
        $connector_form_state = SubformState::createForSubform($form['connector_config'], $form, $form_state);
        $form['connector_config'] = $connector->buildConfigurationForm($form['connector_config'], $connector_form_state);

        // Modify the backend plugin configuration container element.
        $form['connector_config']['#type'] = 'details';
        $form['connector_config']['#title'] = $this->t('Configure %plugin Solr connector', ['%plugin' => $connector->label()]);
        $form['connector_config']['#description'] = $connector->getDescription();
        $form['connector_config']['#open'] = TRUE;
      }
    }
    $form['connector_config'] += ['#type' => 'container'];
    $form['connector_config']['#attributes'] = [
      'id' => 'search-api-solr-connector-config-form',
    ];
    $form['connector_config']['#tree'] = TRUE;

  }

  /**
   * Handles switching the selected Solr connector plugin.
   */
  public static function buildAjaxSolrConnectorConfigForm(array $form, FormStateInterface $form_state) {
    // The work is already done in form(), where we rebuild the entity according
    // to the current form values and then create the backend configuration form
    // based on that. So we just need to return the relevant part of the form
    // here.
    return $form['backend_config']['connector_config'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Check if the Solr connector plugin changed.
    if ($form_state->getValue('connector') != $form_state->get('connector')) {
      $new_connector = $this->solrConnectorPluginManager->createInstance($form_state->getValue('connector'));
      if ($new_connector instanceof PluginFormInterface) {
        $form_state->setRebuild();
      }
      else {
        $form_state->setError($form['connector'], $this->t('The connector could not be activated.'));
      }
    }
    // Check before loading the backend plugin so we don't throw an exception.
    else {
      $this->configuration['connector'] = $form_state->get('connector');
      $connector = $this->getSolrConnector();
      if ($connector instanceof PluginFormInterface) {
        $connector_form_state = SubformState::createForSubform($form['connector_config'], $form, $form_state);
        $connector->validateConfigurationForm($form['connector_config'], $connector_form_state);
      }
      else {
        $form_state->setError($form['connector'], $this->t('The connector could not be activated.'));
      }
    }

    // @todo If any Solr Document datasource is selected, retrieve_data must be set.

    // @todo If solr_document is the only datasource, skip_schema_check must be set.
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['connector'] = $form_state->get('connector');
    $connector = $this->getSolrConnector();
    if ($connector instanceof PluginFormInterface) {
      $connector_form_state = SubformState::createForSubform($form['connector_config'], $form, $form_state);
      $connector->submitConfigurationForm($form['connector_config'], $connector_form_state);
    }

    $values = $form_state->getValues();
    // Since the form is nested into another, we can't simply use #parents for
    // doing this array restructuring magic. (At least not without creating an
    // unnecessary dependency on internal implementation.)
    $values += $values['advanced'];
    $values += $values['multisite'];
    $values += $values['multilingual'];
    $values['optimize'] &= $values['i_know_what_i_do'];

    foreach ($values as $key => $value) {
      $form_state->setValue($key, $value);
    }

    // Clean-up the form to avoid redundant entries in the stored configuration.
    $form_state->unsetValue('advanced');
    $form_state->unsetValue('multisite');
    $form_state->unsetValue('multilingual');
    // The server description is a #type item element, which means it has a
    // value, do not save it.
    $form_state->unsetValue('server_description');
    $form_state->unsetValue('i_know_what_i_do');

    $this->traitSubmitConfigurationForm($form, $form_state);

    // Delete cached endpoint data.
    \Drupal::state()->delete('search_api_solr.endpoint.data');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getSolrConnector() {
    if (!$this->solrConnector) {
      if (!($this->solrConnector = $this->solrConnectorPluginManager->createInstance($this->configuration['connector'], $this->configuration['connector_config']))) {
        throw new SearchApiException("The Solr Connector with ID '$this->configuration['connector']' could not be retrieved.");
      }
    }
    return $this->solrConnector;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function isAvailable() {
    $conn = $this->getSolrConnector();
    return $conn->pingCore() !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedFeatures() {
    return [
      'search_api_autocomplete',
      'search_api_facets',
      'search_api_facets_operator_or',
      'search_api_granular',
      'search_api_mlt',
      'search_api_random_sort',
      'search_api_data_type_location',
      'search_api_grouping',
      // 'search_api_data_type_geohash',.
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDataType($type) {
    static $custom_codes = [];

    if (strpos($type, 'solr_text_custom') === 0) {
      list(, $custom_code) = explode(':', $type);
      if (empty($custom_codes)) {
        $custom_codes = SolrFieldType::getAvailableCustomCodes();
      }
      return in_array($custom_code, $custom_codes);
    }

    return in_array($type, [
      'location',
      'rpt',
      'solr_string_storage',
      'solr_text_omit_norms',
      'solr_text_suggester',
      'solr_text_spellcheck',
      'solr_text_unstemmed',
      'solr_text_wstoken',
      'solr_date_range',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDiscouragedProcessors() {
    return [
      'ignorecase',
      // https://www.drupal.org/project/snowball_stemmer
      'snowball_stemmer',
      'stemmer',
      'stopwords',
      'tokenizer',
      'transliteration',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function viewSettings() {
    /** @var \Drupal\search_api_solr\Plugin\SolrConnector\StandardSolrCloudConnector $connector */
    $connector = $this->getSolrConnector();
    $cloud = $connector instanceof SolrCloudConnectorInterface;

    $info[] = [
      'label' => $this->t('Solr connector plugin'),
      'info' => $connector->label(),
    ];

    $info[] = [
      'label' => $this->t('Solr server URI'),
      'info' => $connector->getServerLink(),
    ];

    if ($cloud) {
      $info[] = [
        'label' => $this->t('Solr collection URI'),
        'info' => $connector->getCollectionLink(),
      ];
    } else {
      $info[] = [
        'label' => $this->t('Solr core URI'),
        'info' => $connector->getCoreLink(),
      ];
    }

    // Add connector-specific information.
    $info = array_merge($info, $connector->viewSettings());

    if ($this->server->status()) {
      // If the server is enabled, check whether Solr can be reached.
      $ping_server = $connector->pingServer();
      if ($ping_server) {
        $msg = $this->t('The Solr server could be reached.');
      }
      else {
        $msg = $this->t('The Solr server could not be reached or is protected by your service provider.');
      }
      $info[] = [
        'label' => $this->t('Server Connection'),
        'info' => $msg,
        'status' => $ping_server ? 'ok' : 'error',
      ];

      $ping = $connector->pingCore();
      if ($ping) {
        $msg = $this->t('The Solr @core could be accessed (latency: @millisecs ms).', ['@core' => $cloud ? 'collection' : 'core', '@millisecs' => $ping * 1000]);
      }
      else {
        $msg = $this->t('The Solr @core could not be accessed. Further data is therefore unavailable.', ['@core' => $cloud ? 'collection' : 'core']);
      }
      $info[] = [
        'label' => $cloud ? $this->t('Collection Connection') : $this->t('Core Connection'),
        'info' => $msg,
        'status' => $ping ? 'ok' : 'error',
      ];

      $version = $connector->getSolrVersion();
      $info[] = [
        'label' => $this->t('Configured Solr Version'),
        'info' => $version,
        'status' => version_compare($version, '0.0.0', '>') ? 'ok' : 'error',
      ];

      if ($ping_server || $ping) {
        $info[] = [
          'label' => $this->t('Detected Solr Version'),
          'info' => $connector->getSolrVersion(TRUE),
          'status' => 'ok',
        ];

        try {
          // If Solr can be reached, provide more information. This isn't done
          // often (only when an admin views the server details), so we clear
          // the cache to get the current data.
          $data = $connector->getLuke();
          if (isset($data['index']['numDocs'])) {
            // Collect the stats.
            $stats_summary = $connector->getStatsSummary();

            $pending_msg = $stats_summary['@pending_docs'] ? $this->t('(@pending_docs sent but not yet processed)', $stats_summary) : '';
            $index_msg = $stats_summary['@index_size'] ? $this->t('(@index_size on disk)', $stats_summary) : '';
            $indexed_message = $this->t('@num items @pending @index_msg', [
              '@num' => $data['index']['numDocs'],
              '@pending' => $pending_msg,
              '@index_msg' => $index_msg,
            ]);
            $info[] = [
              'label' => $this->t('Indexed'),
              'info' => $indexed_message,
            ];

            if (!empty($stats_summary['@deletes_total'])) {
              $info[] = [
                'label' => $this->t('Pending Deletions'),
                'info' => $stats_summary['@deletes_total'],
              ];
            }

            $info[] = [
              'label' => $this->t('Delay'),
              'info' => $this->t('@autocommit_time before updates are processed.', $stats_summary),
            ];

            $status = 'ok';
            if (empty($this->configuration['skip_schema_check'])) {
              if (substr($stats_summary['@schema_version'], 0, 10) == 'search-api') {
                \Drupal::messenger()->addError($this->t('Your schema.xml version is too old. Please replace all configuration files with the ones packaged with this module and re-index you data.'));
                $status = 'error';
              }
              elseif (strpos($stats_summary['@schema_version'], 'drupal-' . SolrBackendInterface::SEARCH_API_SOLR_MIN_SCHEMA_VERSION) !== 0) {
                $variables[':url'] = Url::fromUri('internal:/' . drupal_get_path('module', 'search_api_solr') . '/INSTALL.md')
                  ->toString();
                \Drupal::messenger()->addError($this->t('You are using outdated Solr configuration files. Please follow the instructions in the <a href=":url">INSTALL.md</a> file for setting up Solr.', $variables));
                $status = 'error';
              }
            }
            $info[] = [
              'label' => $this->t('Schema'),
              'info' => $stats_summary['@schema_version'],
              'status' => $status,
            ];

            if (!empty($stats_summary['@collection_name'])) {
              $info[] = [
                'label' => $this->t('Solr Collection Name'),
                'info' => $stats_summary['@collection_name'],
              ];
            }
            elseif (!empty($stats_summary['@core_name'])) {
              $info[] = [
                'label' => $this->t('Solr Core Name'),
                'info' => $stats_summary['@core_name'],
              ];
            }
          }
        }
        catch (SearchApiException $e) {
          $info[] = [
            'label' => $this->t('Additional information'),
            'info' => $this->t('An error occurred while trying to retrieve additional information from the Solr server: %msg', ['%msg' => $e->getMessage()]),
            'status' => 'error',
          ];
        }
      }
    }

    $info[] = [
      'label' => $this->t('Targeted content domain'),
      'info' => $this->getDomain(),
    ];

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {
    if ($this->indexFieldsUpdated($index)) {
      $index->reindex();
      $this->getSolrFieldNames($index, TRUE);
    }
  }

  /**
   * Checks if the recently updated index had any fields changed.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index that was just updated.
   *
   * @return bool
   *   TRUE if any of the fields were updated, FALSE otherwise.
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
    $old_field_names = $this->getSolrFieldNames($original, TRUE);
    $new_field_names = $this->getSolrFieldNames($index, TRUE);
    return $old_field_names != $new_field_names;
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index) {
    // Only delete the index's data if the index isn't read-only. If the index
    // has already been deleted and we only get the ID, we just assume it was
    // read-only to be on the safe side.
    if (is_object($index) && !$index->isReadOnly()) {
      $this->deleteAllIndexItems($index);
      $this->getLanguageSpecificSolrFieldNames(LanguageInterface::LANGCODE_NOT_SPECIFIED,$index, TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items) {
    $connector = $this->getSolrConnector();
    $update_query = $connector->getUpdateQuery();
    $documents = $this->getDocuments($index, $items, $update_query);
    if (!$documents) {
      return [];
    }
    try {
      $update_query->addDocuments($documents);
      $connector->update($update_query);

      $field_names = $this->getSolrFieldNames($index);
      $ret = [];
      foreach ($documents as $document) {
        $ret[] = $document->getFields()[$field_names['search_api_id']];
      }
      \Drupal::state()->set('search_api_solr.' . $index->id() . '.last_update', \Drupal::time()->getCurrentTime());
      return $ret;
    }
    catch (\Exception $e) {
      watchdog_exception('search_api_solr', $e, "%type while indexing: @message in %function (line %line of %file).");
      throw new SearchApiSolrException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDocument(IndexInterface $index, ItemInterface $item) {
    $documents = $this->getDocuments($index, [$item->getId() => $item]);
    return reset($documents);
  }

  /**
   * {@inheritdoc}
   */
  public function getDocuments(IndexInterface $index, array $items, UpdateQuery $update_query = NULL) {
    $connector = $this->getSolrConnector();

    $documents = [];
    $index_id = $this->getTargetedIndexId($index);
    $site_hash = $this->getTargetedSiteHash($index);
    $languages = $this->languageManager->getLanguages();
    $request_time = $this->formatDate(\Drupal::time()->getRequestTime());
    $base_urls = [];

    if (!$update_query) {
      $update_query = $connector->getUpdateQuery();
    }

    /** @var \Drupal\search_api\Item\ItemInterface[] $items */
    foreach ($items as $id => $item) {
      $language_id = $item->getLanguage();
      $field_names = $this->getLanguageSpecificSolrFieldNames($language_id, $index);

      /** @var \Solarium\QueryType\Update\Query\Document\Document $doc */
      $doc = $update_query->createDocument();
      $doc->setField('timestamp', $request_time);
      $doc->setField('id', $this->createId($site_hash, $index_id, $id));
      $doc->setField('index_id', $index_id);
      // Suggester context boolean filter queries have issues with special
      // characters like '/' or ':' if not properly quoted (by solarium). We
      // avoid that by reusing our field name encoding.
      $doc->addField('sm_context_tags', Utility::encodeSolrName('search_api/index:' . $index_id));
      // Add the site hash and language-specific base URL.
      $doc->setField('hash', $site_hash);
      $doc->addField('sm_context_tags', Utility::encodeSolrName('search_api_solr/site_hash:' . $site_hash));
      $doc->addField('sm_context_tags', Utility::encodeSolrName('drupal/langcode:' . $language_id));
      if (!isset($base_urls[$language_id])) {
        $url_options = ['absolute' => TRUE];
        if (isset($languages[$language_id])) {
          $url_options['language'] = $languages[$language_id];
        }
        // An exception is thrown if this is called during a non-HTML response
        // like REST or a redirect without collecting metadata. Avoid that by
        // collecting and discarding it.
        // See https://www.drupal.org/node/2638686.
        $base_urls[$language_id] = Url::fromRoute('<front>', [], $url_options)->toString(TRUE)->getGeneratedUrl();
      }
      $doc->setField('site', $base_urls[$language_id]);
      $item_fields = $item->getFields();
      $item_fields += $special_fields = $this->getSpecialFields($index, $item);
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($item_fields as $name => $field) {
        // If the field is not known for the index, something weird has
        // happened. We refuse to index the items and hope that the others are
        // OK.
        if (!isset($field_names[$name])) {
          $vars = [
            '%field' => $name,
            '@id' => $id,
          ];
          $this->getLogger()->warning('Error while indexing: Unknown field %field on the item with ID @id.', $vars);
          $doc = NULL;
          break;
        }

        $first_value = $this->addIndexField($doc, $field_names[$name], $field->getValues(), $field->getType());
        // Enable sorts in some special cases.
        if ($first_value && !array_key_exists($name, $special_fields)) {
          if (
            (strpos($field_names[$name], 't') === 0 && strpos($field_names[$name], 'twm_suggest') !== 0) ||
            (strpos($field_names[$name], 's') === 0 && strpos($field_names[$name], 'spellcheck') !== 0)
          ) {
            $key = 'sort_' . Utility::encodeSolrName($name);
            if (!$doc->{$key}) {
              // Truncate the string to avoid Solr string field limitation.
              // @see https://www.drupal.org/node/2809429
              // @see https://www.drupal.org/node/2852606
              // 128 characters should be enough for sorting and it makes no
              // sense to heavily increase the index size. The DB backend limits
              // the sort strings to 32 characters. But for example a
              // search_api_id quickly exceeds 32 characters and the interesting
              // ID is at the end of the string:
              // 'entity:entity_test_mulrev_changed/2:en'
              if (mb_strlen($first_value) > 128) {
                $first_value = Unicode::truncate($first_value, 128);
              }
              // Always copy fulltext fields to a dedicated field for faster
              // alpha sorts. Copy strings as well to normalize them.
              $doc->addField($key, $first_value);
            }
          }
          elseif (preg_match('/^([a-z]+)m(_.*)/', $field_names[$name], $matches) && strpos($field_names[$name], 'random_') !== 0) {
            $key = $matches[1] . 's' . $matches[2];
            if (!$doc->{$key}) {
              // For other multi-valued fields (which aren't sortable by nature)
              // we use the same hackish workaround like the DB backend: just
              // copy the first value in a single value field for sorting.
              $doc->addField($key, $first_value);
            }
          }
        }
      }

      if ($doc) {
        $documents[] = $doc;
      }
    }

    // Let other modules alter documents before sending them to solr.
    $this->moduleHandler->alter('search_api_solr_documents', $documents, $index, $items);

    return $documents;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $ids) {
    try {
      $index_id = $this->getTargetedIndexId($index);
      $site_hash = $this->getTargetedSiteHash($index);
      $solr_ids = [];
      foreach ($ids as $id) {
        $solr_ids[] = $this->createId($site_hash, $index_id, $id);
      }
      $connector = $this->getSolrConnector();
      $update_query = $connector->getUpdateQuery();
      $update_query->addDeleteByIds($solr_ids);
      $connector->update($update_query);
      \Drupal::state()->set('search_api_solr.' . $index->id() . '.last_update', \Drupal::time()->getCurrentTime());
    }
    catch (ExceptionInterface $e) {
      throw new SearchApiSolrException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL) {
    // Since the index ID we use for indexing can contain arbitrary
    // prefixes, we have to escape it for use in the query.
    $connector = $this->getSolrConnector();
    $query = '+index_id:' . $this->queryHelper->escapeTerm($this->getTargetedIndexId($index));
    $query .= ' +hash:' . $this->queryHelper->escapeTerm($this->getTargetedSiteHash($index));
    if ($datasource_id) {
      $query .= ' +' . $this->getSolrFieldNames($index)['search_api_datasource'] . ':' . $this->queryHelper->escapeTerm($datasource_id);
    }
    $update_query = $connector->getUpdateQuery();
    $update_query->addDeleteQuery($query);
    $connector->update($update_query);
    \Drupal::state()->set('search_api_solr.' . $index->id() . '.last_update', \Drupal::time()->getCurrentTime());
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexFilterQueryString(IndexInterface $index) {
    $fq = '+index_id:' . $this->queryHelper->escapeTerm($this->getTargetedIndexId($index));

    // Set the site hash filter, if enabled.
    if ($this->configuration['site_hash']) {
      $fq .= ' +hash:' . $this->queryHelper->escapeTerm($this->getTargetedSiteHash($index));
    }

    return $fq;
  }

  /**
   * {@inheritdoc}
   */
  public function finalizeIndex(IndexInterface $index) {
    // Avoid endless loops if finalization hooks trigger searches or streaming
    // expressions themselves.
    static $finalization_in_progress = [];

    if (!isset($finalization_in_progress[$index->id()]) && !$index->isReadOnly()) {
      $settings = $index->getThirdPartySettings('search_api_solr') + search_api_solr_default_index_third_party_settings();
      if (
        // Not empty reflects the default FALSE for outdated index configs, too.
        !empty($settings['finalize']) &&
        \Drupal::state()->get('search_api_solr.' . $index->id() . '.last_update', 0) >= \Drupal::state()->get('search_api_solr.' . $index->id() . '.last_finalization', 0)
      ) {
        $lock = \Drupal::lock();

        $lock_name = 'search_api_solr.' . $index->id() . '.finalization_lock';
        if ($lock->acquire($lock_name)) {
          $vars = ['%index_id' => $index->id(), '%pid' => getmypid()];
          $this->getLogger()->debug('PID %pid, Index %index_id: Finalization lock acquired.', $vars);
          $finalization_in_progress[$index->id()] = TRUE;
          $connector = $this->getSolrConnector();
          $previous_timeout = $connector->adjustTimeout($connector->getFinalizeTimeout());
          try {
            if (!empty($settings['commit_before_finalize'])) {
              $this->ensureCommit($this->getServer());
            }

            $this->moduleHandler->invokeAll('search_api_solr_finalize_index', [$index]);

            if (!empty($settings['commit_after_finalize'])) {
              $this->ensureCommit($this->getServer());
            }

            \Drupal::state()
              ->set('search_api_solr.' . $index->id() . '.last_finalization',
                \Drupal::time()->getRequestTime());
            $lock->release($lock_name);
            $vars = ['%index_id' => $index->id(), '%pid' => getmypid()];
            $this->getLogger()->debug('PID %pid, Index %index_id: Finalization lock released.', $vars);
          } catch (\Exception $e) {
            unset($finalization_in_progress[$index->id()]);
            $lock->release('search_api_solr.' . $index->id() . '.finalization_lock');
            $connector->adjustTimeout($previous_timeout);
            if ($e instanceof StreamException) {
              throw new SearchApiSolrException($e->getMessage() . "\n" . Expression::indent($e->getExpression()), $e->getCode(), $e);
            }
            throw new SearchApiSolrException($e->getMessage(), $e->getCode(), $e);
          }
          unset($finalization_in_progress[$index->id()]);
          $connector->adjustTimeout($previous_timeout);

          return TRUE;
        }
        else {
          if ($lock->wait($lock_name)) {
            // wait() returns TRUE if the lock isn't released within the given
            // timeout (default 30s).
            $vars = ['%index_id' => $index->id(), '%pid' => getmypid()];
            $this->getLogger()->debug('PID %pid, Index %index_id: Waited unsuccessfully for finalization lock.', $vars);
            throw new SearchApiSolrException('The search index currently being rebuilt. Try again later.');
          }

          $this->finalizeIndex($index);
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Options on $query prefixed by 'solr_param_' will be passed natively to Solr
   * as query parameter without the prefix. For example you can set the "Minimum
   * Should Match" parameter 'mm' to '75%' like this:
   * @code
   *   $query->setOption('solr_param_mm', '75%');
   * @endcode
   */
  public function search(QueryInterface $query) {
    $this->finalizeIndex($query->getIndex());

    if ($query->getOption('solr_streaming_expression', FALSE)) {
      $solarium_result = $this->executeStreamingExpression($query);
      // Extract results.
      $search_api_result_set = $this->extractResults($query, $solarium_result);

      $this->moduleHandler->alter('search_api_solr_search_results', $search_api_result_set, $query, $solarium_result);
      $this->postQuery($search_api_result_set, $query, $solarium_result);
    }
    else {
      $mlt_options = $query->getOption('search_api_mlt');
      if (!empty($mlt_options)) {
        $query->addTag('mlt');
      }

      // Get field information.
      /** @var \Drupal\search_api\Entity\Index $index */
      $index = $query->getIndex();

      $connector = $this->getSolrConnector();
      $solarium_query = NULL;
      $edismax = NULL;
      $index_fields = $index->getFields();
      $index_fields += $this->getSpecialFields($index);

      $language_ids = $query->getLanguages();

      // If there are no languages set, we need to set them. As an example, a
      // language might be set by a filter in a search view.
      if (empty($language_ids)) {
        if (!$query->hasTag('views') && $this->configuration['sasm_limit_search_page_to_content_language']) {
          // Limit the language to the current language being used.
          $language_ids[] = \Drupal::languageManager()
            ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
            ->getId();
        }
        else {
          // If the query is generated by views and/or the query isn't limited by
          // any languages we have to search for all languages using their
          // specific fields.
          $language_ids = array_keys(\Drupal::languageManager()->getLanguages());
        }
      }

      if ($this->configuration['sasm_search_page_include_language_independent']) {
        $language_ids[] = LanguageInterface::LANGCODE_NOT_SPECIFIED;
        $language_ids[] = LanguageInterface::LANGCODE_NOT_APPLICABLE;
      }

      $query->setLanguages($language_ids);

      if ($query->hasTag('mlt')) {
        $solarium_query = $this->getMoreLikeThisQuery($query);
      }
      else {
        // Instantiate a Solarium select query.
        $solarium_query = $connector->getSelectQuery();
        $edismax = $solarium_query->getEDisMax();

        $field_names = $this->getSolrFieldNamesKeyedByLanguage($language_ids, $index);

        // Set searched fields.
        $search_fields = $this->getQueryFulltextFields($query);
        $query_fields = [];
        $query_fields_boosted = [];
        foreach ($search_fields as $search_field) {
          /** @var \Drupal\search_api\Item\FieldInterface $field */
          $field = $index_fields[$search_field];
          $boost = $field->getBoost() ? '^' . $field->getBoost() : '';
          $names = [];
          $first_name = reset($field_names[$search_field]);
          if (strpos($first_name, 't') === 0) {
            $names = array_values($field_names[$search_field]);
          }
          else {
            $names[] = $first_name;
          }

          foreach ($names as $name) {
            $query_fields[] = $name;
            $query_fields_boosted[] = $name . $boost;
          }
        }
        $edismax->setQueryFields(implode(' ', $query_fields_boosted));

      }

      $options = $query->getOptions();

      // Set basic filters.
      $filter_queries = $this->getFilterQueries($query, $options);
      foreach ($filter_queries as $id => $filter_query) {
        $solarium_query->createFilterQuery('filters_' . $id)
          ->setQuery($filter_query['query'])
          ->addTags($filter_query['tags']);
      }

      if (!$this->hasIndexJustSolrDocumentDatasource($index)) {
        // Set the Index (and site) filter.
        $solarium_query->createFilterQuery('index_filter')->setQuery(
          $this->getIndexFilterQueryString($index)
        );
      }
      else {
        // Set requestHandler for the query type, if necessary and configured.
        $config = $index->getDatasource('solr_document')->getConfiguration();
        if (!empty($config['request_handler'])) {
          $solarium_query->addParam('qt', $config['request_handler']);
        }

        // Set the default query, if necessary and configured.
        if (!$solarium_query->getQuery() && !empty($config['default_query'])) {
          $solarium_query->setQuery($config['default_query']);
        }

        // The query builder of Search API Solr Search bases on 'OR' which is the
        // default value for solr, too. But a foreign schema could have a
        // non-default config for q.op. Therefor we need to set it explicitly if not
        // set.
        $params = $solarium_query->getParams();
        if (!isset($params['q.op'])) {
          $solarium_query->addParam('q.op', 'OR');
        }
      }

      $unspecific_field_names = $this->getSolrFieldNames($index);
      // For solr_document datasource, search_api_language might not be mapped.
      if (!empty($unspecific_field_names['search_api_language'])) {
        $solarium_query->createFilterQuery('language_filter')->setQuery(
          $this->createFilterQuery($unspecific_field_names['search_api_language'], $language_ids, 'IN', new Field($index, 'search_api_language'), $options)
        );
      }

      // Set the list of fields to retrieve.
      $this->setFields($solarium_query, $query->getOption('search_api_retrieved_field_values', []), $query);

      // Set sorts.
      $this->setSorts($solarium_query, $query);

      // Set facet fields. setSpatial() might add more facets.
      $this->setFacets($query, $solarium_query);

      // Handle spatial filters.
      if (isset($options['search_api_location'])) {
        $this->setSpatial($solarium_query, $options['search_api_location'], $query);
      }

      // Handle spatial filters.
      if (isset($options['search_api_rpt'])) {
        $this->setRpt($solarium_query, $options['search_api_rpt'], $query);
      }

      // Handle field collapsing / grouping.
      if (isset($options['search_api_grouping'])) {
        $this->setGrouping($solarium_query, $query, $options['search_api_grouping'], $index_fields, $field_names);
      }

      if (isset($options['offset'])) {
        $solarium_query->setStart($options['offset']);
      }
      $rows = isset($options['limit']) ? $options['limit'] : 1000000;
      $solarium_query->setRows($rows);

      foreach ($options as $option => $value) {
        if (strpos($option, 'solr_param_') === 0) {
          $solarium_query->addParam(substr($option, 11), $value);
        }
      }

      $this->applySearchWorkarounds($solarium_query, $query);

      try {
        // Allow modules to alter the solarium query.
        $this->moduleHandler->alter('search_api_solr_query', $solarium_query, $query);
        $this->preQuery($solarium_query, $query);

        // Since Solr 7.2 the edsimax query parser doesn't allow local
        // parameters anymore. But since we don't want to force all modules that
        // implemented our hooks to re-write their code, we transform the query
        // back into a lucene query. flattenKeys() was adjusted accordingly, but
        // in a backward compatible way.
        // @see https://lucene.apache.org/solr/guide/7_2/solr-upgrade-notes.html#solr-7-2
        if ($edismax) {
          $parse_mode_id = $query->getParseMode()->getPluginId();
          /** @var Query $solarium_query */
          $params = $solarium_query->getParams();
          // Extract keys.
          $keys = $query->getKeys();
          $query_fields_boosted = $edismax->getQueryFields();
          if (
            (isset($params['defType']) && 'edismax' == $params['defType']) ||
            !$query_fields_boosted
          ) {
            // Edismax was forced via API or the query fields were removed via
            // API.
            $keys = $this->flattenKeys($keys, [], $parse_mode_id);
          }
          else {
            $keys = $this->flattenKeys($keys, explode(' ', $query_fields_boosted), $parse_mode_id);
          }

          if (!empty($keys)) {
            // Set them.
            $solarium_query->setQuery($keys);
          }

         if (!isset($params['defType']) || 'edismax' != $params['defType']) {
           $solarium_query->removeComponent(ComponentAwareQueryInterface::COMPONENT_EDISMAX);
         }
       }

        // Allow modules to alter the converted solarium query.
        $this->moduleHandler->alter('search_api_solr_converted_query', $solarium_query, $query);

        // Send search request.
        $response = $connector->search($solarium_query);
        $body = $response->getBody();
        if (200 != $response->getStatusCode()) {
          throw new SearchApiSolrException(strip_tags($body), $response->getStatusCode());
        }
        $search_api_response = new Response($body, $response->getHeaders());

        $solarium_result = $connector->createSearchResult($solarium_query, $search_api_response);

        // Extract results.
        $search_api_result_set = $this->extractResults($query, $solarium_result);

        // Add warnings, if present.
        if (!empty($warnings)) {
          foreach ($warnings as $warning) {
            $search_api_result_set->addWarning($warning);
          }
        }

        // Extract facets.
        if ($solarium_result instanceof Result) {
          if ($solarium_facet_set = $solarium_result->getFacetSet()) {
            $search_api_result_set->setExtraData('facet_set', $solarium_facet_set);
            if ($search_api_facets = $this->extractFacets($query, $solarium_result)) {
              $search_api_result_set->setExtraData('search_api_facets', $search_api_facets);
            }
          }
        }

        $this->moduleHandler->alter('search_api_solr_search_results', $search_api_result_set, $query, $solarium_result);
        $this->postQuery($search_api_result_set, $query, $solarium_result);
      }
      catch (\Exception $e) {
        throw new SearchApiSolrException('An error occurred while trying to search with Solr: ' . $e->getMessage(), $e->getCode(), $e);
      }
    }
  }

  /**
   * Apply workarounds for special Solr versions before searching.
   *
   * @param \Solarium\Core\Query\QueryInterface $solarium_query
   *   The Solarium select query object.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   */
  protected function applySearchWorkarounds(SolariumQueryInterface $solarium_query, QueryInterface $query) {
    // Do not modify 'Server index status' queries.
    // @see https://www.drupal.org/node/2668852
    if ($query->hasTag('server_index_status')) {
      return;
    }

    /* We keep this as an example.
    $connector = $this->getSolrConnector();
    $schema_version = $connector->getSchemaVersion();
    $solr_version = $connector->getSolrVersion();

    // Schema versions before 4.4 set the default query operator to 'AND'. But
    // incompatibilities since Solr 5.5.0 required a new query builder that
    // bases on 'OR'.
    // @see https://www.drupal.org/node/2724117
    if (version_compare($schema_version, '4.4', '<')) {
    $params = $solarium_query->getParams();
    if (!isset($params['q.op'])) {
    $solarium_query->addParam('q.op', 'OR');
    }
    }
     */
  }

  /**
   * Get the list of fields Solr must return as result.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   *
   * @return array
   */
  protected function getRequiredFields(QueryInterface $query = NULL) {
    $index = $query->getIndex();
    $field_names = $this->getSolrFieldNames($index);
    // The list of fields Solr must return to built a Search API result.
    $required_fields = [$field_names['search_api_id'], $field_names['search_api_language'], $field_names['search_api_relevance']];
    if (!$this->configuration['site_hash']) {
      $required_fields[] = 'hash';
    }

    if ($this->hasIndexJustSolrDocumentDatasource($index)) {
      $config = $this->getDatasourceConfig($index);
      $extra_fields = [
        'label_field',
        'url_field',
      ];
      foreach ($extra_fields as $config_key) {
        if (!empty($config[$config_key])) {
          $required_fields[] = $config[$config_key];
        }
      }
    }

    return array_filter($required_fields);
  }

  /**
   * Set the list of fields Solr should return as result.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The solr query.
   * @param array $fields_to_be_retrieved
   *   The field values to be retrieved from Solr.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   */
  protected function setFields(Query $solarium_query, array $fields_to_be_retrieved, QueryInterface $query) {
    $required_fields = $this->getRequiredFields($query);
    $returned_fields = [];
    $highlight_fields = ['*'];

    if (!empty($this->configuration['retrieve_data'])) {
      $field_names = $this->getSolrFieldNamesKeyedByLanguage($query->getLanguages(), $query->getIndex());

      // If Search API provides information about the fields to retrieve, limit
      // the fields accordingly. ...
      foreach ($fields_to_be_retrieved as $field_name) {
        if (isset($field_names[$field_name])) {
          $returned_fields = array_merge($returned_fields, array_values($field_names[$field_name]));
        }
      }
      if ($returned_fields) {
        $highlight_fields = array_unique($returned_fields);
        $returned_fields = array_merge($returned_fields, $required_fields);
      }
      // ... Otherwise return all fields and score.
      else {
        $returned_fields = ['*', reset($field_names['search_api_relevance'])];
      }
    }
    else {
      $returned_fields = $required_fields;
    }

    $solarium_query->setFields(array_unique($returned_fields));

    try {
      $highlight_config = $query->getIndex()->getProcessor('highlight')->getConfiguration();
      if ($highlight_config['highlight'] != 'never') {
        $this->setHighlighting($solarium_query, $query, $highlight_fields);
      }
    }
    catch (SearchApiException $exception) {
      // Highlighting processor is not enabled for this index. Just use the
      // the index configuration.
      $this->setHighlighting($solarium_query, $query, $highlight_fields);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeStreamingExpression(QueryInterface $query) {
    $stream_expression = $query->getOption('solr_streaming_expression', FALSE);
    if (!$stream_expression) {
      throw new SearchApiSolrException('Streaming expression missing.');
    }

    $connector = $this->getSolrConnector();
    if (!($connector instanceof SolrCloudConnectorInterface)) {
      throw new SearchApiSolrException('Streaming expression are only supported by a Solr Cloud connector.');
    }

    $this->finalizeIndex($query->getIndex());

    $stream = $connector->getStreamQuery();
    $stream->setExpression($stream_expression);
    $stream->setOptions(['documentclass' => 'Drupal\search_api_solr\Solarium\Result\StreamDocument']);
    $this->applySearchWorkarounds($stream, $query);

    $result = NULL;

    try {
      $result = $connector->stream($stream);

      if ($processors = $query->getIndex()->getProcessorsByStage(ProcessorInterface::STAGE_POSTPROCESS_QUERY)) {
        foreach ($processors as $key => $processor) {
          if (!($processor instanceof SolrProcessorInterface)) {
            unset($processors[$key]);
          }
        }

        if (count($processors)) {
          foreach ($processors as $processor) {
            /** @var \Drupal\search_api_solr\Solarium\Result\StreamDocument $document */
            foreach ($result as $document) {
              foreach ($document as $field_name => $field_value) {
                if (is_string($field_value)) {
                  $document->{$field_name} = $processor->decodeStreamingExpressionValue($field_value) ?: $field_value;
                }
                elseif (is_array($field_value)) {
                  foreach ($field_value as &$array_value) {
                    if (is_string($array_value)) {
                      $array_value = $processor->decodeStreamingExpressionValue($array_value) ?: $array_value;
                    }
                  }
                  $document->{$field_name} = $field_value;
                }
              }
            }
          }
        }
      }
    }
    catch (StreamException $e) {
      throw new SearchApiSolrException($e->getMessage() . "\n" . Expression::indent($e->getExpression()), $e->getCode(), $e);
    }
    catch (\Exception $e) {
      throw new SearchApiSolrException('An error occurred while trying execute a streaming expression on Solr: ' . $e->getMessage(), $e->getCode(), $e);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function executeGraphStreamingExpression(QueryInterface $query) {
    $stream_expression = $query->getOption('solr_streaming_expression', FALSE);
    if (!$stream_expression) {
      throw new SearchApiSolrException('Streaming expression missing.');
    }

    $connector = $this->getSolrConnector();
    if (!($connector instanceof SolrCloudConnectorInterface)) {
      throw new SearchApiSolrException('Streaming expression are only supported by a Solr Cloud connector.');
    }

    $this->finalizeIndex($query->getIndex());

    $graph = $connector->getGraphQuery();
    $graph->setExpression($stream_expression);
    $this->applySearchWorkarounds($graph, $query);

    try {
      return $connector->graph($graph);
    }
    catch (\Exception $e) {
      throw new SearchApiSolrException('An error occurred while trying execute a streaming expression on Solr: ' . $e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * Creates an ID used as the unique identifier at the Solr server.
   *
   * This has to consist of both index and item ID. Optionally, the site hash is
   * also included.
   *
   * @param $site_hash
   * @param $index_id
   * @param $item_id
   *
   * @return string
   */
  protected function createId($site_hash, $index_id, $item_id) {
    return "$site_hash-$index_id-$item_id";
  }

  /**
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return array
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getDatasourceConfig(IndexInterface $index) {
    $config = [];
    if ($index->isValidDatasource('solr_document')) {
      $config = $index->getDatasource('solr_document')->getConfiguration();
    }
    elseif ($index->isValidDatasource('solr_multisite_document')) {
      $config = $index->getDatasource('solr_multisite_document')->getConfiguration();
    }
    return $config;
  }

  /**
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return bool
   */
  protected function hasIndexJustSolrDatasources(IndexInterface $index) {
    $datasource_ids = $index->getDatasourceIds();
    $datasource_ids = array_filter($datasource_ids, function ($datasource_id) {
      return strpos($datasource_id, 'solr_') !== 0;
    });
    return !$datasource_ids;
  }

  /**
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return bool
   */
  protected function hasIndexJustSolrDocumentDatasource(IndexInterface $index) {
    $datasource_ids = $index->getDatasourceIds();
    return (1 == count($datasource_ids)) && in_array('solr_document', $datasource_ids);
  }

  /**
   * @param $language_id
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return string[]
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function formatSolrFieldNames($language_id, IndexInterface $index) {
    // Caching is done by getLanguageSpecificSolrFieldNames().
    // This array maps "local property name" => "solr doc property name".
    $field_mapping = [
      'search_api_relevance' => 'score',
      'search_api_random' => 'random',
    ];

    // Add the names of any fields configured on the index.
    $fields = $index->getFields();
    $fields += $this->getSpecialFields($index);
    foreach ($fields as $search_api_name => $field) {
      switch ($field->getDatasourceId()) {
        case 'solr_document':
          $field_mapping[$search_api_name] = $field->getPropertyPath();
          break;

        case 'solr_multisite_document':
          $field_mapping[$search_api_name] =
            Utility::encodeSolrName(
              preg_replace(
                '/^(t[a-z0-9]*[ms]' . SolrBackendInterface::SEARCH_API_SOLR_LANGUAGE_SEPARATOR . ')' . LanguageInterface::LANGCODE_NOT_SPECIFIED .'(.+)/',
                '$1' . $language_id . '$2',
                Utility::decodeSolrName($field->getPropertyPath())
              )
            );
          break;

        default:
          if (empty($field_mapping[$search_api_name])) {
            // Generate a field name; this corresponds with naming conventions in
            // our schema.xml.
            $type = $field->getType();

            if ('solr_text_suggester' == $type) {
              // Any field of this type will be indexed in the same Solr field.
              // The 'twm_suggest' is the backend for the suggester component.
              $field_mapping[$search_api_name] = 'twm_suggest';
              break;
            }

            if ('solr_text_spellcheck' == $type) {
              // Any field of this type will be indexed in the same Solr field.
              $field_mapping[$search_api_name] = 'spellcheck_' . Utility::encodeSolrName($language_id);
              break;
            }

            $type_info = Utility::getDataTypeInfo($type);
            $pref = isset($type_info['prefix']) ? $type_info['prefix'] : '';
            if (strpos($pref, 't') === 0) {
              // All text types need to be treated as multiple because some Search
              // API processors produce boosted string tokens for a single valued
              // drupal field. We need to store such tokens and their boost, too.
              // The dynamic field tm_* will become tm;en* for English. Following
              // this pattern we also have fall backs automatically:
              // - tm;de-AT_*
              // - tm;de_*
              // - tm_*
              // This concept bases on the fact that "longer patterns will be
              // matched first. If equal size patterns both match, the first
              // appearing in the schema will be used." This is not obvious from
              // the example above. But you need to take into account that the
              // real field name for solr will be encoded. So the real values for
              // the example above are:
              // - tm_X3b_de_X2d_AT_*
              // - tm_X3b_de_*
              // - tm_*
              // @see \Drupal\search_api_solr\Utility\Utility::encodeSolrName()
              // @see https://wiki.apache.org/solr/SchemaXml#Dynamic_fields
              $pref .= 'm' . SolrBackendInterface::SEARCH_API_SOLR_LANGUAGE_SEPARATOR . $language_id;
            }
            else {
              if ($this->fieldsHelper->isFieldIdReserved($search_api_name)) {
                $pref .= 's';
              }
              else {
                if ($field->getDataDefinition()
                    ->isList() || $this->isHierarchicalField($field)) {
                  $pref .= 'm';
                }
                else {
                  try {
                    $datasource = $field->getDatasource();
                    if (!$datasource) {
                      throw new SearchApiException();
                    }
                    else {
                      $pref .= $this->getPropertyPathCardinality($field->getPropertyPath(), $datasource->getPropertyDefinitions()) != 1 ? 'm' : 's';
                    }
                  } catch (SearchApiException $e) {
                    // Thrown by $field->getDatasource(). Assume multi value to be
                    // safe.
                    $pref .= 'm';
                  }
                }
              }
            }
            $name = $pref . '_' . $search_api_name;
            $field_mapping[$search_api_name] = Utility::encodeSolrName($name);

            // Add a distance pseudo field for any location field. These fields
            // don't really exist in the solr core, but we tell solr to name the
            // distance calculation results that way. Later we directly pass these
            // as "fields" to Drupal and especially Views.
            if ($type == 'location') {
              // Solr returns the calculated distance value as a single decimal
              // value (even for multi-valued location fields). Therefore we have
              // to prefix the field name accordingly by fts_*. This ensures that
              // this field works as for sorting, too. 'ft' is the prefix for
              // decimal (at the moment).
              $dist_info = Utility::getDataTypeInfo('decimal');
              $field_mapping[$search_api_name . '__distance'] = Utility::encodeSolrName($dist_info['prefix'] . 's_' . $search_api_name . '__distance');
            }
          }
      }
    }

    if ($this->hasIndexJustSolrDatasources($index)) {
      // No other datasource than solr_*, overwrite some search_api_* fields.
      $config = $this->getDatasourceConfig($index);
      $field_mapping['search_api_id'] = $config['id_field'];
      $field_mapping['search_api_language'] = $config['language_field'];
    }

    // Let modules adjust the field mappings.
    $this->moduleHandler->alter('search_api_solr_field_mapping', $index, $field_mapping, $language_id);

    return $field_mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageSpecificSolrFieldNames($language_id, IndexInterface $index, $reset = FALSE) {
    static $field_names = [];

    if ($reset) {
      $field_names = [];
    }

    $index_id = $index->id();
    if (!isset($field_names[$index_id]) || !isset($field_names[$index_id][$language_id])) {
      $field_names[$index_id][$language_id] = $this->formatSolrFieldNames($language_id, $index);
    }

    return $field_names[$index_id][$language_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrFieldNamesKeyedByLanguage(array $language_ids, IndexInterface $index, $reset = FALSE) {
    $field_names = [];

    foreach ($language_ids as $language_id) {
      foreach ($this->getLanguageSpecificSolrFieldNames($language_id, $index, $reset) as $name => $solr_name) {
        $field_names[$name][$language_id] = $solr_name;
          // Just reset once.
        $reset = FALSE;
      }
    }

    return $field_names;
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrFieldNames(IndexInterface $index, $reset = FALSE) {
    // Backwards compatibility.
    return $this->getLanguageSpecificSolrFieldNames(LanguageInterface::LANGCODE_NOT_SPECIFIED, $index, $reset);
  }

  /**
   * Computes the cardinality of a complete property path.
   *
   * @param string $property_path
   *   The property path of the property.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $properties
   *   The properties which form the basis for the property path.
   * @param int $cardinality
   *   The cardinality of the property path so far (for recursion).
   *
   * @return int
   *   The cardinality.
   */
  protected function getPropertyPathCardinality($property_path, array $properties, $cardinality = 1) {
    list($key, $nested_path) = SearchApiUtility::splitPropertyPath($property_path, FALSE);
    if (isset($properties[$key])) {
      $property = $properties[$key];
      if ($property instanceof FieldDefinitionInterface) {
        $storage = $property->getFieldStorageDefinition();
        if ($storage instanceof FieldStorageDefinitionInterface) {
          if ($storage->getCardinality() == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
            // Shortcut. We reached the maximum.
            return FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
          }
          $cardinality *= $storage->getCardinality();
        }
      }

      if (isset($nested_path)) {
        $property = $this->fieldsHelper->getInnerProperty($property);
        if ($property instanceof ComplexDataDefinitionInterface) {
          $cardinality = $this->getPropertyPathCardinality($nested_path, $this->fieldsHelper->getNestedProperties($property), $cardinality);
        }
      }
    }
    return $cardinality;
  }

  /**
   * Checks if a field is (potentially) hierarchical.
   *
   * Fields are (potentially) hierarchical if:
   * - they point to an entity type; and
   * - that entity type contains a property referencing the same type of entity
   *   (so that a hierarchy could be built from that nested property).
   *
   * @see \Drupal\search_api\Plugin\search_api\processor\AddHierarchy::getHierarchyFields()
   *
   * @return bool
   */
  protected function isHierarchicalField(FieldInterface $field) {
    $definition = $field->getDataDefinition();
    if ($definition instanceof ComplexDataDefinitionInterface) {
      $properties = $this->fieldsHelper->getNestedProperties($definition);
      // The property might be an entity data definition itself.
      $properties[''] = $definition;
      foreach ($properties as $property) {
        $property = $this->fieldsHelper->getInnerProperty($property);
        if ($property instanceof EntityDataDefinitionInterface) {
          if ($this->hasHierarchicalProperties($property)) {
            return TRUE;
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * Checks if hierarchical properties are nested on an entity-typed property.
   *
   * @see \Drupal\search_api\Plugin\search_api\processor\AddHierarchy::findHierarchicalProperties()
   *
   * @param \Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface $property
   *   The property to be searched for hierarchical nested properties.
   *
   * @return bool
   */
  protected function hasHierarchicalProperties(EntityDataDefinitionInterface $property) {
    $entity_type_id = $property->getEntityTypeId();

    // Check properties for potential hierarchy. Check two levels down, since
    // Core's entity references all have an additional "entity" sub-property for
    // accessing the actual entity reference, which we'd otherwise miss.
    foreach ($this->fieldsHelper->getNestedProperties($property) as $name_2 => $property_2) {
      $property_2 = $this->fieldsHelper->getInnerProperty($property_2);
      if ($property_2 instanceof EntityDataDefinitionInterface) {
        if ($property_2->getEntityTypeId() == $entity_type_id) {
          return TRUE;
        }
      }
      elseif ($property_2 instanceof ComplexDataDefinitionInterface) {
        foreach ($property_2->getPropertyDefinitions() as $property_3) {
          $property_3 = $this->fieldsHelper->getInnerProperty($property_3);
          if ($property_3 instanceof EntityDataDefinitionInterface) {
            if ($property_3->getEntityTypeId() == $entity_type_id) {
              return TRUE;
            }
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Helper method for indexing.
   *
   * Adds $value with field name $key to the document $doc. The format of $value
   * is the same as specified in
   * \Drupal\search_api\Backend\BackendSpecificInterface::indexItems().
   *
   * @param \Solarium\QueryType\Update\Query\Document\Document $doc
   * @param $key
   * @param array $values
   * @param $type
   *
   * @return bool|float|int|string
   *   The first value of $values that has been added to the index.
   */
  protected function addIndexField(Document $doc, $key, array $values, $type) {
    // Don't index empty values (i.e., when field is missing).
    if (!isset($values)) {
      return '';
    }

    if (strpos($type, 'solr_text_') === 0) {
      $type = 'text';
    }

    $first_value = '';

    // All fields.
    foreach ($values as $value) {
      if (NULL !== $value) {
        switch ($type) {
          case 'boolean':
            $value = $value ? 'true' : 'false';
            break;

          case 'date':
            $value = $this->formatDate($value);
            if ($value === FALSE) {
              continue(2);
            }
            break;

          case 'solr_date_range':
            $start = $this->formatDate($value->getStart());
            $end = $this->formatDate($value->getEnd());
            $value = '[' . $start . ' TO ' . $end . ']';
            break;

          case 'integer':
            $value = (int) $value;
            break;

          case 'decimal':
            $value = (float) $value;
            break;

          case 'text':
            /** @var \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface $value */
            $tokens = $value->getTokens();
            if (is_array($tokens) && !empty($tokens)) {
              foreach ($tokens as $token) {
                // @todo handle token boosts broken?
                // @see https://www.drupal.org/node/2746263
                if ($value = $token->getText()) {
                  $doc->addField($key, $value, $token->getBoost());
                  if (!$first_value) {
                    $first_value = $value;
                  }
                }
              }
              continue(2);
            }
            else {
              $value = $value->getText();
            }
          // No break, now we have a string.
          case 'string':
          default:
            // Keep $value as it is.
            if (!$value) {
              continue(2);
            }
        }

        $doc->addField($key, $value);
        if (!$first_value) {
          $first_value = $value;
        }
      }
    }

    return $first_value;
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
   *
   * @throws SearchApiSolrException
   */
  protected function extractResults(QueryInterface $query, ResultInterface $result) {
    $index = $query->getIndex();
    $fields = $index->getFields(TRUE);
    $site_hash = $this->getTargetedSiteHash($index);
    // We can find the item ID and the score in the special 'search_api_*'
    // properties. Mappings are provided for these properties in
    // SearchApiSolrBackend::getSolrFieldNames().
    $language_unspecific_field_names = $this->getSolrFieldNames($index);
    $id_field = $language_unspecific_field_names['search_api_id'];
    $score_field = $language_unspecific_field_names['search_api_relevance'];
    $language_field = $language_unspecific_field_names['search_api_language'];

    // Set up the results array.
    $result_set = $query->getResults();
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
    if (!empty($grouping['use_grouping'])) {
      $docs = [];
      $resultCount = 0;
      if ($result_set->hasExtraData('search_api_solr_response')) {
        $response = $result_set->getExtraData('search_api_solr_response');
        foreach ($grouping['fields'] as $field) {
          // @todo handle languages
          $solr_field_name = $language_unspecific_field_names[$field];
          if (!empty($response['grouped'][$solr_field_name])) {
            $resultCount = count($response['grouped'][$solr_field_name]);
            foreach ($response['grouped'][$solr_field_name]['groups'] as $group) {
              foreach ($group['doclist']['docs'] as $doc) {
                $docs[] = $doc;
              }
            }
          }
        }
        // Set a default number then get the groups number if possible.
        $result_set->setResultCount($resultCount);
        if (count($grouping['fields']) == 1) {
          $field = reset($grouping['fields']);
          // @todo handle languages
          $solr_field_name = $language_unspecific_field_names[$field];
          if (isset($response['grouped'][$solr_field_name]['ngroups'])) {
            $result_set->setResultCount($response['grouped'][$solr_field_name]['ngroups']);
          }
        }
      }
    }
    else {
      $result_set->setResultCount($result->getNumFound());
      $docs = $result->getDocuments();
    }

    // Add each search result to the results array.
    /** @var \Solarium\QueryType\Select\Result\Document $doc */
    foreach ($docs as $doc) {
      if (is_array($doc)) {
        $doc_fields = $doc;
      }
      else {
        /** @var \Solarium\QueryType\Select\Result\Document $doc */
        $doc_fields = $doc->getFields();
      }
      if (empty($doc_fields[$id_field])) {
        throw new SearchApiSolrException(sprintf('The result does not contain the essential ID field "%s".', $id_field));
      }

      $item_id = $doc_fields[$id_field];
      // For items coming from a different site, we need to adapt the item ID.
      if (isset($doc_fields['hash']) && !$this->configuration['site_hash'] && $doc_fields['hash'] != $site_hash) {
        $item_id = $doc_fields['hash'] . '--' . $item_id;
      }

     $result_item = NULL;
      if ($this->hasIndexJustSolrDatasources($index)) {
        $datasource = '';
        if ($index->isValidDatasource('solr_document')) {
          $datasource = 'solr_document';
        }
        elseif ($index->isValidDatasource('solr_multisite_document')) {
          $datasource = 'solr_multisite_document';
        }
        /** @var \Drupal\search_api_solr\SolrDocumentFactoryInterface $solr_document_factory */
        $solr_document_factory = \Drupal::getContainer()->get($datasource . '.factory');
        $result_item = $this->fieldsHelper->createItem($index, $datasource . '/' . $item_id);
        // Create the typed data object for the Item immediately after the query
        // has been run. Doing this now can prevent the Search API from having to
        // query for individual documents later.
        $result_item->setOriginalObject($solr_document_factory->create($result_item));
      }
      else {
         $result_item = $this->fieldsHelper->createItem($index, $item_id);
      }

      if ($language_field && isset($doc_fields[$language_field])) {
        $language_id = $doc_fields[$language_field];
        $result_item->setLanguage($language_id);
        $field_names = $this->getLanguageSpecificSolrFieldNames($language_id, $index);
      }
      else {
        $field_names = $language_unspecific_field_names;
      }

      $result_item->setExtraData('search_api_solr_document', $doc);

      if (isset($doc_fields[$score_field])) {
        $result_item->setScore($doc_fields[$score_field]);
        unset($doc_fields[$score_field]);
      }
      // The language field should not be removed. We keep it in the values as
      // well for backward compatibility and for easy access.
      unset($doc_fields[$id_field]);

      // Extract properties from the Solr document, translating from Solr to
      // Search API property names. This reverses the mapping in
      // SearchApiSolrBackend::getSolrFieldNames().
      foreach ($field_names as $search_api_property => $solr_property) {
        if (isset($doc_fields[$solr_property]) && isset($fields[$search_api_property])) {
          $doc_field = is_array($doc_fields[$solr_property]) ? $doc_fields[$solr_property] : [$doc_fields[$solr_property]];
          $field = clone $fields[$search_api_property];
          foreach ($doc_field as &$value) {
            switch ($field->getType()) {
              case 'date':
                // Field type convertions
                // Date fields need some special treatment to become valid date values
                // (i.e., timestamps) again.
                if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
                  $value = strtotime($value);
                }
                break;

              case 'text':
                $value = new TextValue($value);
            }
          }
          $field->setValues($doc_field);
          $result_item->setField($search_api_property, $field);
        }
      }

      $solr_id = $this->hasIndexJustSolrDatasources($index) ?
        str_replace('solr_document/', '', $result_item->getId()) :
        $this->createId($this->getTargetedSiteHash($index), $this->getTargetedIndexId($index), $result_item->getId());
      $this->getHighlighting($result->getData(), $solr_id, $result_item, $field_names);

      $result_set->addResultItem($result_item);
    }

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
      return [];
    }

    $field_names = $this->getSolrFieldNames($query->getIndex());
    $connector = $this->getSolrConnector();
    $solr_version = $connector->getSolrVersion();

    $facets = [];
    $index = $query->getIndex();
    $fields = $index->getFields();

    $extract_facets = $query->getOption('search_api_facets', []);

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
                $facets[$delta][] = [
                  'filter' => $term,
                  'count' => $count,
                ];
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
          // This special key is defined in setSpatial().
          if (!preg_match('/^spatial-(.*)-(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/', $key, $matches)) {
            continue;
          }
          if (empty($extract_facets[$matches[1]])) {
            continue;
          }
          $facet = $extract_facets[$matches[1]];
          if ($count >= $facet['min_count']) {
            $facets[$matches[1]][] = [
              'filter' => "[{$matches[2]} {$matches[3]}]",
              'count' => $count,
            ];
          }
        }
      }
    }
    // Extract heatmaps.
    if (isset($result_data['facet_counts']['facet_heatmaps'])) {
      if ($spatials = $query->getOption('search_api_rpt')) {
        foreach ($result_data['facet_counts']['facet_heatmaps'] as $key => $value) {
          if (!preg_match('/^rpts_(.*)$/', $key, $matches)) {
            continue;
          }
          if (empty($extract_facets[$matches[1]])) {
            continue;
          }
          $heatmaps = [];
          if (version_compare($solr_version, '7.5', '>=')) {
            $heatmaps = $value['counts_ints2D'];
          }
          else {
            $heatmaps = array_slice($value, 15);
          }
          array_walk_recursive($heatmaps, function ($heatmaps) use (&$heatmap) {
            $heatmap[] = $heatmaps;
          });
          $count = array_sum($heatmap);
          $facets[$matches[1]][] = [
            'filter' => $value,
            'count' => $count,
          ];
        }
      }
    }

    return $facets;
  }

  /**
   * Serializes a query's conditions as Solr filter queries.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to get the conditions from.
   * @param array $options
   *   The query options.
   *
   * @return array
   *   Array of filter query strings.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getFilterQueries(QueryInterface $query, array &$options) {
    return $this->createFilterQueries($query->getConditionGroup(), $options, $query);
  }

  /**
   * Recursively transforms conditions into a flat array of Solr filter queries.
   *
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   *   The group of conditions.
   * @param array $options
   *   The query options.
   * @param array $langauge_ids
   *   The language IDs required for recursion. Should be empty on initial call!
   *
   * @return array
   *   Array of filter query strings.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function createFilterQueries(ConditionGroupInterface $condition_group, array &$options, QueryInterface $query, $language_ids = []) {
    static $index_fields = [];

    if (empty($language_ids)) {
      // Reset.
      $index_fields = [];
    }

    $index = $query->getIndex();
    if (!isset($index_fields[$index->id()])) {
      $index_fields = $index->getFields(TRUE);
      $index_fields += $this->getSpecialFields($index);
    }

    $fq = [];

    // If there's a language condition take this one anfd keep it for nested
    // conditions until we get a new language condition.
    $conditions = $condition_group->getConditions();
    foreach ($conditions as $condition) {
      if ($condition instanceof ConditionInterface) {
        if ('search_api_language' == $condition->getField()) {
          $language_ids = $condition->getValue();
          if (!is_array($language_ids)) {
            $language_ids = [$language_ids];
          }
        }
      }
    }

    // If there's noe language condition on the first level, take the one from
    // the query.
    if (!$language_ids) {
      $language_ids = $query->getLanguages();
    }

    if (!$language_ids) {
      throw new SearchApiSolrException('Unable to create filter queries if no languge is set on any condition or the query itself.');
    }

    $solr_fields = $this->getSolrFieldNamesKeyedByLanguage($language_ids, $index);

    foreach ($conditions as $condition) {
      if ($condition instanceof ConditionInterface) {
        // Nested condition.
        $field = $condition->getField();
        if (!isset($solr_fields[$field])) {
          throw new SearchApiException("Filter term on unknown or unindexed field $field.");
        }
        $value = $condition->getValue();
        $filter_query = '';

        if (strpos(reset($solr_fields[$field]), 't') === 0) {
          if ($value) {
            if (empty($language_ids)) {
              throw new SearchApiException("Conditon on fulltext field without corresponding condition on search_api_language detected.");
            }

            // Fulltext fields.
            $parse_mode_id = $query->getParseMode()->getPluginId();
            $keys = [
              '#conjunction' => 'OR',
              '#negation' => $condition->getOperator() == '<>',
            ];
            switch ($parse_mode_id) {
              // This is a hack. We assume that phrase is what users want but this
              // prevents an explicit selection of terms.
              // @see https://www.drupal.org/project/search_api/issues/2991134
              case 'terms':
              case 'phrase':
              case 'edismax':
                $keys[] = $value;
                break;
              case 'direct':
                $keys = $value;
                break;
              default:
                throw new SearchApiSolrException('Incompatible parse mode.');
            }
            $filter_query = $this->flattenKeys($keys, $solr_fields[$field], $parse_mode_id);
          }
          else {
            // Fulltext fields checked against NULL.
            $nested_fqs = [];
            foreach ($solr_fields[$field] as $solr_field) {
              $nested_fqs[] = [
                'query' => $this->createFilterQuery($solr_field, $value, $condition->getOperator(), $index_fields[$field], $options),
                'tags' => $condition_group->getTags(),
              ];
            }
            $fq = array_merge($fq, $this->reduceFilterQueries($nested_fqs, new ConditionGroup(
              '=' == $condition->getOperator() ? 'AND' : 'OR',
              $condition_group->getTags()
            )));
          }
        }
        else {
          // Non-fulltext fields.
          $filter_query = $this->createFilterQuery(reset($solr_fields[$field]), $value, $condition->getOperator(), $index_fields[$field], $options);
        }

        if ($filter_query) {
          $fq[] = [
            'query' => $filter_query,
            'tags' => $condition_group->getTags(),
          ];
        }
      }
      else {
        // Nested condition group.
        $nested_fqs = $this->createFilterQueries($condition, $options, $query, $language_ids);
        $fq = array_merge($fq, $this->reduceFilterQueries($nested_fqs, $condition));
      }
    }

    return $fq;
  }

  /**
   * Reduces an array of filter queries to an array containing one filter query.
   *
   * The queries will be logically combined and their tags will be merged.
   *
   * @param array $filter_queries
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   * @param bool $last
   *
   * @return array
   */
  protected function reduceFilterQueries(array $filter_queries, ConditionGroupInterface $condition_group, $last = FALSE) {
    $fq = [];
    if (count($filter_queries) > 1) {
      $queries = [];
      $tags = [];
      $pre = $condition_group->getConjunction() == 'OR' ? '' : '+';
      foreach ($filter_queries as $nested_fq) {
        if (strpos($nested_fq['query'], '-') !== 0) {
          $queries[] = $pre . $nested_fq['query'];
        }
        elseif (!$pre) {
          $queries[] = '(' . $nested_fq['query'] . ')';
        }
        else {
          $queries[] = $nested_fq['query'];
        }
        $tags += $nested_fq['tags'];
      }
      $fq[] = [
        'query' => (!$last ? '(' : '') . implode(' ', $queries) . (!$last ? ')' : ''),
        'tags' => array_unique($tags + $condition_group->getTags()),
      ];
    }
    elseif (!empty($filter_queries)) {
      $fq[] = [
        'query' => $filter_queries[0]['query'],
        'tags' => array_unique($filter_queries[0]['tags'] + $condition_group->getTags()),
      ];
    }

    return $fq;
  }

  /**
   * Create a single search query string.
   *
   * @return string|NULL
   *    A filter query
   */
  protected function createFilterQuery($field, $value, $operator, FieldInterface $index_field, array &$options) {
    if (!is_array($value)) {
      $value = [$value];
    }

    foreach ($value as &$v) {
      if (!is_null($v) || !in_array($operator, ['=', '<>', 'IN', 'NOT IN'])) {
        $v = $this->formatFilterValue($v, $index_field->getType());
        // Remaining NULL values are now converted to empty strings.
      }
    }
    unset($v);

    if (1 == count($value)) {
      $value = array_shift($value);

      switch ($operator) {
        case 'IN':
          $operator = '=';
          break;

        case 'NOT IN':
          $operator = '<>';
          break;
      }
    }

    if (!is_null($value) && isset($options['search_api_location'])) {
      foreach ($options['search_api_location'] as &$spatial) {
        if (!empty($spatial['field']) && $index_field->getFieldIdentifier() == $spatial['field']) {
          // Spatial filter queries need modifications to the query itself.
          // Therefor we just store the parameters an let them be handled later.
          // @see setSpatial()
          // @see createLocationFilterQuery()
          $spatial['filter_query_conditions'] = [
            'field' => $field,
            'value' => $value,
            'operator' => $operator,
          ];
          return NULL;
        }
      }
    }

    switch ($operator) {
      case '<>':
        if (is_null($value)) {
          if ('location' == $index_field->getType()) {
            return $field . ':[0,-180 TO 90,180]';
          }
          else {
            return $this->queryHelper->rangeQuery($field, NULL, NULL);
          }
        }
        else {
          return '(*:* -' . $field . ':'. $this->queryHelper->escapePhrase($value) . ')';
        }

      case '<':
        return $this->queryHelper->rangeQuery($field, NULL, $value, FALSE);

      case '<=':
        return $this->queryHelper->rangeQuery($field, NULL, $value);

      case '>=':
        return $this->queryHelper->rangeQuery($field, $value, NULL);

      case '>':
        return $this->queryHelper->rangeQuery($field, $value, NULL, FALSE);

      case 'BETWEEN':
        return $this->queryHelper->rangeQuery($field, array_shift($value), array_shift($value));

      case 'NOT BETWEEN':
        return '(*:* -' . $this->queryHelper->rangeQuery($field, array_shift($value), array_shift($value)) . ')';

      case 'IN':
        $parts = [];
        $null = FALSE;
        foreach ($value as $v) {
          if (is_null($v)) {
            $null = TRUE;
          }
          else {
            $parts[] = $field . ':' . $this->queryHelper->escapePhrase($v);
          }
        }
        if ($null) {
          // @see https://stackoverflow.com/questions/4238609/how-to-query-solr-for-empty-fields/28859224#28859224
          return '(*:* -' . $this->queryHelper->rangeQuery($field, NULL, NULL) . ')';
        }
        return '(' . implode(" ", $parts) . ')';

      case 'NOT IN':
        $parts = [];
        $null = FALSE;
        foreach ($value as $v) {
          if (is_null($v)) {
            $null = TRUE;
          }
          else {
            $parts[] = '-' . $field . ':' . $this->queryHelper->escapePhrase($v);
          }
        }
        return '(' . ($null ? $this->queryHelper->rangeQuery($field, NULL, NULL) : '*:*') . ' ' . implode(" ", $parts) . ')';

      case '=':
      default:
        if (is_null($value)) {
          // @see https://stackoverflow.com/questions/4238609/how-to-query-solr-for-empty-fields/28859224#28859224
          return '(*:* -' . $this->queryHelper->rangeQuery($field, NULL, NULL) . ')';
        }
        else {
          return $field . ':' . $this->queryHelper->escapePhrase($value);
        }
    }
  }

  /**
   * Create a single search query string.
   */
  protected function createLocationFilterQuery(&$spatial) {
    $spatial_method = (isset($spatial['method']) && in_array($spatial['method'], ['geofilt', 'bbox'])) ? $spatial['method'] : 'geofilt';
    $value = $spatial['filter_query_conditions']['value'];

    switch ($spatial['filter_query_conditions']['operator']) {
      case '<':
      case '<=':
        $spatial['radius'] = $value;
        return '{!' . $spatial_method . '}';

      case '>':
      case '>=':
        $spatial['min_radius'] = $value;
        return "{!frange l=$value}geodist()";

      case 'BETWEEN':
        $spatial['min_radius'] = array_shift($value);
        $spatial['radius'] = array_shift($value);
        return '{!frange l=' . $spatial['min_radius'] . ' u=' . $spatial['radius'] . '}geodist()';

      case '=':
      case '<>':
      case 'NOT BETWEEN':
      case 'IN':
      case 'NOT IN':
      default:
        throw new SearchApiSolrException('Unsupported operator for location queries');
    }
  }

  /**
   * Format a value for filtering on a field of a specific type.
   */
  protected function formatFilterValue($value, $type) {
    $value = trim($value);
    switch ($type) {
      case 'boolean':
        $value = $value ? 'true' : 'false';
        break;

      case 'date':
        $value = $this->formatDate($value);
        if ($value === FALSE) {
          return 0;
        }
        break;

      case 'location':
        // Do not escape.
        return (float) $value;
    }
    return is_null($value) ? '' : $value;
  }

  /**
   * Tries to format given date with solarium query helper.
   *
   * @param mixed $input
   *
   * @return bool|string
   */
  public function formatDate($input) {
    $input = is_numeric($input) ? (int) $input : new \DateTime($input, timezone_open(DATETIME_STORAGE_TIMEZONE));
    return $this->queryHelper->formatDate($input);
  }

  /**
   * Helper method for creating the facet field parameters.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function setFacets(QueryInterface $query, Query $solarium_query) {
    $facets = $query->getOption('search_api_facets', []);
    if (empty($facets)) {
      return;
    }

    $field_names = $this->getSolrFieldNames($query->getIndex());

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
      $facet_field = NULL;

      // Backward compatibility for facets.
      $info += ['query_type' => 'search_api_string'];

      switch ($info['query_type']) {
        case 'search_api_granular':
          $facet_field = $facet_set->createFacetRange([
            'key' => $field,
            'field' => $field,
            'start' => $info['min_value'],
            'end' => $info['max_value'],
            'gap' => $info['granularity'],
          ]);
          $includes = [];
          if ($info['include_lower']) {
            $includes[] = 'lower';
          }
          if ($info['include_upper']) {
            $includes[] = 'upper';
          }
          if ($info['include_edges']) {
            $includes[] = 'edge';
          }
          $facet_field->setInclude($includes);
          break;

        case 'search_api_string':
        default:
          if (strpos($field, 't') === 0) {
            throw new SearchApiSolrException('Facetting on fulltext fields is not yet supported. Consider to add a string field to the index for that purpose.');
          }
          // Create the Solarium facet field object.
          $facet_field = $facet_set->createFacetField($field)->setField($field);
          // Set limit, unless it's the default.
          if ($info['limit'] != 10) {
            $limit = $info['limit'] ? $info['limit'] : -1;
            $facet_field->setLimit($limit);
          }
          // Set missing, if specified.
          if ($info['missing']) {
            $facet_field->setMissing(TRUE);
          }
          else {
            $facet_field->setMissing(FALSE);
          }
      }

      // For "OR" facets, add the expected tag for exclusion.
      if (isset($info['operator']) && strtolower($info['operator']) === 'or') {
        // @see https://cwiki.apache.org/confluence/display/solr/Faceting#Faceting-LocalParametersforFaceting
        $facet_field->setExcludes(['facet:' . $info['field']]);
      }

      // Set mincount, unless it's the default.
      if ($info['min_count'] != 1) {
        $facet_field->setMinCount($info['min_count']);
      }
    }
  }

  /**
   * Allow custom changes before sending a search query to Solr.
   *
   * This allows subclasses to apply custom changes before the query is sent to
   * Solr. Works exactly like hook_search_api_solr_query_alter().
   *
   * @param \Solarium\Core\Query\QueryInterface $solarium_query
   *   The Solarium query object.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   */
  protected function preQuery(SolariumQueryInterface $solarium_query, QueryInterface $query) {
  }

  /**
   * Allow custom changes before search results are returned for subclasses.
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

  /**
   * Implements autocomplete compatible to AutocompleteBackendInterface.
   *
   * @see \Drupal\search_api_autocomplete\AutocompleteBackendInterface
   */
  public function getAutocompleteSuggestions(QueryInterface $query, $search, $incomplete_key, $user_input) {
    $suggestions = [];
    if ($solarium_query = $this->getAutocompleteQuery($incomplete_key, $user_input)) {
      try {
        $suggestion_factory = new SuggestionFactory($user_input);
        $this->setAutocompleteTermQuery($query, $solarium_query, $incomplete_key);
        $result = $this->getSolrConnector()->execute($solarium_query);
        $suggestions = $this->getAutocompleteTermSuggestions($result, $suggestion_factory, $incomplete_key);
        // Filter out duplicate suggestions.
        $this->filterDuplicateAutocompleteSuggestions($suggestions);
      } catch (SearchApiException $e) {
        watchdog_exception('search_api_solr', $e);
      }
    }

    return $suggestions;
  }

  /**
   * @param $incomplete_key
   * @param $user_input
   *
   * @return AutocompleteQuery|null
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getAutocompleteQuery(&$incomplete_key, &$user_input) {
    // Make the input lowercase as the indexed data is (usually) also all
    // lowercase.
    $incomplete_key = mb_strtolower($incomplete_key);
    $user_input = mb_strtolower($user_input);
    $connector = $this->getSolrConnector();
    $solr_version = $connector->getSolrVersion();
    if (version_compare($solr_version, '6.5', '=')) {
      $this->getLogger()
        ->error('Solr 6.5.x contains a bug that breaks the autocomplete feature. Downgrade to 6.4.x or upgrade to 6.6.x at least.');
      return NULL;
    }

    return $connector->getAutocompleteQuery();
  }

  /**
   * Get the fields to search for autocomplete terms.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the completed user input so far.
   *
   * @return array
   */
  protected function getAutocompleteFields(QueryInterface $query) {
    $fl = [];
    $language_ids = $query->getLanguages();
    $field_names = $this->getSolrFieldNamesKeyedByLanguage($language_ids, $query->getIndex());
    foreach ($this->getQueryFulltextFields($query) as $fulltext_field) {
      $fl = array_merge($fl, array_values($field_names[$fulltext_field]));
    }
    return $fl;
  }

  /**
   * @param $suggestions
   */
  protected function filterDuplicateAutocompleteSuggestions(&$suggestions) {
    $added_suggestions = [];
    $added_urls = [];
    /** @var \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface $suggestion */
    foreach ($suggestions as $key => $suggestion) {
      if (
        !in_array($suggestion->getSuggestedKeys(), $added_suggestions, TRUE) ||
        !in_array($suggestion->getUrl(), $added_urls, TRUE)
      ) {
        $added_suggestions[] = $suggestion->getSuggestedKeys();
        $added_urls[] = $suggestion->getUrl();
      }
      else {
        unset($suggestions[$key]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTermsSuggestions(QueryInterface $query, $search, $incomplete_key, $user_input) {
    // Allow modules to alter the solarium autocomplete query.
    $this->moduleHandler->alter('search_api_solr_terms_autocomplete_query', $query);
    return $this->getAutocompleteSuggestions($query, $search, $incomplete_key, $user_input);
  }

  /**
   * {@inheritdoc}
   */
  public function getSpellcheckSuggestions(QueryInterface $query, $search, $incomplete_key, $user_input) {
    $suggestions = [];
    if ($solarium_query = $this->getAutocompleteQuery($incomplete_key, $user_input)) {
      try {
        $suggestion_factory = new SuggestionFactory($user_input);
        $this->setAutocompleteSpellCheckQuery($query, $solarium_query, $user_input);
        // Allow modules to alter the solarium autocomplete query.
        $this->moduleHandler->alter('search_api_solr_spellcheck_autocomplete_query', $solarium_query, $query);
        $result = $this->getSolrConnector()->execute($solarium_query);
        $suggestions = $this->getAutocompleteSpellCheckSuggestions($result, $suggestion_factory);
        // Filter out duplicate suggestions.
        $this->filterDuplicateAutocompleteSuggestions($suggestions);
      } catch (SearchApiException $e) {
        watchdog_exception('search_api_solr', $e);
      }
    }

    return $suggestions;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggesterSuggestions(QueryInterface $query, $search, $incomplete_key, $user_input, $options = []) {
    $suggestions = [];
    if ($solarium_query = $this->getAutocompleteQuery($incomplete_key, $user_input)) {
      try {
        $suggestion_factory = new SuggestionFactory($user_input);
        $this->setAutocompleteSuggesterQuery($query, $solarium_query, $user_input, $options);
        // Allow modules to alter the solarium autocomplete query.
        $this->moduleHandler->alter('search_api_solr_suggester_autocomplete_query', $solarium_query, $query);
        $result = $this->getSolrConnector()->execute($solarium_query);
        $suggestions = $this->getAutocompleteSuggesterSuggestions($result, $suggestion_factory);
        // Filter out duplicate suggestions.
        $this->filterDuplicateAutocompleteSuggestions($suggestions);
      } catch (SearchApiException $e) {
        watchdog_exception('search_api_solr', $e);
      }
    }

    return $suggestions;
  }

  /**
   * Set the spellcheck parameters for the solarium autocomplete query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the completed user input so far.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the completed user input so far.
   * @param AutocompleteQuery $solarium_query
   *   An autocomplete solarium query.
   * @param string $user_input
   *   The user input.
   */
  protected function setAutocompleteSpellCheckQuery(QueryInterface $query, AutocompleteQuery $solarium_query, $user_input) {
    /** @var \Solarium\Component\Spellcheck $spellcheck_component */
    $spellcheck_component = $solarium_query->getSpellcheck();
    if ($languages = $query->getLanguages()) {
      foreach ($languages as $language) {
        // @todo set multiple dictionaries
        // Convert zk-hans to zk_hans.
        $spellcheck_component->setDictionary(str_replace('-', '_', $language));
      }
    }
    else {
      $spellcheck_component->setDictionary(LanguageInterface::LANGCODE_NOT_SPECIFIED);
    }
    $spellcheck_component->setQuery($user_input);
    $spellcheck_component->setCount($query->getOption('limit', 1));
  }

  /**
   * Set the term parameters for the solarium autocomplete query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the completed user input so far.
   * @param AutocompleteQuery $solarium_query
   *   An autocomplete solarium query.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed.
   */
  protected function setAutocompleteTermQuery(QueryInterface $query, AutocompleteQuery $solarium_query, $incomplete_key) {
    $fl = $this->getAutocompleteFields($query);
    $terms_component = $solarium_query->getTerms();
    $terms_component->setFields($fl);
    $terms_component->setPrefix($incomplete_key);
    $terms_component->setLimit($query->getOption('limit',10));
  }

  /**
   * Set the suggester parameters for the solarium autocomplete query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the completed user input so far.
   * @param AutocompleteQuery $solarium_query
   *   An autocomplete solarium query.
   * @param string $user_input
   *   The user input.
   * @param array $options
   *   'dictionary' as string, 'context_filter_tags' as array of strings.
   */
  protected function setAutocompleteSuggesterQuery(QueryInterface $query, AutocompleteQuery $solarium_query, $user_input, $options = []) {
    if (isset($options['context_filter_tags']) && in_array('drupal/langcode:multilingual', $options['context_filter_tags'])) {
      $langcodes = $query->getLanguages();
      if (count($langcodes) == 1) {
        $langcode = reset($langcodes);
        $options['context_filter_tags'] = str_replace('drupal/langcode:multilingual', 'drupal/langcode:' . $langcode, $options['context_filter_tags']);
        $options['dictionary'] = $langcode;
      }
      else {
        foreach ($options['context_filter_tags'] as $key => $tag) {
          if ('drupal/langcode:multilingual' == $tag) {
            unset($options['context_filter_tags'][$key]);
            break;
          }
        }
      }
    }

    $suggester_component = $solarium_query->getSuggester();
    $suggester_component->setQuery($user_input);
    $suggester_component->setDictionary(!empty($options['dictionary']) ? $options['dictionary'] : LanguageInterface::LANGCODE_NOT_SPECIFIED);
    if (!empty($options['context_filter_tags'])) {
      $suggester_component->setContextFilterQuery(
        Utility::buildSuggesterContextFilterQuery($options['context_filter_tags']));
    }
    $suggester_component->setCount($query->getOption('limit',10));
    // The search_api_autocomplete module highlights by itself.
    $solarium_query->addParam('suggest.highlight', FALSE);
  }

  /**
   * Get the spellcheck suggestions from the autocomplete query result.
   *
   * @param \Solarium\Core\Query\Result\ResultInterface $result
   *  A autocomplete query result.
   * @param \Drupal\search_api_autocomplete\Suggestion\SuggestionFactory $suggestion_factory
   *   The suggestion factory.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of suggestions.
   */
  protected function getAutocompleteSpellCheckSuggestions(ResultInterface $result, SuggestionFactory $suggestion_factory) {
    $suggestions = [];
    if ($spellcheck_results = $result->getComponent(ComponentAwareQueryInterface::COMPONENT_SPELLCHECK)) {
      foreach ($spellcheck_results as $term_result) {
        $keys = [];
        /** @var \Solarium\Component\Result\Spellcheck\Suggestion $term_result */
        foreach ($term_result->getWords() as $correction) {
          $keys[] = $correction['word'];
        }
        if ($keys) {
          $suggestions[] = $suggestion_factory->createFromSuggestedKeys(implode(' ', $keys));
        }
      }
    }
    return $suggestions;
  }

  /**
   * Get the term suggestions from the autocomplete query result.
   *
   * @param \Solarium\Core\Query\Result\ResultInterface $result
   *  A autocomplete query result.
   * @param \Drupal\search_api_autocomplete\Suggestion\SuggestionFactory $suggestion_factory
   *   The suggestion factory.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of suggestions.
   */
  protected function getAutocompleteTermSuggestions(ResultInterface $result, SuggestionFactory $suggestion_factory, $incomplete_key) {
    $suggestions = [];
    if ($terms_results = $result->getComponent(ComponentAwareQueryInterface::COMPONENT_TERMS)) {
      $autocomplete_terms = [];
      foreach ($terms_results as $fields) {
        foreach ($fields as $term => $count) {
          if ($term != $incomplete_key) {
            $autocomplete_terms[$term] = $count;
          }
        }
      }

      foreach ($autocomplete_terms as $term => $count) {
        $suggestion_suffix = mb_substr($term, mb_strlen($incomplete_key));
        $suggestions[] = $suggestion_factory->createFromSuggestionSuffix($suggestion_suffix, $count);
      }
    }
    return $suggestions;
  }

  /**
   * Get the term suggestions from the autocomplete query result.
   *
   * @param \Solarium\Core\Query\Result\ResultInterface $result
   *  A autocomplete query result.
   * @param \Drupal\search_api_autocomplete\Suggestion\SuggestionFactory $suggestion_factory
   *   The suggestion factory.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of suggestions.
   */
  protected function getAutocompleteSuggesterSuggestions(ResultInterface $result, SuggestionFactory $suggestion_factory) {
    $suggestions = [];
    if ($phrases_result = $result->getComponent(ComponentAwareQueryInterface::COMPONENT_SUGGESTER)) {
      foreach ($phrases_result->getAll() as $phrases) {
        /** @var \Solarium\QueryType\Suggester\Result\Term $phrases */
        foreach ($phrases->getSuggestions() as $phrase) {
          $suggestions[] = $suggestion_factory->createFromSuggestedKeys($phrase['term']);
        }
      }
    }
    return $suggestions;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);

    // Update the configuration of the Solr connector as well by replacing it by
    // a new instance with the latest configuration.
    $this->solrConnector = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexId(IndexInterface $index) {
    $settings = $index->getThirdPartySettings('search_api_solr') + search_api_solr_default_index_third_party_settings();
    return $this->configuration['server_prefix'] . $settings['advanced']['index_prefix'] . $index->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetedIndexId(IndexInterface $index) {
    $config = $this->getDatasourceConfig($index);
    return isset($config['target_index']) ? $config['target_index'] : $this->getIndexId($index);
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetedSiteHash(IndexInterface $index) {
    $config = $this->getDatasourceConfig($index);
    return isset($config['target_hash']) ? $config['target_hash'] : Utility::getSiteHash();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->calculatePluginDependencies($this->getSolrConnector());

    /** @var \Drupal\search_api_solr\Controller\SolrFieldTypeListBuilder $list_builder */
    $list_builder = \Drupal::entityTypeManager()->getListBuilder('solr_field_type');
    $list_builder->setBackend($this);
    $solr_field_types = $list_builder->load();
    /** @var \Drupal\search_api_solr\Entity\SolrFieldType $solr_field_type */
    foreach ($solr_field_types as $solr_field_type) {
      $this->addDependency('config', $solr_field_type->getConfigDependencyName());
    }

    return $this->dependencies;
  }

  /**
   * Extract and format highlighting information for a specific item.
   *
   * Will also use highlighted fields to replace retrieved field data, if the
   * corresponding option is set.
   *
   * @param array $data
   *   The data extracted from a Solr result.
   * @param string $solr_id
   *   The ID of the result item.
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The fields of the result item.
   * @param array $field_mapping
   *   Mapping from search_api field names to Solr field names.
   */
  protected function getHighlighting($data, $solr_id, ItemInterface $item, array $field_mapping) {
    if (isset($data['highlighting'][$solr_id]) && !empty($this->configuration['highlight_data'])) {
      $prefix = '<strong>';
      $suffix = '</strong>';
      try {
        $highlight_config = $item->getIndex()->getProcessor('highlight')->getConfiguration();
        if ($highlight_config['highlight'] == 'never') {
          return;
        }
        $prefix = $highlight_config['prefix'];
        $suffix = $highlight_config['suffix'];
      }
      catch (SearchApiException $exception) {
        // Highlighting processor is not enabled for this index.
      }
      $snippets = [];
      $keys = [];
      foreach ($field_mapping as $search_api_property => $solr_property) {
        if (!empty($data['highlighting'][$solr_id][$solr_property])) {
          foreach ($data['highlighting'][$solr_id][$solr_property] as $value) {
            $keys = array_merge($keys, Utility::getHighlightedKeys($value));
            // Contrary to above, we here want to preserve HTML, so we just
            // replace the [HIGHLIGHT] tags with the appropriate format.
            $snippets[$search_api_property][] = Utility::formatHighlighting($value, $prefix, $suffix);
          }
        }
      }
      if ($snippets) {
        $item->setExtraData('highlighted_fields', $snippets);
        $item->setExtraData('highlighted_keys', array_unique($keys));
      }
    }
  }

  /**
   * Flattens keys and fields into a single search string.
   *
   * Formatting the keys into a Solr query can be a bit complex. Keep in mind
   * that the default operator is OR. For some combinations we had to take
   * decisions because different interpretations are possible and we have to
   * ensure that stop words in boolean combinations don't lead to zero results.
   * Therfore this function will produce these queries:
   *
   * #conjunction | #negation | fields | parse mode     | return value
   * ---------------------------------------------------------------------------
   * AND          | FALSE     | []     | terms / phrase | +(+A +B)
   * AND          | TRUE      | []     | terms / phrase | -(+A +B)
   * OR           | FALSE     | []     | terms / phrase | +(A B)
   * OR           | TRUE      | []     | terms / phrase | -(A B)
   * AND          | FALSE     | [x]    | terms / phrase | +(x:(+A +B)^1)
   * AND          | TRUE      | [x]    | terms / phrase | -(x:(+A +B)^1)
   * OR           | FALSE     | [x]    | terms / phrase | +(x:(A B)^1)
   * OR           | TRUE      | [x]    | terms / phrase | -(x:(A B)^1)
   * AND          | FALSE     | [x,y]  | terms          | +((+(x:A^1 y:A^1) +(x:B^1 y:B^1)) x:(+A +B)^1 y:(+A +B)^1)
   * AND          | FALSE     | [x,y]  | phrase         | +(x:(+A +B)^1 y:(+A +B)^1)
   * AND          | TRUE      | [x,y]  | terms          | -((+(x:A^1 y:A^1) +(x:B^1 y:B^1)) x:(+A +B)^1 y:(+A +B)^1)
   * AND          | TRUE      | [x,y]  | phrase         | -(x:(+A +B)^1 y:(+A +B)^1)
   * OR           | FALSE     | [x,y]  | terms          | +(((x:A^1 y:A^1) (x:B^1 y:B^1)) x:(A B)^1 y:(A B)^1)
   * OR           | FALSE     | [x,y]  | phrase         | +(x:(A B)^1 y:(A B)^1)
   * OR           | TRUE      | [x,y]  | terms          | -(((x:A^1 y:A^1) (x:B^1 y:B^1)) x:(A B)^1 y:(A B)^1)
   * OR           | TRUE      | [x,y]  | phrase         | -(x:(A B)^1 y:(A B)^1)
   * AND          | FALSE     | [x,y]  | edismax        | +({!edismax qf=x^1,y^1}+A +B)
   * AND          | TRUE      | [x,y]  | edismax        | -({!edismax qf=x^1,y^1}+A +B)
   * OR           | FALSE     | [x,y]  | edismax        | +({!edismax qf=x^1,y^1}A B)
   * OR           | TRUE      | [x,y]  | edismax        | -({!edismax qf=x^1,y^1}A B)
   * AND / OR     | FALSE     | [x]    | direct         | +(x:(A)^1)
   * AND / OR     | TRUE      | [x]    | direct         | -(x:(A)^1)
   * AND / OR     | FALSE     | [x,y]  | direct         | +(x:(A)^1 y:(A)^1)
   * AND / OR     | TRUE      | [x,y]  | direct         | -(x:(A)^1 y:(A)^1)
   *
   * @param array|string $keys
   *   The keys array to flatten, formatted as specified by
   *   \Drupal\search_api\Query\QueryInterface::getKeys() or a phrase string.
   * @param array $fields
   * @param string $parse_mode_id
   *
   * @return string
   *   A Solr query string representing the same keys.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function flattenKeys($keys, array $fields = [], string $parse_mode_id = 'phrase') {
    $k = [];
    $pre = '+';
    $neg = '';
    $query_parts = [];

    if (is_array($keys)) {
      if (isset($keys['#conjunction']) && $keys['#conjunction'] == 'OR') {
        $pre = '';
      }

      if (!empty($keys['#negation'])) {
        $neg = '-';
      }

      $escaped = isset($keys['#escaped']) ? $keys['#escaped'] : FALSE;

      foreach ($keys as $key_nr => $key) {
        // We cannot use \Drupal\Core\Render\Element::children() anymore because
        // $keys is not a valid render array.
        if ($key_nr[0] === '#' || !$key) {
          continue;
        }
        if (is_array($key)) {
          if ('edismax' == $parse_mode_id) {
            throw new SearchApiSolrException('Incompatible parse mode.');
          }
          if ($subkeys = $this->flattenKeys($key, $fields, $parse_mode_id)) {
            $query_parts[] = $subkeys;
          }
        }
        elseif ($escaped) {
          $k[] = trim($key);
        }
        else {
          switch ($parse_mode_id) {
            // Using the 'phrase' parse mode, Search API provides one big phrase
            // as keys. Using the 'terms' parse mode, Search API provides chunks
            // of single terms as keys. But these chunks might contain not just
            // real terms but again a phrase if you enter something like this in
            // the search box: term1 "term2 as phrase" term3. This will be
            // converted in this keys array: ['term1', 'term2 as phrase',
            // 'term3']. To have Solr behave like the database backend, these
            // three "terms" should be handled like three phrases.
            case 'terms':
            case 'phrase':
            case 'edismax':
              $k[] = $this->queryHelper->escapePhrase(trim($key));
              break;
            default:
              throw new SearchApiSolrException('Incompatible parse mode.');
          }
        }
      }
    }
    elseif (is_string($keys)) {
      switch ($parse_mode_id) {
        case 'direct':
          $pre = '';
          $k[] = '(' . trim($keys) .')';
          break;
        default:
          throw new SearchApiSolrException('Incompatible parse mode.');
      }
    }

    if ($k) {
      switch ($parse_mode_id) {
        case 'edismax':
          $query_parts[] = "({!edismax qf='" . implode(' ', $fields) . "'}" . $pre . implode(' ' . $pre, $k) . ')';
          break;

        case "terms":
          if (count($k) > 1 && count($fields) > 0) {
            $key_parts = [];
            foreach ($k as $l) {
              $field_parts = [];
              foreach ($fields as $f) {
                $field = $f;
                $boost_or_fuzzy = '';
                // Split on operators for boost (^), fixed score (^=), fuzzy (~).
                if ($split = preg_split('/([\^~])/', $f, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE)) {
                  $field = array_shift($split);
                  $boost_or_fuzzy = implode('', $split);
                }
                $field_parts[] = $field . ':' . $l . $boost_or_fuzzy;
              }
              $key_parts[] = $pre . '(' . implode(' ', $field_parts) . ')';
            }
            $query_parts[] = '(' . implode(' ', $key_parts) . ')';
          }
          // No break! Execute 'default', too.

        default:
          if (count($fields) > 0) {
            foreach ($fields as $f) {
              $field = $f;
              $boost_or_fuzzy = '';
              // Split on operators for boost (^), fixed score (^=), fuzzy (~).
              if ($split = preg_split('/([\^~])/', $f, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE)) {
                $field = array_shift($split);
                $boost_or_fuzzy = implode('', $split);
              }
              $query_parts[] = $field . ':(' . $pre . implode(' ' . $pre, $k) . ')' . $boost_or_fuzzy;
            }
          }
          else {
            $query_parts[] = '(' . $pre . implode(' ' . $pre, $k) . ')';
          }
      }
    }

    if (count($query_parts) == 1) {
      return $neg . reset($query_parts);
    }
    elseif (count($query_parts) > 1) {
      return $neg . '(' . implode(' ', $query_parts) . ')';
    }
    else {
      return '';
    }
  }

  /**
   * Sets the highlighting parameters.
   *
   * (The $query parameter currently isn't used and only here for the potential
   * sake of subclasses.)
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The Solarium select query object.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query object.
   * @param array $query_fields
   *   The solr fields to be highlighted.
   */
  protected function setHighlighting(Query $solarium_query, QueryInterface $query, $highlighted_fields = []) {
    if (!empty($this->configuration['highlight_data'])) {
      $settings = $query->getIndex()->getThirdPartySettings('search_api_solr') + search_api_solr_default_index_third_party_settings();
      $highlighter = $settings['highlighter'];

      $hl = $solarium_query->getHighlighting();
      $hl->setSimplePrefix('[HIGHLIGHT]');
      $hl->setSimplePostfix('[/HIGHLIGHT]');
      $hl->setSnippets($highlighter['highlight']['snippets']);
      $hl->setFragSize($highlighter['highlight']['fragsize']);
      $hl->setMergeContiguous($highlighter['highlight']['mergeContiguous']);
      $hl->setRequireFieldMatch($highlighter['highlight']['requireFieldMatch']);

      // Overwrite Solr default values only if required to have shorter request
      // strings.
      if (51200 != $highlighter['maxAnalyzedChars']) {
        $hl->setMaxAnalyzedChars($highlighter['maxAnalyzedChars']);
      }
      if ('gap' != $highlighter['fragmenter']) {
        $hl->setFragmenter($highlighter['fragmenter']);
        if ('regex' != $highlighter['fragmenter']) {
          $hl->setRegexPattern($highlighter['regex']['pattern']);
          if (0.5 != $highlighter['regex']['slop']) {
            $hl->setRegexSlop($highlighter['regex']['slop']);
          }
          if (10000 != $highlighter['regex']['maxAnalyzedChars']) {
            $hl->setRegexMaxAnalyzedChars($highlighter['regex']['maxAnalyzedChars']);
          }
        }
      }
      if (!$highlighter['usePhraseHighlighter']) {
        $hl->setUsePhraseHighlighter(FALSE);
      }
      if (!$highlighter['highlightMultiTerm']) {
        $hl->setHighlightMultiTerm(FALSE);
      }
      if ($highlighter['preserveMulti']) {
        $hl->setPreserveMulti(TRUE);
      }

      foreach ($highlighted_fields as $highlighted_field) {
        // We must not set the fields at once using setFields() to not break
        // the altered queries.
        $hl->addField($highlighted_field);
      }
    }
  }

  /**
   * Changes the query to a "More Like This" query.
   *
   * @param \Solarium\QueryType\MorelikeThis\Query $solarium_query
   *   The solr mlt query.
   *
   * @return \Solarium\QueryType\MorelikeThis\Query $solarium_query
   */
  protected function getMoreLikeThisQuery(QueryInterface $query) {
    $connector = $this->getSolrConnector();
    $solarium_query = $connector->getMoreLikeThisQuery();
    $mlt_options = $query->getOption('search_api_mlt');
    $language_ids = $query->getLanguages();
    $field_names = $this->getSolrFieldNamesKeyedByLanguage($language_ids);

    $ids = [];
    foreach ($query->getIndex()->getDatasources() as $datasource) {
      if ($entity_type_id = $datasource->getEntityTypeId()) {
        $entity = \Drupal::entityTypeManager()
          ->getStorage($entity_type_id)
          ->load($mlt_options['id']);

        if ($entity instanceof ContentEntityInterface) {
          $translated = FALSE;
          if ($entity->isTranslatable()) {
            foreach ($language_ids as $language_id) {
              if ($entity->hasTranslation($language_id)) {
                $ids[] = SearchApiUtility::createCombinedId(
                  $datasource->getPluginId(),
                  $datasource->getItemId(
                    $entity->getTranslation($language_id)->getTypedData()
                  )
                );
                $translated = TRUE;
              }
            }
          }

          if (!$translated) {
            // Fall back to the default language of the entity.
            $ids[] = SearchApiUtility::createCombinedId(
              $datasource->getPluginId(),
              $datasource->getItemId($entity->getTypedData())
            );
          }
        }
        else {
          $ids[] = $mlt_options['id'];
        }
      }
    }

    if (!empty($ids)) {
      $index = $query->getIndex();
      $index_id = $this->getTargetedIndexId($index);
      $site_hash = $this->getTargetedSiteHash($index);
      if (!$this->hasIndexJustSolrDatasources($index)) {
        array_walk($ids, function (&$id, $key) use ($site_hash, $index_id) {
          $id = $this->createId($site_hash, $index_id, $id);
          $id = $this->queryHelper->escapePhrase($id);
        });
      }
      $solarium_query->setQuery('id:' . implode(' id:', $ids));
    }

    $mlt_fl = [];
    foreach ($mlt_options['fields'] as $mlt_field) {
      $first_field = reset($field_names[$mlt_field]);
      // Date fields don't seem to be supported at all in MLT queries.
      if (strpos($first_field, 'd') !== 0) {
        if (strpos($first_field, 't') !== 0) {
          $mlt_fl[] = $first_field;
          // For non-text fields, set minimum word length to 0.
          $solarium_query->addParam('f.' . $first_field . '.mlt.minwl', 0);
        }
        else {
          $mlt_fl = array_merge($mlt_fl, array_values($field_names[$mlt_field]));
        }
      }
    }

    $solarium_query->setMltFields($mlt_fl);
    // @todo Add some configuration options here and support more MLT options.
    $solarium_query->setMinimumDocumentFrequency(1);
    $solarium_query->setMinimumTermFrequency(1);

    return $solarium_query;
  }

  /**
   * Adds spatial features to the search query.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The solr query.
   * @param array $spatial_options
   *   The spatial options to add.
   * @param QueryInterface $query
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function setSpatial(Query $solarium_query, array $spatial_options, QueryInterface $query) {
    if (count($spatial_options) > 1) {
      throw new SearchApiSolrException('Only one spatial search can be handled per query.');
    }

    $field_names = $this->getSolrFieldNames($query->getIndex());
    $spatial = reset($spatial_options);
    $solr_field = $field_names[$spatial['field']];
    $distance_field = $spatial['field'] . '__distance';
    $solr_distance_field = $field_names[$distance_field];
    $spatial['lat'] = (float) $spatial['lat'];
    $spatial['lon'] = (float) $spatial['lon'];
    $spatial['radius'] = isset($spatial['radius']) ? (float) $spatial['radius'] : 0.0;
    $spatial['min_radius'] = isset($spatial['min_radius']) ? (float) $spatial['min_radius'] : 0.0;

    if (!isset($spatial['filter_query_conditions'])) {
      $spatial['filter_query_conditions'] = [];
    }
    $spatial['filter_query_conditions'] += [
      'field' => $solr_field,
      'value' => $spatial['radius'],
      'operator' => '<',
    ];

    // Add a field to the result set containing the calculated distance.
    $solarium_query->addField($solr_distance_field . ':geodist()');
    // Set the common spatial parameters on the query.
    $spatial_query = $solarium_query->getSpatial();
    $spatial_query->setDistance($spatial['radius']);
    $spatial_query->setField($solr_field);
    $spatial_query->setPoint($spatial['lat'] . ',' . $spatial['lon']);
    // Add the conditions of the spatial query. This might adust the values of
    // 'radius' and 'min_radius' required later for facets.
    $solarium_query->createFilterQuery($solr_field)
      ->setQuery($this->createLocationFilterQuery($spatial));

    // Tell solr to sort by distance if the field is given by Search API.
    $sorts = $solarium_query->getSorts();
    if (isset($sorts[$solr_distance_field])) {
      $new_sorts = [];
      foreach ($sorts as $key => $order) {
        if ($key == $solr_distance_field) {
          $new_sorts['geodist()'] = $order;
        }
        else {
          $new_sorts[$key] = $order;
        }
      }
      $solarium_query->clearSorts();
      $solarium_query->setSorts($new_sorts);
    }

    // Change the facet parameters for spatial fields to return distance
    // facets.
    $facet_set = $solarium_query->getFacetSet();
    if (!empty($facet_set)) {
      /** @var \Solarium\Component\Facet\Field[] $facets */
      $facets = $facet_set->getFacets();
      foreach ($facets as $delta => $facet) {
        $facet_options = $facet->getOptions();
        if ($facet_options['field'] != $solr_distance_field) {
          continue;
        }
        $facet_set->removeFacet($delta);

        $limit = $facet->getLimit();

        // @todo Check if these defaults make any sense.
        $steps = $limit > 0 ? $limit : 5;
        $step = ($spatial['radius'] - $spatial['min_radius']) / $steps;

        for ($i = 0; $i < $steps; $i++) {
          $distance_min = $spatial['min_radius'] + ($step * $i);
          // @todo $step - 1 means 1km less. That opens a gap in the facets of
          //   1km that is not covered.
          $distance_max = $distance_min + $step - 1;
          // Define our own facet key to transport the min and max values.
          // These will be extracted in extractFacets().
          $key = "spatial-{$distance_field}-{$distance_min}-{$distance_max}";
          // Due to a limitation/bug in Solarium, it is not possible to use
          // setQuery method for geo facets.
          // So the key is misused to get a correct query.
          // @see https://github.com/solariumphp/solarium/issues/229
          $facet_set->createFacetQuery($key . ' frange l=' . $distance_min . ' u=' . $distance_max)->setQuery('geodist()');
        }
      }
    }
  }

  /**
   * Adds rpt spatial features to the search query.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The solr query.
   * @param array $rpt_options
   *   The rpt spatial options to add.
   * @param QueryInterface $query
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   *   Thrown when more than one rpt spatial searches are added.
   */
  protected function setRpt(Query $solarium_query, array $rpt_options, QueryInterface $query) {
    // Add location filter.
    if (count($rpt_options) > 1) {
      throw new SearchApiSolrException('Only one spatial search can be handled per query.');
    }

    $field_names = $this->getSolrFieldNames($query->getIndex());
    $rpt = reset($rpt_options);
    $solr_field = $field_names[$rpt['field']];
    $rpt['geom'] = isset($rpt['geom']) ? $rpt['geom'] : '["-180 -90" TO "180 90"]';

    // Add location filter.
    $solarium_query->createFilterQuery($solr_field)->setQuery($solr_field . ':' . $rpt['geom']);

    // Add Solr Query params.
    $solarium_query->addParam('facet', 'on');
    $solarium_query->addParam('facet.heatmap', $solr_field);
    $solarium_query->addParam('facet.heatmap.geom', $rpt['geom']);
    $solarium_query->addParam('facet.heatmap.format', $rpt['format']);
    $solarium_query->addParam('facet.heatmap.maxCells', $rpt['maxCells']);
    $solarium_query->addParam('facet.heatmap.gridLevel', $rpt['gridLevel']);
  }

  /**
   * Sets sorting for the query.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   * @param \Drupal\search_api\Query\QueryInterface $query
   */
  protected function setSorts(Query $solarium_query, QueryInterface $query) {
    $field_names = $this->getSolrFieldNamesKeyedByLanguage($query->getLanguages(), $query->getIndex());
    foreach ($query->getSorts() as $field => $order) {
      $solarium_query->addSort(Utility::getSortableSolrField($field, $field_names, $query), strtolower($order));
    }
  }

  /**
   * Sets grouping for the query.
   */
  protected function setGrouping(Query $solarium_query, QueryInterface $query, $grouping_options = [], $index_fields = [], $field_names = []) {
    if (!empty($grouping_options['use_grouping'])) {

      $group_fields = [];

      foreach ($grouping_options['fields'] as $collapse_field) {
        // @todo languages
        $first_name = reset($field_names[$collapse_field]);
        /** @var $field Field $type */
        $field = $index_fields[$collapse_field];
        $type = $field->getType();
        if ($this->dataTypeHelper->isTextType($type) || 's' != Utility::getSolrFieldCardinality($first_name)) {
          $this->getLogger()->error('Grouping is not supported for field @field. Only single-valued fields not indexed as "Fulltext" are supported.',
            ['@field' => $index_fields[$collapse_field]['name']]);
        }
        else {
          $group_fields[] = $first_name;
        }
      }

      if (!empty($group_fields)) {
        // Activate grouping on the solarium query.
        $grouping_component = $solarium_query->getGrouping();

        $grouping_component->setFields($group_fields)
          // We always want the number of groups returned so that we get pagers
          // done right.
          ->setNumberOfGroups(TRUE)
          ->setTruncate(!empty($grouping_options['truncate']))
          ->setFacet(!empty($grouping_options['group_facet']));

        if (!empty($grouping_options['group_limit']) && ($grouping_options['group_limit'] != 1)) {
          $grouping_component->setLimit($grouping_options['group_limit']);
        }

        if (!empty($grouping_options['group_sort'])) {
          $sorts = [];
          foreach ($grouping_options['group_sort'] as $group_sort_field => $order) {
            $sorts[] = Utility::getSortableSolrField($group_sort_field, $field_names, $query) . ' ' . strtolower($order);
          }

          $grouping_component->setSort(implode(', ', $sorts));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function extractContentFromFile($filepath) {
    $connector = $this->getSolrConnector();

    $query = $connector->getExtractQuery();
    $query->setExtractOnly(TRUE);
    $query->setFile($filepath);

    // Execute the query.
    $result = $connector->extract($query);
    return $connector->getContentFromExtractResult($result, $filepath);
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendDefinedFields(IndexInterface $index) {
    $backend_defined_fields = [];

    foreach ($index->getFields() as $field) {
      if ($field->getType() == 'location') {
        $distance_field_name = $field->getFieldIdentifier() . '__distance';
        $property_path_name = $field->getPropertyPath() . '__distance';
        $distance_field = new Field($index, $distance_field_name);
        $distance_field->setLabel($field->getLabel() . ' (distance)');
        $distance_field->setDataDefinition(DataDefinition::create('decimal'));
        $distance_field->setType('decimal');
        $distance_field->setDatasourceId($field->getDatasourceId());
        $distance_field->setPropertyPath($property_path_name);

        $backend_defined_fields[$distance_field_name] = $distance_field;
      }
    }

    return $backend_defined_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getDomain() {
    return (isset($this->configuration['domain']) && !empty($this->configuration['domain'])) ? $this->configuration['domain'] : 'generic';
  }

  /**
   * {@inheritdoc}
   */
  public function isManagedSchema() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isOptimizeEnabled() {
    return isset($this->configuration['optimize']) ? $this->configuration['optimize'] : FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Don't return the big twm_suggest field.
   */
  protected function getQueryFulltextFields(QueryInterface $query) {
    $fulltext_fields = parent::getQueryFulltextFields($query);
    $solr_field_names = $this->getSolrFieldNames($query->getIndex());
    return array_filter($fulltext_fields, function ($value) use ($solr_field_names) {
      return 'twm_suggest' != $solr_field_names[$value] & strpos($solr_field_names[$value], 'spellcheck_') !== 0;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaLanguageStatistics() {
    $available = $this->getSolrConnector()->pingCore();
    $stats = [];
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $solr_field_type_name = Utility::encodeSolrName('text' . '_' . $language->getId());
      $stats[$language->getId()] = $available ? $this->isPartOfSchema('fieldTypes', $solr_field_type_name) : FALSE;
    }
    return $stats;
  }

  /**
   * Indicates if an 'element' is part of the Solr server's schema.
   *
   * @param string $kind
   *   The kind of the element, for example 'dynamicFields' or 'fieldTypes'.
   *
   * @param string $name
   *   The name of the element.
   *
   * @return bool
   *   True if an element of the given kind and name exists, false otherwise.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function isPartOfSchema($kind, $name) {
    static $previous_calls;

    $state_key = 'sasm.' . $this->getServer()->id() . '.schema_parts';
    $state = \Drupal::state();
    $schema_parts = $state->get($state_key);
    // @todo reset that drupal state from time to time

    if (
      !is_array($schema_parts) || empty($schema_parts[$kind]) ||
      (!in_array($name, $schema_parts[$kind]) && !isset($previous_calls[$kind]))
    ) {
      $response = $this->getSolrConnector()
        ->coreRestGet('schema/' . strtolower($kind));
      if (empty($response[$kind])) {
        throw new SearchApiSolrException('Missing information about ' . $kind . ' in response to REST request.');
      }
      // Delete the old state.
      $schema_parts[$kind] = [];
      foreach ($response[$kind] as $row) {
        $schema_parts[$kind][] = $row['name'];
      }
      $state->set($state_key, $schema_parts);
      $previous_calls[$kind] = TRUE;
    }

    return in_array($name, $schema_parts[$kind]);
  }

}
