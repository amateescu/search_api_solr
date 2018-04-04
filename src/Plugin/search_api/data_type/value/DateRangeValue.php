<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type\value;

/**
 * Represents a single date range value.
 */
class DateRangeValue implements DateRangeValueInterface {

  /**
   * The start date.
   *
   * @var string
   */
  protected $start;

  /**
   * The end date.
   *
   * @var string
   */
  protected $end;

  /**
   * Constructs a DateRangeValue object.
   *
   * @param $start
   * @param $end
   */
  public function __construct($start, $end) {
    $this->start = $start;
    $this->end = $end;
  }

  /**
   * {@inheritdoc}
   */
  public function getStart() {
    return $this->start;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnd() {
    return $this->end;
  }

}
