<?php

/**
 * @file
 * Contains \Drupal\as_search\Plugin\search_api\backend\SearchApiSolrMultilingualBackend.
 */

namespace Drupal\search_api_solr_multilingual\Plugin\search_api\backend;

/**
 * @SearchApiBackend(
 *   id = "search_api_solr_multilingual",
 *   label = @Translation("Solr Multilingual"),
 *   description = @Translation("Index items using an Apache Solr Multilingual search server.")
 * )
 */
class SearchApiSolrMultilingualBackend extends AbstractSearchApiSolrMultilingualBackend {

  protected function createSolrDynamicField($solr_field_name, $solr_field_type_name) {
    // @todo configurable kind of error message and log entry and status report

    return FALSE;
  }

  protected function createSolrMultilingualFieldType($solr_field_type_name) {
    // @todo configurable kind of error message and log entry and status report

    return FALSE;
  }
}
