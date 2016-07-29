<?php

namespace Drupal\search_api_solr\Tests;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Tests\WebTestBase;

/**
 * Tests the Views integration of the Search API.
 *
 * @group search_api_solr
 */
class ViewsTest extends \Drupal\search_api\Tests\ViewsTest {

  use ExampleContentTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = array('search_api_solr_test');

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    // Skip parent::setUp().
    WebTestBase::setUp();

    // Swap database backend for Solr backend.
    $config_factory = \Drupal::configFactory();
    $config_factory->getEditable('search_api.index.database_search_index')->delete();
    $config_factory->rename('search_api.index.solr_search_index', 'search_api.index.database_search_index');
    $config_factory->getEditable('search_api.index.database_search_index')->set('id', 'database_search_index')->save();

    // Now do the same as parent::setUp().
    $this->setUpExampleStructure();
    \Drupal::getContainer()
      ->get('search_api.index_task_manager')
      ->addItemsAll(Index::load($this->indexId));
    $this->insertExampleContent();
    $this->indexItems($this->indexId);
  }

}
