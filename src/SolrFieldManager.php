<?php

namespace Drupal\search_api_solr;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_solr\TypedData\SolrFieldDefinition;

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
   * @return \Drupal\search_api_solr\TypedData\SolrFieldDefinitionInterface[]
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
      drupal_set_message($this->t('Could not connect to server %server, %message', ['%server' => $server->id(), '%message' => $e->getMessage()]), 'error');
      // @todo Inject the logger service.
      \Drupal::logger('search_api_solr')->error('Could not connect to server %server, %message', ['%server' => $server->id(), '%message' => $e->getMessage()]);
    }
    return $fields;
  }

}
