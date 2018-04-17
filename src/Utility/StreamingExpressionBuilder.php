<?php

namespace Drupal\search_api_solr\Utility;

use Drupal\search_api\IndexInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrCloudConnectorInterface;
use Solarium\QueryType\Stream\Expression;

/**
 * Provides methods for creating streaming expressions targeting a given index.
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
   * @var IndexInterface
   */
  protected $index;

  /**
   * @var string
   */
  protected $request_time;

  /**
   * @var string[]
   */
  protected $field_name_mapping;

  /**
   * @var string[]
   */
  protected $all_fields_mapped;

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
    $this->index = $index;
    $this->request_time = $backend->formatDate(\Drupal::time()->getRequestTime());
    $this->all_fields_mapped = $backend->getSolrFieldNames($index) + [
        // Search API Solr Search specific fields.
        'id' => 'id',
        'index_id' => 'index_id',
        'hash' => 'hash',
        'timestamp' => 'timestamp',
      ];
    $this->field_name_mapping = $this->all_fields_mapped + [
        // Graph traversal reserved names. We can't get a conflict here since all
        // dynamic fields are prefixed.
        'node' => 'node',
        'collection' => 'collection',
        'field' => 'field',
        'level' => 'level',
        'ancestors' => 'ancestors',
      ];
    $this->query_helper = $connector->getQueryHelper();
  }

  /**
   * Returns the Solr Cloud collection name for the current index.
   *
   * @param string $search_api_field_name
   *
   * @return string
   *   The collection name.
   */
  public function _collection() {
    return $this->collection;
  }

  /**
   * Converts a Search API field name into a Solr field name.
   *
   * @param string $search_api_field_name
   *
   * @return string
   *   The Solr field name.
   *
   * @throws \InvalidArgumentException
   */
  public function _field(string $search_api_field_name) {
    if (!isset($this->field_name_mapping[$search_api_field_name])) {
      throw new \InvalidArgumentException(sprintf('Field "%s" does not exists in index "%s".', $search_api_field_name, $this->index->id()));
    }
    return $this->field_name_mapping[$search_api_field_name];
  }

  /**
   * Formats a list of Search API field names into a string of Solr field names.
   *
   * @param array $search_api_field_names
   * @param string $delimiter
   *
   * @return string
   *   A list of Solr field names.
   */
  public function _field_list(array $search_api_field_names, string $delimiter = ',') {
    return trim(array_reduce(
      $search_api_field_names,
      function ($carry, $search_api_field_name) use ($delimiter) {
        return $carry . $this->_field($search_api_field_name) . $delimiter;
      },
      ''
    ), $delimiter);
  }

  /**
   * Formats the list of all Search API fields as a string of Solr field names.
   *
   * @param string $delimiter
   * @param array $blacklist
   *
   * @return string
   *   A list of all Solr field names for the index.
   */
  public function _all_fields_list(string $delimiter = ',', array $blacklist = []) {
    return implode($delimiter, array_diff_key($this->all_fields_mapped, array_flip($blacklist)));
  }

  /**
   * Escapes a value to be used in a Solr search query.
   *
   * @param string $value
   * @param bool $single_term
   *   Escapes the value as single term if TRUE, otherwise as phrase.
   *
   * @return string
   *   The escaped value.
   */
  public function _escaped_value(string $value, bool $single_term = TRUE) {
    return $single_term ?
      $this->query_helper->escapeTerm($value) :
      $this->query_helper->escapePhrase($value);
  }

  /**
   * Formats a field and it's value to be used in a Solr search query.
   *
   * @param string $search_api_field_name
   * @param string $value
   *
   * @return string
   *   The Solr field name and the value as 'field:value'.
   */
  public function _field_value(string $search_api_field_name, string $value) {
    return $this->_field($search_api_field_name) . ':' . $value;
  }

  /**
   * Formats a field and it's escaped value to be used in a Solr search query.
   *
   * @param string $search_api_field_name
   * @param string $value
   * @param bool $single_term
   *   Escapes the value as single term if TRUE, otherwise as phrase.
   *
   * @return string
   *   The Solr field name and the escaped value as 'field:value'.
   */
  public function _field_escaped_value(string $search_api_field_name, string $value, bool $single_term = TRUE) {
    return $this->_field($search_api_field_name) . ':' . $this->_escaped_value($value, $single_term);
  }

  /**
   * Eases intersect() streaming expressions by applying required sorts.
   *
   * @param string $stream1
   *  A streaming expression as string.
   * @param string $stream2
   *  A streaming expression as string.
   * @param string $field
   *  The Search API field name or Solr reserved field name to use for the
   *  intersection.
   *
   * @return string
   *  A chainable streaming expression as string.
   */
  public function _intersect(string $stream1, string $stream2, string $field) {
    $solr_field = $this->_field($field);
    return
      $this->intersect(
        $this->sort(
          $stream1,
          'by="' . $solr_field . ' ASC"'
        ),
        $this->sort(
          $stream2,
          'by="' . $solr_field . ' ASC"'
        ),
        'on=' . $solr_field
      );
  }

  /**
   * Eases search() streaming expressions if all results are required.
   *
   * Internally this function switches to the /export query type by default. But
   * if you run into errors like "field XY requires DocValues" you should use
   * _search_all().
   *
   * @return string
   *  A chainable streaming expression as string.
   */
  public function _export_all() {
    return
      $this->search(
        $this->_collection(),
        implode(', ', func_get_args()),
        // Compared to the default query handler, the export query handler
        // doesn't limit the number of results.
        'qt="/export"'
      );
  }

  /**
   * Eases search() streaming expressions if all results are required.
   *
   * Internally this function uses the default /select query type and sets the
   * rows parameter "to be 10000000 or some other ridiculously large value that
   * is higher than the possible number of rows that are expected".
   * @see https://wiki.apache.org/solr/CommonQueryParameters
   * @see https://lucene.apache.org/solr/guide/7_3/stream-source-reference.html
   *
   * @return string
   *  A chainable streaming expression as string.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function _search_all() {
    return
      $this->search(
        $this->_collection(),
        implode(', ', func_get_args()),
        'rows=' . ($this->index->getTrackerInstance()->getTotalItemsCount() * 10)
      );
  }

  /**
   * @param $stream
   *
   * @return string
   *  A chainable streaming expression as string.
   */
  public function _update($stream) {
    return $this->update(
      $this->_collection(),
      'batchSize=500',
      $stream
    );
  }

  /**
   * @param $stream
   *
   * @return string
   *  A chainable streaming expression as string.
   */
  public function _commit($stream) {
    return $this->commit(
      $this->_collection(),
      'softCommit=true',
      $stream
    );
  }

  /**
   * @param $stream
   *
   * @return string
   *  A chainable streaming expression as string.
   */
  public function _commit_update($stream) {
    return $this->_commit(
      $this->_update($stream)
    );
  }

  /**
   * Returns a Solr filter query to limit results to the current index.
   *
   * @return string
   *   The filter query ready to use for the 'fq' parameter.
   */
  public function _index_filter_query() {
    return $this->index_filter_query;
  }

  /**
   * Returns the ID of the current index.
   *
   * @return string
   *   The index ID.
   */
  public function _index_id() {
    return $this->index->id();
  }

  /**
   * Returns the Search API Solr Search site hash of the drupal installation.
   *
   * @see Utility::getSiteHash()
   *
   * @return string
   *   The site hash.
   */
  public function _site_hash() {
    return Utility::getSiteHash();
  }

  /**
   * @return string
   */
  public function _request_time() {
    return $this->request_time;
  }

  public function _timestamp_value() {
    return 'val(' . $this->request_time . ') as timestamp';
  }
}
