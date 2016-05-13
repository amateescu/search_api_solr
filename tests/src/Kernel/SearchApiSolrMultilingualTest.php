<?php

/**
 * @file
 * Contains \Drupal\search_api_solr_multilingual\Tests\SearchApiSolrMultilingualTest.
 */

namespace Drupal\Tests\search_api_solr_multilingual\Kernel;

use Drupal\Tests\search_api\Kernel\BackendTestBase;
use Drupal\Tests\search_api_solr\Kernel\SearchApiSolrTest;

/**
 * Tests index and search capabilities using the Solr search backend.
 *
 * @group search_api_solr_multilingual
 */
class SearchApiSolrMultilingualTest extends SearchApiSolrTest {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = array(
    'search_api_solr_multilingual',
    'search_api_solr_multilingual_test',
  );

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
    BackendTestBase::setUp();

    $this->installConfig(array('search_api_solr_multilingual_test'));

    $this->detectSolrAvailability();
  }

}
