<?php

namespace Drupal\search_api_solr\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\Utility\Utility as SearchApiSolrUtility;
use Drupal\search_api_solr\SolrFieldTypeInterface;

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
 *       "delete" = "Drupal\search_api_solr\Form\SolrFieldTypeDeleteForm"
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
 *     "disable-for-server" = "/admin/config/search/search-api/server/{search_api_server}/solr_field_type/{solr_field_type}/disable",
 *     "enable-for-server" = "/admin/config/search/search-api/server/{search_api_server}/solr_field_type/{solr_field_type}/enable",
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
  protected $spellcheck_field_type;

  /**
   * Solr Collated Field Type definition.
   *
   * @var array
   */
  protected $collated_field_type;

  /**
   * Solr Unstemmed Field Type definition.
   *
   * @var  array
   */
  protected $unstemmed_field_type;

  /**
   * The custom code targeted by this Solr Field Type.
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
  public function setFieldType(array $field_type) {
    $this->field_type = $field_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpellcheckFieldType() {
    return $this->spellcheck_field_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setSpellcheckFieldType(array $spellcheck_field_type) {
    $this->spellcheck_field_type = $spellcheck_field_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollatedFieldType() {
    return $this->collated_field_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setCollatedFieldType(array $collated_field_type) {
    $this->collated_field_type = $collated_field_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnstemmedFieldType() {
    return $this->unstemmed_field_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setUnstemmedFieldType(array $unstemmed_field_type) {
    $this->unstemmed_field_type = $unstemmed_field_type;
    return $this;
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
   *   An array of domains as strings.
   */
  public static function getAvailableDomains() {
    $domains = [['generic']];
    $config_factory = \Drupal::configFactory();
    foreach ($config_factory->listAll('search_api_solr.solr_field_type.') as $field_type_name) {
      $config = $config_factory->get($field_type_name);
      $domains[] = $config->get('domains');
    }
    $domains = array_unique(array_merge(...$domains));
    sort($domains);
    return $domains;
  }

  /**
   * Get all available custom codes.
   *
   * @return string[]
   *   An array of custom codes as strings.
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
        if ('multiterm' === $analyzer['type']) {
          $type = 'multiTermAnalyzer';
        }
        else {
          $type = $analyzer['type'] . 'Analyzer';
        }
        unset($analyzer['type']);
      }
      $field_type[$type] = $analyzer;
    }

    /** @noinspection PhpComposerExtensionStubsInspection */
    return $pretty ?
      json_encode($field_type, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) :
      Json::encode($field_type);
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
    $analyzers = [
      'index' => 'indexAnalyzer',
      'query' => 'queryAnalyzer',
      'multiterm' => 'multiTermAnalyzer',
      'analyzer' => 'analyzer',
    ];
    foreach ($analyzers as $type => $analyzer) {
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
  public function getSpellcheckFieldTypeAsJson(bool $pretty = FALSE) {
    if ($this->spellcheck_field_type) {
      /** @noinspection PhpComposerExtensionStubsInspection */
      return $pretty ?
        json_encode($this->spellcheck_field_type, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) :
        Json::encode($this->spellcheck_field_type);
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
  public function getCollatedFieldTypeAsJson(bool $pretty = FALSE) {
    if ($this->collated_field_type) {
      /** @noinspection PhpComposerExtensionStubsInspection */
      return $pretty ?
        json_encode($this->collated_field_type, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) :
        Json::encode($this->collated_field_type);
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function setCollatedFieldTypeAsJson($collated_field_type) {
    $this->collated_field_type = Json::decode($collated_field_type);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnstemmedFieldTypeAsJson(bool $pretty = FALSE) {
    if ($this->unstemmed_field_type) {
      /** @noinspection PhpComposerExtensionStubsInspection */
      return $pretty ?
        json_encode($this->unstemmed_field_type, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) :
        Json::encode($this->unstemmed_field_type);
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function setUnstemmedFieldTypeAsJson($unstemmed_field_type) {
    $this->unstemmed_field_type = Json::decode($unstemmed_field_type);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypeAsXml($add_comment = TRUE) {
    return $this->getSubFieldTypeAsXml($this->field_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getSpellcheckFieldTypeAsXml($add_comment = TRUE) {
    return $this->spellcheck_field_type ?
      $this->getSubFieldTypeAsXml($this->spellcheck_field_type, ' spellcheck') : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCollatedFieldTypeAsXml($add_comment = TRUE) {
    return $this->collated_field_type ?
      $this->getSubFieldTypeAsXml($this->collated_field_type, ' collated') : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getUnstemmedFieldTypeAsXml($add_comment = TRUE) {
    return $this->unstemmed_field_type ?
      $this->getSubFieldTypeAsXml($this->unstemmed_field_type, ' unstemmed') : '';
  }

  /**
   * Serializes a filed type as XML fragment as required by Solr.
   *
   * @param array $field_type
   * @param string $additional_label
   * @param bool $add_comment
   *
   * @return string
   */
  protected function getSubFieldTypeAsXml(array $field_type, string $additional_label = '', bool $add_comment = TRUE) {
    $formatted_xml_string = $this->buildXmlFromArray('fieldType', $field_type);

    $comment = '';
    if ($add_comment) {
      $comment = "<!--\n  " . $this->label() . $additional_label . "\n  " .
        $this->getMinimumSolrVersion() .
        "\n-->\n";
    }

    return $comment . $formatted_xml_string;
  }


  /**
   * Formats a given array to an XML string.
   */
  protected function buildXmlFromArray($root_element_name, array $attributes) {
    /** @noinspection PhpComposerExtensionStubsInspection */
    $root = new \SimpleXMLElement('<' . $root_element_name . '/>');
    self::buildXmlFromArrayRecursive($root, $attributes);

    // Create formatted string.
    /** @noinspection PhpComposerExtensionStubsInspection */
    $dom = dom_import_simplexml($root)->ownerDocument;
    $dom->formatOutput = TRUE;
    $formatted_xml_string = $dom->saveXML();

    // Remove the XML declaration before returning the XML fragment.
    return preg_replace('/<\?.*?\?>\s*\n?/', '', $formatted_xml_string);
  }

  /**
   * Builds a SimpleXMLElement recursively from an array of attributes.
   *
   * @param \SimpleXMLElement $element
   *   The root SimpleXMLElement.
   * @param array $attributes
   *   An associative array of key/value attributes. Can be multi-level.
   */
  protected static function buildXmlFromArrayRecursive(\SimpleXMLElement $element, array $attributes) {
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
            self::buildXmlFromArrayRecursive($child, $inner_attributes);
          }
        }
        else {
          $child = $element->addChild($key);
          self::buildXmlFromArrayRecursive($child, $value);
        }
      }
    }
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
  public function setSolrConfigs(array $solr_configs) {
    return $this->solr_configs = $solr_configs;
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrConfigsAsXml($add_comment = TRUE) {
    $formatted_xml_string = $this->buildXmlFromArray('solrconfigs', $this->solr_configs);

    $comment = '';
    if ($add_comment) {
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

    $prefixes = $this->custom_code ? [
      'tc' . $this->custom_code,
      'toc' . $this->custom_code,
      'tuc' . $this->custom_code,
    ] : ['t', 'to', 'tu'];
    foreach ($prefixes as $prefix_without_cardinality) {
      foreach (['s', 'm'] as $cardinality) {
        $prefix = $prefix_without_cardinality . $cardinality;
        $name = $prefix . SolrBackendInterface::SEARCH_API_SOLR_LANGUAGE_SEPARATOR . $this->field_type_language_code . '_';
        $dynamic_fields[] = $dynamic_field = [
          'name' => SearchApiSolrUtility::encodeSolrName($name) . '*',
          'type' => ((strpos($prefix, 'tu') === 0 && !empty($this->unstemmed_field_type)) ? $this->unstemmed_field_type['name'] : $this->field_type['name']),
          'stored' => TRUE,
          'indexed' => TRUE,
          'multiValued' => ('m' === $cardinality),
          'termVectors' => TRUE,
          'omitNorms' => strpos($prefix, 'to') === 0,
        ];
        if (LanguageInterface::LANGCODE_NOT_SPECIFIED === $this->field_type_language_code) {
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

      if (LanguageInterface::LANGCODE_NOT_SPECIFIED === $this->field_type_language_code) {
        // Add a language-unspecific default dynamic spellcheck field as
        // fallback for languages we don't have a dedicated config for.
        $spellcheck_field['name'] = 'spellcheck_*';
        $dynamic_fields[] = $spellcheck_field;
      }
    }

    if ($collated_field = $this->getCollatedField()) {
      $dynamic_fields[] = $collated_field;

      if (LanguageInterface::LANGCODE_NOT_SPECIFIED === $this->field_type_language_code) {
        // Add a language-unspecific default dynamic sort field as fallback for
        // languages we don't have a dedicated config for.
        $collated_field['name'] = 'sort_*';
        $dynamic_fields[] = $collated_field;
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
   * Returns the spellcheck field definition.
   *
   * @return array|null
   *   The array containing the spellcheck field definition or null if is
   *   not configured for this field type.
   */
  protected function getSpellcheckField() {
    $spellcheck_field = NULL;

    if ($this->spellcheck_field_type) {
      $spellcheck_field = [
        // Don't use the language separator here! This field name is used
        // without it in the solrconfig.xml. Due to the fact that we leverage a
        // dynamic field here to enable the language fallback we need to append
        // '*', but not '_*' because we'll never append a field name!
        'name' => 'spellcheck_' . $this->field_type_language_code . '*',
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
   * Returns the collated field definition.
   *
   * @return array|null
   *   The array containing the collated field definition or null if is
   *   not configured for this field type.
   */
  protected function getCollatedField() {
    $collated_field = NULL;

    if ($this->collated_field_type) {
      $collated_field = [
        'name' => SearchApiSolrUtility::encodeSolrName('sort' . SolrBackendInterface::SEARCH_API_SOLR_LANGUAGE_SEPARATOR . $this->field_type_language_code) . '_*',
        'type' => $this->collated_field_type['name'],
        'stored' => FALSE,
        'indexed' => FALSE,
        'docValues' => TRUE,
      ];
    }

    return $collated_field;
  }

  /**
   * {@inheritdoc}
   */
  public function getCopyFields() {
    return [];
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
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTextFiles(array $text_files) {
    $this->text_files = [];
    foreach ($text_files as $name => $content) {
      $this->addTextFile($name, $content);
    }
    return $this;
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
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    if (
      'collection' === $rel ||
      'disable-for-server' === $rel ||
      'enable-for-server' === $rel
    ) {
      $uri_route_parameters['search_api_server'] = \Drupal::routeMatch()->getRawParameter('search_api_server')
        // To be removed when https://www.drupal.org/node/2919648 is fixed.
        ?: 'core_issue_2919648_workaround';
    }

    return $uri_route_parameters;
  }

}
