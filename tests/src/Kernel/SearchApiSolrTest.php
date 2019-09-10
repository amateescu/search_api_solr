<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api_autocomplete\Entity\Search;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\Tests\search_api_solr\Traits\InvokeMethodTrait;
use Drupal\search_api_solr\Utility\SolrCommitTrait;
use Drupal\search_api_solr\Utility\Utility as SolrUtility;
use Drupal\user\Entity\User;

/**
 * Tests index and search capabilities using the Solr search backend.
 *
 * @group search_api_solr
 */
class SearchApiSolrTest extends SolrBackendTestBase {

  use SolrCommitTrait;
  use InvokeMethodTrait;

  protected $languageIds = ['en', 'de', 'de-at'];

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'language',
    'search_api_autocomplete',
    'user',
  ];

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * {@inheritdoc}
   */
  protected function installConfigs() {
    foreach ($this->languageIds as $language_id) {
      ConfigurableLanguage::createFromLangcode($language_id)->save();
    }

    parent::installConfigs();
  }

  /**
   * {@inheritdoc}
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
    $this->indexPrefixTest();
  }

  /**
   * Tests index prefix.
   */
  protected function indexPrefixTest() {
    $backend = Server::load($this->serverId)->getBackend();
    $index = $this->getIndex();
    $prefixed_index_id = $this->invokeMethod($backend, 'getIndexId', [$index]);
    $this->assertEquals('server_prefixindex_prefix' . $index->id(), $prefixed_index_id);
  }

  /**
   * Regression tests for #2469547.
   */
  protected function regressionTest2469547() {
    $this->travisLogger->debug('SearchApiSolrTest::regressionTest2469547()');
    return;

    // @todo
    // @codingStandardsIgnoreStart
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
    // @codingStandardsIgnoreEnd
  }

  /**
   * Regression tests for #2888629.
   */
  protected function regressionTest2888629() {
    $this->travisLogger->debug('SearchApiSolrTest::regressionTest2888629()');

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
   * {@inheritdoc}
   */
  public function searchSuccess() {
    $this->travisLogger->debug('SearchApiSolrTest::searchSuccess()');

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
   *   An array of facet results.
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
    $this->travisLogger->debug('SearchApiSolrTest::checkModuleUninstall()');

    // See whether clearing the server works.
    // Regression test for #2156151.
    /** @var \Drupal\search_api\ServerInterface $server */
    $server = Server::load($this->serverId);
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load($this->indexId);
    $server->deleteAllIndexItems($index);
    $this->ensureCommit($index);
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
   * Checks backend specific features.
   */
  protected function checkBackendSpecificFeatures() {
    $this->checkBasicAuth();
    $this->checkQueryParsers();
    $this->checkQueryConditions();
    $this->checkHighlight();
    $this->checkSearchResultGrouping();
    $this->clearIndex();
    $this->checkDatasourceAdditionAndDeletion();
    $this->clearIndex();
    $this->checkRetrieveData();
    $this->clearIndex();
    $this->checkSearchResultSorts();
  }

  /**
   * Tests the conversion of Search API queries into Solr queries.
   */
  protected function checkQueryParsers() {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = Server::load($this->serverId)->getBackend();

    $query = $this->buildSearch('foo "apple pie" bar');

    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      [],
      'phrase'
    );
    $this->assertEquals('(+"foo" +"apple pie" +"bar")', $flat);

    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      [],
      'terms'
    );
    $this->assertEquals('(+"foo" +"apple pie" +"bar")', $flat);

    $exception = FALSE;
    try {
      $flat = SolrUtility::flattenKeys(
        $query->getKeys(),
        [],
        'edismax'
      );
    }
    catch (SearchApiSolrException $e) {
      $exception = TRUE;
    }
    $this->assertTrue($exception);

    $exception = FALSE;
    try {
      $flat = SolrUtility::flattenKeys(
        $query->getKeys(),
        [],
        'direct'
      );
    }
    catch (SearchApiSolrException $e) {
      $exception = TRUE;
    }
    $this->assertTrue($exception);

    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      ['solr_field'],
      'phrase'
    );
    $this->assertEquals('solr_field:(+"foo" +"apple pie" +"bar")', $flat);

    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      ['solr_field'],
      'terms'
    );
    $this->assertEquals('((+(solr_field:"foo") +(solr_field:"apple pie") +(solr_field:"bar")) solr_field:(+"foo" +"apple pie" +"bar"))', $flat);

    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      ['solr_field'],
      'edismax'
    );
    $this->assertEquals('({!edismax qf=\'solr_field\'}+"foo" +"apple pie" +"bar")', $flat);

    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      ['solr_field_1', 'solr_field_2'],
      'phrase'
    );
    $this->assertEquals('(solr_field_1:(+"foo" +"apple pie" +"bar") solr_field_2:(+"foo" +"apple pie" +"bar"))', $flat);

    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      ['solr_field_1', 'solr_field_2'],
      'terms'
    );
    $this->assertEquals('((+(solr_field_1:"foo" solr_field_2:"foo") +(solr_field_1:"apple pie" solr_field_2:"apple pie") +(solr_field_1:"bar" solr_field_2:"bar")) solr_field_1:(+"foo" +"apple pie" +"bar") solr_field_2:(+"foo" +"apple pie" +"bar"))', $flat);

    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      ['solr_field_1', 'solr_field_2'],
      'edismax'
    );
    $this->assertEquals('({!edismax qf=\'solr_field_1 solr_field_2\'}+"foo" +"apple pie" +"bar")', $flat);

    $flat = SolrUtility::flattenKeys(
      $query->getKeys(),
      [],
      'keys'
    );
    $this->assertEquals('+"foo" +"apple pie" +"bar"', $flat);
  }

  /**
   * Tests the conversion of Search API queries into Solr queries.
   */
  protected function checkQueryConditions() {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = Server::load($this->serverId)->getBackend();
    $options = [];

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $query->addCondition('id', 5, '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('its_id:"5"', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $query->addCondition('id', 5, '<>');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(*:* -its_id:"5")', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $query->addCondition('id', 3, '<>');
    $query->addCondition('id', 5, '<>');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(*:* -its_id:"3")', $fq[0]['query']);
    $this->assertEquals('(*:* -its_id:"5")', $fq[1]['query']);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('id', 3, '<>');
    $condition_group->addCondition('id', 5, '<>');
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+(*:* -its_id:"3") +(*:* -its_id:"5"))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('id', 5, '<>');
    $condition_group->addCondition('type', 3);
    $condition_group->addCondition('category', 7);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+(*:* -its_id:"5") +ss_type:"3" +ss_category:"7")', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $inner_condition_group = $query->createConditionGroup('OR');
    $condition_group->addCondition('id', 5, '<>');
    $inner_condition_group->addCondition('type', 3);
    $inner_condition_group->addCondition('category', 7);
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+(*:* -its_id:"5") +(ss_type:"3" ss_category:"7"))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    // Condition groups with null value queries are special snowflakes.
    // @see https://www.drupal.org/node/2888629
    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $inner_condition_group = $query->createConditionGroup('OR');
    $condition_group->addCondition('id', 5, '<>');
    $inner_condition_group->addCondition('type', 3);
    $inner_condition_group->addCondition('category', NULL);
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+(*:* -its_id:"5") +(ss_type:"3" (*:* -ss_category:[* TO *])))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $inner_condition_group_or = $query->createConditionGroup('OR');
    $inner_condition_group_or->addCondition('id', 3);
    $inner_condition_group_or->addCondition('type', 7, '<>');
    $inner_condition_group_and = $query->createConditionGroup();
    $inner_condition_group_and->addCondition('id', 1);
    $inner_condition_group_and->addCondition('type', 2, '<>');
    $inner_condition_group_and->addCondition('category', 5, '<');
    $condition_group->addConditionGroup($inner_condition_group_or);
    $condition_group->addConditionGroup($inner_condition_group_and);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+(its_id:"3" (*:* -ss_type:"7")) +(+its_id:"1" +(*:* -ss_type:"2") +ss_category:{* TO "5"}))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('id', 5);
    $condition_group->addCondition('type', [1, 2, 3], 'NOT IN');
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+its_id:"5" +(*:* -ss_type:("1" "2" "3")))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('id', 5);
    $inner_condition_group = $query->createConditionGroup();
    $inner_condition_group->addCondition('type', [1, 2, 3], 'NOT IN');
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+its_id:"5" +(*:* -ss_type:("1" "2" "3")))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    // Test tagging of a single filter query of a facet query.
    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
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
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('ss_category:"article_category"', $fq[0]['query'], 'Condition found in tagged first filter query');
    $this->assertEquals(['facet:tagtosearchfor' => 'facet:tagtosearchfor'], $fq[0]['tags'], 'Tag found in tagged first filter query');
    $this->assertEquals('ss_category:[* TO *]', $fq[1]['query'], 'Condition found in unrelated second filter query');
    $this->assertEquals([], $fq[1]['tags'], 'No tag found in second filter query');

    // @see https://www.drupal.org/node/2753917
    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $conditions = $query->createConditionGroup('OR', ['facet:id']);
    $conditions->addCondition('id', 'A');
    $conditions->addCondition('id', 'B');
    $query->addConditionGroup($conditions);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals(1, count($fq));
    $this->assertEquals(['facet:id' => 'facet:id'], $fq[0]['tags']);
    $this->assertEquals('(its_id:"A" its_id:"B")', $fq[0]['query']);

    $query = $this->buildSearch();
    $query->setLanguages([LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $conditions = $query->createConditionGroup('AND', ['facet:id']);
    $conditions->addCondition('id', 'A');
    $conditions->addCondition('id', 'B');
    $query->addConditionGroup($conditions);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals(1, count($fq));
    $this->assertEquals(['facet:id' => 'facet:id'], $fq[0]['tags']);
    $this->assertEquals('(+its_id:"A" +its_id:"B")', $fq[0]['query']);

    $query = $this->buildSearch();
    $query->setLanguages(['en']);
    $query->addCondition('id', 5, '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('its_id:"5"', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->setLanguages(['en', 'de']);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('id', 5);
    $inner_condition_group = $query->createConditionGroup();
    $inner_condition_group->addCondition('type', [1, 2, 3], 'NOT IN');
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+its_id:"5" +(*:* -ss_type:("1" "2" "3")))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->setLanguages(['en']);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('id', 5);
    $condition_group->addCondition('search_api_language', 'de');
    $inner_condition_group = $query->createConditionGroup();
    $inner_condition_group->addCondition('type', [1, 2, 3], 'NOT IN');
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('(+its_id:"5" +ss_search_api_language:"de" +(*:* -ss_type:("1" "2" "3")))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->setLanguages(['en']);
    $query->addCondition('body', 'some text', '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('tm_X3b_en_body:("some text")', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $parse_mode_manager = \Drupal::service('plugin.manager.search_api.parse_mode');
    $parse_mode_phrase = $parse_mode_manager->createInstance('phrase');

    $query = $this->buildSearch();
    $query->setLanguages(['en']);
    $query->setParseMode($parse_mode_phrase);
    $query->addCondition('body', 'some text', '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('tm_X3b_en_body:("some text")', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->setLanguages(['en']);
    $query->setParseMode($parse_mode_phrase);
    $query->addCondition('body', ['some', 'text'], '=');
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, &$options]);
    $this->assertEquals('tm_X3b_en_body:("some" "text")', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));
  }

  /**
   * Tests retrieve_data options.
   */
  protected function checkRetrieveData() {
    $this->travisLogger->debug('SearchApiSolrTest::checkRetrieveData()');

    $server = $this->getIndex()->getServerInstance();
    $config = $server->getBackendConfig();
    $backend = $server->getBackend();

    $this->indexItems($this->indexId);

    // Retrieve just required fields.
    $query = $this->buildSearch('foobar');
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      /** @var \Solarium\QueryType\Select\Result\Document $solr_document */
      $solr_document = $result->getExtraData('search_api_solr_document', NULL);
      $fields = $solr_document->getFields();
      $this->assertEquals('entity:entity_test_mulrev_changed/3:en', $fields['ss_search_api_id']);
      $this->assertEquals('en', $fields['ss_search_api_language']);
      $this->assertArrayHasKey('score', $fields);
      $this->assertArrayNotHasKey('tm_X3b_en_body', $fields);
      $this->assertArrayNotHasKey('id', $fields);
      $this->assertArrayNotHasKey('its_id', $fields);
      $this->assertArrayNotHasKey('twm_suggest', $fields);
    }

    // Retrieve all fields.
    $config['retrieve_data'] = TRUE;
    $server->setBackendConfig($config);
    $server->save();

    $query = $this->buildSearch('foobar');
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      /** @var \Solarium\QueryType\Select\Result\Document $solr_document */
      $solr_document = $result->getExtraData('search_api_solr_document', NULL);
      $fields = $solr_document->getFields();
      $this->assertEquals('entity:entity_test_mulrev_changed/3:en', $fields['ss_search_api_id']);
      $this->assertEquals('en', $fields['ss_search_api_language']);
      $this->assertArrayHasKey('score', $fields);
      $this->assertArrayHasKey('tm_X3b_en_body', $fields);
      $this->assertContains('search_index-entity:entity_test_mulrev_changed/3:en', $fields['id']);
      $this->assertEquals('3', $fields['its_id']);
      $this->assertArrayHasKey('twm_suggest', $fields);
    }

    // Retrieve list of fields in addition to required fields.
    $query = $this->buildSearch('foobar');
    $query->setOption('search_api_retrieved_field_values', ['body' => 'body']);
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      /** @var \Solarium\QueryType\Select\Result\Document $solr_document */
      $solr_document = $result->getExtraData('search_api_solr_document', NULL);
      $fields = $solr_document->getFields();
      $this->assertEquals('entity:entity_test_mulrev_changed/3:en', $fields['ss_search_api_id']);
      $this->assertEquals('en', $fields['ss_search_api_language']);
      $this->assertArrayHasKey('score', $fields);
      $this->assertArrayHasKey('tm_X3b_en_body', $fields);
      $this->assertArrayNotHasKey('id', $fields);
      $this->assertArrayNotHasKey('its_id', $fields);
      $this->assertArrayNotHasKey('twm_suggest', $fields);
    }

    $fulltext_fields = array_flip($this->invokeMethod($backend, 'getQueryFulltextFields', [$query]));
    $this->assertArrayHasKey('name', $fulltext_fields);
    $this->assertArrayHasKey('body', $fulltext_fields);
    $this->assertArrayHasKey('body_unstemmed', $fulltext_fields);
    $this->assertArrayHasKey('category_edge', $fulltext_fields);
    // body_suggest should be removed by getQueryFulltextFields().
    $this->assertArrayNotHasKey('body_suggest', $fulltext_fields);
  }

  /**
   * Tests highlight options.
   */
  protected function checkHighlight() {
    $this->travisLogger->debug('SearchApiSolrTest::checkHighlight()');

    $server = $this->getIndex()->getServerInstance();
    $config = $server->getBackendConfig();

    $this->indexItems($this->indexId);

    $query = $this->buildSearch('foobar');
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $this->assertEmpty($result->getExtraData('highlighted_fields', []));
      $this->assertEmpty($result->getExtraData('highlighted_keys', []));
    }

    $config['highlight_data'] = TRUE;
    $server->setBackendConfig($config);
    $server->save();

    $query = $this->buildSearch('foobar');
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $this->assertContains('<strong>foobar</strong>', (string) $result->getExtraData('highlighted_fields', ['body' => ['']])['body'][0]);
      $this->assertEquals(['foobar'], $result->getExtraData('highlighted_keys', []));
      $this->assertEquals('… bar … test <strong>foobar</strong> Case …', $result->getExcerpt());
    }

    // Test highlghting with stemming.
    $query = $this->buildSearch('foobars');
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »foobar« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $this->assertContains('<strong>foobar</strong>', (string) $result->getExtraData('highlighted_fields', ['body' => ['']])['body'][0]);
      $this->assertEquals(['foobar'], $result->getExtraData('highlighted_keys', []));
      $this->assertEquals('… bar … test <strong>foobar</strong> Case …', $result->getExcerpt());
    }
  }

  /**
   * Test that basic auth config gets passed to Solarium.
   */
  protected function checkBasicAuth() {
    $server = $this->getServer();
    $config = $server->getBackendConfig();
    $config['connector_config']['username'] = 'foo';
    $config['connector_config']['password'] = 'bar';
    $server->setBackendConfig($config);
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $server->getBackend();
    $auth = $backend->getSolrConnector()->getEndpoint()->getAuthentication();
    $this->assertEquals(['username' => 'foo', 'password' => 'bar'], $auth);

    $config['connector_config']['username'] = '';
    $config['connector_config']['password'] = '';
    $server->setBackendConfig($config);
  }

  /**
   * Tests addition and deletion of a data source.
   */
  protected function checkDatasourceAdditionAndDeletion() {
    $this->travisLogger->debug('SearchApiSolrTest::checkDatasourceAdditionAndDeletion()');

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

    $this->ensureCommit($index);

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
   *   A random string of the specified length.
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
   * Tests search result grouping.
   */
  public function checkSearchResultGrouping() {
    $this->travisLogger->debug('SearchApiSolrTest::checkSearchResultGrouping()');

    if (in_array('search_api_grouping', $this->getIndex()->getServerInstance()->getBackend()->getSupportedFeatures())) {
      $query = $this->buildSearch(NULL, [], [], FALSE);
      $query->setOption('search_api_grouping', [
        'use_grouping' => TRUE,
        'fields' => [
          'type',
        ],
      ]);
      $results = $query->execute();

      $this->assertEquals(2, $results->getResultCount(), 'Get the results count grouping by type.');
      $data = $results->getExtraData('search_api_solr_response');
      $this->assertEquals(5, $data['grouped']['ss_type']['matches'], 'Get the total documents after grouping.');
      $this->assertEquals(2, $data['grouped']['ss_type']['ngroups'], 'Get the number of groups after grouping.');
      $this->assertResults([1, 4], $results, 'Grouping by type');
    }
    else {
      $this->markTestSkipped("The selected backend/connector doesn't support the *search_api_grouping* feature.");
    }
  }

  /**
   * Tests search result sorts.
   */
  protected function checkSearchResultSorts() {
    $this->travisLogger->debug('SearchApiSolrTest::checkSearchResultSorts()');

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
    $this->assertResults([1, 2, 4, 5, 3, 6, 7], $results, 'Sort by last update date');

    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('changed', QueryInterface::SORT_DESC)
      ->execute();
    $this->assertResults([7, 6, 3, 5, 4, 2, 1], $results, 'Sort by last update date descending');

    $this->removeTestEntity(6);
    $this->removeTestEntity(7);
  }

  /**
   * Tests the autocomplete support and ngram results.
   */
  public function testAutocompleteAndNgram() {
    $this->travisLogger->debug('SearchApiSolrTest::testAutocompleteAndNgram()');

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

    /** @var \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend $backend */
    $backend = Server::load($this->serverId)->getBackend();
    $autocompleteSearch = new Search([], 'search_api_autocomplete_search');

    $query = $this->buildSearch(['artic'], [], ['body_unstemmed'], FALSE);
    $query->setLanguages(['en']);
    $suggestions = $backend->getAutocompleteSuggestions($query, $autocompleteSearch, 'artic', 'artic');
    $this->assertEquals(1, count($suggestions));
    $this->assertEquals('le', $suggestions[0]->getSuggestionSuffix());
    $this->assertEquals(2, $suggestions[0]->getResultsCount());

    $query = $this->buildSearch(['artic'], [], ['body'], FALSE);
    $query->setLanguages(['en']);
    $suggestions = $backend->getTermsSuggestions($query, $autocompleteSearch, 'artic', 'artic');
    $this->assertEquals(1, count($suggestions));
    // This time we test the stemmed token.
    $this->assertEquals('l', $suggestions[0]->getSuggestionSuffix());
    $this->assertEquals(2, $suggestions[0]->getResultsCount());

    $query = $this->buildSearch(['articel'], [], ['body'], FALSE);
    $query->setLanguages(['en']);
    $suggestions = $backend->getSpellcheckSuggestions($query, $autocompleteSearch, 'articel', 'articel');
    $this->assertEquals(1, count($suggestions));
    $this->assertEquals('article', $suggestions[0]->getSuggestedKeys());
    $this->assertEquals(0, $suggestions[0]->getResultsCount());

    $query = $this->buildSearch(['article tre'], [], ['body_unstemmed'], FALSE);
    $query->setLanguages(['en']);
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
    // @codingStandardsIgnoreStart
    // $query = $this->buildSearch(['articel cats doks'], [], ['body'], FALSE);
    // $query->setLanguages(['en']);
    // $suggestions = $backend->getSpellcheckSuggestions($query, $autocompleteSearch, 'doks', 'articel doks');
    // $this->assertEquals(1, count($suggestions));
    // $this->assertEquals('article dogs', $suggestions[0]->getSuggestedKeys());

    // $query = $this->buildSearch(['articel tre'], [], ['body'], FALSE);
    // $query->setLanguages(['en']);
    // $suggestions = $backend->getAutocompleteSuggestions($query, $autocompleteSearch, 'tre', 'articel tre');
    // $this->assertEquals(5, count($suggestions));
    // $this->assertEquals('e', $suggestions[0]->getSuggestionSuffix());
    // $this->assertEquals(1, $suggestions[0]->getResultsCount());
    // $this->assertEquals('es', $suggestions[1]->getSuggestionSuffix());
    // @codingStandardsIgnoreEnd

    // @todo Add more suggester tests.
    $query = $this->buildSearch(['artic'], [], ['body'], FALSE);
    $query->setLanguages(['en']);
    $suggestions = $backend->getSuggesterSuggestions($query, $autocompleteSearch, 'artic', 'artic');
    $this->assertEquals(2, count($suggestions));

    // Since we don't specify the result weights explicitly for this suggester
    // we need to deal with a random order and need predictable array keys.
    foreach ($suggestions as $suggestion) {
      $suggestions[$suggestion->getSuggestedKeys()] = $suggestion;
    }
    $this->assertEquals('artic', $suggestions['The test <b>artic</b>le number 1 about cats, dogs and trees.']->getUserInput());
    $this->assertEquals('The test <b>', $suggestions['The test <b>artic</b>le number 1 about cats, dogs and trees.']->getSuggestionPrefix());
    $this->assertEquals('</b>le number 1 about cats, dogs and trees.', $suggestions['The test <b>artic</b>le number 1 about cats, dogs and trees.']->getSuggestionSuffix());
    $this->assertEquals('The test <b>artic</b>le number 1 about cats, dogs and trees.', $suggestions['The test <b>artic</b>le number 1 about cats, dogs and trees.']->getSuggestedKeys());

    $this->assertEquals('artic', $suggestions['The test <b>artic</b>le number 2 about a tree.']->getUserInput());
    $this->assertEquals('The test <b>', $suggestions['The test <b>artic</b>le number 2 about a tree.']->getSuggestionPrefix());
    $this->assertEquals('</b>le number 2 about a tree.', $suggestions['The test <b>artic</b>le number 2 about a tree.']->getSuggestionSuffix());
    $this->assertEquals('The test <b>artic</b>le number 2 about a tree.', $suggestions['The test <b>artic</b>le number 2 about a tree.']->getSuggestedKeys());

    // Tests NGram and Edge NGram search result.
    foreach (['category_ngram', 'category_edge'] as $field) {
      $results = $this->buildSearch(['tre'], [], [$field])
        ->execute();
      $this->assertResults([1, 2], $results, $field . ': tre');

      $results = $this->buildSearch(['Dog'], [], [$field])
        ->execute();
      $this->assertResults([1], $results, $field . ': Dog');

      $results = $this->buildSearch([], [], [])
        ->addCondition($field, 'Dog')
        ->execute();
      $this->assertResults([1], $results, $field . ': Dog as condition');
    }

    // Tests NGram search result.
    $result_set = [
      'category_ngram' => [1, 2],
      'category_ngram_string' => [1, 2],
      'category_edge' => [],
      'category_edge_string' => [],
    ];
    foreach ($result_set as $field => $expected_results) {
      $results = $this->buildSearch(['re'], [], [$field])
        ->execute();
      $this->assertResults($expected_results, $results, $field . ': re');
    }

    foreach (['category_ngram_string' => [1, 2], 'category_edge_string' => [2]] as $field => $expected_results) {
      $results = $this->buildSearch(['tre'], [], [$field])
        ->execute();
      $this->assertResults($expected_results, $results, $field . ': tre');
    }
  }

  /**
   * Tests language fallback and language limiting via options.
   */
  public function testLanguageFallbackAndLanguageLimitedByOptions() {
    $this->travisLogger->debug('SearchApiSolrTest::testLanguageFallbackAndLanguageLimitedByOptions()');

    $this->insertMultilingualExampleContent();
    $this->indexItems($this->indexId);

    $index = $this->getIndex();
    $connector = $index->getServerInstance()->getBackend()->getSolrConnector();

    $results = $this->buildSearch()->execute();
    $this->assertEquals(6, $results->getResultCount(), 'Number of indexed entities is correct.');

    // Stemming "en":
    // gene => gene
    // genes => gene
    //
    // Stemming "de":
    // Gen => gen
    // Gene => gen.
    $query = $this->buildSearch('Gen');
    $query->sort('name');
    $query->setLanguages(['en', 'de']);
    $results = $query->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Two results for "Gen" in German entities. No results for "Gen" in English entities.');
    $params = $connector->getRequestParams();
    $this->assertEquals('ss_search_api_language:("en" "de")', $params['fq'][1]);
    $this->assertEquals('ss_search_api_id asc,sort_X3b_en_name asc', $params['sort'][0]);

    $query = $this->buildSearch('Gene');
    $query->setLanguages(['en', 'de']);
    $results = $query->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Two results for "Gene" in German entities. Two results for "Gene" in English entities.');

    // Stemming of "de-at" should fall back to "de".
    $query = $this->buildSearch('Gen');
    $query->setLanguages(['de-at']);
    $results = $query->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Two results for "Gen" in Austrian entities.');
    $query = $this->buildSearch('Gene');
    $query->setLanguages(['de-at']);
    $results = $query->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Two results for "Gene" in Austrian entities.');
    $params = $connector->getRequestParams();
    $this->assertEquals('ss_search_api_language:"de-at"', $params['fq'][1]);

    $settings = $index->getThirdPartySettings('search_api_solr');
    $settings['multilingual']['limit_to_content_language'] = FALSE;
    $settings['multilingual']['include_language_independent'] = FALSE;
    $index->setThirdPartySetting('search_api_solr', 'multilingual', $settings['multilingual']);
    $index->save();
    $this->assertFalse($this->getIndex()->getThirdPartySetting('search_api_solr', 'multilingual')['limit_to_content_language']);
    $this->assertFalse($this->getIndex()->getThirdPartySetting('search_api_solr', 'multilingual')['include_language_independent']);

    // Stemming "en":
    // gene => gene
    // genes => gene
    //
    // Stemming "de":
    // Gen => gen
    // Gene => gen.
    $results = $this->buildSearch('gene', [], ['body'])->execute();
    $expected_results = [
      1 => 'en',
      2 => 'en',
      3 => 'de',
      4 => 'de',
      5 => 'de-at',
      6 => 'de-at',
    ];
    $this->assertResults($expected_results, $results, 'Search all languages for "gene".');

    $settings['multilingual']['limit_to_content_language'] = TRUE;
    $index->setThirdPartySetting('search_api_solr', 'multilingual', $settings['multilingual']);
    $index->save();
    $this->assertTrue($this->getIndex()->getThirdPartySetting('search_api_solr', 'multilingual')['limit_to_content_language']);

    // Current content language is "en".
    $results = $this->buildSearch('gene', [], ['body'])->execute();
    $expected_results = [
      1 => 'en',
      2 => 'en',
    ];
    $this->assertResults($expected_results, $results, 'Search content language for "gene".');

    // A query created by Views must not be overruled.
    $results = $this->buildSearch('gene', [], ['body'])->addTag('views')->execute();
    $expected_results = [
      1 => 'en',
      2 => 'en',
      3 => 'de',
      4 => 'de',
      5 => 'de-at',
      6 => 'de-at',
    ];
    $this->assertResults($expected_results, $results, 'Search all languages for "gene".');

    $settings['multilingual']['include_language_independent'] = TRUE;
    $index->setThirdPartySetting('search_api_solr', 'multilingual', $settings['multilingual']);
    $index->save();
    $this->assertTrue($this->getIndex()->getThirdPartySetting('search_api_solr', 'multilingual')['include_language_independent']);

    $results = $this->buildSearch('gene', [], ['body'])->execute();
    $expected_results = [
      1 => 'en',
      2 => 'en',
      7 => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      8 => LanguageInterface::LANGCODE_NOT_APPLICABLE,
    ];
    $this->assertResults($expected_results, $results, 'Search content and unspecified language for "gene".');

    $settings['multilingual']['limit_to_content_language'] = FALSE;
    $index->setThirdPartySetting('search_api_solr', 'multilingual', $settings['multilingual']);
    $index->save();
    $this->assertFalse($this->getIndex()->getThirdPartySetting('search_api_solr', 'multilingual')['limit_to_content_language']);

    $results = $this->buildSearch('gene', [], ['body'])->execute();
    $expected_results = [
      1 => 'en',
      2 => 'en',
      3 => 'de',
      4 => 'de',
      5 => 'de-at',
      6 => 'de-at',
      7 => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      8 => LanguageInterface::LANGCODE_NOT_APPLICABLE,
    ];
    $this->assertResults($expected_results, $results, 'Search all and unspecified languages for "gene".');

    $this->assertFalse($this->getIndex()->isReindexing());
    ConfigurableLanguage::createFromLangcode('de-ch')->save();
    $this->assertTrue($this->getIndex()->isReindexing());
  }

  /**
   * Creates several test entities.
   */
  protected function insertMultilingualExampleContent() {
    $this->addTestEntity(1, [
      'name' => 'en 1',
      'body' => 'gene',
      'type' => 'item',
      'langcode' => 'en',
    ]);
    $this->addTestEntity(2, [
      'name' => 'en 2',
      'body' => 'genes',
      'type' => 'item',
      'langcode' => 'en',
    ]);
    $this->addTestEntity(3, [
      'name' => 'de 3',
      'body' => 'Gen',
      'type' => 'item',
      'langcode' => 'de',
    ]);
    $this->addTestEntity(4, [
      'name' => 'de 4',
      'body' => 'Gen',
      'type' => 'item',
      'langcode' => 'de',
    ]);
    $this->addTestEntity(5, [
      'name' => 'de-at 5',
      'body' => 'Gen',
      'type' => 'item',
      'langcode' => 'de-at',
    ]);
    $this->addTestEntity(6, [
      'name' => 'de-at 6',
      'body' => 'Gen',
      'type' => 'item',
      'langcode' => 'de-at',
    ]);
    $this->addTestEntity(7, [
      'name' => 'und 7',
      'body' => 'gene',
      'type' => 'item',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $this->addTestEntity(8, [
      'name' => 'zxx 8',
      'body' => 'gene',
      'type' => 'item',
      'langcode' => LanguageInterface::LANGCODE_NOT_APPLICABLE,
    ]);
    $count = \Drupal::entityQuery('entity_test_mulrev_changed')->count()->execute();
    $this->assertEquals(8, $count, "$count items inserted.");
  }

  /**
   * {@inheritdoc}
   *
   * If the list of entity ids contains language codes it will be handled here,
   * otherwise it will be handed over to the parent implementation.
   *
   * @param array $entity_ids
   *   An array of entity IDs or an array keyed by entity IDs and langcodes as
   *   values.
   *
   * @return string[]
   *   An array of item IDs.
   */
  protected function getItemIds(array $entity_ids) {
    $item_ids = [];
    if (!empty($entity_ids)) {
      $keys = array_keys($entity_ids);
      $first_key = reset($keys);
      if (0 === $first_key) {
        return parent::getItemIds($entity_ids);
      }
      else {
        foreach ($entity_ids as $id => $langcode) {
          $item_ids[] = Utility::createCombinedId('entity:entity_test_mulrev_changed', $id . ':' . $langcode);
        }
      }
    }
    return $item_ids;
  }

  /**
   * Test generation of Solr configuration files.
   *
   * @dataProvider configGenerationDataProvider
   */
  public function testConfigGeneration(array $files) {
    $server = $this->getServer();
    $solr_major_version = $server->getBackend()->getSolrConnector()->getSolrMajorVersion();
    $solr_version = $server->getBackend()->getSolrConnector()->getSolrVersion();
    $solr_install_dir = '/opt/solr-' . $solr_version;

    $backend_config = $server->getBackendConfig();
    // Relative path for official docker image.
    $backend_config['connector_config']['solr_install_dir'] = $solr_install_dir;
    $server->setBackendConfig($backend_config);
    $server->save();

    /** @var \Drupal\search_api_solr\Controller\SolrFieldTypeListBuilder $list_builder */
    $list_builder = \Drupal::entityTypeManager()
      ->getListBuilder('solr_field_type');

    $list_builder->setServer($server);

    $config_files = $list_builder->getConfigFiles();

    foreach ($files as $file_name => $expected_strings) {
      $this->assertArrayHasKey($file_name, $config_files);
      foreach ($expected_strings as $string) {
        $this->assertContains($string, $config_files[$file_name]);
      }
    }

    $config_name = 'name="drupal-' . SolrBackendInterface::SEARCH_API_SOLR_MIN_SCHEMA_VERSION . '-solr-' . $solr_major_version . '.x"';
    $this->assertContains($config_name, $config_files['solrconfig.xml']);
    $this->assertContains($config_name, $config_files['schema.xml']);
    $this->assertContains('solr.luceneMatchVersion=' . $solr_major_version, $config_files['solrcore.properties']);
    $this->assertContains($server->id(), $config_files['test.txt']);
    $this->assertNotContains('<jmx />', $config_files['solrconfig_extra.xml']);
    if ('true' === SOLR_CLOUD) {
      $this->assertContains('<statsCache class="org.apache.solr.search.stats.LRUStatsCache" />', $config_files['solrconfig_extra.xml']);
    }
    else {
      $this->assertNotContains('<statsCache', $config_files['solrconfig_extra.xml']);
    }

    // Write files for docker to disk.
    if ('8' === $solr_major_version) {
      foreach ($config_files as $file_name => $content) {
        file_put_contents(__DIR__ . '/../../solr-conf/' . $solr_major_version . '.x/' . $file_name, $content);
      }
    }

    $backend_config['connector_config']['jmx'] = TRUE;
    $backend_config['disabled_field_types'] = ['text_foo_en_6_0_0', 'text_de_6_0_0', 'text_de_7_0_0'];
    $server->setBackendConfig($backend_config);
    $server->save();
    // Reset list builder's static cache.
    $list_builder->setServer($server);

    $config_files = $list_builder->getConfigFiles();
    $this->assertContains('<jmx />', $config_files['solrconfig_extra.xml']);
    $this->assertContains('solr.install.dir=' . $solr_install_dir, $config_files['solrcore.properties']);
    $this->assertContains('text_en', $config_files['schema_extra_types.xml']);
    $this->assertNotContains('text_foo_en', $config_files['schema_extra_types.xml']);
    $this->assertNotContains('text_de', $config_files['schema_extra_types.xml']);

    $this->assertContains('ts_X3b_en_*', $config_files['schema_extra_fields.xml']);
    $this->assertNotContains('ts_X3b_de_*', $config_files['schema_extra_fields.xml']);

    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $server->getBackend();
    if ($backend->getSolrConnector()->isCloud()) {
      $this->assertNotContains('solr.replication', $config_files['solrcore.properties']);
      $this->assertNotContains('"/replication"', $config_files['solrconfig.xml']);
      $this->assertNotContains('"/get"', $config_files['solrconfig.xml']);
    }
    else {
      $this->assertContains('solr.replication', $config_files['solrcore.properties']);
      $this->assertContains('"/replication"', $config_files['solrconfig.xml']);
      $this->assertContains('"/get"', $config_files['solrconfig.xml']);
    }
  }

  /**
   * Data provider for testConfigGeneration method.
   */
  public function configGenerationDataProvider() {
    // @codingStandardsIgnoreStart
    return [[[
      'schema_extra_types.xml' => [
        # phonetic is currently not available vor Solr 6.x.
        #'fieldType name="text_phonetic_en" class="solr.TextField"',
        'fieldType name="text_en" class="solr.TextField"',
        'fieldType name="text_de" class="solr.TextField"',
        '<fieldType name="collated_und" class="solr.ICUCollationField" locale="en" strength="primary" caseLevel="false"/>',
'<!--
  Fulltext Foo English
  6.0.0
-->
<fieldType name="text_foo_en" class="solr.TextField" positionIncrementGap="100">
  <analyzer type="index">
    <tokenizer class="solr.WhitespaceTokenizerFactory"/>
    <filter class="solr.LengthFilterFactory" min="2" max="100"/>
    <filter class="solr.LowerCaseFilterFactory"/>
    <filter class="solr.RemoveDuplicatesTokenFilterFactory"/>
  </analyzer>
  <analyzer type="query">
    <tokenizer class="solr.WhitespaceTokenizerFactory"/>
    <filter class="solr.LengthFilterFactory" min="2" max="100"/>
    <filter class="solr.LowerCaseFilterFactory"/>
    <filter class="solr.RemoveDuplicatesTokenFilterFactory"/>
  </analyzer>
  <similarity class="solr.DFRSimilarityFactory">
    <str name="basicModel">I(F)</str>
    <str name="afterEffect">B</str>
    <str name="normalization">H3</str>
    <float name="mu">900</float>
  </similarity>
</fieldType>',
      ],
      'schema_extra_fields.xml' => [
        # phonetic is currently not available vor Solr 6.x.
        #'<dynamicField name="tcphonetics_X3b_en_*" type="text_phonetic_en" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        #'<dynamicField name="tcphoneticm_X3b_en_*" type="text_phonetic_en" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        #'<dynamicField name="tocphonetics_X3b_en_*" type="text_phonetic_en" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="true" />',
        #'<dynamicField name="tocphoneticm_X3b_en_*" type="text_phonetic_en" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="true" />',
        '<dynamicField name="ts_X3b_en_*" type="text_en" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tm_X3b_en_*" type="text_en" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tos_X3b_en_*" type="text_en" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="true" />',
        '<dynamicField name="tom_X3b_en_*" type="text_en" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="true" />',
        '<dynamicField name="tus_X3b_en_*" type="text_unstemmed_en" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tum_X3b_en_*" type="text_unstemmed_en" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="ts_X3b_und_*" type="text_und" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tm_X3b_und_*" type="text_und" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tos_X3b_und_*" type="text_und" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="true" />',
        '<dynamicField name="tom_X3b_und_*" type="text_und" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="true" />',
        '<dynamicField name="tus_X3b_und_*" type="text_und" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tum_X3b_und_*" type="text_und" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tus_*" type="text_und" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tum_*" type="text_und" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="ts_X3b_de_*" type="text_de" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tm_X3b_de_*" type="text_de" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tos_X3b_de_*" type="text_de" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="true" />',
        '<dynamicField name="tom_X3b_de_*" type="text_de" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="true" />',
        '<dynamicField name="tus_X3b_de_*" type="text_unstemmed_de" stored="true" indexed="true" multiValued="false" termVectors="true" omitNorms="false" />',
        '<dynamicField name="tum_X3b_de_*" type="text_unstemmed_de" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="false" />',
        '<dynamicField name="spellcheck_und*" type="text_spell_und" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="true" />',
        '<dynamicField name="spellcheck_*" type="text_spell_und" stored="true" indexed="true" multiValued="true" termVectors="true" omitNorms="true" />',
        '<dynamicField name="sort_X3b_en_*" type="collated_en" stored="false" indexed="false" docValues="true" />',
        '<dynamicField name="sort_X3b_de_*" type="collated_de" stored="false" indexed="false" docValues="true" />',
        '<dynamicField name="sort_X3b_und_*" type="collated_und" stored="false" indexed="false" docValues="true" />',
        '<dynamicField name="sort_*" type="collated_und" stored="false" indexed="false" docValues="true" />',
      ],
      'solrconfig_extra.xml' => [
        '<str name="name">en</str>',
        '<str name="name">de</str>',
      ],
      # phonetic is currently not available vor Solr 6.x.
      #'stopwords_phonetic_en.txt' => [],
      #'protwords_phonetic_en.txt' => [],
      'stopwords_en.txt' => [],
      'synonyms_en.txt' => [
        'drupal, durpal',
      ],
      'protwords_en.txt' => [],
      'accents_en.txt' => [
        '"\u00C4" => "A"'
      ],
      'stopwords_de.txt' => [],
      'synonyms_de.txt' => [
        'drupal, durpal',
      ],
      'protwords_de.txt' => [],
      'accents_de.txt' => [
        ' Not needed if German2 Porter stemmer is used.'
      ],
      'solrcore.properties' => [],
      'elevate.xml' => [],
      'schema.xml' => [],
      'solrconfig.xml' => [],
      'test.txt' => [
        'hook_search_api_solr_config_files_alter() works'
      ],
    ]]];
    // @codingStandardsIgnoreEnd
  }

}
