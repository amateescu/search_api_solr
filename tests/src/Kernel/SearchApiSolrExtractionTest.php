<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\search_api\Entity\Server;

/**
 * Test tika extension based PDF extraction.
 *
 * @group search_api_solr
 */
class SearchApiSolrExtractionTest extends SolrBackendTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'search_api_solr_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function installConfigs() {
    parent::installConfigs();

    $this->installConfig(['search_api_solr_test']);
  }

  /**
   * Test tika extension based PDF extraction.
   */
  public function testBackend() {
    $filepath = drupal_get_path('module', 'search_api_solr_test') . '/assets/test_extraction.pdf';
    /** @var \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend $backend */
    $backend = Server::load($this->serverId)->getBackend();
    $content = $backend->extractContentFromFile($filepath);
    $this->assertContains('The extraction seems working!', $content);
  }

}
