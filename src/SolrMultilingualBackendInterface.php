<?php

namespace Drupal\search_api_solr_multilingual;

use Drupal\search_api\IndexInterface;
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

  /**
   * Returns the targeted content domain of the server.
   *
   * @return string
   */
  public function getDomain();

}
