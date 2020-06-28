<?php

namespace Drupal\search_api_solr_devel\Logging;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\devel\DevelDumperManagerInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api_solr\Utility\Utility;
use Solarium\Core\Event\Events;
use Solarium\QueryType\Select\Query\Query;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to handle Solarium events.
 */
class SolariumRequestLogger implements EventSubscriberInterface {

  use StringTranslationTrait;
  use LoggerTrait;

  /**
   * The Devel dumper manager.
   *
   * @var \Drupal\devel\DevelDumperManagerInterface
   */
  protected $develDumperManager;

  /**
   * Constructs a ModuleRouteSubscriber object.
   *
   * @param \Drupal\devel\DevelDumperManagerInterface $develDumperManager
   *   The dump manager.
   */
  public function __construct(DevelDumperManagerInterface $develDumperManager) {
    $this->develDumperManager = $develDumperManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      Events::POST_CREATE_QUERY => 'postCreateQuery',
      Events::PRE_EXECUTE_REQUEST => 'preExecuteRequest',
      Events::POST_EXECUTE_REQUEST => 'postExecuteRequest',
    ];
  }

  /**
   * Dumps a Solr query as drupal messages.
   *
   * @param \Drupal\search_api_solr\Solarium\EventDispatcher\EventProxy $event
   *   The pre execute event.
   */
  public function postCreateQuery($event) {
    /** @var \Solarium\Core\Event\PostCreateQuery $event */
    $query = $event->getQuery();
    if ($query instanceof Query) {
      /** @var $query */
      $query->getDebug();
      $query->addParam('echoParams', 'all')
        ->setOmitHeader(FALSE);
    }
  }

  /**
   * Dumps a Solr query as drupal messages.
   *
   * @param \Drupal\search_api_solr\Solarium\EventDispatcher\EventProxy $event
   *   The pre execute event.
   */
  public function preExecuteRequest($event) {
    /** @var \Solarium\Core\Event\PreExecuteRequest $event */
    $request = $event->getRequest();

    $this->develDumperManager->message($request, 'Solr request', 'debug', 'kint');
    $this->develDumperManager->debug($request, 'Solr request');
  }

  /**
   * Dumps a Solr response status as drupal messages and logs the response body.
   *
   * @param \Drupal\search_api_solr\Solarium\EventDispatcher\EventProxy $event
   *   The post execute event.
   */
  public function postExecuteRequest($event) {
    /** @var \Solarium\Core\Event\PostExecuteRequest $event */
    $response = $event->getResponse();

    $this->develDumperManager->debug($response, 'Solr response');
  }
}
