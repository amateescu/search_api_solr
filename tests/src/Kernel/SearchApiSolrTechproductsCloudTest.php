<?php

namespace Drupal\Tests\search_api_solr\Kernel;

/**
 * Tests the document datasources using the solr techproducts example.
 *
 * @group search_api_solr
 * @group solr_cloud
 */
class SearchApiSolrTechproductsCloudTest extends AbstractSearchApiSolrTechproductsTest {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'search_api_solr_cloud_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function installConfigs() {
    parent::installConfigs();

    $this->installConfig([
      'search_api_solr_cloud_test',
    ]);
  }

  public function testTopicStreamingExpressions() {
    try {
      $this->firstSearch();
    } catch (\Exception $e) {
      $this->markTestSkipped('Techproducts example not reachable.');
    }

    $index = $this->getIndex();

    /** @var \Drupal\search_api_solr\Utility\StreamingExpressionQueryHelper $queryHelper */
    $queryHelper = \Drupal::service('search_api_solr.streaming_expression_query_helper');
    $query = $queryHelper->createQuery($index);
    $exp = $queryHelper->getStreamingExpressionBuilder($query);

    $topic_expression = $exp->_topic_all(
      $exp->_checkpoint('all_products'),
      'q="*:*"',
      'fl="' . $exp->_field('search_api_id') . '"'
    );

    $queryHelper->setStreamingExpression($query, $topic_expression);
    $results = $query->execute();
    $this->assertEquals(32, $results->getResultCount());

    $query = $queryHelper->createQuery($index);
    $queryHelper->setStreamingExpression($query, $topic_expression);
    $results = $query->execute();
    $this->assertEquals(0, $results->getResultCount());

    $topic_expression = $exp->_topic(
      $exp->_checkpoint('20_products'),
      'q="*:*"',
      'fl="' . $exp->_field('search_api_id') . '"',
      // Rows per shard!
      'rows="10"'
    );
    $query = $queryHelper->createQuery($index);
    $queryHelper->setStreamingExpression($query, $topic_expression);
    $results = $query->execute();
    // We have two shards for techproducts. Both return 10 rows.
    $this->assertEquals(20, $results->getResultCount());

    $query = $queryHelper->createQuery($index);
    $queryHelper->setStreamingExpression($query, $topic_expression);
    $results = $query->execute();
    $this->assertEquals(12, $results->getResultCount());

    $query = $queryHelper->createQuery($index);
    $queryHelper->setStreamingExpression($query, $topic_expression);
    $results = $query->execute();
    $this->assertEquals(0, $results->getResultCount());
  }

}
