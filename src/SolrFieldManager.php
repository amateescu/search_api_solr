<?php

namespace Drupal\search_api_solr_datasource;

use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr_datasource\SolrFieldDefinition;

/**
 * Manages the discovery of Solr fields.
 */
class SolrFieldManager implements SolrFieldManagerInterface {

  /**
   * (@inheritdoc}
   */
  public function getFieldDefinitions($server) {
    // @todo Handle non-Solr servers.
    // @todo Cache the results.
    $fields = array();
    try {
      $luke = $server->getBackend()->getSolrConnector()->getLuke();
    }
    catch (SearchApiSolrException $e) {
      drupal_set_message($this->t('Could not connect to server %server, %message', ['%server' => $server->id(), '%message' => $e->getMessage()]), 'error');
      // @todo Inject the logger service.
      \Drupal::logger('search_api_solr_datasource')->error('Could not connect to server %server, %message', ['%server' => $server->id(), '%message' => $e->getMessage()]);
    }
    foreach ($luke['fields'] as $label => $defintion) {
      $field = new SolrFieldDefinition($defintion);
      $field->setLabel($label);
      $fields[$label] = $field;
    }
    return $fields;
  }

}
