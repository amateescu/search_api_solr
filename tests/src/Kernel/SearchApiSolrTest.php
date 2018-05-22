<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api_autocomplete\Entity\Search;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\Tests\search_api_solr\Traits\InvokeMethodTrait;
use Drupal\search_api_solr\Utility\SolrCommitTrait;
use Drupal\user\Entity\User;

/**
 * Tests index and search capabilities using the Solr search backend.
 *
 * @group search_api_solr
 */
class SearchApiSolrTest extends SolrBackendTestBase {

  use SolrCommitTrait;
  use InvokeMethodTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'search_api_autocomplete',
    'search_api_solr_test',
    'user',
  ];

  /**
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * {@inheritdoc}
   */
  protected function installConfigs() {
    parent::installConfigs();

    $this->installConfig(['search_api_solr_test']);
  }

  /**
   *
   */
  protected function commonSolrBackendSetUp() {
    parent::commonSolrBackendSetUp();

    $this->installEntitySchema('user');
    $this->fieldsHelper = \Drupal::getContainer()->get('search_api.fields_helper');
  }

  /**
   * {@inheritdoc}
   */
  protected function backendSpecificRegressionTests() {
    $this->regressionTest2888629();
    $this->regressionTest2850160();
  }

  /**
   * Regression tests for #2469547.
   */
  protected function regressionTest2469547() {
    $query = $this->buildSearch();
    $facets = [];
    $facets['body'] = [
      'field' => 'body',
      'limit' => 0,
      'min_count' => 1,
      'missing' => FALSE,
    ];
    $query->setOption('search_api_facets', $facets);
    $query->addCondition('id', 5, '<>');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = $this->getExpectedFacetsOfRegressionTest2469547();
    // We can't guarantee the order of returned facets, since "bar" and "foobar"
    // both occur once, so we have to manually sort the returned facets first.
    $facets = $results->getExtraData('search_api_facets', [])['body'];
    usort($facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $facets, 'Correct facets were returned for a fulltext field.');
  }

  /**
   * Regression tests for #2888629.
   */
  protected function regressionTest2888629() {
    $query = $this->buildSearch();
    $query->addCondition('category', NULL);
    $results = $query->execute();
    $this->assertResults([3], $results, 'comparing against NULL');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('category', 'article_category', '<>');
    $conditions->addCondition('category', NULL);
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults([1, 2, 3], $results, 'group comparing against category NOT article_category OR category NULL');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('AND');
    $conditions->addCondition('body', NULL, '<>');
    $conditions->addCondition('category', 'article_category', '<>');
    $conditions->addCondition('category', NULL, '<>');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults([1, 2], $results, 'group comparing against body NOT NULL AND category NOT article_category AND category NOT NULL');
  }

  /**
   * Regression tests for #2850160.
   */
  public function regressionTest2850160() {
    $backend = Server::load($this->serverId)->getBackend();
    $index = $this->getIndex();

    // Load existing test entity.
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev_changed')->load(1);

    // Prepare Search API item.
    $id = Utility::createCombinedId('entity:entity_test_mulrev_changed', $entity->id());
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    $item = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createItemFromObject($index, $entity->getTypedData(), $id);
    $item->setBoost('3.0');

    // Get Solr document.
    /** @var \Solarium\QueryType\Update\Query\Document\Document $document */
    $document = $this->invokeMethod($backend, 'getDocument', [$index, $item]);

    // Compare boost values.
    $this->assertEquals($item->getBoost(), $document->getBoost());
  }

  public function searchSuccess() {
    parent::searchSuccess();

    $parse_mode_manager = \Drupal::service('plugin.manager.search_api.parse_mode');
    $parse_mode_direct = $parse_mode_manager->createInstance('direct');

    $results = $this->buildSearch('+test +case', [], ['body'])
      ->setParseMode($parse_mode_direct)
      ->execute();
    $this->assertResults([1, 2, 3], $results, 'Parse mode direct with AND');

    $results = $this->buildSearch('test -case', [], ['body'])
      ->setParseMode($parse_mode_direct)
      ->execute();
    $this->assertResults([4], $results, 'Parse mode direct with NOT');

    $results = $this->buildSearch('"test case"', [], ['body'])
      ->setParseMode($parse_mode_direct)
      ->execute();
    $this->assertResults([1, 2], $results, 'Parse mode direct with phrase');
  }

  /**
   * Return the expected facets for regression test 2469547.
   *
   * The facets differ for Solr backends because of case-insensitive filters.
   *
   * @return array
   */
  protected function getExpectedFacetsOfRegressionTest2469547() {
    return [
      ['count' => 4, 'filter' => '"test"'],
      ['count' => 3, 'filter' => '"case"'],
      ['count' => 1, 'filter' => '"bar"'],
      ['count' => 1, 'filter' => '"foobar"'],
    ];
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
    $this->ensureCommit($server);
    $query = $this->buildSearch();
    $results = $query->execute();
    $this->assertEquals(0, $results->getResultCount(), 'Clearing the server worked correctly.');
  }

  /**
   * {@inheritdoc}
   */
  protected function assertIgnored(ResultSetInterface $results, array $ignored = [], $message = 'No keys were ignored.') {
    // Nothing to do here since the Solr backend doesn't keep a list of ignored
    // fields.
  }

  /**
   * Gets the Drupal Fields and their Solr mapping.
   *
   * @param \Drupal\search_api_solr\SolrBackendInterface $backend
   *   The backend the mapping is used for.
   *
   * @return array
   *   [$fields, $mapping]
   */
  protected function getFieldsAndMapping(SolrBackendInterface $backend) {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load($this->indexId);
    $fields = $index->getFields();
    $fields += $this->invokeMethod($backend, 'getSpecialFields', [$index]);
    $field_info = [
      'type' => 'string',
      'original type' => 'string',
    ];
    $fields['x'] = $this->fieldsHelper->createField($index, 'x', $field_info);
    $fields['y'] = $this->fieldsHelper->createField($index, 'y', $field_info);
    $fields['z'] = $this->fieldsHelper->createField($index, 'z', $field_info);

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
    $options = [];

    $query = $this->buildSearch();
    $query->addCondition('x', 5, '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('solr_x:"5"', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->addCondition('x', 5, '<>');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('(*:* -solr_x:"5")', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->addCondition('x', 3, '<>');
    $query->addCondition('x', 5, '<>');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('(*:* -solr_x:"3")', $fq[0]['query']);
    $this->assertEquals('(*:* -solr_x:"5")', $fq[1]['query']);

    $query = $this->buildSearch();
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('x', 3, '<>');
    $condition_group->addCondition('x', 5, '<>');
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('(+(*:* -solr_x:"3") +(*:* -solr_x:"5"))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('x', 5, '<>');
    $condition_group->addCondition('y', 3);
    $condition_group->addCondition('z', 7);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('(+(*:* -solr_x:"5") +solr_y:"3" +solr_z:"7")', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $condition_group = $query->createConditionGroup();
    $inner_condition_group = $query->createConditionGroup('OR');
    $condition_group->addCondition('x', 5, '<>');
    $inner_condition_group->addCondition('y', 3);
    $inner_condition_group->addCondition('z', 7);
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('(+(*:* -solr_x:"5") +(solr_y:"3" solr_z:"7"))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    // Condition groups with null value queries are special snowflakes.
    // @see https://www.drupal.org/node/2888629
    $query = $this->buildSearch();
    $condition_group = $query->createConditionGroup();
    $inner_condition_group = $query->createConditionGroup('OR');
    $condition_group->addCondition('x', 5, '<>');
    $inner_condition_group->addCondition('y', 3);
    $inner_condition_group->addCondition('z', NULL);
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('(+(*:* -solr_x:"5") +(solr_y:"3" (*:* -solr_z:[* TO *])))', $fq[0]['query']);
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
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('(+(solr_x:"3" (*:* -solr_y:"7")) +(+solr_x:"1" +(*:* -solr_y:"2") +solr_z:{* TO "5"}))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('x', 5);
    $condition_group->addCondition('y', [1, 2, 3], 'NOT IN');
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('(+solr_x:"5" +(*:* -solr_y:"1" -solr_y:"2" -solr_y:"3"))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('x', 5);
    $inner_condition_group = $query->createConditionGroup();
    $inner_condition_group->addCondition('y', [1, 2, 3], 'NOT IN');
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('(+solr_x:"5" +(*:* -solr_y:"1" -solr_y:"2" -solr_y:"3"))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    // Test tagging of a single filter query of a facet query.
    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR', ['facet:' . 'tagtosearchfor']);
    $conditions->addCondition('category', 'article_category');
    $query->addConditionGroup($conditions);
    $conditions = $query->createConditionGroup('AND');
    $conditions->addCondition('category', NULL, '<>');
    $query->addConditionGroup($conditions);
    $facets['category'] = [
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'or',
    ];
    $query->setOption('search_api_facets', $facets);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('ss_category:"article_category"', $fq[0]['query'], 'Condition found in tagged first filter query');
    $this->assertEquals(['facet:tagtosearchfor' => 'facet:tagtosearchfor'], $fq[0]['tags'], 'Tag found in tagged first filter query');
    $this->assertEquals('ss_category:[* TO *]', $fq[1]['query'], 'Condition found in unrelated second filter query');
    $this->assertEquals([], $fq[1]['tags'], 'No tag found in second filter query');

    // @see https://www.drupal.org/node/2753917
    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR', ['facet:x']);
    $conditions->addCondition('x', 'A');
    $conditions->addCondition('x', 'B');
    $query->addConditionGroup($conditions);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals(1, count($fq));
    $this->assertEquals(['facet:x' => 'facet:x'], $fq[0]['tags']);
    $this->assertEquals('(solr_x:"A" solr_x:"B")', $fq[0]['query']);

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('AND', ['facet:x']);
    $conditions->addCondition('x', 'A');
    $conditions->addCondition('x', 'B');
    $query->addConditionGroup($conditions);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals(1, count($fq));
    $this->assertEquals(['facet:x' => 'facet:x'], $fq[0]['tags']);
    $this->assertEquals('(+solr_x:"A" +solr_x:"B")', $fq[0]['query']);
  }

  /**
   * Tests the conversion of language aware queries into Solr queries.
   */
  public function testQueryConditionsAndLanguageFilter() {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = Server::load($this->serverId)->getBackend();
    list($fields, $mapping) = $this->getFieldsAndMapping($backend);
    $options = [];

    $query = $this->buildSearch();
    $query->setLanguages(['en']);
    $query->addCondition('x', 5, '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('solr_x:"5"', $fq[0]['query']);
    $this->assertEquals('ss_search_api_language:"en"', $fq[1]['query']);

    $query = $this->buildSearch();
    $query->setLanguages(['en', 'de']);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('x', 5);
    $inner_condition_group = $query->createConditionGroup();
    $inner_condition_group->addCondition('y', [1, 2, 3], 'NOT IN');
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('(+solr_x:"5" +(*:* -solr_y:"1" -solr_y:"2" -solr_y:"3"))', $fq[0]['query']);
    $this->assertEquals('(ss_search_api_language:"en" ss_search_api_language:"de")', $fq[1]['query']);
  }

  /**
   * Tests highlight options.
   */
  public function testHighlight() {
    $config = $this->getIndex()->getServerInstance()->getBackendConfig();

    $this->insertExampleContent();
    $this->indexItems($this->indexId);

    $config['retrieve_data'] = TRUE;
    $config['highlight_data'] = TRUE;
    $query = $this->buildSearch('foobar');
    $query->getIndex()->getServerInstance()->setBackendConfig($config);
    $results = $query->execute();

    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $this->assertContains('<strong>foobar</strong>', (string) $result->getExtraData('highlighted_fields', ['body' => ['']])['body'][0]);
    }

    $config['highlight_data'] = FALSE;
    $query = $this->buildSearch('foobar');
    $query->getIndex()->getServerInstance()->setBackendConfig($config);
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $this->assertEmpty($result->getExtraData('highlighted_fields', []));
    }
  }

  /**
   * Test that basic auth config gets passed to Solarium.
   */
  public function testBasicAuth() {
    $server = $this->getServer();
    $config = $server->getBackendConfig();
    $config['connector_config']['username'] = 'foo';
    $config['connector_config']['password'] = 'bar';
    $server->setBackendConfig($config);
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $server->getBackend();
    $auth = $backend->getSolrConnector()->getEndpoint()->getAuthentication();
    $this->assertEquals(['username' => 'foo', 'password' => 'bar'], $auth);
  }

  /**
   * Tests addition and deletion of a data source.
   */
  public function testDatasourceAdditionAndDeletion() {
    $this->insertExampleContent();
    $this->indexItems($this->indexId);

    $results = $this->buildSearch()->execute();
    $this->assertEquals(5, $results->getResultCount(), 'Number of indexed entities is correct.');

    try {
      $results = $this->buildSearch()->addCondition('uid', 0, '>')->execute();
      $this->fail('Field uid must not yet exists in this index.');
    }
    catch (\Exception $e) {
      $this->assertEquals('Filter term on unknown or unindexed field uid.', $e->getMessage());
    }

    $index = $this->getIndex();
    $index->set('datasource_settings', $index->get('datasource_settings') + [
      'entity:user' => [],
    ]);
    $info = [
      'label' => 'uid',
      'type' => 'integer',
      'datasource_id' => 'entity:user',
      'property_path' => 'uid',
    ];
    $index->addField($this->fieldsHelper->createField($index, 'uid', $info));
    $index->save();

    User::create([
      'uid' => 1,
      'name' => 'root',
      'langcode' => 'en',
    ])->save();

    $this->indexItems($this->indexId);

    $results = $this->buildSearch()->execute();
    $this->assertEquals(6, $results->getResultCount(), 'Number of indexed entities in multi datasource index is correct.');

    $results = $this->buildSearch()->addCondition('uid', 0, '>')->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for users returned correct number of results.');

    $index = $this->getIndex();
    $index->removeDatasource('entity:user')->save();

    $this->ensureCommit($index->getServerInstance());

    $results = $this->buildSearch()->execute();
    $this->assertEquals(5, $results->getResultCount(), 'Number of indexed entities is correct.');

    try {
      $results = $this->buildSearch()->addCondition('uid', 0, '>')->execute();
      $this->fail('Field uid must not yet exists in this index.');
    }
    catch (\Exception $e) {
      $this->assertEquals('Filter term on unknown or unindexed field uid.', $e->getMessage());
    }
  }

  /**
   * Produces a string of given comprising diverse chars.
   *
   * @param int $length
   *   Length of the string.
   *
   * @return string
   */
  protected function getLongText($length) {
    $sequence = 'abcdefghijklmnopqrstuwxyz1234567890,./;\'[]\\<>?:"{}|~!@#$%^&*()_+`1234567890-=ööążźćęółńABCDEFGHIJKLMNOPQRSTUWXYZ';
    $result = '';
    $i = 0;

    $sequenceLength = strlen($sequence);
    while ($i++ != $length) {
      $result .= $sequence[$i % $sequenceLength];
    }

    return $result;
  }

  /**
   * Tests search result sorts.
   */
  public function testSearchResultSorts() {
    $this->insertExampleContent();

    // Add node with body length just above the solr limit for search fields.
    // It's exceeded by just a single char to simulate an edge case.
    $this->addTestEntity(6, [
      'name' => 'Long text',
      'body' => $this->getLongText(32767),
      'type' => 'article',
    ]);

    // Add another node with body length equal to the limit.
    $this->addTestEntity(7, [
      'name' => 'Z long',
      'body' => $this->getLongText(32766),
      'type' => 'article',
    ]);

    $this->indexItems($this->indexId);

    // Type text.
    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('name')
      // Force an expected order for identical names.
      ->sort('search_api_id')
      ->execute();
    $this->assertResults([3, 5, 1, 4, 2, 6, 7], $results, 'Sort by name.');

    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('name', QueryInterface::SORT_DESC)
      // Force an expected order for identical names.
      ->sort('search_api_id')
      ->execute();
    $this->assertResults([7, 6, 2, 4, 1, 5, 3], $results, 'Sort by name descending.');

    // Type string.
    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('type')
      // Force an expected order for identical types.
      ->sort('search_api_id')
      ->execute();
    $this->assertResults([4, 5, 6, 7, 1, 2, 3], $results, 'Sort by type.');

    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('type', QueryInterface::SORT_DESC)
      // Force an expected order for identical types.
      ->sort('search_api_id')
      ->execute();
    $this->assertResults([1, 2, 3, 4, 5, 6, 7], $results, 'Sort by type descending.');

    // Type multi-value string. Uses first value.
    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('keywords')
      // Force an expected order for identical keywords.
      ->sort('search_api_id')
      ->execute();
    $this->assertResults([3, 6, 7, 4, 1, 2, 5], $results, 'Sort by keywords.');

    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('keywords', QueryInterface::SORT_DESC)
      // Force an expected order for identical keywords.
      ->sort('search_api_id')
      ->execute();
    $this->assertResults([1, 2, 5, 4, 3, 6, 7], $results, 'Sort by keywords descending.');

    // Type decimal.
    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('width')
      // Force an expected order for identical width.
      ->sort('search_api_id')
      ->execute();
    $this->assertResults([1, 2, 3, 6, 7, 4, 5], $results, 'Sort by width.');

    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('width', QueryInterface::SORT_DESC)
      // Force an expected order for identical width.
      ->sort('search_api_id')
      ->execute();
    $this->assertResults([5, 4, 1, 2, 3, 6, 7], $results, 'Sort by width descending.');

    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('changed')
      ->execute();
    $this->assertResults([1, 2, 3, 4, 5, 6, 7], $results, 'Sort by last update date');

    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('changed', QueryInterface::SORT_DESC)
      ->execute();
    $this->assertResults([7, 6, 5, 4, 3, 2, 1], $results, 'Sort by last update date descending');
  }

  /**
   * Tests the autocomplete support.
   */
  public function testAutocomplete() {
    $this->addTestEntity(1, [
      'name' => 'Test Article 1',
      'body' => 'The test article number 1 about cats, dogs and trees.',
      'type' => 'article',
    ]);

    // Add another node with body length equal to the limit.
    $this->addTestEntity(2, [
      'name' => 'Test Article 1',
      'body' => 'The test article number 2 about a tree.',
      'type' => 'article',
    ]);

    $this->indexItems($this->indexId);

    /** @var \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend $backend */
    $backend = Server::load($this->serverId)->getBackend();
    $autocompleteSearch = new Search([], 'search_api_autocomplete_search');

    $query = $this->buildSearch(['artic'], [], ['body_unstemmed'], FALSE);
    $suggestions = $backend->getAutocompleteSuggestions($query, $autocompleteSearch, 'artic', 'artic');
    $this->assertEquals(1, count($suggestions));
    $this->assertEquals('le', $suggestions[0]->getSuggestionSuffix());
    $this->assertEquals(2, $suggestions[0]->getResultsCount());

    $query = $this->buildSearch(['artic'], [], ['body'], FALSE);
    $suggestions = $backend->getTermsSuggestions($query, $autocompleteSearch, 'artic', 'artic');
    $this->assertEquals(1, count($suggestions));
    // This time we test the stemmed token.
    $this->assertEquals('l', $suggestions[0]->getSuggestionSuffix());
    $this->assertEquals(2, $suggestions[0]->getResultsCount());

    $query = $this->buildSearch(['articel'], [], ['body'], FALSE);
    $suggestions = $backend->getSpellcheckSuggestions($query, $autocompleteSearch, 'articel', 'articel');
    $this->assertEquals(1, count($suggestions));
    $this->assertEquals('article', $suggestions[0]->getSuggestedKeys());
    $this->assertEquals(0, $suggestions[0]->getResultsCount());

    $query = $this->buildSearch(['article tre'], [], ['body_unstemmed'], FALSE);
    $suggestions = $backend->getAutocompleteSuggestions($query, $autocompleteSearch, 'tre', 'article tre');
    $this->assertEquals('article tree', $suggestions[0]->getSuggestedKeys());
    $this->assertEquals(1, $suggestions[0]->getResultsCount());
    // Having set preserveOriginal in WordDelimiter let punction remain.
    $this->assertEquals('article tree.', $suggestions[1]->getSuggestedKeys());
    $this->assertEquals(1, $suggestions[1]->getResultsCount());
    $this->assertEquals('article trees', $suggestions[2]->getSuggestedKeys());
    $this->assertEquals(1, $suggestions[2]->getResultsCount());
    $this->assertEquals('article trees.', $suggestions[3]->getSuggestedKeys());
    $this->assertEquals(1, $suggestions[3]->getResultsCount());

    // @todo spellcheck tests
    #$query = $this->buildSearch(['articel doks'], [], ['body'], FALSE);
    #$suggestions = $backend->getSpellcheckSuggestions($query, $autocompleteSearch, 'doks', 'articel doks');
    #$this->assertEquals(1, count($suggestions));
    #$this->assertEquals('article dogs', $suggestions[0]->getSuggestedKeys());

    #$query = $this->buildSearch(['articel tre'], [], ['body'], FALSE);
    #$suggestions = $backend->getAutocompleteSuggestions($query, $autocompleteSearch, 'tre', 'articel tre');
    #$this->assertEquals(5, count($suggestions));
    #$this->assertEquals('e', $suggestions[0]->getSuggestionSuffix());
    #$this->assertEquals(1, $suggestions[0]->getResultsCount());
    #$this->assertEquals('es', $suggestions[1]->getSuggestionSuffix());

    $query = $this->buildSearch(['artic'], [], ['body'], FALSE);
    $suggestions = $backend->getSuggesterSuggestions($query, $autocompleteSearch, 'artic', 'artic');
    $this->assertEquals(2, count($suggestions));

    $this->assertEquals('artic', $suggestions[0]->getUserInput());
    $this->assertEquals('The test <b>', $suggestions[0]->getSuggestionPrefix());
    $this->assertEquals('</b>le number 1 about cats, dogs and trees.', $suggestions[0]->getSuggestionSuffix());
    $this->assertEquals('The test <b>artic</b>le number 1 about cats, dogs and trees.', $suggestions[0]->getSuggestedKeys());

    $this->assertEquals('artic', $suggestions[1]->getUserInput());
    $this->assertEquals('The test <b>', $suggestions[1]->getSuggestionPrefix());
    $this->assertEquals('</b>le number 2 about a tree.', $suggestions[1]->getSuggestionSuffix());
    $this->assertEquals('The test <b>artic</b>le number 2 about a tree.', $suggestions[1]->getSuggestedKeys());

    // @todo more suggester tests
  }

  /**
   * Tests ngram search result.
   */
  public function testNgramResult() {
    $this->addTestEntity(1, [
      'name' => 'Test Article 1',
      'body' => 'The test article number 1 about cats, dogs and trees.',
      'type' => 'article',
      'category' => 'dogs and trees',
    ]);

    // Add another node with body length equal to the limit.
    $this->addTestEntity(2, [
      'name' => 'Test Article 1',
      'body' => 'The test article number 2 about a tree.',
      'type' => 'article',
      'category' => 'trees',
    ]);

    $this->indexItems($this->indexId);

    $results = $this->buildSearch(['tre'], [], ['category_ngram'])
      ->execute();
    $this->assertResults([1, 2], $results, 'Ngram text "tre".');

    $results = $this->buildSearch([], [], [])
      ->addCondition('category_ngram_string', 'tre')
      ->execute();
    $this->assertResults([2], $results, 'Ngram string "tre".');

    $results = $this->buildSearch(['Dog'], [], ['category_ngram'])
      ->execute();
    $this->assertResults([1], $results, 'Ngram text "Dog".');

    $results = $this->buildSearch([], [], [])
      ->addCondition('category_ngram_string', 'Dog')
      ->execute();
    $this->assertResults([1], $results, 'Ngram string "Dog".');
  }

}
