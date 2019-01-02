<?php

namespace Drupal\search_api_solr\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\Utility\Utility as SearchApiSolrUtility;
use Drupal\search_api_solr\SolrFieldTypeInterface;
use Drupal\search_api_solr\Utility\Utility;

/**
 * Defines the SolrFieldType entity.
 *
 * @ConfigEntityType(
 *   id = "solr_field_type",
 *   label = @Translation("Solr Field Type"),
 *   handlers = {
 *     "list_builder" = "Drupal\search_api_solr\Controller\SolrFieldTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\search_api_solr\Form\SolrFieldTypeForm",
 *       "edit" = "Drupal\search_api_solr\Form\SolrFieldTypeForm",
 *       "delete" = "Drupal\search_api_solr\Form\SolrFieldTypeDeleteForm",
 *       "export" = "Drupal\search_api_solr\Form\SolrFieldTypeExportForm"
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
 *     "edit-form" = "/admin/config/search/search-api/solr_field_type/{solr_field_type}",
 *     "delete-form" = "/admin/config/search/search-api/solr_field_type/{solr_field_type}/delete",
 *     "export-form" = "/admin/config/search/search-api/solr_field_type/{solr_field_type}/export",
 *     "collection" = "/admin/config/search/search-api/server/{search_api_server}/solr_field_type"
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
   * Solr Spellcheck Field Type definition.
   *
   * @var array
   */
  protected $spellcheck_field_type = NULL;

  /**
   * The cutom code targeted by this Solr Field Type.
   *
   * @var string
   */
  protected $custom_code;

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
   * Solr Field Type specific additions to solrconfig.xml.
   *
   * @var array
   */
  protected $solr_configs;

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
  public function getSpellcheckFieldType() {
    return $this->field_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomCode() {
    return $this->custom_code;
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
    foreach ($config_factory->listAll('search_api_solr.solr_field_type.') as $field_type_name) {
      $config = $config_factory->get($field_type_name);
      $domains = array_merge($domains, $config->get('domains'));
    }
    sort($domains);
    return array_unique($domains);
  }

  /**
   * Get all available custom codes.
   *
   * @return array
   */
  public static function getAvailableCustomCodes() {
    $custom_codes = [];
    $config_factory = \Drupal::configFactory();
    foreach ($config_factory->listAll('search_api_solr.solr_field_type.') as $field_type_name) {
      $config = $config_factory->get($field_type_name);
      if ($custom_code = $config->get('custom_code')) {
        $custom_codes[] = $custom_code;
      }
    }
    return array_unique($custom_codes);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypeAsJson(bool $pretty = FALSE) {
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

    return $pretty ?
      json_encode($field_type, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) : Json::encode($field_type);
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldTypeAsJson($field_type) {
    $field_type = $this->field_type = Json::decode($field_type);

    // Unfortunately the JSON encoded field type definition still uses the
    // element names "indexAnalyzer", "queryAnalyzer" and "multiTermAnalyzer"
    // which are deprecated in the XML format. Therefore we need to add some
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
    $formatted_xml_string = $this->buildXmlFromArray('fieldType', $this->field_type);

    $comment = '';
    if ($add_commment) {
      $comment = "<!--\n  " . $this->label() . "\n  " .
        $this->getMinimumSolrVersion() .
        "\n-->\n";
    }

    return $comment . $formatted_xml_string;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpellcheckFieldTypeAsJson(bool $pretty = FALSE) {
    if ($this->spellcheck_field_type) {
      return $pretty ?
        json_encode($this->spellcheck_field_type, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) : Json::encode($this->spellcheck_field_type);
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function setSpellcheckFieldTypeAsJson($spellcheck_field_type) {
    $this->spellcheck_field_type = Json::decode($spellcheck_field_type);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpellcheckFieldTypeAsXml($add_commment = TRUE) {
    if ($this->spellcheck_field_type) {
      $formatted_xml_string = $this->buildXmlFromArray('fieldType', $this->spellcheck_field_type);

      $comment = '';
      if ($add_commment) {
        $comment = "<!--\n  " . $this->label() . " Spellcheck\n  " .
          $this->getMinimumSolrVersion() .
          "\n-->\n";
      }

      return $comment . $formatted_xml_string;
    }

    return '';
  }

  /**
   *
   */
  protected function buildXmlFromArray($root_element_name, array $attributes) {
    $root = new \SimpleXMLElement('<' . $root_element_name . '/>');

    $f = function (\SimpleXMLElement $element, array $attributes) use (&$f) {
      foreach ($attributes as $key => $value) {
        if (is_scalar($value)) {
          if (is_bool($value) === TRUE) {
            // SimpleXMLElement::addAtribute() converts booleans to integers 0
            // and 1. But Solr requires the strings 'false' and 'true'.
            $value = $value ? 'true' : 'false';
          }

          switch ($key) {
            case 'VALUE':
              // @see https://stackoverflow.com/questions/3153477
              $element[0] = $value;
              break;

            case 'CDATA':
              $element[0] = '<![CDATA[' . $value . ']]>';
              break;

            default:
              $element->addAttribute($key, $value);
          }
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

    $f($root, $attributes);

    // Create formatted string.
    $dom = dom_import_simplexml($root)->ownerDocument;
    $dom->formatOutput = TRUE;
    $formatted_xml_string = $dom->saveXML();

    // Remove the XML declaration before returning the XML fragment.
    return preg_replace('/<\?.*?\?>\s*\n?/', '', $formatted_xml_string);
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrConfigs() {
    return $this->solr_configs;
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrConfigsAsXml($add_commment = TRUE) {
    $formatted_xml_string = $this->buildXmlFromArray('solrconfigs', $this->solr_configs);

    $comment = '';
    if ($add_commment) {
      $comment = "<!--\n  Special configs for " . $this->label() . "\n  " .
        $this->getMinimumSolrVersion() .
        "\n-->\n";
    }

    // Remove the fake root element the XML fragment.
    return $comment . preg_replace('@</?solrconfigs/?>@', '', $formatted_xml_string);
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicFields() {
    $dynamic_fields = [];

    $prefixes = $this->custom_code ? ['tc' . $this->custom_code, 'toc' . $this->custom_code] : ['t', 'to'];
    foreach ($prefixes as $prefix_without_cardinality) {
      foreach (['s', 'm'] as $cardinality) {
        $prefix = $prefix_without_cardinality . $cardinality;
        $name = $prefix . SolrBackendInterface::SEARCH_API_SOLR_LANGUAGE_SEPARATOR . $this->field_type_language_code . '_';
        $dynamic_fields[] = $dynamic_field = [
          'name' => SearchApiSolrUtility::encodeSolrName($name) . '*',
          'type' => $this->field_type['name'],
          'stored' => TRUE,
          'indexed' => TRUE,
          'multiValued' => ('m' === $cardinality),
          'termVectors' => TRUE,
          'omitNorms' => strpos($prefix, 'to') === 0,
        ];
        if (LanguageInterface::LANGCODE_NOT_SPECIFIED == $this->field_type_language_code) {
          // Add a language-unspecific default dynamic field as fallback for
          // languages we don't have a dedicated config for.
          $dynamic_field['name'] = SearchApiSolrUtility::encodeSolrName($prefix) . '_*';
          $dynamic_fields[] = $dynamic_field;
        }
      }
    }

    if ($spellcheck_field = $this->getSpellcheckField()) {
      // Spellcheck fields need to be dynamic to have a language fallback, for
      // example de-at => de.
      $dynamic_fields[] = $spellcheck_field;

      if (LanguageInterface::LANGCODE_NOT_SPECIFIED == $this->field_type_language_code) {
        // Add a language-unspecific default dynamic spellcheck field as
        // fallback for languages we don't have a dedicated config for.
        $spellcheck_field['name'] = 'spellcheck_*';
        $static_fields[] = $spellcheck_field;
      }
    }

    return $dynamic_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getStaticFields() {
    return [];
  }

  /**
   * @return array|null
   */
  protected function getSpellcheckField() {
    $spellcheck_field = NULL;

    if ($this->spellcheck_field_type) {
      $spellcheck_field = [
        'name' => 'spellcheck_' . SearchApiSolrUtility::encodeSolrName($this->field_type_language_code) . '*',
        'type' => $this->spellcheck_field_type['name'],
        'stored' => TRUE,
        'indexed' => TRUE,
        'multiValued' => TRUE,
        'termVectors' => TRUE,
        'omitNorms' => TRUE,
      ];
    }

    return $spellcheck_field;
  }

  /**
   * {@inheritdoc}
   */
  public function getCopyFields() {
    $copy_fields = [];
    // Foreach (array('ts' => 'terms_ts', 'tm' => 'terms_tm', 'tos' => 'terms_ts', 'tom' => 'terms_tm') as $src_prefix => $dest_prefix) {
    // $copy_fields[] = [
    // 'source' => SearchApiSolrUtility::encodeSolrName(
    // Utility::getLanguageSpecificSolrDynamicFieldPrefix($src_prefix, $this->field_type_language_code)
    // ) . '*',
    // 'dest' => SearchApiSolrUtility::encodeSolrName(
    // Utility::getLanguageSpecificSolrDynamicFieldPrefix($dest_prefix, $this->field_type_language_code)
    // ) . '*',
    // ];
    // }.
    return $copy_fields;
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
  public function requiresManagedSchema() {
    if (isset($this->field_type['analyzers'])) {
      foreach ($this->field_type['analyzers'] as $analyzer) {
        if (isset($analyzer['filters'])) {
          foreach ($analyzer['filters'] as $filter) {
            if (strpos($filter['class'], 'solr.Managed') === 0) {
              return TRUE;
            }
          }
        }
      }
    }
    return FALSE;
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

    if ('collection' == $rel) {
      $uri_route_parameters['search_api_server'] = \Drupal::routeMatch()->getRawParameter('search_api_server')
        // To be removed when https://www.drupal.org/node/2919648 is fixed.
        ?: 'core_issue_2919648_workaround';
    }

    return $uri_route_parameters;
  }

}
