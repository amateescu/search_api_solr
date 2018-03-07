<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\StringDataType;

/**
 * Provides a graph edge uuid data type.
 *
 * @SearchApiDataType(
 *   id = "graph_edge_uuid",
 *   label = @Translation("Graph Edge UUID"),
 *   description = @Translation("String field to store a graph edge's UUID."),
 *   fallback_type = "string",
 *   prefix = "sge"
 * )
 */
class GraphEdgeUuidDataType extends StringDataType {
}
