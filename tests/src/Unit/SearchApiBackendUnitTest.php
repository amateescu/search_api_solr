<?php

namespace Drupal\Tests\search_api_solr\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\search_api\Utility\DataTypeHelperInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Drupal\search_api_solr\SolrConnector\SolrConnectorPluginManager;
use Drupal\search_api_solr\SolrConnectorInterface;
use Drupal\Tests\search_api_solr\Traits\InvokeMethodTrait;
use Drupal\Tests\UnitTestCase;
use Solarium\Core\Query\Helper;
use Solarium\QueryType\Update\Query\Document\Document;

// @see datetime.module
define('DATETIME_STORAGE_TIMEZONE', 'UTC');

/**
 * Tests functionality of the backend.
 *
 * @coversDefaultClass \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend
 *
 * @group search_api_solr
 */
class SearchApiBackendUnitTest extends UnitTestCase {

  use InvokeMethodTrait;

  /**
   * @covers       ::addIndexField
   *
   * @dataProvider addIndexFieldDataProvider
   *
   * @param mixed $input
   *   Field value.
   *
   * @param string $type
   *   Field type.
   *
   * @param mixed $expected
   *   Expected result.
   */
  public function testIndexField($input, $type, $expected) {
    $field = 'testField';
    $document = $this->prophesize(Document::class);
    $document
      ->addField($field, $expected)
      ->shouldBeCalled();

    $args = [
      $document->reveal(),
      $field,
      [$input],
      $type,
    ];

    // Get dummies for most constructor args of SearchApiSolrBackend.
    $module_handler = $this->prophesize(ModuleHandlerInterface::class)->reveal();
    $config = $this->prophesize(Config::class)->reveal();
    $language_manager = $this->prophesize(LanguageManagerInterface::class)->reveal();
    $solr_connector_plugin_manager = $this->prophesize(SolrConnectorPluginManager::class)->reveal();
    $fields_helper = $this->prophesize(FieldsHelperInterface::class)->reveal();
    $data_type_helper = $this->prophesize(DataTypeHelperInterface::class)->reveal();

    // This helper is actually used.
    $query_helper = new Helper();

    $backend = new SearchApiSolrBackend([], NULL, [], $module_handler, $config, $language_manager, $solr_connector_plugin_manager, $fields_helper, $data_type_helper, $query_helper);

    // addIndexField() should convert the $input according to $type and call
    // Document::addField() with the correctly converted $input.
    $this->invokeMethod(
      $backend,
      'addIndexField',
      $args,
      []
    );
  }

  /**
   * Data provider for testIndexField method. Set of values can be extended to
   * check other field types and values.
   *
   * @return array
   */
  public function addIndexFieldDataProvider() {
    return [
      ['0', 'boolean', 'false'],
      ['1', 'boolean', 'true'],
      [0, 'boolean', 'false'],
      [1, 'boolean', 'true'],
      [FALSE, 'boolean', 'false'],
      [TRUE, 'boolean', 'true'],
      ['2016-05-25T14:00:00+10', 'date', '2016-05-25T04:00:00Z'],
      ['1465819200', 'date', '2016-06-13T12:00:00Z'],
    ];
  }

}
