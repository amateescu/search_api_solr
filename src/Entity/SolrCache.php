<?php

namespace Drupal\search_api_solr\Entity;

use Drupal\search_api_solr\SolrCacheInterface;

/**
 * Defines the SolrCache entity.
 *
 * @ConfigEntityType(
 *   id = "solr_cache",
 *   label = @Translation("Solr Cache"),
 *   handlers = {
 *     "list_builder" = "Drupal\search_api_solr\Controller\SolrCacheListBuilder",
 *     "form" = {
 *     }
 *   },
 *   config_prefix = "solr_cache",
 *   admin_permission = "administer search_api",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "disable-for-server" = "/admin/config/search/search-api/server/{search_api_server}/solr_cache/{solr_cache}/disable",
 *     "enable-for-server" = "/admin/config/search/search-api/server/{search_api_server}/solr_cache/{solr_cache}/enable",
 *     "collection" = "/admin/config/search/search-api/server/{search_api_server}/solr_cache"
 *   }
 * )
 */
class SolrCache extends AbstractSolrConfig implements SolrCacheInterface {

  /**
   * Solr custom cache definition.
   *
   * @var array
   */
  protected $cache;

  /**
   * Solr filterCache definition.
   *
   * @var array
   */
  protected $filter_cache;

  /**
   * Solr queryResultCache definition.
   *
   * @var array
   */
  protected $query_result_cache;

  /**
   * Solr documentCache definition.
   *
   * @var array
   */
  protected $document_cache;

  /**
   * Solr fieldValueCache definition.
   *
   * @var array
   */
  protected $field_value_cache;

  /**
   * The targeted environments.
   *
   * @var string[]
   */
  protected $environments;

  /**
   * {@inheritdoc}
   */
  public function getCache() {
    return $this->cache ?? ($this->filter_cache ?? ($this->query_result_cache ?? ($this->document_cache ?? $this->field_value_cache)));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheName() {
    $root = $this->getRootElementName();
    if ('cache' === $root && isset($this->cache['name'])) {
      return $this->cache['name'];
    }
    return $root;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironments() {
    return $this->environments;
  }

  /**
   * Get all available environments.
   *
   * @return string[]
   *   An array of environments as strings.
   */
  public static function getAvailableEnvironments() {
    $environments = [];
    $config_factory = \Drupal::configFactory();
    foreach ($config_factory->listAll('search_api_solr.solr_cache.') as $cache) {
      $config = $config_factory->get($cache);
      $environments = array_merge($environments, $config->get('environments'));
    }
    return array_unique($environments);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheAsXml($add_comment = TRUE) {
    $comment = '';
    if ($add_comment) {
      $comment = "<!--\n  " . $this->label() . "\n  " .
        $this->getMinimumSolrVersion() .
        "\n-->\n";
    }

    $formatted_xml_string = $this->buildXmlFromArray($this->getRootElementName(), $this->getCache());

    return $comment . $formatted_xml_string;
  }

  protected function getRootElementName() {
    $root = 'cache';
    if ($this->filter_cache) {
      $root = 'filterCache';
    }
    elseif ($this->query_result_cache) {
      $root = 'queryResultCache';
    }
    elseif ($this->document_cache) {
      $root = 'documentCache';
    }
    elseif ($this->field_value_cache) {
      $root = 'fieldValueCache';
    }
    return $root;
  }
}
