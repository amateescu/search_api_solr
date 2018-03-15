<?php

namespace Drupal\search_api_solr\Utility;

use Drupal\search_api\IndexInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrCloudConnectorInterface;
use Solarium\QueryType\Stream\Expression;

/**
 * Provides methods for creating search queries and statically caching results.
 */
class StreamingExpressionBuilder extends Expression {

  /**
   * @var string
   */
  protected $collection;

  /**
   * @var string
   */
  protected $index_filter_query;

  /**
   * @var string
   */
  protected $index_id;

  /**
   * @var string[]
   */
  protected $field_name_mapping;

  /**
   * @var \Solarium\Core\Query\Helper
   */
  protected $query_helper;

  /**
   * StreamingExpressionBuilder constructor.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function __construct(IndexInterface $index) {
    $server = $index->getServerInstance();
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $server->getBackend();
    $connector = $backend->getSolrConnector();

    if (!($connector instanceof SolrCloudConnectorInterface)) {
      throw new SearchApiSolrException('Streaming expression are only supported by a Solr Cloud connector.');
    }

    $this->collection = $connector->getCollectionName();
    $this->index_filter_query = $backend->getIndexFilterQueryString($index);
    $this->index_id = $index->id();
    $this->field_name_mapping = $backend->getSolrFieldNames($index) + [
        'index_id' => 'index_id',
        'hash' => 'hash',
        'timestamp' => 'timestamp',
      ];
    $this->query_helper = $connector->getQueryHelper();
  }

  /**
   * @param string $search_api_field_name
   *
   * @return string
   */
  public function _collection() {
    return $this->collection;
  }

  /**
   * @param string $search_api_field_name
   *
   * @return string
   */
  public function _field(string $search_api_field_name) {
    return $this->field_name_mapping[$search_api_field_name];
  }

  /**
   * @param array $search_api_field_names
   * @param string $delimiter
   *
   * @return string
   */
  public function _field_list(array $search_api_field_names, $delimiter = ',') {
    return trim(array_reduce(
      $search_api_field_names,
      function ($carry, $search_api_field_name) use ($delimiter) {
        return $carry . $this->_field($search_api_field_name) . $delimiter;
      },
      ''
    ), $delimiter);
  }

  /**
   * @param string $value
   * @param bool $single_term
   *
   * @return string
   */
  public function _escaped_value(string $value, bool $single_term = TRUE) {
    return $single_term ?
      $this->query_helper->escapeTerm($value) :
      $this->query_helper->escapePhrase($value);
  }

  /**
   * @param string $search_api_field_name
   * @param string $value
   *
   * @return string
   */
  public function _field_value(string $search_api_field_name, string $value) {
    return $this->_field($search_api_field_name) . ':' . $value;
  }

  /**
   * @param string $search_api_field_name
   * @param string $value
   * @param bool $single_term
   *
   * @return string
   */
  public function _field_escaped_value(string $search_api_field_name, string $value, bool $single_term = TRUE) {
    return $this->_field($search_api_field_name) . ':' . $this->_escaped_value($value, $single_term);
  }

  /**
   * @return string
   */
  public function _index_filter_query() {
    return $this->index_filter_query;
  }

  /**
   * @return string
   */
  public function _index_id() {
    return $this->index_id;
  }

  /**
   * @return string
   */
  public function _site_hash() {
    return Utility::getSiteHash();
  }
}
