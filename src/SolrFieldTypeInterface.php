<?php

namespace Drupal\search_api_solr_multilingual;

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
   * Gets the Solr Field Type definition as JSON.
   *
   * The JSON format is used to interact with a managed Solr schema.
   *
   * @return string
   *   The Solr Field Type definition as JSON.
   */
  public function getFieldTypeAsJson();

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
   * @return $this
   */
  public function setFieldTypeAsJson($field_type);

  /**
   * Gets the Solr Field Type definition as XML fragment.
   *
   * The XML format is used as part of a classic Solr schema.
   *
   * @param bool $add_commment
   *   Wether to add a comment to the XML or not to explain the purpose of this
   *   Solr Field Type.
   *
   * @return string
   *   The Solr Field Type definition as XML.
   */
  public function getFieldTypeAsXml();

  /**
   * Gets a list of dynamic Solr fields that will use this Solr Field Type.
   *
   * @return array
   */
  public function getDynamicFields();

  /**
   * Gets all text files required by the Solr Field Type definition.
   *
   * @return array
   */
  public function getTextFiles();

  /**
   * Adds a single text file to the Solr Field Type.
   *
   * @param string $name
   *   The name of the text file.
   *
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
   */
  public function setTextFiles($text_files);

  /**
   * Indicates if the Solr Field Type requires a server using a managed schema.
   *
   * @return bool
   *   True if the Solr Field Type requires a managed schema, false if the Solr
   *   Field Type is designed for a classic schema.
   */
  public function isManagedSchema();

  /**
   * Sets if the Solr Field Type requires a server using a managed schema.
   *
   * @param bool $managed_schema
   */
  public function setManagedSchema($managed_schema);

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
   */
  public function setMinimumSolrVersion($minimum_solr_version);

}
