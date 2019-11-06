<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrCacheInterface;
use Drupal\search_api_solr\SolrFieldTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipStream\Option\Archive;

/**
 * Provides different listings of SolrFieldType.
 */
class SolrCacheController extends ControllerBase {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * Constructs a SolrFieldTypeController object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * Provides the listing page.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return array
   *   A render array as expected by drupal_render().
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function listing(ServerInterface $search_api_server) {
    return $this->getListBuilder($search_api_server)->render();
  }

  /**
   * Gets the list builder for 'solr_cache'.
   *
   * Ensures that the list builder uses the correct Solr backend.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return \Drupal\search_api_solr\Controller\SolrCacheListBuilder
   *   The SolrCache list builder object.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getListBuilder(ServerInterface $search_api_server) {
    /** @var SolrCacheListBuilder $list_builder */
    $list_builder = $this->entityTypeManager()->getListBuilder('solr_cache');
    $list_builder->setServer($search_api_server);
    return $list_builder;
  }

  /**
   * Provides an XML snippet containing all query cache settings as XML.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSolrconfigQueryXml(ServerInterface $search_api_server) {
    return new Response(
      $this->getListBuilder($search_api_server)->getCachesXml(),
      200,
      [
        'Content-Type' => 'application/xml',
        'Content-Disposition' => 'attachment; filename=ssolrconfig_query.xml',
      ]
    );
  }

  /**
   * Disables a Solr Cache on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   * @param \Drupal\search_api_solr\SolrCacheInterface $solr_cache
   *
   * @return RedirectResponse
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function disableOnServer(ServerInterface $search_api_server, SolrCacheInterface $solr_cache) {
    $backend_config = $search_api_server->getBackendConfig();
    $backend_config['disabled_caches'][] = $solr_cache->id();
    $backend_config['disabled_caches'] = array_unique($backend_config['disabled_caches']);
    $search_api_server->setBackendConfig($backend_config);
    $search_api_server->save();
    return new RedirectResponse(Url::fromRoute('entity.solr_cache.collection', ['search_api_server' => $search_api_server->id()], ['query' => ['time' => \Drupal::time()->getRequestTime()]])->toString());
  }

  /**
   * Enables a Solr Field Type on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   * @param \Drupal\search_api_solr\SolrCacheInterface $solr_cache
   *
   * @return RedirectResponse
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function enableOnServer(ServerInterface $search_api_server, SolrCacheInterface $solr_cache) {
    $backend_config = $search_api_server->getBackendConfig();
    $backend_config['disabled_caches'] = array_values(array_diff($backend_config['disabled_caches'], [$solr_cache->id()]));
    $search_api_server->setBackendConfig($backend_config);
    $search_api_server->save();
    return new RedirectResponse(Url::fromRoute('entity.solr_cache.collection', ['search_api_server' => $search_api_server->id()], ['query' => ['time' => \Drupal::time()->getRequestTime()]])->toString());
  }

}
