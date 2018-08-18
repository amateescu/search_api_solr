<?php

namespace Drupal\search_api_solr\Solarium\Result;

use Solarium\QueryType\Select\Result\AbstractDocument;
use Solarium\QueryType\Select\Result\DocumentInterface;

/**
 * Stream result Solr document.
 */
class StreamDocument extends AbstractDocument implements DocumentInterface {

  /**
   * Constructor.
   *
   * @param array $fields
   */
  public function __construct(array $fields) {
    $this->fields = $fields;
  }

  /**
   * Set field value.
   *
   * @param string $name
   * @param mixed $value
   */
  public function __set($name, $value)
  {
    $this->fields[$name] = $value;
  }
}
