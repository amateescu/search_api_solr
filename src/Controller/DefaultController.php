<?php

/**
 * @file
 * Contains Drupal\apachesolr_multilingual\Controller\DefaultController.
 */

namespace Drupal\apachesolr_multilingual\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class DefaultController.
 *
 * @package Drupal\apachesolr_multilingual\Controller
 */
class DefaultController extends ControllerBase {
  /**
   * Hello.
   *
   * @return string
   *   Return Hello string.
   */
  public function hello($name) {
    return [
        '#type' => 'markup',
        '#markup' => $this->t('Hello @name!', ['@name' => $name])
    ];
  }

}
