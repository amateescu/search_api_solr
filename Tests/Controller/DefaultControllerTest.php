<?php

/**
 * @file
 * Contains Drupal\search_api_solr_multilingual\Tests\DefaultController.
 */

namespace Drupal\search_api_solr_multilingual\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Provides automated tests for the search_api_solr_multilingual module.
 */
class DefaultControllerTest extends WebTestBase {
  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => "search_api_solr_multilingual DefaultController's controller functionality",
      'description' => 'Test Unit for module search_api_solr_multilingual and controller DefaultController.',
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
   * Tests search_api_solr_multilingual functionality.
   */
  public function testDefaultController() {
    // Check that the basic functions of module search_api_solr_multilingual.
    $this->assertEqual(TRUE, TRUE, 'Test Unit Generated via App Console.');
  }

}
