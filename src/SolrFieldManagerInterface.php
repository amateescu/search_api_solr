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
   * @param \Drupal\search_api\ServerInterface $server
   *   The server from which we are retreiving field information.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The array of field definitions for the server, keyed by field name.
   */
  public function getFieldDefinitions(ServerInterface $server);

}
