<?php

/**
 * @file
 * Contains \Drupal\search_api_solr\Tests\IntegrationTest.
 */

namespace Drupal\search_api_solr_multilingual\Tests;

/**
 * Tests the overall functionality of the Search API framework and admin UI.
 *
 * @group search_api_solr_multilingual
 */
class IntegrationTest extends \Drupal\search_api_solr\Tests\IntegrationTest {

  /**
   * {@inheritdoc}
   */
  protected $serverBackend = 'search_api_solr_multilingual';

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'search_api_solr_multilingual',
  );

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function testFramework() {
    parent::testFramework();
  }

}