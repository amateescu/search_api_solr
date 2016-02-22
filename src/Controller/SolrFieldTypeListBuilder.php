<?php

/**
 * @file
 * Contains Drupal\search_api_solr_multilingual\Controller\SolrFieldTypeListBuilder.
 */

namespace Drupal\search_api_solr_multilingual\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of SolrFieldType.
 */
class SolrFieldTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Solr Field Type');
    $header['id'] = $this->t('Machine name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $this->getLabel($entity);
    $row['id'] = $entity->id();
    // You probably want a few more properties here...
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    // @todo filter by server

    $entity_ids = $this->getEntityIds();
    $entities = $this->storage->loadMultipleOverrideFree($entity_ids);

    // Sort the entities using the entity class's sort() method.
    // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
    uasort($entities, array($this->entityType->getClass(), 'sort'));
    return $entities;
  }

  /**
   * @inheritdoc
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    if ($entity->access('view') && $entity->hasLinkTemplate('export-form')) {
      $operations['export'] = array(
        'title' => $this->t('Export'),
        'weight' => 10,
        'url' => $entity->urlInfo('export-form'),
      );
    }

    return $operations;
  }

  public function getSchemaExtraTypesXml() {
    $xml = '<types>';
    foreach ($this->load() as $entity) {
      $xml .= "\n" . $entity->getFieldTypeAsXml();
    }
    $xml .= "\n</types>";

    $build['file'] = array(
      '#plain_text' => $xml,
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    );

    return $build;
  }

  public function getSchemaExtraFieldsXml() {
    $xml = "<fields>\n";
    foreach ($this->load() as $entity) {
      foreach ($entity->getDynamicFields() as $dynamic_field) {
        $xml .= '<dynamicField ';
        foreach ($dynamic_field as $attribute => $value) {
          $xml .= $attribute . '="' . (is_bool($value) ? ($value  ? 'true' : 'false') : $value). '" ';
        }
        $xml .= " />\n";
      }
    }
    $xml .= '</fields>';

    $build['file'] = array(
      '#plain_text' => $xml,
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    );

    return $build;
  }
}
