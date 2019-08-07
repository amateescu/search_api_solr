<?php

namespace Drupal\search_api_solr\SolrConnector;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Url;
use Drupal\search_api\Plugin\ConfigurablePluginBase;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrConnectorInterface;
use Solarium\Client;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\Request;
use Solarium\Core\Client\Response;
use Solarium\Core\Query\QueryInterface;
use Solarium\Exception\HttpException;
use Solarium\QueryType\Extract\Result as ExtractResult;
use Solarium\QueryType\Update\Query\Query as UpdateQuery;
use Solarium\QueryType\Select\Query\Query;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class for Solr connector plugins.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_solr_connector_info_alter(). The definition includes the
 * following keys:
 * - id: The unique, system-wide identifier of the backend class.
 * - label: The human-readable name of the backend class, translated.
 * - description: A human-readable description for the backend class,
 *   translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SolrConnector(
 *   id = "my_connector",
 *   label = @Translation("My connector"),
 *   description = @Translation("Authenticates with SuperAuth™.")
 * )
 * @endcode
 *
 * @see \Drupal\search_api_solr\Annotation\SolrConnector
 * @see \Drupal\search_api_solr\SolrConnector\SolrConnectorPluginManager
 * @see \Drupal\search_api_solr\SolrConnectorInterface
 * @see plugin_api
 */
abstract class SolrConnectorPluginBase extends ConfigurablePluginBase implements SolrConnectorInterface, PluginFormInterface {

  use PluginFormTrait {
    submitConfigurationForm as traitSubmitConfigurationForm;
  }

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * A connection to the Solr server.
   *
   * @var \Solarium\Client
   */
  protected $solr;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $plugin->eventDispatcher = $container->get('event_dispatcher');

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'scheme' => 'http',
      'host' => 'localhost',
      'port' => 8983,
      'path' => '/',
      'core' => '',
      'timeout' => 5,
      'index_timeout' => 5,
      'optimize_timeout' => 10,
      'finalize_timeout' => 30,
      'solr_version' => '',
      'http_method' => 'AUTO',
      'commit_within' => 1000,
      'jmx' => FALSE,
      'solr_install_dir' => '../../..',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $configuration['port'] = (int) $configuration['port'];
    $configuration['timeout'] = (int) $configuration['timeout'];
    $configuration['index_timeout'] = (int) $configuration['index_timeout'];
    $configuration['optimize_timeout'] = (int) $configuration['optimize_timeout'];
    $configuration['finalize_timeout'] = (int) $configuration['finalize_timeout'];
    $configuration['commit_within'] = (int) $configuration['commit_within'];
    $configuration['jmx'] = (bool) $configuration['jmx'];

    parent::setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['scheme'] = [
      '#type' => 'select',
      '#title' => $this->t('HTTP protocol'),
      '#description' => $this->t('The HTTP protocol to use for sending queries.'),
      '#default_value' => isset($this->configuration['scheme']) ? $this->configuration['scheme'] : 'http',
      '#options' => [
        'http' => 'http',
        'https' => 'https',
      ],
    ];

    $form['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr host'),
      '#description' => $this->t('The host name or IP of your Solr server, e.g. <code>localhost</code> or <code>www.example.com</code>.'),
      '#default_value' => isset($this->configuration['host']) ? $this->configuration['host'] : '',
      '#required' => TRUE,
    ];

    $form['port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr port'),
      '#description' => $this->t('The Jetty example server is at port 8983, while Tomcat uses 8080 by default.'),
      '#default_value' => isset($this->configuration['port']) ? $this->configuration['port'] : '',
      '#required' => TRUE,
    ];

    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr path'),
      '#description' => $this->t('The path that identifies the Solr instance to use on the server.'),
      '#default_value' => isset($this->configuration['path']) ? $this->configuration['path'] : '/',
    ];

    $form['core'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr core'),
      '#description' => $this->t('The name that identifies the Solr core to use on the server.'),
      '#default_value' => isset($this->configuration['core']) ? $this->configuration['core'] : '',
      '#required' => TRUE,
    ];

    $form['timeout'] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 180,
      '#title' => $this->t('Query timeout'),
      '#description' => $this->t('The timeout in seconds for search queries sent to the Solr server.'),
      '#default_value' => isset($this->configuration['timeout']) ? $this->configuration['timeout'] : 5,
      '#required' => TRUE,
    ];

    $form['index_timeout'] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 180,
      '#title' => $this->t('Index timeout'),
      '#description' => $this->t('The timeout in seconds for indexing requests to the Solr server.'),
      '#default_value' => isset($this->configuration['index_timeout']) ? $this->configuration['index_timeout'] : 5,
      '#required' => TRUE,
    ];

    $form['optimize_timeout'] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 180,
      '#title' => $this->t('Optimize timeout'),
      '#description' => $this->t('The timeout in seconds for background index optimization queries on a Solr server.'),
      '#default_value' => isset($this->configuration['optimize_timeout']) ? $this->configuration['optimize_timeout'] : 10,
      '#required' => TRUE,
    ];

    $form['finalize_timeout'] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 180,
      '#title' => $this->t('Finalize timeout'),
      '#description' => $this->t('The timeout in seconds for index finalization queries on a Solr server.'),
      '#default_value' => isset($this->configuration['finalize_timeout']) ? $this->configuration['finalize_timeout'] : 30,
      '#required' => TRUE,
    ];

    $form['commit_within'] = [
      '#type' => 'number',
      '#min' => 0,
      '#title' => $this->t('Commit within'),
      '#description' => $this->t('The limit in milliseconds within a (soft) commit on Solr is forced after any updating the index in any way. Setting the value to "0" turns off this dynamic enforcement and lets Solr behave like configured solrconf.xml.'),
      '#default_value' => isset($this->configuration['commit_within']) ? $this->configuration['commit_within'] : 1000,
      '#required' => TRUE,
    ];

    $form['workarounds'] = [
      '#type' => 'details',
      '#title' => $this->t('Connector Workarounds'),
    ];

    $form['workarounds']['solr_version'] = [
      '#type' => 'select',
      '#title' => $this->t('Solr version override'),
      '#description' => $this->t('Specify the Solr version manually in case it cannot be retrived automatically. The version can be found in the Solr admin interface under "Solr Specification Version" or "solr-spec"'),
      '#options' => [
        '' => $this->t('Determine automatically'),
        '6' => '6.x',
        '7' => '7.x',
        '8' => '8.x',
      ],
      '#default_value' => isset($this->configuration['solr_version']) ? $this->configuration['solr_version'] : '',
    ];

    $form['workarounds']['http_method'] = [
      '#type' => 'select',
      '#title' => $this->t('HTTP method'),
      '#description' => $this->t('The HTTP method to use for sending queries. GET will often fail with larger queries, while POST should not be cached. AUTO will use GET when possible, and POST for queries that are too large.'),
      '#default_value' => isset($this->configuration['http_method']) ? $this->configuration['http_method'] : 'AUTO',
      '#options' => [
        'AUTO' => $this->t('AUTO'),
        'POST' => 'POST',
        'GET' => 'GET',
      ],
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced server configuration'),
    ];

    $form['advanced']['jmx'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable JMX'),
      '#description' => $this->t('Enable JMX based monitoring.'),
      '#default_value' => isset($this->configuration['jmx']) ? $this->configuration['jmx'] : FALSE,
    ];

    $form['advanced']['solr_install_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('solr.install.dir'),
      '#description' => $this->t('The path where Solr is installed on the server, relative to the configuration or absolute. Some examples are "../../.." for Solr downloaded from apache.org, "/opt/solr-EXACT_VERSION_STRING" for the official Solr docker container, "/usr/local/opt/solr/libexec" for installations via homebrew on macOS or "/opt/solr" for some linux distributions. If you use different systems for development, testing and production you can use drupal config overwrites to adjust the value per environment or adjust the generated solrcore.properties per environment or use java virtual machine options to set the property.'),
      '#default_value' => isset($this->configuration['solr_install_dir']) ? $this->configuration['solr_install_dir'] : '../../..',
    ];

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
    if (!empty($values['path']) && strpos($values['path'], '/') !== 0) {
      $form_state->setError($form['path'], $this->t('If provided the path has to start with "/".'));
    }
    if (!empty($values['core']) && strpos($values['core'], '/') === 0) {
      $form_state->setError($form['core'], $this->t('Core or collection must not start with "/".'));
    }

    if (!$form_state->hasAnyErrors()) {
      // Try to orchestrate a server link from form values.
      $solr = new Client(NULL, $this->eventDispatcher);
      $solr->createEndpoint($values + ['key' => 'search_api_solr'], TRUE);
      try {
        $this->getServerLink();
      }
      catch (\InvalidArgumentException $e) {
        foreach (['scheme', 'host', 'port', 'path', 'core'] as $part) {
          $form_state->setError($form[$part], $this->t('The server link generated from the form values is illegal.'));
        }
      }
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
    foreach ($values['workarounds'] as $key => $value) {
      $form_state->setValue($key, $value);
    }
    foreach ($values['advanced'] as $key => $value) {
      $form_state->setValue($key, $value);
    }

    // Clean-up the form to avoid redundant entries in the stored configuration.
    $form_state->unsetValue('workarounds');
    $form_state->unsetValue('advanced');

    $this->traitSubmitConfigurationForm($form, $form_state);
  }

  /**
   * Prepares the connection to the Solr server.
   */
  protected function connect() {
    if (!$this->solr) {
      $this->solr = new Client(NULL, $this->eventDispatcher);
      $this->solr->createEndpoint($this->configuration + ['key' => 'search_api_solr'], TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isCloud() {
    return FALSE;
  }

  /**
   * Returns a the Solr server URI.
   */
  protected function getServerUri() {
    $this->connect();
    $url_path = $this->solr->getEndpoint()->getServerUri();
    if ($this->configuration['host'] === 'localhost' && !empty($_SERVER['SERVER_NAME'])) {
      $url_path = str_replace('localhost', $_SERVER['SERVER_NAME'], $url_path);
    }

    return $url_path;
  }

  /**
   * {@inheritdoc}
   */
  public function getServerLink() {
    $url_path = $this->getServerUri();
    $url = Url::fromUri($url_path);

    return Link::fromTextAndUrl($url_path, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function getCoreLink() {
    $url_path = $this->getServerUri() . 'solr/#/' . $this->configuration['core'];
    $url = Url::fromUri($url_path);

    return Link::fromTextAndUrl($url_path, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrVersion($force_auto_detect = FALSE) {
    // Allow for overrides by the user.
    if (!$force_auto_detect && !empty($this->configuration['solr_version'])) {
      // In most cases the already stored solr_version is just the major version
      // number as integer. In this case we will expand it to the minimum
      // corresponding full version string.
      $min_version = ['0', '0', '0'];
      $version = explode('.', $this->configuration['solr_version']) + $min_version;

      return implode('.', $version);
    }

    $info = [];
    try {
      $info = $this->getCoreInfo();
    }
    catch (\Exception $e) {
      try {
        $info = $this->getServerInfo();
      }
      catch (SearchApiSolrException $e) {
      }
    }

    // Get our solr version number.
    if (isset($info['lucene']['solr-spec-version'])) {
      return $info['lucene']['solr-spec-version'];
    }

    return '0.0.0';
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrMajorVersion($version = '') {
    list($major, ,) = explode('.', $version ?: $this->getSolrVersion());
    return $major;
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrBranch($version = '') {
    return $this->getSolrMajorVersion($version) . '.x';
  }

  /**
   * {@inheritdoc}
   */
  public function getLuceneMatchVersion($version = '') {
    list($major, $minor,) = explode('.', $version ?: $this->getSolrVersion());
    return $major . '.' . $minor;
  }

  /**
   * {@inheritdoc}
   */
  public function getServerInfo($reset = FALSE) {
    return $this->getDataFromHandler('admin/info/system', $reset);
  }

  /**
   * {@inheritdoc}
   */
  public function getCoreInfo($reset = FALSE) {
    return $this->getDataFromHandler($this->configuration['core'] . '/admin/system', $reset);
  }

  /**
   * {@inheritdoc}
   */
  public function getLuke() {
    return $this->getDataFromHandler($this->configuration['core'] . '/admin/luke', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaVersionString($reset = FALSE) {
    return $this->getCoreInfo($reset)['core']['schema'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaVersion($reset = FALSE) {
    $parts = explode('-', $this->getSchemaVersionString($reset));
    return $parts[1];
  }

  /**
   * Gets data from a Solr endpoint using a given handler.
   *
   * @param string $handler
   *   The handler used for the API query.
   * @param bool $reset
   *   If TRUE the server will be asked regardless if a previous call is cached.
   *
   * @return array
   *   Response data with system information.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function getDataFromHandler($handler, $reset = FALSE) {
    static $previous_calls = [];

    $this->connect();

    // We keep the results in a state instead of a cache because we want to
    // access parts of this data even if Solr is temporarily not reachable and
    // caches are cleared.
    $state_key = 'search_api_solr.endpoint.data';
    $state = \Drupal::state();
    $endpoint_data = $state->get($state_key);
    $server_uri = $this->getServerUri();

    if (!isset($previous_calls[$server_uri][$handler]) || !isset($endpoint_data[$server_uri][$handler]) || $reset) {
      // Don't retry multiple times in case of an exception.
      $previous_calls[$server_uri][$handler] = TRUE;

      if (!is_array($endpoint_data) || !isset($endpoint_data[$server_uri][$handler]) || $reset) {
        $query = $this->solr->createApi([
          'handler' => $handler,
          'version' => Request::API_V1,
        ]);
        $endpoint_data[$server_uri][$handler] = $this->execute($query)->getData();
        $state->set($state_key, $endpoint_data);
      }
    }

    return $endpoint_data[$server_uri][$handler];
  }

  /**
   * {@inheritdoc}
   */
  public function pingCore(array $options = []) {
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
      // Don't handle the exception. Just return FALSE below.
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function pingServer() {
    return $this->getServerInfo(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatsSummary() {
    $this->connect();

    $summary = [
      '@pending_docs' => '',
      '@autocommit_time_seconds' => '',
      '@autocommit_time' => '',
      '@deletes_by_id' => '',
      '@deletes_by_query' => '',
      '@deletes_total' => '',
      '@schema_version' => '',
      '@core_name' => '',
      '@index_size' => '',
    ];

    $query = $this->solr->createPing();
    $query->setResponseWriter(Query::WT_PHPS);
    $query->setHandler('admin/mbeans?stats=true');
    $stats = $this->execute($query)->getData();
    if (!empty($stats)) {
      $solr_version = $this->getSolrVersion(TRUE);
      $max_time = -1;
      if (version_compare($solr_version, '7.0', '>=')) {
        $update_handler_stats = $stats['solr-mbeans']['UPDATE']['updateHandler']['stats'];
        $summary['@pending_docs'] = (int) $update_handler_stats['UPDATE.updateHandler.docsPending'];
        if (isset($update_handler_stats['UPDATE.updateHandler.softAutoCommitMaxTime'])) {
          $max_time = (int) $update_handler_stats['UPDATE.updateHandler.softAutoCommitMaxTime'];
        }
        $summary['@deletes_by_id'] = (int) $update_handler_stats['UPDATE.updateHandler.deletesById'];
        $summary['@deletes_by_query'] = (int) $update_handler_stats['UPDATE.updateHandler.deletesByQuery'];
        $summary['@core_name'] = $stats['solr-mbeans']['CORE']['core']['stats']['CORE.coreName'];
        $summary['@index_size'] = $stats['solr-mbeans']['CORE']['core']['stats']['INDEX.size'];
      }
      else {
        $update_handler_stats = $stats['solr-mbeans']['UPDATEHANDLER']['updateHandler']['stats'];
        $summary['@pending_docs'] = (int) $update_handler_stats['docsPending'];
        $max_time = (int) $update_handler_stats['autocommit maxTime'];
        $summary['@deletes_by_id'] = (int) $update_handler_stats['deletesById'];
        $summary['@deletes_by_query'] = (int) $update_handler_stats['deletesByQuery'];
        $summary['@core_name'] = $stats['solr-mbeans']['CORE']['core']['stats']['coreName'];
        if (version_compare($solr_version, '6.4', '>=')) {
          // @see https://issues.apache.org/jira/browse/SOLR-3990
          $summary['@index_size'] = $stats['solr-mbeans']['CORE']['core']['stats']['size'];
        }
        else {
          $summary['@index_size'] = $stats['solr-mbeans']['QUERYHANDLER']['/replication']['stats']['indexSize'];
        }
      }

      $summary['@autocommit_time_seconds'] = $max_time / 1000;
      $summary['@autocommit_time'] = \Drupal::service('date.formatter')->formatInterval($max_time / 1000);
      $summary['@deletes_total'] = $summary['@deletes_by_id'] + $summary['@deletes_by_query'];
      $summary['@schema_version'] = $this->getSchemaVersionString(TRUE);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function coreRestGet($path) {
    return $this->restRequest($this->configuration['core'] . '/' . ltrim($path, '/'));
  }

  /**
   * {@inheritdoc}
   */
  public function coreRestPost($path, $command_json = '') {
    return $this->restRequest($this->configuration['core'] . '/' . ltrim($path, '/'), Request::METHOD_POST, $command_json);
  }

  /**
   * {@inheritdoc}
   */
  public function serverRestGet($path) {
    return $this->restRequest($path);
  }

  /**
   * {@inheritdoc}
   */
  public function serverRestPost($path, $command_json = '') {
    return $this->restRequest($path, Request::METHOD_POST, $command_json);
  }

  /**
   * Sends a REST request to the Solr server endpoint and returns the result.
   *
   * @param string $handler
   *   The handler used for the API query.
   * @param string $method
   *   The HTTP request method.
   * @param string $command_json
   *   The command to send encoded as JSON.
   *
   * @return string
   *   The decoded response.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function restRequest($handler, $method = Request::METHOD_GET, $command_json = '') {
    $this->connect();
    $query = $this->solr->createApi([
      'handler' => $handler,
      'accept' => 'application/json',
      'contenttype' => 'application/json',
      'method' => $method,
      'rawdata' => (Request::METHOD_POST == $method ? $command_json : NULL),
    ]);

    $endpoint = $this->solr->getEndpoint();
    $timeout = $endpoint->getTimeout();
    // @todo Distinguish between different flavors of REST requests and use
    //   different timeout settings.
    $endpoint->setTimeout($this->configuration['optimize_timeout']);
    $response = $this->execute($query);
    $endpoint->setTimeout($timeout);
    $output = $response->getData();
    // \Drupal::logger('search_api_solr')->info(print_r($output, true));.
    if (!empty($output['errors'])) {
      throw new SearchApiSolrException('Error trying to send a REST request.' .
        "\nError message(s):" . print_r($output['errors'], TRUE));
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdateQuery() {
    $this->connect();
    return $this->solr->createUpdate();
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectQuery() {
    $this->connect();
    return $this->solr->createSelect();
  }

  /**
   * {@inheritdoc}
   */
  public function getMoreLikeThisQuery() {
    $this->connect();
    return $this->solr->createMoreLikeThis();
  }

  /**
   * {@inheritdoc}
   */
  public function getTermsQuery() {
    $this->connect();
    return $this->solr->createTerms();
  }

  /**
   * {@inheritdoc}
   */
  public function getSpellcheckQuery() {
    $this->connect();
    return $this->solr->createSpellcheck();
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggesterQuery() {
    $this->connect();
    return $this->solr->createSuggester();
  }

  /**
   * {@inheritdoc}
   */
  public function getAutocompleteQuery() {
    $this->connect();
    $this->solr->registerQueryType('autocomplete', '\Drupal\search_api_solr\Solarium\Autocomplete\Query');
    return $this->solr->createQuery('autocomplete');
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryHelper(QueryInterface $query = NULL) {
    if ($query) {
      return $query->getHelper();
    }

    return \Drupal::service('solarium.query_helper');
  }

  /**
   * {@inheritdoc}
   */
  public function getExtractQuery() {
    $this->connect();
    return $this->solr->createExtract();
  }

  /**
   * Creates a CustomizeRequest object.
   *
   * @return \Solarium\Plugin\CustomizeRequest\CustomizeRequest|\Solarium\Core\Plugin\PluginInterface
   *   The Solarium CustomizeRequest object.
   */
  protected function customizeRequest() {
    $this->connect();
    return $this->solr->getPlugin('customizerequest');
  }

  /**
   * {@inheritdoc}
   */
  public function search(Query $query, ?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    // Use the 'postbigrequest' plugin if no specific http method is
    // configured. The plugin needs to be loaded before the request is
    // created.
    if ($this->configuration['http_method'] === 'AUTO') {
      $this->solr->getPlugin('postbigrequest');
    }

    // Use the manual method of creating a Solarium request so we can control
    // the HTTP method.
    $request = $this->solr->createRequest($query);

    // Set the configured HTTP method.
    if ($this->configuration['http_method'] === 'POST') {
      $request->setMethod(Request::METHOD_POST);
    }
    elseif ($this->configuration['http_method'] === 'GET') {
      $request->setMethod(Request::METHOD_GET);
    }

    return $this->executeRequest($request, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function createSearchResult(QueryInterface $query, Response $response) {
    return $this->solr->createResult($query, $response);
  }

  /**
   * {@inheritdoc}
   */
  public function update(UpdateQuery $query, ?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }
    // The default timeout is set for search queries. The configured timeout
    // might differ and needs to be set now because solarium doesn't
    // distinguish between these types.
    $timeout = $endpoint->getTimeout();
    $endpoint->setTimeout($this->configuration['index_timeout']);
    if ($this->configuration['commit_within']) {
      // Do a commitWithin since that is automatically a softCommit since Solr 4
      // and a delayed hard commit with Solr 3.4+.
      // By default we wait 1 second after the request arrived for solr to parse
      // the commit. This allows us to return to Drupal and let Solr handle what
      // it needs to handle.
      // @see http://wiki.apache.org/solr/NearRealtimeSearch
      /** @var \Solarium\Plugin\CustomizeRequest\CustomizeRequest $request */
      $request = $this->customizeRequest();
      $request->createCustomization('id')
        ->setType('param')
        ->setName('commitWithin')
        ->setValue($this->configuration['commit_within']);
    }

    $result = $this->execute($query, $endpoint);

    // Reset the timeout setting to the default value for search queries.
    $endpoint->setTimeout($timeout);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(QueryInterface $query, ?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    try {
      return $this->solr->execute($query, $endpoint);
    }
    catch (HttpException $e) {
      $this->handleHttpException($e, $endpoint);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeRequest(Request $request, ?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    try {
      return $this->solr->executeRequest($request, $endpoint);
    }
    catch (HttpException $e) {
      $this->handleHttpException($e, $endpoint);
    }
  }

  /**
   * Converts a HttpException in an easier to read SearchApiSolrException.
   *
   * @param \Solarium\Exception\HttpException $e
   *   The HttpException object.
   * @param \Solarium\Core\Client\Endpoint $endpoint
   *   The Solarium endpoint.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function handleHttpException(HttpException $e, ?Endpoint $endpoint) {
    $response_code = $e->getCode();
    switch ($response_code) {
      case 404:
        $description = 'not found';
        break;

      case 401:
      case 403:
        $description = 'access denied';
        break;

      case 500:
        $description = 'internal error. Check your Solr logs for details!';
        break;

      default:
        $description = sprintf('unreachable or returned unexpected response code "%d"', $response_code);
    }
    throw new SearchApiSolrException('Solr endpoint ' . $endpoint->getServerUri() . " $description.", $response_code, $e);
  }

  /**
   * {@inheritdoc}
   */
  public function optimize(?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }
    // The default timeout is set for search queries. The configured timeout
    // might differ and needs to be set now because solarium doesn't
    // distinguish between these types.
    $timeout = $endpoint->getTimeout();
    $endpoint->setTimeout($this->configuration['optimize_timeout']);

    $update_query = $this->solr->createUpdate();
    $update_query->addOptimize(TRUE, FALSE);

    $this->execute($update_query, $endpoint);

    // Reset the timeout setting to the default value for search queries.
    $endpoint->setTimeout($timeout);
  }

  /**
   * {@inheritdoc}
   */
  public function adjustTimeout(int $timeout, ?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }
    $previous_timeout = $this->getTimeout($endpoint);
    $endpoint->setTimeout($timeout);
    return $previous_timeout;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeout(?Endpoint $endpoint = NULL) {
    $this->connect();

    if (!$endpoint) {
      $endpoint = $this->solr->getEndpoint();
    }

    return $endpoint->getTimeout();
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexTimeout() {
    return $this->configuration['index_timeout'];
  }

  /**
   * {@inheritdoc}
   */
  public function getOptimizeTimeout() {
    return $this->configuration['optimize_timeout'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFinalizeTimeout() {
    return $this->configuration['finalize_timeout'];
  }

  /**
   * {@inheritdoc}
   */
  public function extract(QueryInterface $query, ?Endpoint $endpoint = NULL) {
    return $this->execute($query, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentFromExtractResult(ExtractResult $result, $filepath) {
    $array_data = $result->getData();

    if (isset($array_data[basename($filepath)])) {
      return $array_data[basename($filepath)];
    }

    // In most (or every) cases when an error happens we won't reach that point,
    // because a Solr exception is already pased through. Anyway, this exception
    // will be thrown if the solarium library surprises us again. ;-)
    throw new SearchApiSolrException('Unable to find extracted files within the Solr response body.');
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint($key = 'search_api_solr') {
    $this->connect();
    return $this->solr->getEndpoint($key);
  }

  /**
   * {@inheritdoc}
   */
  public function createEndpoint(string $key, array $additional_configuration = []) {
    $this->connect();
    return $this->solr->createEndpoint(['key' => $key] + $additional_configuration + $this->configuration, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getFile($file = NULL) {
    $this->connect();
    $query = $this->solr->createApi([
      'handler' => $this->configuration['core'] . '/admin/file',
    ]);
    if ($file) {
      $query->addParam('file', $file);
    }

    return $this->execute($query)->getResponse();
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    // It's safe to unset the solr client completely before serialization
    // because connect() will set it up again correctly after deserialization.
    unset($this->solr);
    return parent::__sleep();
  }

  /**
   * {@inheritdoc}
   */
  public function alterConfigFiles(array &$files, string $lucene_match_version, string $server_id = '') {
    if (!empty($this->configuration['jmx'])) {
      $files['solrconfig_extra.xml'] .= "<jmx />\n";
    }
    if (!empty($this->configuration['solr_install_dir'])) {
      $files['solrcore.properties'] = preg_replace("/solr\.install\.dir.*$/", 'solr.install.dir=' . $this->configuration['solr_install_dir'], $files['solrcore.properties']);
    }
  }

}
