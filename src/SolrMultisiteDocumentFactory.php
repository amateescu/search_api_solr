<?php

namespace Drupal\search_api_solr;

use Drupal\search_api\Item\ItemInterface;

/**
 * Defines a class for a Solr Document factory.
 */
class SolrMultisiteDocumentFactory extends SolrDocumentFactory {

  /**
   * {@inheritdoc}
   */
  public function create(ItemInterface $item) {
    $plugin = $this->typedDataManager->getDefinition('solr_multisite_document')['class'];
    return $plugin::createFromItem($item);
  }

}
