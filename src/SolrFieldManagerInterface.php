<?php

namespace Drupal\search_api_solr_datasource;

use Drupal\search_api\ServerInterface;

/**
 * Defines an interface for a Solr field manager.
 */
interface SolrFieldManagerInterface {

  /**
   * Gets the field definitions for a Solr server.
   *
   * @param string $server_id
   *   The ID of the Server from which we are retrieving field information.
   *
   * @return \Drupal\search_api_solr_datasource\TypedData\SolrFieldDefinitionInterface[]
   *   The array of field definitions for the server, keyed by field name.
   */
  public function getFieldDefinitions($server_id);

}
