<?php

namespace Drupal\search_api_solr_devel\Logging;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\devel\DevelDumperManagerInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api_solr\Utility\Utility;
use Solarium\Core\Event\Events;
use Solarium\Core\Event\PreExecuteRequest;
use Solarium\Core\Event\PostExecuteRequest;
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
      Events::PRE_EXECUTE_REQUEST => 'preExecuteRequest',
      Events::POST_EXECUTE_REQUEST => 'postExecuteRequest',
    ];
  }

  /**
   * Dumps a Solr query as drupal messages.
   *
   * @param \Solarium\Core\Event\PreExecuteRequest $event
   *   The pre execute event.
   */
  public function preExecuteRequest(PreExecuteRequest $event) {
    $request = $event->getRequest();
    $parsedRequestParams = Utility::parseRequestParams($request);

    $this->develDumperManager->message(
      $request->getUri(),
      $this->t('Try to send Solr request')
    );
    $this->develDumperManager->message(
      $parsedRequestParams,
      $request->getMethod()
    );

    $this->getLogger()->debug($request->getQueryString());
  }

  /**
   * Dumps a Solr response status as drupal messages and logs the response body.
   *
   * @param \Solarium\Core\Event\PostExecuteRequest $event
   *   The post execute event.
   */
  public function postExecuteRequest(PostExecuteRequest $event) {
    $response = $event->getResponse();

    $this->develDumperManager->message(
      $response->getStatusCode() . ' ' . $response->getStatusMessage(),
      $this->t('Received Solr response')
    );

    $this->getLogger()->debug($response->getBody());
    $this->showLoggerHint();
  }

  /**
   * Helper function for postExecuteRequest().
   */
  protected function showLoggerHint() {
    static $hint = FALSE;

    if (!$hint) {
      $hint = TRUE;
      $this->develDumperManager->message(
        'Type: search_api, Severity: Debug',
        $this->t('Check the logs for detailed Solr response bodies'),
        'warning');
    }
  }

}
