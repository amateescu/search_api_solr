<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Drupal\search_api_solr\SolrBackendInterface;
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
    'search_api_solr_test',
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
   * Whether a Solr core is available for testing.
   *
   * Drupal testbots do not support having a solr server, so they can't execute
   * these tests.
   *
   * @var bool
   */
  protected $solrAvailable = FALSE;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installConfig(array('search_api_solr_test'));

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
      if ($backend instanceof SearchApiSolrBackend && $backend->getSolrHelper()->pingCore()) {
        $this->solrAvailable = TRUE;
      }
    }
    catch (\Exception $e) {
    }
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
    if ($this->solrAvailable) {
      /** @var \Drupal\search_api\IndexInterface $index */
      $index = Index::load($this->indexId);
      $index->clear();
      // Deleting items take at least 1 second for Solr to parse it so that drupal
      // doesn't get timeouts while waiting for Solr. Lets give it 2 seconds to
      // make sure we are in bounds.
      sleep(2);
    }
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
  protected function checkSecondServer() {
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

  /**
   * Calls protected/private method of a class.
   *
   * @param object &$object
   *   Instantiated object that we will run method on.
   * @param string
   *  Method name to call.
   * @param array $parameters
   *   Array of parameters to pass into method.
   *
   * @return mixed
   *   Method return.
   */
  protected function invokeMethod(&$object, $methodName, array $parameters = []) {
    $reflection = new \ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);

    return $method->invokeArgs($object, $parameters);
  }

  /**
   * Gets the Drupal Fields and their Solr mapping.
   *
   * @param \Drupal\search_api_solr\SolrBackendInterface $backend
   *    The backend the mapping is used for.
   *
   * @return array
   *   [$fields, $mapping]
   */
  protected function getFieldsAndMapping(SolrBackendInterface $backend) {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load($this->indexId);
    $fields = $index->getFields();
    $fields += $this->invokeMethod($backend, 'getSpecialFields', [$index]);
    $field_info = array(
      'type' => 'string',
      'original type' => 'string',
    );
    $fields['x'] = Utility::createField($index, 'x', $field_info);
    $fields['y'] = Utility::createField($index, 'y', $field_info);
    $fields['z'] = Utility::createField($index, 'z', $field_info);

    $mapping = $backend->getSolrFieldNames($index) + [
      'x' => 'solr_x',
      'y' => 'solr_y',
      'z' => 'solr_z',
    ];

    return [$fields, $mapping];
  }

  /**
   * Tests the conversion of Search API queries into Solr queries.
   */
  public function testQueryConditions() {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = Server::load($this->serverId)->getBackend();
    list($fields, $mapping) = $this->getFieldsAndMapping($backend);

    $query = $this->buildSearch();
    $query->addCondition('x', 5, '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields]);
    $this->assertEquals('solr_x:"5"', $fq[0]);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->addCondition('x', 5, '<>');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields]);
    $this->assertEquals('-solr_x:"5"', $fq[0]);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->addCondition('x', 3, '<>');
    $query->addCondition('x', 5, '<>');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields]);
    $this->assertEquals('-solr_x:"3"', $fq[0]);
    $this->assertEquals('-solr_x:"5"', $fq[1]);

    $query = $this->buildSearch();
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('x', 3, '<>');
    $condition_group->addCondition('x', 5, '<>');
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields]);
    $this->assertEquals('(-solr_x:"3" -solr_x:"5")', $fq[0]);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('x', 5, '<>');
    $condition_group->addCondition('y', 3);
    $condition_group->addCondition('z', 7);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields]);
    $this->assertEquals('(-solr_x:"5" +solr_y:"3" +solr_z:"7")', $fq[0]);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $condition_group = $query->createConditionGroup();
    $inner_condition_group = $query->createConditionGroup('OR');
    $condition_group->addCondition('x', 5, '<>');
    $inner_condition_group->addCondition('y', 3);
    $inner_condition_group->addCondition('z', 7);
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields]);
    $this->assertEquals('(-solr_x:"5" +(solr_y:"3" solr_z:"7"))', $fq[0]);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $condition_group = $query->createConditionGroup();
    $inner_condition_group_or = $query->createConditionGroup('OR');
    $inner_condition_group_or->addCondition('x', 3);
    $inner_condition_group_or->addCondition('y', 7, '<>');
    $inner_condition_group_and = $query->createConditionGroup();
    $inner_condition_group_and->addCondition('x', 1);
    $inner_condition_group_and->addCondition('y', 2, '<>');
    $inner_condition_group_and->addCondition('z', 5, '<');
    $condition_group->addConditionGroup($inner_condition_group_or);
    $condition_group->addConditionGroup($inner_condition_group_and);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields]);
    $this->assertEquals('(+(solr_x:"3" (-solr_y:"7")) +(+solr_x:"1" -solr_y:"2" +solr_z:{* TO "5"}))', $fq[0]);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('x', 5);
    $condition_group->addCondition('y', [1, 2 ,3], 'NOT IN');
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields]);
    $this->assertEquals('(+solr_x:"5" +(*:* -solr_y:"1" -solr_y:"2" -solr_y:"3"))', $fq[0]);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('x', 5);
    $inner_condition_group = $query->createConditionGroup();
    $inner_condition_group->addCondition('y', [1, 2 ,3], 'NOT IN');
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields]);
    $this->assertEquals('(+solr_x:"5" +(*:* -solr_y:"1" -solr_y:"2" -solr_y:"3"))', $fq[0]);
    $this->assertFalse(isset($fq[1]));
  }

  /**
   * Tests the conversion of language aware queries into Solr queries.
   */
  public function testQueryConditionsAndLanguageFilter() {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = Server::load($this->serverId)->getBackend();
    list($fields, $mapping) = $this->getFieldsAndMapping($backend);

    $query = $this->buildSearch();
    $query->setLanguages(['en']);
    $query->addCondition('x', 5, '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields]);
    $this->assertEquals('solr_x:"5"', $fq[0]);
    $this->assertEquals('sm_search_api_language:"en"', $fq[1]);

    $query = $this->buildSearch();
    $query->setLanguages(['en', 'de']);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('x', 5);
    $inner_condition_group = $query->createConditionGroup();
    $inner_condition_group->addCondition('y', [1, 2, 3], 'NOT IN');
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields]);
    $this->assertEquals('(+solr_x:"5" +(*:* -solr_y:"1" -solr_y:"2" -solr_y:"3"))', $fq[0]);
    $this->assertEquals('(sm_search_api_language:"en" sm_search_api_language:"de")', $fq[1]);
  }

}
