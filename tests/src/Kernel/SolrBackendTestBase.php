<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_solr_test\Logger\InMemoryLogger;
use Drupal\Tests\search_api\Kernel\BackendTestBase;
use Drupal\search_api_solr\Utility\SolrCommitTrait;

/**
 * Tests location searches and distance facets using the Solr search backend.
 *
 * @group search_api_solr
 */
abstract class SolrBackendTestBase extends BackendTestBase {

  use SolrCommitTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'search_api_solr',
  ];

  /**
   * A Search API server ID.
   *
   * @var string
   */
  protected $serverId = 'solr_search_server';

  /**
   * A Search API index ID.
   *
   * @var string
   */
  protected $indexId = 'solr_search_index';

  /**
   * The in-memory logger.
   *
   * @var \Drupal\search_api_solr_test\Logger\InMemoryLogger
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installConfigs();
    $this->commonSolrBackendSetUp();

    $this->logger = new InMemoryLogger();
    \Drupal::service('logger.factory')->addLogger($this->logger);
  }

  /**
   * Called by setUp() to install required configs.
   */
  protected function installConfigs() {
    $this->installConfig([
      'search_api_solr',
    ]);
  }

  /**
   * Required parts of the setUp() function that are the same for all backends.
   */
  protected function commonSolrBackendSetUp() {}

  /**
   * Clear the index after every test.
   */
  public function tearDown() {
    $this->clearIndex();
    parent::tearDown();
  }

  /**
   * Tests the last logged level and message.
   */
  protected function assertLogMessage($level, $message) {
    $last_message = $this->logger->getLastMessage();
    $this->assertEquals($level, $last_message['level']);
    $this->assertEquals($message, $last_message['message']);
  }

  /**
   * {@inheritdoc}
   */
  protected function indexItems($index_id) {
    $index_status = parent::indexItems($index_id);
    $index = Index::load($index_id);
    $this->ensureCommit($index->getServerInstance());
    return $index_status;
  }

  /**
   * {@inheritdoc}
   */
  protected function clearIndex() {
    $index = Index::load($this->indexId);
    $index->clear();
    $this->ensureCommit($index->getServerInstance());
  }

  /**
   * Executes a query and skips search_api post processing of results.
   *
   * A light weight alternative to $query->execute() if we don't want to get
   * heavy weight search_api results here, but more or less raw solr results.
   * The data as it is returned by Solr could be accessed by calling
   * getExtraData('search_api_solr_response') on the result set returned here.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to be executed.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   The results of the search.
   */
  protected function executeQueryWithoutPostProcessing(QueryInterface $query) {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load($this->indexId);

    $query->preExecute();
    return $index->getServerInstance()->search($query);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkIndexWithoutFields() {
    $index = parent::checkIndexWithoutFields();
    $index->clear();
    $this->ensureCommit($index->getServerInstance());
  }

  /**
   * {@inheritdoc}
   */
  protected function checkServerBackend() {}

  /**
   * {@inheritdoc}
   */
  protected function updateIndex() {}

  /**
   * {@inheritdoc}
   */
  protected function checkSecondServer() {}

  /**
   * {@inheritdoc}
   */
  protected function checkModuleUninstall() {}

  /**
   * Gets the Solr version.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getSolrVersion() {
    static $solr_version = FALSE;

    if (!$solr_version) {
      /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
      $backend = Server::load($this->serverId)->getBackend();
      $connector = $backend->getSolrConnector();
      $solr_version = $connector->getSolrVersion();
    }

    return $solr_version;
  }

}
