<?php

namespace Drupal\search_api_solr_datasource\Plugin\DataType;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\TypedData;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api_solr_datasource\TypedData\SolrDocumentDefinition;

/**
 * Defines the "Solr document" data type.
 *
 * Instances of this class wrap Search API Item objects and allow to deal with
 * items based upon the Typed Data API.
 *
 * @DataType(
 *   id = "solr_document",
 *   label = @Translation("Solr document"),
 *   description = @Translation("Records from a Solr index."),
 *   definition_class = "\Drupal\search_api_solr_datasource\TypedData\SolrDocumentDefinition"
 * )
 */
class SolrDocument extends TypedData implements \IteratorAggregate, ComplexDataInterface {

  /**
   * The wrapped Search API Item.
   *
   * @var \Drupal\search_api\Item\ItemInterface|null
   */
  protected $item;

  /**
   * Creates an instance wrapping the given Item.
   *
   * @param \Drupal\search_api\Item\ItemInterface|null $item
   *   The Item object to wrap.
   *
   * @return static
   */
  public static function createFromItem(ItemInterface $item) {
    $server_id = $item->getIndex()->getServerInstance()->id();
    $definition = SolrDocumentDefinition::create($server_id);
    $instance = new static($definition);
    $instance->setValue($item);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->item;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($item, $notify = TRUE) {
    $this->item = $item;
  }

  /**
   * {@inheritdoc}
   */
  public function get($property_name) {
    if (!isset($this->item)) {
      throw new MissingDataException("Unable to get Solr field $property_name as no item has been provided.");
    }
    $field = $this->item->getField($property_name);
    if ($field === NULL) {
      throw new \InvalidArgumentException("The Solr field $property_name has not been configured in the index.");
    }
    // Create a new typed data object from the item's field data.
    return SolrField::createFromField($field, $property_name, $this);
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value, $notify = TRUE) {
    // Do nothing because we treat Solr documents as read-only.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties($include_computed = FALSE) {
    // @todo Implement this.
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    // @todo Implement this.
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return !isset($this->item);
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($name) {
    // Do nothing.  Unlike content entities, Items don't need to be notified of
    // changes.
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    return isset($this->item) ? $this->item->getIterator() : new \ArrayIterator([]);
  }

}
