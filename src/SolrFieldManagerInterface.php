<?php

namespace Drupal\search_api_solr;

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
   * @return \Drupal\search_api_solr\TypedData\SolrFieldDefinitionInterface[]
   *   The array of field definitions for the server, keyed by field name.
   */
  public function getFieldDefinitions($server_id);

}
