<?php

/**
 * @file
 * Contains \Drupal\search_api_solr_multilingual\Tests\SearchApiSolrMultilingualTest.
 */

namespace Drupal\Tests\search_api_solr_multilingual\Kernel;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Utility;
use Drupal\Tests\search_api\Kernel\BackendTestBase;
use Drupal\Tests\search_api_solr\Kernel\SearchApiSolrTest;

/**
 * Tests index and search capabilities using the Solr search backend.
 *
 * @group search_api_solr_multilingual
 */
class SearchApiSolrMultilingualTest extends SearchApiSolrTest {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = array(
    'language',
    'search_api_solr_multilingual',
    'search_api_solr_multilingual_test',
  );

  /**
   * A Search API server ID.
   *
   * @var string
   */
  protected $serverId = 'solr_multilingual_search_server';

  /**
   * A Search API index ID.
   *
   * @var string
   */
  protected $indexId = 'solr_multilingual_search_index';


  /**
   * {@inheritdoc}
   */
  public function setUp() {
    BackendTestBase::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['search_api_solr', 'search_api_solr_multilingual', 'search_api_solr_multilingual_test']);

    $this->detectSolrAvailability();
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
    $this->assertEquals('(+ss_search_api_language:"en" +solr_x:"5")', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));

    $query = $this->buildSearch();
    $query->setLanguages(['en', 'de']);
    $condition_group = $query->createConditionGroup();
    $condition_group->addCondition('x', 5);
    $inner_condition_group = $query->createConditionGroup();
    $inner_condition_group->addCondition('y', [1, 2, 3], 'NOT IN');
    $condition_group->addConditionGroup($inner_condition_group);
    $query->addConditionGroup($condition_group);
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields]);
    $this->assertEquals('(+ss_search_api_language:"en" +(+solr_x:"5" +(*:* -solr_y:"1" -solr_y:"2" -solr_y:"3"))) (+ss_search_api_language:"de" +(+solr_x:"5" +(*:* -solr_y:"1" -solr_y:"2" -solr_y:"3")))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedFacetsOfregressionTest2469547() {
    return [
      ['count' => 4, 'filter' => '"test"'],
      ['count' => 3, 'filter' => '"case"'],
      ['count' => 2, 'filter' => '"cas"'],
      ['count' => 1, 'filter' => '"bar"'],
      ['count' => 1, 'filter' => '"foobar"'],
    ];
  }

}
