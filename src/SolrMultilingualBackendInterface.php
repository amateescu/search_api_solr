<?php

/**
 * @file
 * Contains \Drupal\search_api_solr\SolrMultilingualBackendInterface.
 */

namespace Drupal\search_api_solr_multilingual;

use Drupal\search_api_solr\SolrBackendInterface;


/**
 * Provides an interface defining a Multilingual Solr Search API Backend.
 */
interface SolrMultilingualBackendInterface extends SolrBackendInterface {

  /**
   * Indicates if the Solr server uses a managed schema.
   *
   * @return bool
   *   True if the Solr server uses a managed schema, false if the Solr server
   *   uses a classic schema.
   */
  public function isManagedSchema();

}