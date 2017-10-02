<?php

namespace Drupal\search_api_solr\TypedData;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;

/**
 * A typed data definition class for describing Solr documents.
 */
class SolrDocumentDefinition extends ComplexDataDefinitionBase implements SolrDocumentDefinitionInterface {

  /**
   * The Search API server the Solr document definition belongs to.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * Creates a new Solr document definition.
   *
   * @param string $server_id
   *   The Search API server the Solr document definition belongs to.
   *
   * @return static
   */
  public static function create($server_id) {
    $definition['type'] = 'solr_document:' . $server_id;
    $document_definition = new static($definition);
    $document_definition->setServerId($server_id);
    return $document_definition;
  }

  /**
   * {@inheritdoc}
   */
  public static function createFromDataType($data_type) {
    // The data type should be in the form of "solr_document:$server_id".
    $parts = explode(':', $data_type, 2);
    if ($parts[0] != 'solr_document') {
      throw new \InvalidArgumentException('Data type must be in the form of "solr_document:SERVER_ID".');
    }
    if (empty($parts[1])) {
      throw new \InvalidArgumentException('A Search API Server must be specified.');
    }

    return self::create($parts[1]);
  }

  /**
   * {@inheritdoc}
   */
  public function getServerId() {
    return isset($this->definition['constraints']['Server']) ? $this->definition['constraints']['Server'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setServerId($server_id) {
    return $this->addConstraint('Server', $server_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    if (!isset($this->propertyDefinitions)) {
      $this->propertyDefinitions = [];
      if (!empty($this->getServerId())) {
        /** @var \Drupal\search_api_solr\SolrFieldManagerInterface $field_manager */
        $field_manager = \Drupal::getContainer()->get('solr_field.manager');
        $this->propertyDefinitions = $field_manager->getFieldDefinitions($this->getServerId());
      }
    }
    return $this->propertyDefinitions;
  }

}
