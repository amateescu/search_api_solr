<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrConfigInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides different listings of SolrRequestHandler.
 */
class SolrRequestHandlerController extends AbstractSolrEntityController {

  /**
   * Constructs a SolrCacheController object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    parent::__construct($messenger);
    $this->entity_type_id = 'solr_request_handler';
    $this->disabled_key = 'disabled_request_handlers';
    $this->collection_route = 'entity.solr_request_handler.collection';
  }

  /**
   * Disables a Solr Entity on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   * @param \Drupal\search_api_solr\SolrConfigInterface $solr_request_handler
   * @return RedirectResponse
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function disableOnServer(ServerInterface $search_api_server, SolrConfigInterface $solr_request_handler) {
    return parent::disableOnServer($search_api_server, $solr_request_handler);
  }

  /**
   * Enables a Solr Entity on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   * @param \Drupal\search_api_solr\SolrConfigInterface $solr_request_handler
   *
   * @return RedirectResponse
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function enableOnServer(ServerInterface $search_api_server, SolrConfigInterface $solr_request_handler) {
    return parent::enableOnServer($search_api_server, $solr_request_handler);
  }

}
