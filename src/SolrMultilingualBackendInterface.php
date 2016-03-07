<?php

/**
 * @file
 * Contains \Drupal\search_api_solr\SolrMultilingualBackendInterface.
 */

namespace Drupal\search_api_solr_multilingual;

use Drupal\search_api_solr\SolrBackendInterface;


/**
 */
interface SolrMultilingualBackendInterface extends SolrBackendInterface {

  public function isManagedSchema();

}