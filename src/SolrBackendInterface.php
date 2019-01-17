<?php

namespace Drupal\search_api_solr;

use Drupal\search_api\Backend\BackendInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Query\QueryInterface;
use Solarium\QueryType\Update\Query\Query as UpdateQuery;

/**
 * Defines an interface for Solr search backend plugins.
 *
 * It extends the generic \Drupal\search_api\Backend\BackendInterface and covers
 * additional Solr specific methods.
 */
interface SolrBackendInterface extends BackendInterface {

  /**
   * The minimum required Solr schema version.
   */
  const SEARCH_API_SOLR_MIN_SCHEMA_VERSION = '8.3.0';

  /**
   * The separator to indicate the start of a language ID. We must not use any
   * character that has a special meaning within regular expressions. Additionally
   * we have to avoid characters that are valid for Drupal machine names.
   * The end of a language ID is indicated by an underscore '_' which could not
   * occur within the language ID itself because Drupal uses lanague tags.
   *
   * @see http://de2.php.net/manual/en/regexp.reference.meta.php
   * @see https://www.w3.org/International/articles/language-tags/
   */
  const SEARCH_API_SOLR_LANGUAGE_SEPARATOR = ';';

  /**
   * Creates a list of all indexed field names mapped to their Solr field names.
   *
   * The special fields "search_api_id" and "search_api_relevance" are also
   * included. Any Solr fields that exist on search results are mapped back to
   * to their local field names in the final result set.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search Api index.
   * @param bool $reset
   *   (optional) Whether to reset the static cache.
   *
   * @see SearchApiSolrBackend::search()
   */
  public function getSolrFieldNames(IndexInterface $index, $reset = FALSE);

  /**
   * Gets a language-specific mapping from Drupal to Solr field names.
   *
   * @param string $language_id
   *   The language to get the mapping for.
   * @param \Drupal\search_api\IndexInterface $index_fields
   *   The fields handled by the curent index.
   * @param bool $reset
   *   (optional) Whether to reset the static cache.
   *
   * @return array
   *   The language-specific mapping from Drupal to Solr field names.
   */
  public function getLanguageSpecificSolrFieldNames($language_id, IndexInterface $index, $reset = FALSE);

  /**
   * Gets a language-specific mapping from Drupal to Solr field names.
   *
   * @param array $language_ids
   *   The language to get the mapping for.
   * @param \Drupal\search_api\IndexInterface $index_fields
   *   The fields handled by the curent index.
   * @param bool $reset
   *   (optional) Whether to reset the static cache.
   *
   * @return array
   *   The language-specific mapping from Drupal to Solr field names.
   */
  public function getSolrFieldNamesKeyedByLanguage(array $language_ids, IndexInterface $index, $reset = FALSE);

  /**
   * Returns the Solr connector used for this backend.
   *
   * @return \Drupal\search_api_solr\SolrConnectorInterface
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSolrConnector();

  /**
   * Retrieves a Solr document from an search api index item.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search api index.
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   An item to get documents for.
   *
   * @return \Solarium\QueryType\Update\Query\Document\Document
   *   A solr document.
   */
  public function getDocument(IndexInterface $index, ItemInterface $item);

  /**
   * Retrieves Solr documents from search api index items.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search api index.
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   An array of items to get documents for.
   * @param \Solarium\QueryType\Update\Query\Query $update_query
   *   The existing update query the documents should be added to.
   *
   * @return \Solarium\QueryType\Update\Query\Document\Document[]
   *   An array of solr documents.
   */
  public function getDocuments(IndexInterface $index, array $items, UpdateQuery $update_query = NULL);

  /**
   * Extract a file's content using tika within a solr server.
   *
   * @param string $filepath
   *   The real path of the file to be extracted.
   *
   * @return string
   *   The text extracted from the file.
   */
  public function extractContentFromFile($filepath);

  /**
   * Returns the targeted content domain of the server.
   *
   * @return string
   */
  public function getDomain();

  /**
   * Indicates if the Solr server uses a managed schema.
   *
   * @return bool
   *   True if the Solr server uses a managed schema, false if the Solr server
   *   uses a classic schema.
   */
  public function isManagedSchema();

  /**
   * Indicates if the Solr index should be optimized daily.
   *
   * @return bool
   *   True if the Solr index should be optimized daily, false otherwise.
   */
  public function isOptimizeEnabled();

  /**
   * Returns a ready to use query string to filter results by index and site.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return string
   */
  public function getIndexFilterQueryString(IndexInterface $index);

  /**
   * Prefixes an index ID as configured.
   *
   * The resulting ID will be a concatenation of the following strings:
   * - If set, the server-specific index_prefix.
   * - If set, the index-specific prefix.
   * - The index's machine name.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   *
   * @return string
   *   The prefixed machine name.
   */
  public function getIndexId(IndexInterface $index);

  /**
   * Returns the targeted Index ID. In case of multisite it might differ.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return string
   */
  public function getTargetedIndexId(IndexInterface $index);

  /**
   * Returns the targeted site hash. In case of multisite it might differ.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return string
   */
  public function getTargetedSiteHash(IndexInterface $index);

  /**
   * Executes a streaming expression.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *
   * @return \Solarium\QueryType\Stream\Result
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function executeStreamingExpression(QueryInterface $query);

  /**
   * Executes a graph streaming expression.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *
   * @return \Solarium\QueryType\Graph\Result
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function executeGraphStreamingExpression(QueryInterface $query);

  /**
   * Apply any finalization commands to a solr index.
   *
   * Only if globally configured to do so and only the first time after changes
   * to the index from the drupal side.
   *
  /**
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return bool
   *  True if a finalization run, false otherwise. False doesn't indicate an
   *  error!
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function finalizeIndex(IndexInterface $index);

  /**
   * Gets schema language statistics for the multilingual Solr server.
   *
   * @return array
   *   Stats as associative array keyed by language IDs and a boolean value to
   *   indicate if corresponding field types are existing on the server's
   *   current schema.
   */
  public function getSchemaLanguageStatistics();

}
