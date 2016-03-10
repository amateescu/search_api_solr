<?php

/**
 * @file
 * Contains \Drupal\search_api_solr\Tests\SearchApiSolrTest.
 */

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Drupal\Tests\search_api_db\Kernel\BackendTest;

/**
 * Tests index and search capabilities using the Solr search backend.
 *
 * @group search_api_solr
 */
class SearchApiSolrTest extends BackendTest {

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
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('search_api_solr', 'search_api_test_solr');

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // @todo For some reason the init event (see AutoloaderSubscriber) is not
    //   working in command line tests
    $file_path = drupal_get_path('module', 'search_api_solr') . '/vendor/autoload.php';
    if (!class_exists('Solarium\\Client') && ($file_path != DRUPAL_ROOT . '/core/vendor/autoload.php')) {
      require_once $file_path;
    }

    $this->installConfig(array('search_api_test_solr'));

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
  protected function checkServerTables() {
    // The Solr backend doesn't create any database tables.
  }

  /**
   * {@inheritdoc}
   */
  protected function updateIndex() {
    // The parent assertions don't make sense for the Solr backend.
  }

  /**
   * {@inheritdoc}
   */
  protected function checkMultiValuedInfo() {
    // We don't keep multi-valued (or any other) field information.
  }

  /**
   * {@inheritdoc}
   */
  protected function editServerPartial($enable = TRUE) {
    // There is no "partial matching" option for Solr servers (yet).
  }

  /**
   * {@inheritdoc}
   */
  protected function searchSuccessPartial() {
    // There is no "partial matching" option for Solr servers (yet).
  }

  /**
   * {@inheritdoc}
   */
  protected function editServer() {
    // The parent assertions don't make sense for the Solr backend.
  }

  /**
   * {@inheritdoc}
   */
  protected function searchSuccess2() {
    // This method tests the 'min_chars' option of the Database backend, which
    // we don't have in Solr.
    // @todo Copy tests from the Apachesolr module which create Solr cores on
    // the fly with various schemas.
  }

  /**
   * Tests various previously fixed bugs, mostly from the Database backend.
   *
   * Needs to be overridden here since some of the tests don't apply.
   */
  protected function regressionTests() {
    // Regression tests for #2007872.
    $results = $this->buildSearch('test')
      ->sort('id', QueryInterface::SORT_ASC)
      ->sort('type', QueryInterface::SORT_ASC)
      ->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Sorting on field with NULLs returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 3, 4)), array_keys($results->getResultItems()), 'Sorting on field with NULLs returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('id', 3);
    $conditions->addCondition('type', 'article');
    $query->addConditionGroup($conditions);
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(3, $results->getResultCount(), 'OR filter on field with NULLs returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(3, 4, 5)), array_keys($results->getResultItems()), 'OR filter on field with NULLs returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression tests for #1863672.
    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('keywords', 'orange');
    $conditions->addCondition('keywords', 'apple');
    $query->addConditionGroup($conditions);
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(4, $results->getResultCount(), 'OR filter on multi-valued field returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 4, 5)), array_keys($results->getResultItems()), 'OR filter on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('keywords', 'orange');
    $conditions->addCondition('keywords', 'strawberry');
    $query->addConditionGroup($conditions);
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('keywords', 'apple');
    $conditions->addCondition('keywords', 'grape');
    $query->addConditionGroup($conditions);
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(3, $results->getResultCount(), 'Multiple OR filters on multi-valued field returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(2, 4, 5)), array_keys($results->getResultItems()), 'Multiple OR filters on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $conditions1 = $query->createConditionGroup('OR');
    $conditions = $query->createConditionGroup('AND');
    $conditions->addCondition('keywords', 'orange');
    $conditions->addCondition('keywords', 'apple');
    $conditions1->addConditionGroup($conditions);
    $conditions = $query->createConditionGroup('AND');
    $conditions->addCondition('keywords', 'strawberry');
    $conditions->addCondition('keywords', 'grape');
    $conditions1->addConditionGroup($conditions);
    $query->addConditionGroup($conditions1);
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(3, $results->getResultCount(), 'Complex nested filters on multi-valued field returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(2, 4, 5)), array_keys($results->getResultItems()), 'Complex nested filters on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression tests for #2040543.
    $query = $this->buildSearch();
    $facets['category'] = array(
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article_category"'),
      array('count' => 2, 'filter' => '"item_category"'),
      array('count' => 1, 'filter' => '!'),
    );
    $type_facets = $results->getExtraData('search_api_facets')['category'];
    usort($type_facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $type_facets, 'Correct facets were returned');

    $query = $this->buildSearch();
    $facets['category']['missing'] = FALSE;
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article_category"'),
      array('count' => 2, 'filter' => '"item_category"'),
    );
    $type_facets = $results->getExtraData('search_api_facets')['category'];
    usort($type_facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $type_facets, 'Correct facets were returned');

    // Regression tests for #2111753.
    $keys = array(
      '#conjunction' => 'OR',
      'foo',
      'test',
    );
    $query = $this->buildSearch($keys, array(), array('name'));
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(3, $results->getResultCount(), 'OR keywords returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 4)), array_keys($results->getResultItems()), 'OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch($keys, array(), array('name', 'body'));
    $query->range(0, 0);
    $results = $query->execute();
    $this->assertEquals(5, $results->getResultCount(), 'Multi-field OR keywords returned correct number of results.');
    $this->assertFalse($results->getResultItems(), 'Multi-field OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'OR',
      'foo',
      'test',
      array(
        '#conjunction' => 'AND',
        'bar',
        'baz',
      ),
    );
    $query = $this->buildSearch($keys, array(), array('name'));
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Nested OR keywords returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 4, 5)), array_keys($results->getResultItems()), 'Nested OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'OR',
      array(
        '#conjunction' => 'AND',
        'foo',
        'test',
      ),
      array(
        '#conjunction' => 'AND',
        'bar',
        'baz',
      ),
    );
    $query = $this->buildSearch($keys, array(), array('name', 'body'));
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Nested multi-field OR keywords returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 4, 5)), array_keys($results->getResultItems()), 'Nested multi-field OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression tests for #2127001.
    $keys = array(
      '#conjunction' => 'AND',
      '#negation' => TRUE,
      'foo',
      'bar',
    );
    $results = $this->buildSearch($keys)
      ->sort('search_api_id', QueryInterface::SORT_ASC)
      ->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Negated AND fulltext search returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(3, 4)), array_keys($results->getResultItems()), 'Negated AND fulltext search returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'OR',
      '#negation' => TRUE,
      'foo',
      'baz',
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Negated OR fulltext search returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(3)), array_keys($results->getResultItems()), 'Negated OR fulltext search returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'AND',
        '#negation' => TRUE,
        'foo',
        'bar',
      ),
    );
    $results = $this->buildSearch($keys)
      ->sort('search_api_id', QueryInterface::SORT_ASC)
      ->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Nested NOT AND fulltext search returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(3, 4)), array_keys($results->getResultItems()), 'Nested NOT AND fulltext search returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression tests for #2136409.
    $query = $this->buildSearch();
    $query->addCondition('category', NULL);
    $query->sort('search_api_id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'NULL filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(3)), array_keys($results->getResultItems()), 'NULL filter returned correct result.');

    $query = $this->buildSearch();
    $query->addCondition('category', NULL, '<>');
    $query->sort('search_api_id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(4, $results->getResultCount(), 'NOT NULL filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 4, 5)), array_keys($results->getResultItems()), 'NOT NULL filter returned correct result.');

    // Regression tests for #1658964.
    $query = $this->buildSearch();
    $facets['type'] = array(
      'field' => 'type',
      'limit' => 0,
      'min_count' => 0,
      'missing' => TRUE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->addCondition('type', 'article');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article"'),
      array('count' => 0, 'filter' => '!'),
      array('count' => 0, 'filter' => '"item"'),
    );
    $facets = $results->getExtraData('search_api_facets', array())['type'];
    usort($facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $facets, 'Correct facets were returned');

    // Regression tests for #2469547.
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

    // Regression tests for #1403916.
    $query = $this->buildSearch('test foo');
    $facets = array();
    $facets['type'] = array(
      'field' => 'type',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"item"'),
      array('count' => 1, 'filter' => '"article"'),
    );
    $facets = $results->getExtraData('search_api_facets', array())['type'];
    usort($facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $facets, 'Correct facets were returned');

    // Regression tests for #2557291.
    $results = $this->buildSearch('smile' . json_decode('"\u1F601"'))
      ->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for keywords with umlauts returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1)), array_keys($results->getResultItems()), 'Search for keywords with umlauts returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch()
      ->addCondition('keywords', 'grape', '<>')
      ->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Negated filter on multi-valued field returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 3)), array_keys($results->getResultItems()), 'Negated filter on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
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
