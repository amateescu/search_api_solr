<?php

namespace Drupal\search_api_solr;

/**
 * Provides an interface defining a SolrFieldType entity.
 */
interface SolrCacheInterface extends SolrConfigInterface {

  /**
   * Gets the environments targeted by this Solr Cache.
   *
   * @return string[]
   *   Environments.
   */
  public function getEnvironments();

  /**
   * Gets the Solr Cache definition as nested associative array.
   *
   * @return array
   *   The Solr Cache definition as nested associative array.
   */
  public function getCache();

  /**
   * Gets the Solr Cache name.
   *
   * @return string
   *   The Solr Cache name.
   */
  public function getCacheName();

  /**
   * Gets the Solr Cache definition as XML fragment.
   *
   * The XML format is used as part of a solrconfig.xml.
   *
   * @param bool $add_comment
   *   Wether to add a comment to the XML or not to explain the purpose of this
   *   Solr Cache.
   *
   * @return string
   *   The Solr Cache definition as XML.
   */
  public function getCacheAsXml($add_comment = TRUE);

}
