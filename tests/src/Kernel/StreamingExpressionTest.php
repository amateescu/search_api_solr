<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api_solr\Utility\Utility;

/**
 * Provides tests for building streaming expressions.
 *
 * @group search_api_solr
 */
class StreamingExpressionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field',
    'entity_test',
    'search_api',
    'search_api_solr',
    'search_api_solr_test',
    'search_api_test_example_content',
    'text',
    'user',
  ];

  /**
   * @var \Drupal\search_api_solr\Utility\StreamingExpressionQueryHelper
   */
  protected $queryHelper;

  /**
   * @var \Drupal\search_api\Query\Query
   */
  protected $query;

  /**
   * @var \Drupal\search_api_solr\Utility\StreamingExpressionBuilder
   */
  protected $exp;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installSchema('user', ['users_data']);

    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');

    $this->installConfig([
      'search_api_test_example_content',
      'search_api',
      'search_api_solr',
      'search_api_solr_test',
    ]);

    $this->index = Index::load('solr_search_index');
    $backend = $this->index->getServerInstance()->getBackend();
    $config = $backend->getConfiguration();
    // Streaming expressions are only supported by Solr Cloud.
    $config['connector'] = 'solr_cloud';
    $backend->setConfiguration($config);
    $this->queryHelper = \Drupal::getContainer()->get('search_api_solr.streaming_expression_query_helper');
    $this->query = $this->queryHelper->createQuery($this->index);
    $this->exp = $this->queryHelper->getStreamingExpressionBuilder($this->query);
  }

  /**
   * Tests streaming expression builder.
   */
  public function testStreamingExpressionBuilder() {
    $streaming_expression =
      $this->exp->select(
        $this->exp->search(
          $this->exp->_collection(),
          'q=' . $this->exp->_field_escaped_value('search_api_datasource', 'entity:entity_test_mulrev_changed'),
          'fq="' . $this->exp->_index_filter_query() . '"',
          'fl="' . $this->exp->_field_list(['name', 'body', 'created']) . '"',
          'sort="' . $this->exp->_field('created') . ' DESC"',
          'qt="/export"'
        ),
        $this->exp->_field_list(['name', 'body'])
      );

    $this->assertEquals(
      'select(search(d8, q=ss_search_api_datasource:entity\:entity_test_mulrev_changed, fq="+index_id:solr_search_index +hash:' . Utility::getSiteHash() . '", fl="tm_name,tm_body,ds_created", sort="ds_created DESC", qt="/export"), tm_name,tm_body)',
      $streaming_expression
    );
  }
}
