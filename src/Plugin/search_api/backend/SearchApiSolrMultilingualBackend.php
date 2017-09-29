<?php

namespace Drupal\search_api_solr_multilingual\Plugin\search_api\backend;

/**
 * @SearchApiBackend(
 *   id = "search_api_solr_multilingual",
 *   label = @Translation("Multilingual Solr"),
 *   description = @Translation("Index items using an Solr search server configured for multilingual content.")
 * )
 */
class SearchApiSolrMultilingualBackend extends AbstractSearchApiSolrMultilingualBackend {

  /**
   * {@inheritdoc}
   */
  protected function createSolrDynamicField($solr_field_name, $solr_field_type_name) {
    // @todo configurable kind of error message and log entry and status report

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function createSolrMultilingualFieldType($solr_field_type_name) {
    // @todo configurable kind of error message and log entry and status report

    return FALSE;
  }

}
