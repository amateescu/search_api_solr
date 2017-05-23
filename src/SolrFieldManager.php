<?php

namespace Drupal\search_api_solr_datasource;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr_datasource\TypedData\SolrFieldDefinition;

/**
 * Manages the discovery of Solr fields.
 */
class SolrFieldManager implements SolrFieldManagerInterface {

  use UseCacheBackendTrait;
  use StringTranslationTrait;

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
  public function getFieldDefinitions($server_id) {
    if (!isset($this->fieldDefinitions[$server_id])) {
      // Not prepared, try to load from cache.
      $cid = 'solr_field_definitions:' . $server_id;
      if ($cache = $this->cacheGet($cid)) {
        $field_definitions = $cache->data;
      }
      elseif ($field_definitions = $this->buildFieldDefinitions($server_id)) {
        // Only cache the field definitions if they aren't empty.
        $this->cacheSet($cid, $field_definitions, Cache::PERMANENT, ['search_api_server' => $server_id]);
      }
      $this->fieldDefinitions[$server_id] = $field_definitions;
    }
    return $this->fieldDefinitions[$server_id];
  }

  /**
   * Builds the field definitions for a Solr server from its Luke handler.
   *
   * @param string $server_id
   *   The server from which we are retrieving field information.
   *
   * @return \Drupal\search_api_solr_datasource\TypedData\SolrFieldDefinitionInterface[]
   *   The array of field definitions for the server, keyed by field name.
   *
   * @throws \InvalidArgumentException
   */
  protected function buildFieldDefinitions($server_id) {
    // Load the server entity.
    $server = Server::load($server_id);
    if ($server === NULL) {
      throw new \InvalidArgumentException('The Search API server could not be loaded.');
    }
    if (!$server->getBackend() instanceof SolrBackendInterface) {
      throw new \InvalidArgumentException("The Search API server's backend must be an instance of SolrBackendInterface.");
    }
    $fields = [];
    try {
      $luke = $server->getBackend()->getSolrConnector()->getLuke();
      foreach ($luke['fields'] as $label => $definition) {
        $field = new SolrFieldDefinition($definition);
        $field->setLabel($label);
        $fields[$label] = $field;
      }
    }
    catch (SearchApiSolrException $e) {
      drupal_set_message($this->t('Could not connect to server %server, %message', ['%server' => $server->id(), '%message' => $e->getMessage()]), 'error');
      // @todo Inject the logger service.
      \Drupal::logger('search_api_solr_datasource')->error('Could not connect to server %server, %message', ['%server' => $server->id(), '%message' => $e->getMessage()]);
    }
    return $fields;
  }

}
