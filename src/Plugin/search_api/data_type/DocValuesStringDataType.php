<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\StringDataType;

/**
 * Provides a graph string data type.
 *
 * @SearchApiDataType(
 *   id = "solr_string_doc_values",
 *   label = @Translation("String (incl. docValues)"),
 *   description = @Translation("String field having docValues set to TRUE. Required for Solr streaming expressions and graph queries."),
 *   fallback_type = "string",
 *   prefix = "sd"
 * )
 */
class DocValuesStringDataType extends StringDataType {
}
