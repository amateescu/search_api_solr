<?php

namespace Drupal\search_api_solr;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\TypedData\SolrFieldDefinition;
use Psr\Log\LoggerInterface;

/**
 * Manages the discovery of Solr fields.
 */
class SolrFieldManager implements SolrFieldManagerInterface {

  use UseCacheBackendTrait;
  use StringTranslationTrait;
  use LoggerTrait;

  /**
   * Static cache of field definitions per Solr server.
   *
   * @var array
   */
  protected $fieldDefinitions;

  /**
   * Storage for Search API servers.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $serverStorage;

  /**
   * Constructs a new SorFieldManager.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger for Search API.
   */
  public function __construct(CacheBackendInterface $cache_backend, EntityTypeManagerInterface $entityTypeManager, LoggerInterface $logger) {
    $this->cacheBackend = $cache_backend;
    $this->serverStorage = $entityTypeManager->getStorage('search_api_server');
    $this->setLogger($logger);
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
      else {
        /** @var \Drupal\search_api\ServerInterface|null $server */
        $server = $this->serverStorage->load($server_id);
        // Load the server entity.
        if ($server === NULL) {
          throw new \InvalidArgumentException('The Search API server could not be loaded.');
        }

        // Don't attempt to connect to server if config is disabled. Cache will
        // clear itself when server config is enabled again.
        $field_definitions = $server->status() ? $this->buildFieldDefinitions($server) : [];
        $this->cacheSet($cid, $field_definitions, Cache::PERMANENT, $server->getCacheTagsToInvalidate());
      }

      $this->fieldDefinitions[$server_id] = $field_definitions;
    }
    return $this->fieldDefinitions[$server_id];
  }

  /**
   * Builds the field definitions for a Solr server from its Luke handler.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   The server from which we are retrieving field information.
   *
   * @return \Drupal\search_api_solr\TypedData\SolrFieldDefinitionInterface[]
   *   The array of field definitions for the server, keyed by field name.
   *
   * @throws \InvalidArgumentException
   */
  protected function buildFieldDefinitions(ServerInterface $server) {
    $backend = $server->getBackend();
    if (!$backend instanceof SolrBackendInterface) {
      throw new \InvalidArgumentException("The Search API server's backend must be an instance of SolrBackendInterface.");
    }
    $fields = [];
    try {
      $luke = $backend->getSolrConnector()->getLuke();
      foreach ($luke['fields'] as $name => $definition) {
        $field = new SolrFieldDefinition($definition);
        $label = Unicode::ucfirst(trim(str_replace('_', ' ', $name)));
        $field->setLabel($label);
        // The Search API can't deal with arbitrary item types. To make things
        // easier, just use one of those known to the Search API.
        if (strpos($field->getDataType(), 'text') !== FALSE) {
          $field->setDataType('search_api_text');
        }
        elseif (strpos($field->getDataType(), 'date') !== FALSE) {
          $field->setDataType('timestamp');
        }
        elseif (strpos($field->getDataType(), 'int') !== FALSE) {
          $field->setDataType('integer');
        }
        elseif (strpos($field->getDataType(), 'long') !== FALSE) {
          $field->setDataType('integer');
        }
        elseif (strpos($field->getDataType(), 'float') !== FALSE) {
          $field->setDataType('float');
        }
        elseif (strpos($field->getDataType(), 'double') !== FALSE) {
          $field->setDataType('float');
        }
        elseif (strpos($field->getDataType(), 'bool') !== FALSE) {
          $field->setDataType('boolean');
        }
        else {
          $field->setDataType('string');
        }
        $fields[$name] = $field;
      }
    }
    catch (SearchApiSolrException $e) {
      $this->getLogger()->error('Could not connect to server %server, %message', ['%server' => $server->id(), '%message' => $e->getMessage()]);
    }
    return $fields;
  }

}
