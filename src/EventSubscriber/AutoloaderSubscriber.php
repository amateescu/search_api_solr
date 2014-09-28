<?php

/**
 * @file
 * Contains \Drupal\search_api_solr\AutoloaderSubscriber.
 */

namespace Drupal\search_api_solr\EventSubscriber;

use Drupal\Component\Utility\String;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AutoloaderSubscriber implements EventSubscriberInterface {

  /**
   * @var bool
   */
  protected $autoloaderRegistered = false;

  /**
   * Implements \Symfony\Component\EventDispatcher\EventSubscriberInterface::getSubscribedEvents().
   */
  public static function getSubscribedEvents() {
    return array(
      KernelEvents::REQUEST => array('onRequest', 999),
    );
  }

  /**
   * Registers the autoloader.
   */
  public function onRequest(GetResponseEvent $event) {
    try {
      $this->registerAutoloader();
    }
    catch (\RuntimeException $e) {
      if (PHP_SAPI !== 'cli') {
        watchdog_exception('search_api_solr', $e, NULL, array(), WATCHDOG_WARNING);
      }
    }
  }

  /**
   * Registers the autoloader.
   *
   * @throws \RuntimeException
   */
  public function registerAutoloader() {
    if (!$this->autoloaderRegistered) {

      $filepath = $this->getAutoloadFilepath();
      if (!is_file($filepath)) {
        throw new \RuntimeException(String::format('Autoloader not found: @filepath', array('@filepath' => $filepath)));
      }
      if (!class_exists('Solarium\\Client') && ($filepath != DRUPAL_ROOT . '/core/vendor/autoload.php')) {
        $this->autoloaderRegistered = TRUE;
        require $filepath;
      }
    }
  }

  /**
   * Returns the absolute path to the autoload.php file.
   *
   * @return string
   */
  public function getAutoloadFilepath() {
    return drupal_get_path('module', 'search_api_solr') . '/vendor/autoload.php';
  }

}
