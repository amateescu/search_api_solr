<?php

namespace Drupal\search_api_solr_datasource;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SearchApiSolrException;

/**
 * Manages the discovery of Solr fields.
 */
class SolrFieldManager implements SolrFieldManagerInterface {

  use UseCacheBackendTrait;

  /**
   * Static cache of field definitions per Solr server.
   *
   * @var array
   */
  protected $fieldDefinitions;

  /**
   * Constructs a new SorFieldManager.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(CacheBackendInterface $cache_backend) {
    $this->cacheBackend = $cache_backend;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions(ServerInterface $server) {
    if (!isset($this->fieldDefinitions[$server->id()])) {
      // Not prepared, try to load from cache.
      $cid = 'solr_field_definitions:' . $server->id();
      if ($cache = $this->cacheGet($cid)) {
        $field_definitions = $cache->data;
      }
      else {
        // Rebuild the definitions and put it into the cache.
        $field_definitions = $this->buildFieldDefinitions($server);
        $this->cacheSet($cid, $field_definitions , Cache::PERMANENT, ['search_api_server' => $server->id()]);
      }
      $this->fieldDefinitions[$server->id()] = $field_definitions;
    }
    return $this->fieldDefinitions[$server->id()];
  }

  /**
   * Builds the field definitions for a Solr server from its Luke handler.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   The server from which we are retreiving field information.
   *
   * @return \Drupal\search_api_solr_datasource\SolrFieldDefinitionInterface[]
   *   The array of field definitions for the server, keyed by field name.
   */
  protected function buildFieldDefinitions(ServerInterface $server) {
    // @todo Handle non-Solr servers.
    $fields = array();
    try {
      $luke = $server->getBackend()->getSolrConnector()->getLuke();
    }
    catch (SearchApiSolrException $e) {
      drupal_set_message($this->t('Could not connect to server %server, %message', ['%server' => $server->id(), '%message' => $e->getMessage()]), 'error');
      // @todo Inject the logger service.
      \Drupal::logger('search_api_solr_datasource')->error('Could not connect to server %server, %message', ['%server' => $server->id(), '%message' => $e->getMessage()]);
    }
    foreach ($luke['fields'] as $label => $defintion) {
      $field = new SolrFieldDefinition($defintion);
      $field->setLabel($label);
      $fields[$label] = $field;
    }
    return $fields;
  }

}
