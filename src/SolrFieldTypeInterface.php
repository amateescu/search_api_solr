<?php

namespace Drupal\search_api_solr;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a SolrFieldType entity.
 */
interface SolrFieldTypeInterface extends ConfigEntityInterface {

  /**
   * Gets the Solr Field Type name.
   *
   * @return string
   *   The Solr Field Type name.
   */
  public function getFieldTypeName();

  /**
   * Gets the custom code targeted by this Solr Field Type.
   *
   * @return string
   *   Custom code.
   */
  public function getCustomCode();

  /**
   * Gets the language targeted by this Solr Field Type.
   *
   * @return string
   *   Language code.
   */
  public function getFieldTypeLanguageCode();

  /**
   * Gets the domains the field type is suitable for.
   *
   * @return string[]
   *   An array of domains as strings.
   */
  public function getDomains();

  /**
   * Gets the Solr Field Type definition as nested associative array.
   *
   * @return array
   *   The Solr Field Type definition as nested associative array.
   */
  public function getFieldType();

  /**
   * Sets the Solr Field Type definition as nested associative array.
   *
   * @param array $field_type
   *   The Solr Field Type definition as nested associative array.
   *
   * @return self
   */
  public function setFieldType(array $field_type);

  /**
   * Gets the Solr Field Type definition as JSON.
   *
   * The JSON format is used to interact with a managed Solr schema.
   *
   * @param bool $pretty
   *   Return pretty printed JSON.
   *
   * @return string
   *   The Solr Field Type definition as JSON.
   */
  public function getFieldTypeAsJson(bool $pretty = FALSE);

  /**
   * Sets the Solr Field Type definition as JSON.
   *
   * Decodes the Solr Field Type definition encoded as JSON and stores an
   * nested associative array internally. This method in useful to import a
   * field type from an existing Solr server.
   *
   * @param string $field_type
   *   The Solr Field Type definition as JSON.
   *
   * @return self
   */
  public function setFieldTypeAsJson($field_type);

  /**
   * Gets the Solr Field Type definition as XML fragment.
   *
   * The XML format is used as part of a classic Solr schema.
   *
   * @param bool $add_comment
   *   Wether to add a comment to the XML or not to explain the purpose of this
   *   Solr Field Type.
   *
   * @return string
   *   The Solr Field Type definition as XML.
   */
  public function getFieldTypeAsXml($add_comment = TRUE);

  /**
   * Gets the Solr Spellcheck Field Type definition as nested associative array.
   *
   * @return array|null
   *   The Solr SpellcheckField Type definition as nested associative array or
   *   NULL if it doesn't exist.
   */
  public function getSpellcheckFieldType();

  /**
   * Sets the Solr Spellcheck Field Type definition as nested associative array.
   *
   * @param array $spellcheck_field_type
   *   The Solr SpellcheckField Type definition as nested associative array.
   *
   * @return self
   */
  public function setSpellcheckFieldType(array $spellcheck_field_type);

  /**
   * Gets the Solr Spellcheck Field Type definition as JSON.
   *
   * The JSON format is used to interact with a managed Solr schema.
   *
   * @param bool $pretty
   *   Return pretty printed JSON.
   *
   * @return string
   *   The Solr Spellcheck Field Type definition as JSON.
   */
  public function getSpellcheckFieldTypeAsJson(bool $pretty = FALSE);

  /**
   * Sets the Solr Spellcheck Field Type definition as JSON.
   *
   * Decodes the Solr Field Type definition encoded as JSON and stores an
   * nested associative array internally. This method in useful to import a
   * field type from an existing Solr server.
   *
   * @param string $spellcheck_field_type
   *   The Solr Spellcheck Field Type definition as JSON, might be empty if it
   *   doesn't exist.
   *
   * @return self
   */
  public function setSpellcheckFieldTypeAsJson($spellcheck_field_type);

  /**
   * Gets the Solr Spellcheck Spellcheck Field Type definition as XML fragment.
   *
   * The XML format is used as part of a classic Solr schema.
   *
   * @param bool $add_comment
   *   Wether to add a comment to the XML or not to explain the purpose of this
   *   Solr Field Type.
   *
   * @return string
   *   The Solr Spellcheck Field Type definition as XML, might be empty if it
   *   doesn't exist.
   */
  public function getSpellcheckFieldTypeAsXml($add_comment = TRUE);

  /**
   * Gets the Solr Collated Field Type definition as nested associative array.
   *
   * @return array|null
   *   The Solr Collated Type definition as nested associative array or
   *   NULL if it doesn't exist.
   */
  public function getCollatedFieldType();

  /**
   * Sets the Solr Collated Field Type definition as nested associative array.
   *
   * @param array $collated_field_type
   *   The Solr Collated Type definition as nested associative array.
   *
   * @return self
   */
  public function setCollatedFieldType(array $collated_field_type);

  /**
   * Gets the Solr Collated Field Type definition as JSON.
   *
   * The JSON format is used to interact with a managed Solr schema.
   *
   * @param bool $pretty
   *   Return pretty printed JSON.
   *
   * @return string
   *   The Solr Spellcheck Field Type definition as JSON.
   */
  public function getCollatedFieldTypeAsJson(bool $pretty = FALSE);

  /**
   * Sets the Solr Collated Field Type definition as JSON.
   *
   * Decodes the Solr Field Type definition encoded as JSON and stores an
   * nested associative array internally. This method in useful to import a
   * field type from an existing Solr server.
   *
   * @param string $collated_field_type
   *   The Solr Spellcheck Field Type definition as JSON, might be empty if it
   *   doesn't exist.
   *
   * @return self
   */
  public function setCollatedFieldTypeAsJson($collated_field_type);

  /**
   * Gets the Solr Collated Field Type definition as XML fragment.
   *
   * The XML format is used as part of classic Solr schema.
   *
   * @param bool $add_comment
   *   Wether to add a comment to the XML or not to explain the purpose of thid
   *   Solr Field Type.
   *
   * @return string
   *   The Solr Collated Field Type definition as XML, might be empty
   *   if it doesn't exist.
   */
  public function getCollatedFieldTypeAsXml($add_comment = TRUE);

  /**
   * Gets the Solr Unstemmed Field Type definition as nested associative array.
   *
   * @return array|NULL
   *   The Solr Unstemmed Field Type definition as nested associative array or
   *   NULL if it doesn't exist.
   */
  public function getUnstemmedFieldType();

  /**
   * Sets the Solr Unstemmed Field Type definition as nested associative array.
   *
   * @param array $unstemmed_field_type
   *   The Solr Unstemmed Field Type definition as nested associative array.
   *
   * @return self
   */
  public function setUnstemmedFieldType(array $unstemmed_field_type);

  /**
   * Gets the Solr Unstemmed Field Type definition as JSON.
   *
   * The JSON format is used to interact with a managed Solr schema.
   *
   * @param bool $pretty
   *   Return pretty printed JSON.
   *
   * @return string
   *   The Solr Unstemmed Field Type definition as JSON.
   */
  public function getUnstemmedFieldTypeAsJson(bool $pretty = FALSE);

  /**
   * Sets the Solr Unstemmed Field Type definition as JSON.
   *
   * Decodes the Solr Field Type definition encoded as JSON and stores an
   * nested associative array internally. This method in useful to import a
   * field type from an existing Solr server.
   *
   * @param string $unstemmed_field_type
   *   The Solr Unstemmed Field Type definition as JSON, might be empty if it
   *   doesn't exist.
   *
   * @return self
   */
  public function setUnstemmedFieldTypeAsJson($unstemmed_field_type);

  /**
   * Gets the Solr Unstemmed Field Type definition as XML fragment.
   *
   * The XML format is used as part of classic Solr schema.
   *
   * @param bool $add_comment
   *   Wether to add a comment to the XML or not to explain the purpose of this
   *   Solr Field Type.
   *
   * @return string
   *    The Solr Unstemmed Field Type definition as XML, might be empty if it
   *    doesn't exist.
   */
  public function getUnstemmedFieldTypeAsXml($add_comment = TRUE);

  /**
   * Gets a list of dynamic Solr fields that will use this Solr Field Type.
   *
   * @return array
   *   An array of dynamic field definitions.
   */
  public function getDynamicFields();

  /**
   * Gets a list of static fields that will use this Solr Field Type.
   *
   * @return array
   *   An array of static field definitions.
   */
  public function getStaticFields();

  /**
   * Gets a list of copy fields.
   *
   * @return array
   *   An array of copy field definitions. A copy field definition consists of
   *   arrays like ['source' => 'fieldA', 'dest' => 'fieldB'].
   */
  public function getCopyFields();

  /**
   * Gets the Solr Field Type specific additions to solrconfig.xml as array.
   *
   * @return array
   *   The Solr Field Type specific additions to solrconfig.xml as nested
   *   associative array.
   */
  public function getSolrConfigs();

  /**
   * Sets the Solr Field Type specific additions to solrconfig.xml as array.
   *
   * @param array $solr_configs
   *   The Solr Field Type specific additions to solrconfig.xml as nested
   *   associative array.
   *
   * @return self
   */
  public function setSolrConfigs(array $solr_configs);

  /**
   * Gets the Solr Field Type specific additions to solrconfig.xml as XML.
   *
   * The XML format is used as part of a classic Solr solrconf.xml.
   *
   * @param bool $add_comment
   *   Wether to add a comment to the XML or not to explain the purpose of
   *   these configs.
   *
   * @return string
   *   The Solr Field Type specific additions to solrconfig.xml as XML.
   */
  public function getSolrConfigsAsXml($add_comment = TRUE);

  /**
   * Gets all text files required by the Solr Field Type definition.
   *
   * @return array
   *   An array of text files required by the Solr Field Type definition.
   */
  public function getTextFiles();

  /**
   * Adds a single text file to the Solr Field Type.
   *
   * @param string $name
   *   The name of the text file.
   * @param string $content
   *   The content of the text file.
   */
  public function addTextFile($name, $content);

  /**
   * Adds multiple text files to the Solr Field Type.
   *
   * @param array $text_files
   *   An associative array using the file names as keys and the file contents
   *   as values.
   *
   * @return self
   */
  public function setTextFiles(array $text_files);

  /**
   * Indicates if the field type requires a managed Solr schema.
   *
   * @return bool
   *   Whether the field type requires a managed schema.
   */
  public function requiresManagedSchema();

  /**
   * Gets the minimum Solr version that is supported by this Solr Field Type.
   *
   * @return string
   *   A Solr version string.
   */
  public function getMinimumSolrVersion();

  /**
   * Sets the minimum Solr version that is supported by this Solr Field Type.
   *
   * @param string $minimum_solr_version
   *   A Solr version string.
   *
   * @return self
   */
  public function setMinimumSolrVersion($minimum_solr_version);

}
