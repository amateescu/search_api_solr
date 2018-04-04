<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type\value;

/**
 * Provides an interface for date range field values.
 */
interface DateRangeValueInterface {

  /**
   * Retrieves the start date.
   *
   * @return string
   */
  public function getStart();

  /**
   * Retrieves the end date.
   *
   * @return string
   */
  public function getEnd();

}
