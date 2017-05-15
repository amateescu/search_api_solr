<?php

namespace Drupal\Tests\search_api_solr_multilingual\Kernel;

use Drupal\search_api\Entity\Server;
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
   * The mocked logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    BackendTestBase::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['search_api_solr', 'search_api_solr_multilingual', 'search_api_solr_multilingual_test']);

    $this->logger = $this->getMock('Psr\Log\LoggerInterface');
    $this->logger->method('log')->willThrowException(new \Exception('logger triggered'));
    \Drupal::getContainer()->get('logger.factory')->addLogger($this->logger);

    $this->detectSolrAvailability();

    $this->fieldsHelper = \Drupal::getContainer()->get('search_api.fields_helper');
  }

  /**
   * {@inheritdoc}
   */
  public function testAutocomplete() {
    // @todo
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
    $fq = $this->invokeMethod($backend, 'getFilterQueries', [$query, $mapping, $fields, &$options]);
    $this->assertEquals('(+ss_search_api_language:"en" +(+solr_x:"5" +(*:* -solr_y:"1" -solr_y:"2" -solr_y:"3"))) (+ss_search_api_language:"de" +(+solr_x:"5" +(*:* -solr_y:"1" -solr_y:"2" -solr_y:"3")))', $fq[0]['query']);
    $this->assertFalse(isset($fq[1]));
  }

  /**
   * Tests classic multilingual schema.
   */
  public function testClassicMultilingualSchema() {
    /** @var Drupal\search_api_solr_multilingual\Controller\SolrFieldTypeListBuilder $list_builder */
    $list_builder = \Drupal::entityTypeManager()
      ->getListBuilder('solr_field_type');

    // @todo
  }

  /**
   * Tests language fallback.
   */
  public function testLanguageFallback() {
    $server = $this->getIndex()->getServerInstance();
    $config = $server->getBackendConfig();
    // $server->setBackendConfig(['solr_version' => '4.5.1'] + $server->getBackendConfig());

    // Only run further tests if we have a Solr core available.
    if ($this->solrAvailable) {
      $config['sasm_language_unspecific_fallback_on_schema_issues'] = FALSE;
      $server->setBackendConfig($config)->save();
      $this->assertFalse($this->getIndex()->getServerInstance()->getBackendConfig()['sasm_language_unspecific_fallback_on_schema_issues']);

      $this->insertMultilingualExampleContent();

      try {
        $this->indexItems($this->indexId);
        $this->fail('Indexing a non-existing language without fallback enabled did not throw an exception.');
      }
      catch (\Exception $e) {
        $this->assertEquals('logger triggered', $e->getMessage());
      }

      $this->clearIndex();

      $config['sasm_language_unspecific_fallback_on_schema_issues'] = TRUE;
      $server->setBackendConfig($config)->save();
      $this->assertTrue($this->getIndex()->getServerInstance()->getBackendConfig()['sasm_language_unspecific_fallback_on_schema_issues']);

      $this->indexItems($this->indexId);

      $results = $this->buildSearch()->execute();
      $this->assertEquals(6, $results->getResultCount(), 'Number of indexed entities is correct.');

      // Stemming "en":
      // gene => gene
      // genes => gene
      //
      // Stemming "de":
      // Gen => gen
      // Gene => gen
      $query = $this->buildSearch('Gen');
      $query->setLanguages(['en', 'de']);
      $results = $query->execute();
      $this->assertEquals(2, $results->getResultCount(), 'Two results for "Gen" in German entities. No results for "Gen" in English entities.');

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
    }
    else {
      $this->assertTrue(TRUE, 'Error: The Solr instance could not be found. Please enable a multi-core one on http://localhost:8983/solr/d8');
    }

  }

  /**
   * Creates several test entities.
   */
  protected function insertMultilingualExampleContent() {
    $this->addTestEntity(1, array(
      'name' => 'en 1',
      'body' => 'gene',
      'type' => 'item',
      'langcode' => 'en',
    ));
    $this->addTestEntity(2, array(
      'name' => 'en 2',
      'body' => 'genes',
      'type' => 'item',
      'langcode' => 'en',
    ));
    $this->addTestEntity(3, array(
      'name' => 'de 3',
      'body' => 'Gen',
      'type' => 'item',
      'langcode' => 'de',
    ));
    $this->addTestEntity(4, array(
      'name' => 'de 4',
      'body' => 'Gen',
      'type' => 'item',
      'langcode' => 'de',
    ));
    $this->addTestEntity(5, array(
      'name' => 'de-at 5',
      'body' => 'Gen',
      'type' => 'item',
      'langcode' => 'de-at',
    ));
    $this->addTestEntity(6, array(
      'name' => 'de-at 6',
      'body' => 'Gen',
      'type' => 'item',
      'langcode' => 'de-at',
    ));
    $count = \Drupal::entityQuery('entity_test_mulrev_changed')->count()->execute();
    $this->assertEquals(6, $count, "$count items inserted.");
  }

}
