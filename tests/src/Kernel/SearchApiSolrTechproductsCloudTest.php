<?php

namespace Drupal\Tests\search_api_solr\Kernel;

/**
 * Tests the 'Any Schema' Solr search backend.
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
    $query1 = $queryHelper->createQuery($index);
    $query2 = $queryHelper->createQuery($index);
    $exp = $queryHelper->getStreamingExpressionBuilder($query1);

    $topic_expression = $exp->_topic(
      $exp->_checkpoint('all_products'),
      'q="*:*"',
      'fl="' . $exp->_field('search_api_id') . '"'
    );

    $queryHelper->setStreamingExpression($query1, $topic_expression);
    $results = $query1->execute();
    $this->assertEquals(32, $results->getResultCount());

    $queryHelper->setStreamingExpression($query2, $topic_expression);
    $results = $query2->execute();
    $this->assertEquals(0, $results->getResultCount());
  }
}
