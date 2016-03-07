<?php

/**
 * @file
 * Contains Drupal\search_api_solr_multilingual\Controller\SolrFieldTypeListBuilder.
 */

namespace Drupal\search_api_solr_multilingual\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr_multilingual\SolrFieldTypeInterface;
use Drupal\search_api_solr_multilingual\SolrMultilingualBackendInterface;
use ZipStream\ZipStream;

/**
 * Provides a listing of SolrFieldType.
 */
class SolrFieldTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * @var SolrMultilingualBackendInterface
   */
  protected $backend;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Solr Field Type'),
      'minimum_solr_version' => $this->t('Minimum Solr Version'),
      'managed_schema' => $this->t('Managed Schema Required'),
      'langcode' => $this->t('Language'),
      'id' => $this->t('Machine name'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(SolrFieldTypeInterface $solr_field_type) {
    $row = [
      'label' => $this->getLabel($solr_field_type),
      'minimum_solr_version' => $solr_field_type->getMinimumSolrVersion(),
      // @todo format
      'managed_schema' => $solr_field_type->isManagedSchema(),
      // @todo format
      'langcode' => $solr_field_type->language()->getId(),
      'id' => $solr_field_type->id(),
    ];
    return $row + parent::buildRow($solr_field_type);
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $solr_version = $this->getBackend()->getSolrHelper()->getSolrVersion();
    $entity_ids = $this->getEntityIds();
    $entities = $this->storage->loadMultipleOverrideFree($entity_ids);

    // We filter to those field types that are relevant for the current server.
    // There are multiple entities having the same field_type.name but different
    // values for managed_schema and minimum_solr_version.
    foreach ($entities as $key => $solr_field_type) {
      /** @var SolrFieldTypeInterface $solr_field_type */
      if ($solr_field_type->isManagedSchema() != $this->getBackend()->isManagedSchema() ||
        version_compare($solr_field_type->getMinimumSolrVersion(), $solr_version, '>')
      ) {
        unset($entities[$key]);
      }
      //@todo

    }

    // managed_schema: true
    // minimum_solr_version: 5.2.0
    // field_type.name: text_de

    // Sort the entities using the entity class's sort() method.
    // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
    uasort($entities, array($this->entityType->getClass(), 'sort'));
    return $entities;
  }

  /**
   * @inheritdoc
   */
  public function getDefaultOperations(SolrFieldTypeInterface $solr_field_type) {
    $operations = parent::getDefaultOperations($solr_field_type);

    if ($solr_field_type->access('view') && $solr_field_type->hasLinkTemplate('export-form')) {
      $operations['export'] = array(
        'title' => $this->t('Export'),
        'weight' => 10,
        'url' => $solr_field_type->urlInfo('export-form'),
      );
    }

    return $operations;
  }

  public function getSchemaExtraTypesXml() {
    return $this->getPlainTextRenderArray($this->generateSchemaExtraTypesXml());
  }

  protected function generateSchemaExtraTypesXml() {
    $xml = '<types>';
    /** @var SolrFieldTypeInterface $solr_field_type */
    foreach ($this->load() as $solr_field_type) {
      if (!$solr_field_type->isManagedSchema()) {
        $xml .= "\n" . $solr_field_type->getFieldTypeAsXml();
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
    /** @var SolrFieldTypeInterface $solr_field_type */
    foreach ($this->load() as $solr_field_type) {
      if (!$solr_field_type->isManagedSchema()) {
        foreach ($solr_field_type->getDynamicFields() as $dynamic_field) {
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
    // @todo apply decision taken in https://www.drupal.org/node/2661698
    require drupal_get_path('module', 'search_api_solr_multilingual') . '/vendor/autoload.php';

    $solr_helper = $this->getBackend()->getSolrHelper();
    $solr_major_version = $solr_helper->getSolrMajorVersion();
    $search_api_solr_conf_path = drupal_get_path('module', 'search_api_solr') . '/solr-conf/' . $solr_major_version . '.x/';
    $solrcore_properties = parse_ini_file($search_api_solr_conf_path . 'solrcore.properties', FALSE, INI_SCANNER_RAW);

    $zip = new ZipStream('config.zip');
    $zip->addFile('schema_extra_types.xml', $this->generateSchemaExtraTypesXml());
    $zip->addFile('schema_extra_fields.xml', $this->generateSchemaExtraFieldsXml());
    foreach (['elevate.xml', 'mapping-ISOLatin1Accent.txt', 'schema.xml', 'protwords.txt', 'solrconfig.xml', 'stopwords.txt', 'synonyms.txt'] as $file_name) {
      $zip->addFileFromPath($file_name, $search_api_solr_conf_path . $file_name);
    }
    // Add language specific text files.
    /** @var SolrFieldTypeInterface $solr_field_type */
    foreach ($this->load() as $solr_field_type) {
      $text_files = $solr_field_type->getTextFiles();
      foreach ($text_files as $text_file_name => $text_file) {
        $language_specific_text_file_name = $text_file_name . '_' . $solr_field_type->language()->getId() . '.txt';
        $zip->addFile($language_specific_text_file_name, $text_file);
        $solrcore_properties['solr.replication.confFiles'] .= ',' . $language_specific_text_file_name;
      }
    }

    $solrcore_properties['solr.luceneMatchVersion'] = $solr_helper->getSolrVersion();
    // @todo
    //$solrcore_properties['solr.replication.masterUrl']

    $solrcore_properties_string = '';
    foreach ($solrcore_properties as $property => $value) {
      $solrcore_properties_string .= $property . '=' . $value . "\n";
    }
    $zip->addFile('solrcore.properties', $solrcore_properties_string);

    // @todo provide a hook to add more things.

    return $zip;
  }

  public function setServer(ServerInterface $server) {
    $this->backend = $server->getBackend();

  }

  /**
   * @return \Drupal\search_api_solr_multilingual\SolrMultilingualBackendInterface
   */
  protected function getBackend() {
    return $this->backend;
  }

}
