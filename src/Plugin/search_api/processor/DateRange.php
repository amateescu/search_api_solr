<?php

namespace Drupal\search_api_solr\Plugin\search_api\processor;

use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api_solr\Plugin\search_api\data_type\value\DateRangeValue;

/**
 * Add date ranges to the index.
 *
 * @SearchApiProcessor(
 *   id = "solr_date_range",
 *   label = @Translation("Date ranges"),
 *   description = @Translation("Date ranges."),
 *   stages = {
 *     "preprocess_index" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class DateRange extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items) {
    foreach ($items as $item) {
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($item->getFields() as $name => $field) {
        if ('solr_date_range' == $field->getType()) {
          $required_properties = [
            $item->getDatasourceId() => [
              $field->getPropertyPath() . ':value' => 'start',
              $field->getPropertyPath() . ':end_value' => 'end',
            ],
          ];
          foreach ($this->getFieldsHelper()->extractItemValues([$item], $required_properties) as $key => $dates) {
            $values[$key] = new DateRangeValue($dates['start'][0], $dates['end'][0]);
          }
          $field->setValues($values);
        }
      }
    }
  }

}
