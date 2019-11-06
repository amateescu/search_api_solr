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
   * The targeted environments.
   *
   * @var string[]
   */
  protected $environments;

  /**
   * {@inheritdoc}
   */
  public function getCache() {
    return $this->cache;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheName() {
    return $this->cache['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironments() {
    return empty($this->environments) ? ['default'] : $this->environments;
  }

  /**
   * Get all available environments.
   *
   * @return string[]
   *   An array of environments as strings.
   */
  public static function getAvailableEnvironments() {
    $environments = [['default']];
    $config_factory = \Drupal::configFactory();
    foreach ($config_factory->listAll('search_api_solr.solr_cache.') as $cache) {
      $config = $config_factory->get($cache);
      $environments[] = $config->get('environments');
    }
    $environments = array_unique(array_merge(...$environments));
    sort($environments);
    return $environments;
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

    $copy = $this->cache;
    $root = 'cache';
    switch ($this->cache['name']) {
      case 'filter':
      case 'queryResult':
      case 'document':
      case 'fieldValue':
        $root = $this->cache['name'] . 'Cache';
        unset($copy['name']);
        break;
    }

    $formatted_xml_string = $this->buildXmlFromArray($root, $copy);

    return $comment . $formatted_xml_string . "\n";
  }
}
