<?php

namespace Drupal\search_api_solr\Utility;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\QueryHelper;

/**
 * Provides methods for creating streaming expressions.
 */
class StreamingExpressionQueryHelper extends QueryHelper {

  /**
   * @param \Drupal\search_api\Query\QueryInterface $query
   *
   * @return \Drupal\search_api_solr\Utility\StreamingExpressionBuilder
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getStreamingExpressionBuilder(QueryInterface $query) {
    return new StreamingExpressionBuilder($query->getIndex());
  }

  /**
   * @param \Drupal\search_api\Query\QueryInterface $query
   * @param string $streaming_expression
   */
  public function setStreamingExpression(QueryInterface $query, string $streaming_expression) {
    $query->setOption('solr_streaming_expression', $streaming_expression);
  }
}
