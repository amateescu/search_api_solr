<?php
/**
 * Created by PhpStorm.
 * User: nick.veenhof
 * Date: 10/3/14
 * Time: 11:53 AM
 */

namespace Drupal\search_api_solr\Plugin\SearchApi\Backend;
use Drupal\search_api\Server\ServerInterface;

/**
 * Provides a trait with helper functions for classes implementing Solr Backend classes.
 */
trait SolrTrait {

  /**
   * A connection to the Solr server.
   *
   * @var \Solarium\Client
   */
  protected $solr;

  /**
   * @var
   */
  protected $search_api_server;

  /**
   * Constructs a FieldTrait object.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The field's index.
   * @param string $field_identifier
   *   The field's combined identifier, with datasource prefix if applicable.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    if (!empty($configuration['server']) && $configuration['server'] instanceof ServerInterface) {
      $this->search_api_server = $configuration['server'];
    }
  }

  /**
   * Escapes a Search API field name for passing to Solr.
   *
   * Since field names can only contain one special character, ":", there is no
   * need to use the complete escape() method.
   *
   * @param string $value
   *   The field name to escape.
   *
   * @return string
   *   An escaped string suitable for passing to Solr.
   */
  public static function escapeFieldName($value) {
    $value = str_replace(':', '\:', $value);
    return $value;
  }


} 