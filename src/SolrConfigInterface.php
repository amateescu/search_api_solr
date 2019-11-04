<?php

namespace Drupal\search_api_solr;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a Solr Config entity.
 */
interface SolrConfigInterface extends ConfigEntityInterface {

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
