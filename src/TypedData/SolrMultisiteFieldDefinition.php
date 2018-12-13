<?php

namespace Drupal\search_api_solr\TypedData;

use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines a class for Solr field definitions.
 */
class SolrMultisiteFieldDefinition extends DataDefinition {

  /**
   * {@inheritdoc}
   */
  public function isList() {
    // @todo
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isReadOnly() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isComputed() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired() {
    return FALSE;
  }

}
