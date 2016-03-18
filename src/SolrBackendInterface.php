<?php

/**
 * @file
 * Contains \Drupal\search_api_solr\SolrBackendInterface.
 */

namespace Drupal\search_api_solr;

use Drupal\search_api\Backend\BackendInterface;
use Drupal\search_api\IndexInterface;

/**
 * Defines an interface for Solr search backend plugins.
 *
 * It extends the generic \Drupal\search_api\Backend\BackendInterface and covers
 * additional Solr specific methods.
 */
interface SolrBackendInterface extends BackendInterface {

  /**
   * Returns the solr helper class.
   *
   * @return \Drupal\search_api_solr\Solr\SolrHelper
   *  The Solr helper class.
   */
  public function getSolrHelper();

  /**
   * Sets the Solr helper class.
   *
   * @param \Drupal\search_api_solr\Solr\SolrHelper $solrHelper
   *  The Solr helper class.
   */
  public function setSolrHelper($solrHelper);

  /**
   * Returns the Solarium client.
   *
   * @return \Solarium\Client
   *   The solarium instance object.
   */
  public function getSolr();

  /**
   * Creates a list of all indexed field names mapped to their Solr field names.
   *
   * The special fields "search_api_id" and "search_api_relevance" are also
   * included. Any Solr fields that exist on search results are mapped back to
   * to their local field names in the final result set.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search Api index.
   * @param bool $single_value_name
   *   (optional) Whether to return names for fields which store only the first
   *   value of the field. Defaults to FALSE.
   * @param bool $reset
   *   (optional) Whether to reset the static cache.
   *
   * @see SearchApiSolrBackend::search()
   */
  public function getFieldNames(IndexInterface $index, $single_value_name = FALSE, $reset = FALSE);

  /**
   * Pings the Solr server to tell whether it can be accessed.
   *
   * Uses the admin/ping request handler.
   */
  public function ping();

  /**
   * Gets the currently used Solr connection object.
   *
   * @return \Solarium\Client
   *   The solr connection object used by this server.
   */
  public function getSolrConnection();

  /**
   * Gets metadata about fields in the Solr/Lucene index.
   * @todo SearchApiSolrConnectionInterface and SearchApiSolrField don't exist!
   *
   * @param int $num_terms
   *   Number of 'top terms' to return.
   *
   * @return array
   *   An array of SearchApiSolrField objects.
   *
   * @see SearchApiSolrConnectionInterface::getFields()
   */
  public function getFields($num_terms = 0);

  /**
   * Retrieves a config file or file list from the Solr server.
   *
   * Uses the admin/file request handler.
   *
   * @param string|null $file
   *   (optional) The name of the file to retrieve. If the file is a directory,
   *   the directory contents are instead listed and returned. NULL represents
   *   the root config directory.
   *
   * @return \Solarium\Core\Client\Response
   *   A Solarium response object containing either the file contents or a file
   *   list.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getFile($file = NULL);

}