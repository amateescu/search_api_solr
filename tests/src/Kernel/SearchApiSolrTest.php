<?php

/**
 * @file
 * Contains \Drupal\search_api_solr\Tests\SearchApiSolrTest.
 */

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Drupal\Tests\search_api\Kernel\BackendTestBase;

/**
 * Tests index and search capabilities using the Solr search backend.
 *
 * @group search_api_solr
 */
class SearchApiSolrTest extends BackendTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = array(
    'search_api_solr',
    'search_api_test_solr',
  );

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
   * Whether a Solr core is available for testing. Mostly needed because Drupal
   * testbots do not support this.
   *
   * @var bool
   */
  protected $solrAvailable = FALSE;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installConfig(array('search_api_test_solr'));

    $this->detectSolrAvailability();
  }

  /**
   * Detects the availability of a Solr Server and sets $this->solrAvailable.
   */
  protected function detectSolrAvailability() {
    // Because this is a kernel test, the routing isn't built by default, so
    // we have to force it.
    \Drupal::service('router.builder')->rebuild();

    try {
      $backend = Server::load($this->serverId)->getBackend();
      if ($backend instanceof SearchApiSolrBackend && $backend->ping()) {
        $this->solrAvailable = TRUE;
      }
    }
    catch (\Exception $e) {}
  }

  /**
   * Clear the index after every test.
   */
  public function tearDown() {
    $this->clearIndex();
    parent::tearDown();
  }

  /**
   * Tests various indexing scenarios for the Solr search backend.
   */
  public function testFramework() {
    // Only run the tests if we have a Solr core available.
    if ($this->solrAvailable) {
      parent::testFramework();
    }
    else {
      $this->assertTrue(TRUE, 'Error: The Solr instance could not be found. Please enable a multi-core one on http://localhost:8983/solr/d8');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function indexItems($index_id) {
    $index_status = parent::indexItems($index_id);
    sleep(2);
    return $index_status;
  }

  /**
   * {@inheritdoc}
   */
  protected function clearIndex() {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load($this->indexId);
    $index->clear();
    // Deleting items take at least 1 second for Solr to parse it so that drupal
    // doesn't get timeouts while waiting for Solr. Lets give it 2 seconds to
    // make sure we are in bounds.
    sleep(2);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkServerBackend() {
    // The Solr backend doesn't create any database tables.
  }

  /**
   * {@inheritdoc}
   */
  protected function updateIndex() {
    // The parent assertions don't make sense for the Solr backend.
  }

  /**
   * Second server.
   */
  protected function regressionTestSecondServer() {
    // @todo
  }

  /**
   * Regression tests for #2469547.
   */
  protected function regressionTest2469547() {
    $query = $this->buildSearch();
    $facets = array();
    $facets['body'] = array(
      'field' => 'body',
      'limit' => 0,
      'min_count' => 1,
      'missing' => FALSE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->addCondition('id', 5, '<>');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 4, 'filter' => '"test"'),
      array('count' => 3, 'filter' => '"case"'),
      array('count' => 1, 'filter' => '"bar"'),
      array('count' => 1, 'filter' => '"foobar"'),
    );
    // We can't guarantee the order of returned facets, since "bar" and "foobar"
    // both occur once, so we have to manually sort the returned facets first.
    $facets = $results->getExtraData('search_api_facets', array())['body'];
    usort($facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $facets, 'Correct facets were returned for a fulltext field.');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkModuleUninstall() {
    // See whether clearing the server works.
    // Regression test for #2156151.
    /** @var \Drupal\search_api\ServerInterface $server */
    $server = Server::load($this->serverId);
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load($this->indexId);
    $server->deleteAllIndexItems($index);
    // Deleting items take at least 1 second for Solr to parse it so that drupal
    // doesn't get timeouts while waiting for Solr. Lets give it 2 seconds to
    // make sure we are in bounds.
    sleep(2);
    $query = $this->buildSearch();
    $results = $query->execute();
    $this->assertEquals(0, $results->getResultCount(), 'Clearing the server worked correctly.');
  }

  /**
   * {@inheritdoc}
   */
  protected function assertIgnored(ResultSetInterface $results, array $ignored = array(), $message = 'No keys were ignored.') {
    // Nothing to do here since the Solr backend doesn't keep a list of ignored
    // fields.
  }

}
