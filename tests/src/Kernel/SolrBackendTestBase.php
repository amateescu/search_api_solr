<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\search_api\Entity\Index;
use Drupal\search_api_solr_test\Logger\InMemoryLogger;
use Drupal\Tests\search_api\Kernel\BackendTestBase;

/**
 * Tests location searches and distance facets using the Solr search backend.
 *
 * @group search_api_solr
 */
abstract class SolrBackendTestBase extends BackendTestBase {

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
   * Seconds to wait for a soft commit on Solr.
   *
   * @var int
   */
  protected $waitForCommit = 2;

  /**
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
   *
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
    sleep($this->waitForCommit);
    return $index_status;
  }

  /**
   * {@inheritdoc}
   */
  protected function clearIndex() {
    $index = Index::load($this->indexId);
    $index->clear();
    // Deleting items take at least 1 second for Solr to parse it so that
    // drupal doesn't get timeouts while waiting for Solr. Lets give it some
    // seconds to make sure we are in bounds.
    sleep($this->waitForCommit);
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
    sleep($this->waitForCommit);
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

}
