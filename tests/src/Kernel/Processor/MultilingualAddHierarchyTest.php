<?php

namespace Drupal\Tests\search_api_solr_multilingual\Kernel\Processor;

use Drupal\Tests\search_api_solr\Kernel\Processor\SolrBackendTrait;

/**
 * Tests the "Hierarchy" processor.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\AddHierarchy
 *
 * @group search_api_solr_multilingual
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\processor\AddHierarchy
 */
class AddHierarchyTest extends \Drupal\Tests\search_api\Kernel\Processor\AddHierarchyTest {

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
