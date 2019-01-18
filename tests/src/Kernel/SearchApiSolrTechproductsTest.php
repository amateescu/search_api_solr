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
    'search_api_solr_test',
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
      'search_api_solr_test',
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
      "solr_document/0579B002",
      "solr_document/100-435805",
      "solr_document/3007WFP",
      "solr_document/6H500F0",
      "solr_document/9885A004",
      "solr_document/EN7800GTX/2DHTV/256M",
      "solr_document/EUR",
      "solr_document/F8V7067-APL-KIT",
      "solr_document/GB18030TEST",
      "solr_document/GBP",
    ], array_keys($result->getResultItems()), 'Search for all tech products');

    $server = $this->getIndex()->getServerInstance();
    $config = $server->getBackendConfig();

    // Test processor based highlighting.
    $query = $this->buildSearch('Technology', [], ['manu']);
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »Technology« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $this->assertContains('<strong>Technology</strong>', (string) $result->getExtraData('highlighted_fields', ['manu' => ['']])['manu'][0]);
      $this->assertEmpty($result->getExtraData('highlighted_keys', []));
      $this->assertEquals('… A-DATA <strong>Technology</strong> Inc. …', $result->getExcerpt());
    }

    // Test server based highlighting.
    $config['highlight_data'] = TRUE;
    $server->setBackendConfig($config);
    $server->save();

    $query = $this->buildSearch('Technology', [], ['manu']);
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »Technology« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $this->assertContains('<strong>Technology</strong>', (string) $result->getExtraData('highlighted_fields', ['manu' => ['']])['manu'][0]);
      $this->assertEquals(['Technology'], $result->getExtraData('highlighted_keys', []));
      $this->assertEquals('… A-DATA <strong>Technology</strong> Inc. …', $result->getExcerpt());
    }
  }

}
