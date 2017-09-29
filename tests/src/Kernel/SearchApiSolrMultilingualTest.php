<?php

namespace Drupal\Tests\search_api_solr_multilingual\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Utility\Utility;
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
   * Tests language limiting via options.
   */
  public function testLanguageLimitedByOptions() {
    // Only run further tests if we have a Solr core available.
    if ($this->solrAvailable) {
      $this->insertMultilingualExampleContent();
      $this->indexItems($this->indexId);

      $server = $this->getIndex()->getServerInstance();
      $config = $server->getBackendConfig();

      $config['sasm_limit_search_page_to_content_language'] = FALSE;
      $server->setBackendConfig($config)->save();
      $this->assertFalse($this->getIndex()->getServerInstance()->getBackendConfig()['sasm_limit_search_page_to_content_language']);

      $config['sasm_search_page_include_language_independent'] = FALSE;
      $server->setBackendConfig($config)->save();
      $this->assertFalse($this->getIndex()->getServerInstance()->getBackendConfig()['sasm_search_page_include_language_independent']);

      // Stemming "en":
      // gene => gene
      // genes => gene
      //
      // Stemming "de":
      // Gen => gen
      // Gene => gen
      $results = $this->buildSearch('gene', [], ['body'])->execute();
      $this->assertResults([1 => 'en', 2 => 'en', 3 => 'de', 4 => 'de', 5 => 'de-at', 6 => 'de-at'], $results, 'Search all languages for "gene".');

      $config['sasm_limit_search_page_to_content_language'] = TRUE;
      $server->setBackendConfig($config)->save();
      $this->assertTrue($this->getIndex()->getServerInstance()->getBackendConfig()['sasm_limit_search_page_to_content_language']);

      // Current content language is "en".
      $results = $this->buildSearch('gene', [], ['body'])->execute();
      $this->assertResults([1 => 'en', 2 => 'en'], $results, 'Search content language for "gene".');

      // A query created by Views must not be overruled.
      $results = $this->buildSearch('gene', [], ['body'])->addTag('views')->execute();
      $this->assertResults([1 => 'en', 2 => 'en', 3 => 'de', 4 => 'de', 5 => 'de-at', 6 => 'de-at'], $results, 'Search all languages for "gene".');

      $config['sasm_search_page_include_language_independent'] = TRUE;
      $server->setBackendConfig($config)->save();
      $this->assertTrue($this->getIndex()->getServerInstance()->getBackendConfig()['sasm_search_page_include_language_independent']);

      $results = $this->buildSearch('gene', [], ['body'])->execute();
      $this->assertResults([1 => 'en', 2 => 'en', 7 => LanguageInterface::LANGCODE_NOT_SPECIFIED, 8 => LanguageInterface::LANGCODE_NOT_APPLICABLE], $results, 'Search content and unspecified language for "gene".');

      $config['sasm_limit_search_page_to_content_language'] = FALSE;
      $server->setBackendConfig($config)->save();
      $this->assertFalse($this->getIndex()->getServerInstance()->getBackendConfig()['sasm_limit_search_page_to_content_language']);

      $results = $this->buildSearch('gene', [], ['body'])->execute();
      $this->assertResults([1 => 'en', 2 => 'en', 3 => 'de', 4 => 'de', 5 => 'de-at', 6 => 'de-at', 7 => LanguageInterface::LANGCODE_NOT_SPECIFIED, 8 => LanguageInterface::LANGCODE_NOT_APPLICABLE], $results, 'Search all and unspecified languages for "gene".');
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
    $this->addTestEntity(7, array(
      'name' => 'und 7',
      'body' => 'gene',
      'type' => 'item',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ));
    $this->addTestEntity(8, array(
      'name' => 'zxx 8',
      'body' => 'gene',
      'type' => 'item',
      'langcode' => LanguageInterface::LANGCODE_NOT_APPLICABLE,
    ));
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

}
