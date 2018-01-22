<?php

namespace Drupal\Tests\search_api_solr\Kernel;

/**
 * Tests the 'Any Schema' Solr search backend.
 *
 * @group search_api_solr
 */
class SearchApiSolrTechproductsTest extends SolrBackendTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'search_api_solr_techproducts_test',
  ];

  /**
   * A Search API server ID.
   *
   * @var string
   */
  protected $serverId = 'techproducts';

  /**
   * A Search API index ID.
   *
   * @var string
   */
  protected $indexId = 'techproducts';

  /**
   * {@inheritdoc}
   */
  protected function installConfigs() {
    parent::installConfigs();

    $this->installConfig([
      'search_api_solr_techproducts_test',
    ]);
  }

  /**
   *
   */
  protected function getItemIds(array $result_ids) {
    return $result_ids;
  }

  /**
   * Tests location searches and distance facets.
   */
  public function testBackend() {
    /** @var \Drupal\search_api\Query\ResultSet $result */
    $query = $this->buildSearch(NULL, [], NULL, FALSE)
      ->sort('search_api_id');
    $result = $query->execute();
    $this->assertEquals([
      "0579B002",
      "100-435805",
      "3007WFP",
      "6H500F0",
      "9885A004",
      "EN7800GTX/2DHTV/256M",
      "EUR",
      "F8V7067-APL-KIT",
      "GB18030TEST",
      "GBP",
    ], array_keys($result->getResultItems()), 'Search for all tech products');
  }

}
