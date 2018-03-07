<?php

namespace Drupal\search_api\Plugin\search_api\data_type;

namespace Drupal\search_api_solr\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\StringDataType;

/**
 * Provides a graph node uuid data type.
 *
 * @SearchApiDataType(
 *   id = "graph_node_uuid",
 *   label = @Translation("Graph Node UUID"),
 *   description = @Translation("String field to store a graph node's UUID."),
 *   fallback_type = "string",
 *   prefix = "sgn"
 * )
 */
class GraphNodeUuidDataType extends StringDataType {
}
