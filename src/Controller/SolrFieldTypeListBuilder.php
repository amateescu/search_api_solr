<?php

/**
 * @file
 * Contains Drupal\search_api_solr_multilingual\Controller\SolrFieldTypeListBuilder.
 */

namespace Drupal\search_api_solr_multilingual\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\search_api_solr_multilingual\Entity\SolrFieldType;
use ZipStream\ZipStream;

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
    return $this->getPlainTextRenderArray($this->generateSchemaExtraTypesXml());
  }

  protected function generateSchemaExtraTypesXml() {
    $xml = '<types>';
    /** @var SolrFieldType $entity */
    foreach ($this->load() as $entity) {
      if (strpos($entity->id(), 'm_') !== 0) {
        $xml .= "\n" . $entity->getFieldTypeAsXml();
      }
    }
    $xml .= "\n</types>";

    return $xml;
  }

  public function getSchemaExtraFieldsXml() {
    return $this->getPlainTextRenderArray($this->generateSchemaExtraFieldsXml());
  }

  protected function generateSchemaExtraFieldsXml() {
    $xml = "<fields>\n";
    foreach ($this->load() as $entity) {
      if (strpos($entity->id(), 'm_') !== 0) {
        /** @var SolrFieldType $entity */
        foreach ($entity->getDynamicFields() as $dynamic_field) {
          $xml .= '<dynamicField ';
          foreach ($dynamic_field as $attribute => $value) {
            $xml .= $attribute . '="' . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . '" ';
          }
          $xml .= " />\n";
        }
      }
    }
    $xml .= '</fields>';

    return $xml;
  }

  protected function getPlainTextRenderArray($plain_text) {
    return ['file' => [
      '#plain_text' => $plain_text,
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ],];
  }

  /**
   * @return \ZipStream\ZipStream
   */
  public function getConfigZip() {
    // @todo apply descision taken in https://www.drupal.org/node/2661698
    require drupal_get_path('module', 'search_api_solr_multilingual') . '/vendor/autoload.php';

    // @todo
    $solr_major_version = '5';
    $search_api_solr_conf_path = drupal_get_path('module', 'search_api_solr') . '/solr-conf/' . $solr_major_version . '.x/';

    $zip = new ZipStream('config.zip');
    $zip->addFile('schema_extra_types.xml', $this->generateSchemaExtraTypesXml());
    $zip->addFile('schema_extra_fields.xml', $this->generateSchemaExtraFieldsXml());
    foreach (['elevate.xml', 'mapping-ISOLatin1Accent.txt', 'schema.xml', 'protwords.txt', 'solrconfig.xml', 'solrcore.properties', 'stopwords.txt', 'synonyms.txt'] as $file_name) {
      $zip->addFileFromPath($file_name, $search_api_solr_conf_path . $file_name);
    }
    // @todo add language specific text files

    return $zip;
  }
}
