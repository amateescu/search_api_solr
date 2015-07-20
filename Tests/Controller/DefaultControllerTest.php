<?php

/**
 * @file
 * Contains Drupal\apachesolr_multilingual\Tests\DefaultController.
 */

namespace Drupal\apachesolr_multilingual\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Provides automated tests for the apachesolr_multilingual module.
 */
class DefaultControllerTest extends WebTestBase {
  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => "apachesolr_multilingual DefaultController's controller functionality",
      'description' => 'Test Unit for module apachesolr_multilingual and controller DefaultController.',
      'group' => 'Other',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests apachesolr_multilingual functionality.
   */
  public function testDefaultController() {
    // Check that the basic functions of module apachesolr_multilingual.
    $this->assertEqual(TRUE, TRUE, 'Test Unit Generated via App Console.');
  }

}
