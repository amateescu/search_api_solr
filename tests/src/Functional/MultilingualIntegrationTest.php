<?php

namespace Drupal\Tests\search_api_solr\Functional;

/**
 * Tests the overall functionality of the Search API framework and admin UI.
 *
 * @group search_api_solr
 */
class MultilingualIntegrationTest extends \Drupal\Tests\search_api_solr\Functional\IntegrationTest {

  /**
   * {@inheritdoc}
   */
  protected $serverBackend = 'search_api_solr_multilingual';

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
