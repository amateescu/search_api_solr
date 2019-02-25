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
    $query = $queryHelper->createQuery($index);
    $exp = $queryHelper->getStreamingExpressionBuilder($query);

    $topic_expression = $exp->_topic(
      $exp->_checkpoint('new_documents'),
      'q="*:*"',
      'fl="' . $exp->_field('search_api_id') . '"'
    );

    $queryHelper->setStreamingExpression($query, $topic_expression);
    $results = $query->execute();
    $this->assertEquals(32, $results->getResultCount());
    $results = $query->execute();
    $this->assertEquals(0, $results->getResultCount());
  }
}
