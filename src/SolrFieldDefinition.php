<?php

namespace Drupal\search_api_solr_datasource;

/**
 * Defines a class for Solr field definitions.
 */
class SolrFieldDefinition implements SolrFieldDefinitionInterface {

  /**
   * Human-readable labels for Solr schema properties.
   *
   * @var string[]
   */
  protected static $schemaLabels = array(
    'I' => 'Indexed',
    'T' => 'Tokenized',
    'S' => 'Stored',
    'M' => 'Multivalued',
    'V' => 'TermVector Stored',
    'o' => 'Store Offset With TermVector',
    'p' => 'Store Position With TermVector',
    'O' => 'Omit Norms',
    'L' => 'Lazy',
    'B' => 'Binary',
    'C' => 'Compressed',
    'f' => 'Sort Missing First',
    'l' => 'Sort Missing Last',
  );

  /**
   * The field's machine name in Solr.
   *
   * @var string
   */
  protected $label;

  /**
   * The array holding values for all definition keys.
   *
   * @var array
   */
  protected $definition = array();

  /**
   * An array of Solr schema properties for this field.
   *
   * @var string[]
   */
  protected $schema;

  /**
   * Creates a new Solr field definition.
   *
   * @param string $type
   *   The field's Solr data type.
   *
   * @return static
   *   A new SolrFieldDefinition object.
   */
  public static function create($type) {
    $definition['type'] = $type;
    return new static($definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function createFromDataType($type) {
    return self::create($type);
  }

  /**
   * Constructs a new Solr field definition object.
   *
   * @param array $values
   *   The field's definition as returned from Luke.
   */
  public function __construct(array $values) {
    $this->definition = $values;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Map Solr data types to Drupal data types.
   */
  public function getDataType() {
    return !empty($this->definition['type']) ? $this->definition['type'] : 'any';
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Sets the human-readable label.
   *
   * @param string $label
   *   The label to set.
   *
   * @return static
   *   The object itself for chaining.
   */
  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isList() {
    return $this->isMultivalued();
  }

  /**
   * {@inheritdoc}
   */
  public function isReadOnly() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isComputed() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Fill this out.
   */
  public function getClass() {

  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return isset($this->definition['settings']) ? $this->definition['settings'] : array();
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($setting_name) {
    return isset($this->definition['settings'][$setting_name]) ? $this->definition['settings'][$setting_name] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraint($constraint_name) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function addConstraint($constraint_name, $options = NULL) {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSchema() {
    if (!isset($this->schema)) {
      foreach (str_split(str_replace('-', '', $this->definition['schema'])) as $key) {
        $this->schema[$key] = isset(self::$schemaLabels[$key]) ? self::$schemaLabels[$key] : $key;
      }
    }
    return $this->schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicBase() {
    return isset($this->field['dynamicBase']) ? $this->field['dynamicBase'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexed() {
    $this->getSchema();
    return isset($this->schema['I']);
  }

  /**
   * {@inheritdoc}
   */
  public function isTokenized() {
    $this->getSchema();
    return isset($this->schema['T']);
  }

  /**
   * {@inheritdoc}
   */
  public function isStored() {
    $this->getSchema();
    return isset($this->schema['S']);
  }

  /**
   * {@inheritdoc}
   */
  public function isMultivalued() {
    $this->getSchema();
    return isset($this->schema['M']);
  }

  /**
   * {@inheritdoc}
   */
  public function isTermVectorStored() {
    $this->getSchema();
    return isset($this->schema['V']);
  }

  /**
   * {@inheritdoc}
   */
  public function isStoreOffsetWithTermVector() {
    $this->getSchema();
    return isset($this->schema['o']);
  }

  /**
   * {@inheritdoc}
   */
  public function isStorePositionWithTermVector() {
    $this->getSchema();
    return isset($this->schema['p']);
  }

  /**
   * {@inheritdoc}
   */
  public function isOmitNorms() {
    $this->getSchema();
    return isset($this->schema['O']);
  }

  /**
   * {@inheritdoc}
   */
  public function isLazy() {
    $this->getSchema();
    return isset($this->schema['L']);
  }

  /**
   * {@inheritdoc}
   */
  public function isBinary() {
    $this->getSchema();
    return isset($this->schema['B']);
  }

  /**
   * {@inheritdoc}
   */
  public function isCompressed() {
    $this->getSchema();
    return isset($this->schema['C']);
  }

  /**
   * {@inheritdoc}
   */
  public function isSortMissingFirst() {
    $this->getSchema();
    return isset($this->schema['f']);
  }

  /**
   * {@inheritdoc}
   */
  public function isSortMissingLast() {
    $this->getSchema();
    return isset($this->schema['l']);
  }

  /**
   * {@inheritdoc}
   */
  public function isPossibleKey() {
    return !$this->getDynamicBase()
      && $this->isStored()
      && !$this->isMultivalued();
  }

  /**
   * {@inheritdoc}
   */
  public function isSortable() {
    return $this->isIndexed()
      && !$this->isMultivalued();
  }

  /**
   * {@inheritdoc}
   */
  public function isFulltextSearchable() {
    return $this->isIndexed()
      && $this->isTokenized();
  }

  /**
   * {@inheritdoc}
   */
  public function isFilterable() {
    return $this->isIndexed()
      && !$this->isTokenized();
  }

}
