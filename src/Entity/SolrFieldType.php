<?php

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
   * @var bool
   */
  protected $managed_schema;

  /**
   * Minimum Solr version required by this field type.
   *
   * @var string
   */
  protected $minimum_solr_version;

  /**
   * Solr Field Type definition.
   *
   * @var array
   */
  protected $field_type;

  /**
   * The language targeted by this Solr Field Type.
   *
   * @var string
   */
  protected $field_type_language_code;

  /**
   * The targeted content domains.
   *
   * @var string[]
   */
  protected $domains;

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
  public function getFieldTypeLanguageCode() {
    return $this->field_type_language_code;
  }

  /**
   * {@inheritdoc}
   */
  public function getDomains() {
    return empty($this->domains) ? ['generic'] : $this->domains;
  }

  /**
   * Get all available domains form solr filed type configs.
   *
   * @return string[]
   */
  public static function getAvailableDomains() {
    $domains = ['generic'];
    $config_factory = \Drupal::configFactory();
    foreach ($config_factory->listAll('search_api_solr_multilingual.solr_field_type.') as $field_type_name) {
      $config = $config_factory->get($field_type_name);
      $domains = array_merge($domains, $config->get('domains'));
    }
    sort($domains);
    return array_unique($domains);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypeAsJson() {
    // Unfortunately the JSON encoded field type definition still uses the
    // element names "indexAnalyzer", "queryAnalyzer" and "multiTermAnalyzer"
    // which are deprecated in the XML format. Therefor we need to add some
    // conversion logic.
    $field_type = $this->field_type;
    unset($field_type['analyzers']);

    foreach ($this->field_type['analyzers'] as $analyzer) {
      $type = 'analyzer';
      if (!empty($analyzer['type'])) {
        if ('multiterm' == $analyzer['type']) {
          $type = 'multiTermAnalyzer';
        }
        else {
          $type = $analyzer['type'] . 'Analyzer';
        }
        unset($analyzer['type']);
      }
      $field_type[$type] = $analyzer;
    }

    return Json::encode($field_type);
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldTypeAsJson($field_type) {
    $field_type = $this->field_type = Json::decode($field_type);

    // Unfortunately the JSON encoded field type definition still uses the
    // element names "indexAnalyzer", "queryAnalyzer" and "multiTermAnalyzer"
    // which are deprecated in the XML format. Therefor we need to add some
    // conversion logic.
    foreach (['index' => 'indexAnalyzer', 'query' => 'queryAnalyzer', 'multiterm' => 'multiTermAnalyzer', 'analyzer' => 'analyzer'] as $type => $analyzer) {
      if (!empty($field_type[$analyzer])) {
        unset($this->field_type[$analyzer]);
        if ($type != $analyzer) {
          $field_type[$analyzer]['type'] = $type;
        }
        $this->field_type['analyzers'][] = $field_type[$analyzer];
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypeAsXml($add_commment = TRUE) {
    $root = new \SimpleXMLElement('<fieldType/>');

    $f = function (\SimpleXMLElement $element, array $attributes) use (&$f) {
      foreach ($attributes as $key => $value) {
        if (is_scalar($value)) {
          $element->addAttribute($key, $value);
        }
        elseif (is_array($value)) {
          if (array_key_exists(0, $value)) {
            $key = rtrim($key, 's');
            foreach ($value as $inner_attributes) {
              $child = $element->addChild($key);
              $f($child, $inner_attributes);
            }
          }
          else {
            $child = $element->addChild($key);
            $f($child, $value);
          }
        }
      }
    };
    $f($root, $this->field_type);

    $comment = '';
    if ($add_commment) {
      $comment = "<!--\n  " . $this->label() . "\n  " .
        ($this->isManagedSchema() ? " for managed schema\n  " : '') .
        $this->getMinimumSolrVersion() .
        "\n-->\n";
    }

    // Create formatted string.
    $dom = dom_import_simplexml($root)->ownerDocument;
    $dom->formatOutput = TRUE;
    $formatted_xml_string = $dom->saveXML();

    // Remove the XML declaration before returning the XML fragment.
    return $comment . preg_replace('/<\?.*?\?>\s*\n?/', '', $formatted_xml_string);
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicFields() {
    $dynamic_fields = [];
    foreach (array('ts', 'tm') as $prefix) {
      $dynamic_fields[] = [
        'name' => SearchApiSolrUtility::encodeSolrName(
            Utility::getLanguageSpecificSolrDynamicFieldPrefix($prefix, $this->field_type_language_code)
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
  public function getFieldTypeName() {
    return isset($this->field_type['name']) ? $this->field_type['name'] : '';
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
