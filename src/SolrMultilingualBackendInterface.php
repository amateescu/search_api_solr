<?php

namespace Drupal\search_api_solr;

/**
 * Provides an interface defining a Multilingual Solr Search API Backend.
 */
interface SolrMultilingualBackendInterface extends SolrBackendInterface {

  /**
   * Gets schema language statistics for the multilingual Solr server.
   *
   * @return array
   *   Stats as associative array keyed by language IDs and a boolean value to
   *   indicate if corresponding field types are existing on the server's
   *   current schema.
   */
  public function getSchemaLanguageStatistics();

  /**
   * Indicates if the fallback for not supported languages is active.
   *
   * @return bool
   */
  public function hasLanguageUndefinedFallback();

}
