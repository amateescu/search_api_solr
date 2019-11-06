<?php

namespace Drupal\search_api_solr\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the abstract base class for Solr config entities.
 */
class AbstractSolrConfig extends ConfigEntityBase {

  /**
   * The ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The label.
   *
   * @var string
   */
  protected $label;

  /**
   * Minimum Solr version required by this config.
   *
   * @var string
   */
  protected $minimum_solr_version;

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
    if (!$this->solr_configs) {
      return '';
    }

    $formatted_xml_string = $this->buildXmlFromArray('solrconfigs', $this->solr_configs);

    $comment = '';
    if ($add_comment) {
      $comment = "<!--\n  Special configs for " . $this->label() . "\n  " .
        $this->getMinimumSolrVersion() .
        "\n-->\n";
    }

    // Remove the fake root element the XML fragment.
    return $comment . trim(preg_replace('@</?solrconfigs/?>@', '', $formatted_xml_string),  "\n") . "\n";
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
