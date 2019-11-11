<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrConfigInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides different listings of SolrCache.
 */
class SolrCacheController extends AbstractSolrEntityController {

  /**
   * Constructs a SolrCacheController object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    parent::__construct($messenger);
    $this->entity_type_id = 'solr_cache';
    $this->disabled_key = 'disabled_caches';
    $this->collection_route = 'entity.solr_cache.collection';
  }

  /**
   * Disables a Solr Entity on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   * @param \Drupal\search_api_solr\SolrConfigInterface $solr_cache
   *
   * @return RedirectResponse
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function disableOnServer(ServerInterface $search_api_server, SolrConfigInterface $solr_cache) {
    return parent::disableOnServer($search_api_server, $solr_cache);
  }

  /**
   * Enables a Solr Entity on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   * @param \Drupal\search_api_solr\SolrConfigInterface $solr_cache
   *
   * @return RedirectResponse
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function enableOnServer(ServerInterface $search_api_server, SolrConfigInterface $solr_cache) {
    return parent::enableOnServer($search_api_server, $solr_cache);
  }

}
