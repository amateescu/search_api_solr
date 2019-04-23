<?php

namespace Drupal\Tests\search_api_solr\Kernel;

/**
 * Tests index and search capabilities using the Solr search backend.
 *
 * @group search_api_solr
 * @group solr_cloud
 */
class SearchApiSolrCloudTest extends AbstractSearchApiSolr {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'search_api_solr_cloud_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function installConfigs() {
    parent::installConfigs();

    $this->installConfig(['search_api_solr_cloud_test']);
  }

}
