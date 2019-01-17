<?php

namespace Drupal\search_api_solr\Utility;

use Drupal\Core\Language\LanguageInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrCloudConnectorInterface;
use Drupal\search_api_solr\SolrProcessorInterface;
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
   * @var string
   */
  protected $targeted_index_id;

  /**
   * @var string
   */
  protected $targeted_site_hash;

  /**
   * @var IndexInterface
   */
  protected $index;

  /**
   * @var string
   */
  protected $request_time;

  /**
   * @var string[][]
   */
  protected $all_fields_including_graph_fields_mapped;

  /**
   * @var string[][]
   */
  protected $all_fields_mapped;

  /**
   * @var string[][]
   */
  protected $all_doc_value_fields_mapped;

  /**
   * @var string[][]
   */
  protected $sort_fields_mapped;

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

    $language_ids = array_merge(array_keys(\Drupal::languageManager()->getLanguages()), [LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $this->collection = $connector->getCollectionName();
    $this->index_filter_query = $backend->getIndexFilterQueryString($index);
    $this->targeted_index_id = $backend->getTargetedIndexId($index);
    $this->targeted_site_hash = $backend->getTargetedSiteHash($index);
    $this->index = $index;
    $this->request_time = $backend->formatDate(\Drupal::time()->getRequestTime());
    $this->all_fields_mapped = [];
    foreach ($backend->getSolrFieldNamesKeyedByLanguage($language_ids, $index) as $search_api_field => $solr_field) {
      foreach ($solr_field as $language_id => $solr_field_name) {
        $this->all_fields_mapped[$language_id][$search_api_field] = $solr_field_name;
      }
    }
    foreach ($language_ids as $language_id) {
      $this->all_fields_mapped[$language_id] += [
        // Search API Solr Search specific fields.
        'id' => 'id',
        'index_id' => 'index_id',
        'hash' => 'hash',
        'site' => 'site',
        'timestamp' => 'timestamp',
        'context_tags' => 'sm_context_tags',
        // @todo to be removed
        'spell' => 'spell',
      ];
      $this->all_fields_including_graph_fields_mapped[$language_id] = $this->all_fields_mapped[$language_id] + [
        // Graph traversal reserved names. We can't get a conflict here since all
        // dynamic fields are prefixed.
        'node' => 'node',
        'collection' => 'collection',
        'field' => 'field',
        'level' => 'level',
        'ancestors' => 'ancestors',
      ];
      $this->sort_fields_mapped[$language_id] = [];
      foreach ($this->all_fields_mapped[$language_id] as $search_api_field => $solr_field) {
        if (strpos($solr_field, 't') === 0 || strpos($solr_field, 's') === 0) {
          $this->sort_fields_mapped[$language_id]['sort_' . $search_api_field] = 'sort_' . Utility::encodeSolrName($search_api_field);
        }
        elseif (preg_match('/^([a-z]+)m(_.*)/', $solr_field, $matches) && strpos($solr_field, 'random_') !== 0) {
          $this->sort_fields_mapped[$language_id]['sort' . Utility::decodeSolrName($matches[2])] = $matches[1] . 's' . $matches[2];
        }

        if (
          strpos($solr_field, 's') === 0 ||
          strpos($solr_field, 'i') === 0 ||
          strpos($solr_field, 'f') === 0 ||
          strpos($solr_field, 'p') === 0 ||
          strpos($solr_field, 'b') === 0 ||
          strpos($solr_field, 'h') === 0
        ) {
          $this->all_doc_value_fields_mapped[$language_id][$search_api_field] = $solr_field;
        }
      }
    }

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
   * @param string $language_id
   *
   * @throws \InvalidArgumentException
   */
  public function _field(string $search_api_field_name, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    if (!isset($this->all_fields_including_graph_fields_mapped[$language_id][$search_api_field_name])) {
      if (isset($this->sort_fields_mapped[$language_id][$search_api_field_name])) {
        return $this->sort_fields_mapped[$language_id][$search_api_field_name];
      }
      else {
        throw new \InvalidArgumentException(sprintf('Field %s does not exist in index %s.', $search_api_field_name, $this->targeted_index_id));
      }
    }
    return $this->all_fields_including_graph_fields_mapped[$language_id][$search_api_field_name];
  }

  /**
   * Formats a list of Search API field names into a string of Solr field names.
   *
   * @param array $search_api_field_names
   * @param string $delimiter
   * @param string $language_id
   *
   * @return string
   *   A list of Solr field names.
   */
  public function _field_list(array $search_api_field_names, string $delimiter = ',', string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    return trim(array_reduce(
      $search_api_field_names,
      function ($carry, $search_api_field_name) use ($delimiter, $language_id) {
        return $carry . $this->_field($search_api_field_name, $language_id) . $delimiter;
      },
      ''
    ), $delimiter);
  }

  /**
   * Formats the list of all Search API fields as a string of Solr field names.
   *
   * @param string $delimiter
   * @param bool $include_sorts
   * @param array $blacklist
   * @param string $language_id
   *
   * @return string
   *   A list of all Solr field names for the index.
   */
  public function _all_fields_list(string $delimiter = ',', bool $include_sorts = TRUE, array $blacklist = [], string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    return implode($delimiter, array_diff_key(
      ($include_sorts ? array_merge($this->all_fields_mapped[$language_id], $this->sort_fields_mapped[$language_id]) : $this->all_fields_mapped[$language_id]),
      array_flip($blacklist))
    );
  }

  /**
   * Formats the list of all Search API fields as a string of Solr field names.
   *
   * @param string $delimiter
   * @param bool $include_sorts
   * @param array $blacklist
   * @param string $language_id
   *
   * @return string
   *   A list of all Solr field names for the index.
   */
  public function _all_doc_value_fields_list(string $delimiter = ',', bool $include_sorts = TRUE, array $blacklist = [], string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    return implode($delimiter, array_diff_key(
      // All sort fields have docValues.
      ($include_sorts ? array_merge($this->all_doc_value_fields_mapped[$language_id], $this->sort_fields_mapped[$language_id]) : $this->all_doc_value_fields_mapped[$language_id]),
      array_flip($blacklist))
    );
  }

  /**
   * Escapes a value to be used in a Solr streaming expression.
   *
   * @param string $value
   * @param bool $single_term
   *   Escapes the value as single term if TRUE, otherwise as phrase.
   * @param string $search_api_field_name If provided the method will use it to check for each processor whether the
   *  it is supposed to be run on the value.  If the the name is not provided no processor will act on the value.
   *
   * @return string
   *   The escaped value.
   */
  public function _escaped_value(string $value, bool $single_term = TRUE, string $search_api_field_name = NULL) {
    if (is_string($value) && $search_api_field_name) {
      foreach ($this->index->getProcessorsByStage(ProcessorInterface::STAGE_PREPROCESS_QUERY) as $processor) {
        if ($processor instanceof SolrProcessorInterface) {
          $configuration = $processor->getConfiguration();
          if (in_array($search_api_field_name, $configuration['fields'])) {
            $value = $processor->encodeStreamingExpressionValue($value) ?: $value;
          }
        }
      }
    }
    $escaped_string = $single_term ?
      $this->query_helper->escapeTerm($value) :
      $this->query_helper->escapePhrase($value);
    // If the escaped strings are to be used inside a streaming expression double quotes need to be escaped once more
    // (e.g. q="field:\"word1 word2\"").
    // See also https://issues.apache.org/jira/browse/SOLR-8409
    $escaped_string = str_replace('"', '\\"', $escaped_string);
    return $escaped_string;
  }

  /**
   * Formats a field and its value to be used in a Solr streaming expression.
   *
   * @param string $search_api_field_name
   * @param string $value
   * @param string $language_id
   *
   * @return string
   *   The Solr field name and the value as 'field:value'.
   */
  public function _field_value(string $search_api_field_name, string $value, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    return $this->_field($search_api_field_name, $language_id) . ':' . $value;
  }

  /**
   * Formats a field and its escaped value to be used in a Solr streaming expression.
   *
   * @param string $search_api_field_name
   * @param string $value
   * @param bool $single_term
   *   Escapes the value as single term if TRUE, otherwise as phrase.
   * @param string $language_id
   *
   * @return string
   *   The Solr field name and the escaped value as 'field:value'.
   */
  public function _field_escaped_value(string $search_api_field_name, string $value, bool $single_term = TRUE, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    return $this->_field($search_api_field_name, $language_id) . ':' . $this->_escaped_value($value, $single_term, $search_api_field_name);
  }

  /**
   * Calls _escaped_value on each array element and returns the imploded result.
   *
   * @param string $glue The string to put between the escaped values.
   *   This can be used to create an "or" condition from the array of values,
   *   for example, by passing the string ' || ' as glue.
   * @param array $values The array of values to escape
   * @param bool $single_term Whether to escape as a single term or as a phrase.
   * @param string $search_api_field_name Passed on to _escaped_value();
   *   influences whether processors act on the values.
   * @param string $language_id
   *
   * @return string The imploded string of escaped values.
   */
  public function _escape_and_implode(string $glue, array $values, $single_term = TRUE, string $search_api_field_name = NULL, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    $escaped_values = [];
    foreach ($values as $value) {
      $escaped_values[] = $this->_escaped_value($value, $single_term, $search_api_field_name, $language_id);
    }
    return implode($glue, $escaped_values);
  }

  /**
   * Rename a field within select().
   *
   * @param string $search_api_field_name_source
   * @param string $search_api_field_name_target
   * @param string $language_id
   *
   * @return string
   */
  public function _select_renamed_field(string $search_api_field_name_source, string $search_api_field_name_target, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    return
      $this->_field($search_api_field_name_source, $language_id) . ' as ' . $this->_field($search_api_field_name_target, $language_id);
  }

  /**
   * Copy a field's value to a different field within select().
   *
   * @param string $search_api_field_name_source
   * @param string $search_api_field_name_target
   * @param string $language_id
   *
   * @return string
   */
  public function _select_copied_field(string $search_api_field_name_source, string $search_api_field_name_target, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    return
      $this->concat(
        'fields="' . $this->_field($search_api_field_name_source, $language_id) . '"',
        // Delimiter must be set but is ignored if just one field is provided.
        'delim=","',
        'as="'. $this->_field($search_api_field_name_target, $language_id) .'"'
      );
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
   * @param string $language_id
   *
   * @return string
   *  A chainable streaming expression as string.
   */
  public function _intersect(string $stream1, string $stream2, string $field, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    $solr_field = $this->_field($field, $language_id);
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
   * Eases merge() streaming expressions by applying required sorts.
   *
   * @param string $stream1
   *  A streaming expression as string.
   * @param string $stream2
   *  A streaming expression as string.
   * @param string $field
   *  The Search API field name or Solr reserved field name to use for the
   *  intersection.
   * @param string $language_id
   *
   * @return string
   *  A chainable streaming expression as string.
   */
  public function _merge(string $stream1, string $stream2, string $field, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    $solr_field = $this->_field($field, $language_id);
    return
      $this->merge(
        $this->sort(
          $stream1,
          'by="' . $solr_field . ' ASC"'
        ),
        $this->sort(
          $stream2,
          'by="' . $solr_field . ' ASC"'
        ),
        'on="' . $solr_field . ' ASC"'
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
    static $rows = 0;

    if (!$rows) {
      // The _search_all() streaming expression needs a row limit that much higher
      // then the real number of rows. Therefore we set the max 32bit integer as
      // default. To maximize the number of query result cache hits it is
      // important to not vary this number (often). But to enable you to fine
      // tune your setting, the number is stored as a state.
      $rows = \Drupal::state()
        ->get('search_api_solr.' . $this->targeted_index_id . '.search_all_rows', 2147483647);
    }

    return
      $this->search(
        $this->_collection(),
        implode(', ', func_get_args()),
        'rows=' . $rows
      );
  }

  /**
   * Applies the update decorator to the incoming stream.
   *
   * @param string $stream
   * @param array $options
   *   The option keys are the ones from the Solr documentation, prefixed with
   *   "update.".
   * @see https://lucene.apache.org/solr/guide/7_3/stream-decorator-reference.html#update
   *
   * @return string
   *  A chainable streaming expression as string.
   */
  public function _update(string $stream, array $options = []) {
    $options += [
      'update.batchSize' => 500,
    ];
    return $this->update(
      $this->_collection(),
      'batchSize=' . $options['update.batchSize'],
      $stream
    );
  }

  /**
   * Applies the commit decorator to the incoming stream.
   *
   * @param string $stream
   * @param array $options
   *   The option keys are the ones from the Solr documentation, prefixed with
   *   "commit.".
   * @see https://lucene.apache.org/solr/guide/7_3/stream-decorator-reference.html#commit
   *
   * @return string
   *  A chainable streaming expression as string.
   */
  public function _commit(string $stream, array $options = []) {
    $options += [
      'commit.batchSize'    => 0,
      'commit.waitFlush'    => FALSE,
      'commit.waitSearcher' => FALSE,
      'commit.softCommit'   => FALSE,
    ];
    return $this->commit(
      $this->_collection(),
      'batchSize=' .  $options['commit.batchSize'],
      'waitFlush=' . ($options['commit.waitFlush'] ? 'true' : 'false'),
      'waitSearcher=' . ($options['commit.waitSearcher'] ? 'true' : 'false'),
      'softCommit=' . ($options['commit.softCommit'] ? 'true' : 'false'),
      $stream
    );
  }

  /**
   * A shorthand for _update() and _commit().
   * @param string $stream
   * @param array $options
   *
   * @return string
   *  A chainable streaming expression as string.
   */
  public function _commit_update(string $stream, array $options = []) {
    return $this->_commit(
      $this->_update($stream, $options),
      $options
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
   * Returns the ID of the targeted index.
   *
   * @return string
   *   The index ID.
   */
  public function _index_id() {
    return $this->targeted_index_id;
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
    return $this->targeted_site_hash;
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
