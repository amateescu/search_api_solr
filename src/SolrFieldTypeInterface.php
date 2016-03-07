<?php

/**
 * @file
 * Contains Drupal\search_api_solr_multilingual\SolrFieldTypeInterface.
 */

namespace Drupal\search_api_solr_multilingual;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a SolrFieldType entity.
 */
interface SolrFieldTypeInterface extends ConfigEntityInterface {

  public function getFieldType();

  public function getFieldTypeAsJson();

  public function setFieldTypeAsJson($field_type);

  public function getFieldTypeAsXml();

  public function getDynamicFields();

  public function getTextFiles();

  public function addTextFile($name, $content);

  public function setTextFiles($text_files);

  public function isManagedSchema();

  public function setManagedSchema($managed_schema);

  public function getMinimumSolrVersion();

  public function setMinimumSolrVersion($minimum_solr_version);

}
