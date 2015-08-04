<?php
/**
 * @file
 * Contains \Drupal\apachesolr_multilingual\EventSubscriber\ConfigSubscriber.
 */

namespace Drupal\apachesolr_multilingual\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigInstallerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface {
  /**
   * The config installer.
   */
  protected $configInstaller;

  /**
   * @param $configInstaller
   *   The Config Installer.
   */
  public function __construct(ConfigInstallerInterface $configInstaller) {
    $this->configInstaller = $configInstaller;
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = array('onConfigSave');
    return $events;
  }

  function onConfigSave(ConfigCrudEvent $event) {
    $saved_config = $event->getConfig();

    if (preg_match('@^language\.entity\.(.+)@', $saved_config->getName(), $matches) &&
        $matches[1] != 'und') {
      $restrict_by_dependency = [
        'module' => 'apachesolr_multilingual',
      ];
      // installOptionalConfig will not replace existing configs
      // and it contains a dependency check so we need not perform
      // any checks ourselves
      $this->configInstaller->installOptionalConfig(NULL, $restrict_by_dependency);
    }
    // drupal_set_message($saved_config->getName());
    // drupal_set_message(print_r($saved_config->getRawData(), TRUE));
  }

}
