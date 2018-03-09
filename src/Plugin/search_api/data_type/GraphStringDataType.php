<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\StringDataType;

/**
 * Provides a graph string data type.
 *
 * @SearchApiDataType(
 *   id = "graph_string",
 *   label = @Translation("Graph String"),
 *   description = @Translation("String field to store graph nodes and edges."),
 *   fallback_type = "string",
 *   prefix = "sg"
 * )
 */
class GraphStringDataType extends StringDataType {
}
