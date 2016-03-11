<?php

/**
 * @file
 * Contains Drupal\search_api_solr_multilingual\Entity\SolrFieldType.
 */

namespace Drupal\search_api_solr_multilingual\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\search_api_solr\Utility\Utility as SearchApiSolrUtility;
use Drupal\search_api_solr_multilingual\SolrFieldTypeInterface;
use Drupal\search_api_solr_multilingual\Utility\Utility;

/**
 * Defines the SolrFieldType entity.
 *
 * @ConfigEntityType(
 *   id = "solr_field_type",
 *   label = @Translation("Solr Field Type"),
 *   handlers = {
 *     "list_builder" = "Drupal\search_api_solr_multilingual\Controller\SolrFieldTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\search_api_solr_multilingual\Form\SolrFieldTypeForm",
 *       "edit" = "Drupal\search_api_solr_multilingual\Form\SolrFieldTypeForm",
 *       "delete" = "Drupal\search_api_solr_multilingual\Form\SolrFieldTypeDeleteForm",
 *       "export" = "Drupal\search_api_solr_multilingual\Form\SolrFieldTypeExportForm"
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
 *     "export-form" = "/admin/config/search/search-api/server/{search_api_server}/multilingual/solr_field_type/{solr_field_type}/export",
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
   * Indicates if the field type requires a managed schema.
   *
   * @var boolean
   */
  protected $managed_schema;

  /**
   * Minimum Solr version required by this field type.
   *
   * @var string
   */
  protected $minimum_solr_version;

  /**
   * Solr Field Type definition
   *
   * @var array
   */
  protected $field_type;

  /**
   * Array of various text files required by the Solr Field Type definition.
   *
   * @var array
   */
  protected $text_files;

  /**
   * {@inheritdoc}
   */
  public function getFieldType() {
    return $this->field_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypeAsJson() {
    return Json::encode($this->field_type);
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldTypeAsJson($field_type) {
    $this->field_type = Json::decode($field_type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypeAsXml($add_commment = TRUE) {
    $root = new \SimpleXMLElement('<fieldType/>');

    $f = function (\SimpleXMLElement $element, array $attributes) use (&$f) {
      foreach ($attributes as $key => $value) {
        if (!empty($value)) {
          if (is_scalar($value)) {
            $element->addAttribute($key, $value);
          }
          elseif (is_array($value)) {
            if (array_key_exists(0, $value)) {
              $key = rtrim($key, 's');
              foreach ($value as $attributes) {
                $child = $element->addChild($key);
                $f($child, $attributes);
              }
            }
            else {
              $child = $element->addChild($key);
              $f($child, $value);
            }
          }
        }
      }
    };
    $f($root, $this->field_type);

    $comment = '';
    if ($add_commment) {
      $comment = "<!--\n" . $this->label() . "\n" .
        ($this->isManagedSchema() ? " for managed schema\n" : '') .
        $this->getMinimumSolrVersion() .
        "\n-->\n";
    }

    // Remove the XML declaration before returning the XML fragment.
    return $comment . "\n" . preg_replace('/<\?.*?\?>/', '', $root->asXML());
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicFields() {
    $dynamic_fields = [];
    foreach (array('ts', 'tm') as $prefix) {
      $dynamic_fields[] = [
        'name' => SearchApiSolrUtility::encodeSolrDynamicFieldName(
            Utility::getLanguageSpecificSolrDynamicFieldPrefix($prefix, $this->langcode)
          ) . '*',
        'type' => $this->field_type['name'],
        'stored' => TRUE,
        'indexed' => TRUE,
        'multiValued' => strpos($prefix, 'm') !== FALSE,
        'termVectors' => strpos($prefix, 't') === 0,

      ];
    }
    return $dynamic_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getTextFiles() {
    return $this->text_files;
  }

  /**
   * {@inheritdoc}
   */
  public function addTextFile($name, $content) {
    $this->text_files[$name] = preg_replace('/\R/u', "\n", $content);
  }

  /**
   * {@inheritdoc}
   */
  public function setTextFiles($text_files) {
    $this->text_files = [];
    foreach ($text_files as $name => $content) {
      $this->addTextFile($name, $content);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isManagedSchema() {
    return $this->managed_schema;
  }

  /**
   * {@inheritdoc}
   */
  public function setManagedSchema($managed_schema) {
    $this->managed_schema = $managed_schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getMinimumSolrVersion() {
    return $this->minimum_solr_version;
  }

  /**
   * {@inheritdoc}
   */
  public function setMinimumSolrVersion($minimum_solr_version) {
    $this->minimum_solr_version = $minimum_solr_version;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    $uri_route_parameters['search_api_server'] = \Drupal::routeMatch()->getRawParameter('search_api_server');

    return $uri_route_parameters;
  }

}
