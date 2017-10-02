<?php

namespace Drupal\search_api_solr\TypedData;

use Drupal\Core\TypedData\ComplexDataDefinitionInterface;

/**
 * Interface for typed data Solr document definitions.
 */
interface SolrDocumentDefinitionInterface extends ComplexDataDefinitionInterface {

  /**
   * Gets the Search API Server ID.
   *
   * @return string|null
   *   The Server ID, or NULL if the Server is unknown.
   */
  public function getServerId();

  /**
   * Sets the Search API Server ID.
   *
   * @param string $server_id
   *   The Server ID to set.
   *
   * @return $this
   */
  public function setServerId($server_id);

}
