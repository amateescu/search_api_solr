<?php

namespace Drupal\Tests\search_api_solr\Kernel;

/**
 * Tests the document datasources using the solr techproducts example.
 *
 * @group search_api_solr
 * @group solr_no_cloud
 */
class SearchApiSolrTechproductsTest extends AbstractSearchApiSolrTechproducts {

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

    $this->installConfig([
      'search_api_solr_test',
    ]);
  }

}
