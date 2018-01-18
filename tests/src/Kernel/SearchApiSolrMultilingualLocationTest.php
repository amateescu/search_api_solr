<?php

namespace Drupal\Tests\search_api_solr\Kernel;

/**
 * Tests index and search capabilities using the Solr search backend.
 *
 * @group search_api_solr
 */
class SearchApiSolrMultilingualLocationTest extends SearchApiSolrLocationTest {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'language',
    'search_api_solr_multilingual_test',
  ];

  /**
   * A Search API server ID.
   *
   * @var string
   */
  protected $serverId = 'solr_multilingual_search_server';

  /**
   * A Search API index ID.
   *
   * @var string
   */
  protected $indexId = 'solr_multilingual_search_index';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    SolrBackendTestBase::setUp();
  }

  /**
   * {@inheritdoc}
   */
  protected function installConfigs() {
    parent::installConfigs();

    $this->installConfig([
      'search_api_solr_multilingual_test',
    ]);
  }

}
