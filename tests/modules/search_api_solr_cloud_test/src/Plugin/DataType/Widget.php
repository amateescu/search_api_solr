<?php

namespace Drupal\search_api_solr_cloud_test\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
* @DataType(
*  id = "search_api_solr_test_widget",
*  label = @Translation("Widget"),
*  definition_class = "\Drupal\search_api_solr_cloud_test\TypedData\WidgetDefinition"
* )
*/
class Widget extends Map {}
