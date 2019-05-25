<?php

namespace Drupal\search_api_solr;

use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\search_api\Item\ItemInterface;

/**
 * Defines a class for a Solr Document factory.
 */
class SolrDocumentFactory implements SolrDocumentFactoryInterface {

  protected static $solr_document = 'solr_document';

  /**
   * A typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * Constructs a SolrDocumentFactory object.
   *
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typedDataManager
   *   A typed data manager.
   */
  public function __construct(TypedDataManagerInterface $typedDataManager) {
    $this->typedDataManager = $typedDataManager;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function create(ItemInterface $item) {
    $plugin = $this->typedDataManager->getDefinition(static::$solr_document)['class'];
    return $plugin::createFromItem($item);
  }

}
