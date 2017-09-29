<?php

namespace Drupal\Tests\search_api_solr_multilingual\Kernel\Processor;

use Drupal\Tests\search_api_solr\Kernel\Processor\SolrBackendTrait;

/**
 * Tests the "Content access" processor.
 *
 * @group search_api_solr_multilingual
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\ContentAccess
 */
class ContentAccessTest extends \Drupal\Tests\search_api\Kernel\Processor\ContentAccessTest  {

  use SolrBackendTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'search_api_solr',
    'search_api_solr_multilingual',
    'search_api_solr_multilingual_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL) {
    parent::setUp();
    $this->enableSolrServer('search_api_solr_multilingual_test', '/config/install/search_api.server.solr_multilingual_search_server.yml');
  }

}
