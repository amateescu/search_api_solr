<?php

/**
 * @file
 * Contains Drupal\apachesolr_multilingual\Entity\SolrFieldType.
 */

namespace Drupal\apachesolr_multilingual\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\apachesolr_multilingual\SolrFieldTypeInterface;

/**
 * Defines the SolrFieldType entity.
 *
 * @ConfigEntityType(
 *   id = "solr_field_type",
 *   label = @Translation("Solr Field Type"),
 *   handlers = {
 *     "list_builder" = "Drupal\apachesolr_multilingual\Controller\SolrFieldTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\apachesolr_multilingual\Form\SolrFieldTypeForm",
 *       "edit" = "Drupal\apachesolr_multilingual\Form\SolrFieldTypeForm",
 *       "delete" = "Drupal\apachesolr_multilingual\Form\SolrFieldTypeDeleteForm"
 *     }
 *   },
 *   config_prefix = "solr_field_type",
 *   admin_permission = "administer search_api",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/search/search-api/server/{search_api_server}/multilingual/solr_field_type/{solr_field_type}",
 *     "delete-form" = "/admin/config/search/search-api/server/{search_api_server}/multilingual/solr_field_type/{solr_field_type}/delete",
 *     "collection" = "/admin/config/search/search-api/server/{search_api_server}/multilingual/solr_field_type"
 *   }
 * )
 */
class SolrFieldType extends ConfigEntityBase implements SolrFieldTypeInterface {
  /**
   * The SolrFieldType ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The SolrFieldType label.
   *
   * @var string
   */
  protected $label;

  /**
   * Solr Field Type definition
   *
   * @var array
   */
  protected $field_type;

  /**
   * Array of text files.
   *
   * @var array
   */
  protected $text_files;

  public function getFieldType() {
    return $this->field_type;
  }

  public function getFieldTypeAsJson() {
    return Json::encode($this->field_type);
  }

  public function setFieldTypeAsJson($field_type) {
    $this->field_type = Json::decode($field_type);
    return $this;
  }

  public function getTextFiles() {
    return $this->text_files;
  }

  public function addTextFile($name, $content) {
    $this->text_files[$name] = preg_replace('/\R/u', "\n", $content);
  }

  public function setTextFiles($text_files) {
    $this->text_files = [];
    foreach ($text_files as $name => $content) {
      $this->addTextFile($name, $content);
    }
  }

  /**
   * Gets an array of placeholders for this entity.
   *
   * Individual entity classes may override this method to add additional
   * placeholders if desired. If so, they should be sure to replicate the
   * property caching logic.
   *
   * @param string $rel
   *   The link relationship type, for example: canonical or edit-form.
   *
   * @return array
   *   An array of URI placeholders.
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    $uri_route_parameters['search_api_server'] = \Drupal::routeMatch()->getRawParameter('search_api_server');

    return $uri_route_parameters;
  }

}
