<?php

namespace Drupal\search_api_solr;

use Drupal\Component\Render\FormattableMarkup;

/**
 * Represents an exception that occurs in Search API Solr.
 */
class SearchApiSolrConflictingEntitiesException extends SearchApiSolrException {

  /**
   * @var \Drupal\search_api_solr\SolrConfigInterface[]
   */
  protected $conflicting_entities = [];

  /**
   * @return \Drupal\search_api_solr\SolrConfigInterface[]
   */
  public function getConflictingEntities(): array {
    return $this->conflicting_entities;
  }

  /**
   * @param \Drupal\search_api_solr\SolrConfigInterface[] $conflicting_entities
   */
  public function setConflictingEntities(array $conflicting_entities): void {
    $this->conflicting_entities = $conflicting_entities;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    $conflicts = '<ul>';
    foreach ($this->getConflictingEntities() as $entity) {
      $link = new FormattableMarkup('<li><a href="' . $entity->toUrl('collection')->toString() . '">@label</a></li>', ['@label' => $entity->label()]);
      $conflicts .= $link;
    }
    return $conflicts . '</ul>';
  }

}
