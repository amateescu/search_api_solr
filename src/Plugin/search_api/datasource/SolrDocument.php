<?php

namespace Drupal\search_api_solr\Plugin\search_api\datasource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\Url;
use Drupal\search_api\Datasource\DatasourcePluginBase;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_solr\SolrDocumentFactoryInterface;
use Drupal\search_api_solr\SolrFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents a datasource which exposes external Solr Documents.
 *
 * @SearchApiDatasource(
 *   id = "solr_document",
 *   label = @Translation("Solr Document"),
 *   description = @Translation("Search through external Solr content. (Only works if this index is attached to a Solr-based server.)"),
 * )
 */
class SolrDocument extends DatasourcePluginBase implements PluginFormInterface {

  use PluginFormTrait;
  use LoggerTrait;

  /**
   * The Solr document factory.
   *
   * @var \Drupal\search_api_solr\SolrDocumentFactoryInterface
   */
  protected $solrDocumentFactory;

  /**
   * The Solr field manager.
   *
   * @var \Drupal\search_api_solr\SolrFieldManagerInterface
   */
  protected $solrFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $datasource */
    $datasource = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $datasource->setSolrDocumentFactory($container->get('solr_document.factory'));
    $datasource->setSolrFieldManager($container->get('solr_field.manager'));

    return $datasource;
  }

  /**
   * Sets the Solr document factory.
   *
   * @param \Drupal\search_api_solr\SolrDocumentFactoryInterface $factory
   *   The new entity field manager.
   *
   * @return $this
   */
  public function setSolrDocumentFactory(SolrDocumentFactoryInterface $factory) {
    $this->solrDocumentFactory = $factory;
    return $this;
  }

  /**
   * Returns the Solr document factory.
   *
   * @return \Drupal\search_api_solr\SolrDocumentFactoryInterface
   *   The Solr document factory.
   */
  public function getSolrDocumentFactory() {
    return $this->solrDocumentFactory ?: \Drupal::getContainer()->get('solr_document.factory');
  }

  /**
   * Sets the Solr field manager.
   *
   * @param \Drupal\search_api_solr\SolrFieldManagerInterface $solr_field_manager
   *   The new entity field manager.
   *
   * @return $this
   */
  public function setSolrFieldManager(SolrFieldManagerInterface $solr_field_manager) {
    $this->solrFieldManager = $solr_field_manager;
    return $this;
  }

  /**
   * Returns the Solr field manager.
   *
   * @return \Drupal\search_api_solr\SolrFieldManagerInterface
   *   The Solr field manager.
   */
  public function getSolrFieldManager() {
    return $this->solrFieldManager ?: \Drupal::getContainer()->get('solr_field.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getItemId(ComplexDataInterface $item) {
    return $this->getFieldValue($item, 'id_field');
  }

  /**
   * {@inheritdoc}
   */
  public function getItemLabel(ComplexDataInterface $item) {
    return $this->getFieldValue($item, 'label_field');
  }

  /**
   * {@inheritdoc}
   */
  public function getItemLanguage(ComplexDataInterface $item) {
    if ($this->configuration['language_field']) {
      return $this->getFieldValue($item, 'language_field');
    }
    return parent::getItemLanguage($item);
  }

  /**
   * {@inheritdoc}
   */
  public function getItemUrl(ComplexDataInterface $item) {
    try {
      return Url::fromUri($this->getFieldValue($item, 'url_field'));
    }
    catch (\InvalidArgumentException $e) {
      // Log the exception and return NULL.
      $this->logException($e);
    }
  }

  /**
   * Retrieves a scalar field value from a result item.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   The result item.
   * @param string $config_key
   *   The key in the configuration.
   *
   * @return mixed|null
   *   The scalar value of the specified field (first value for multi-valued
   *   fields), if it exists; NULL otherwise.
   */
  protected function getFieldValue(ComplexDataInterface $item, $config_key) {
    if (empty($this->configuration[$config_key])) {
      return NULL;
    }
    $values = $item->get($this->configuration[$config_key])->getValue();
    if (is_array($values)) {
      $values = $values ? reset($values) : NULL;
    }
    return $values ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    $fields = [];
    $server_id = $this->index->getServerId();
    if ($server_id) {
      $fields = $this->getSolrFieldManager()->getFieldDefinitions($server_id);
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    $documents = [];
    try {
      // Query the index for the Solr documents.
      $results = $this->index->query()
        ->addCondition('search_api_id', $ids, 'IN')
        ->execute()
        ->getResultItems();
      foreach ($results as $id => $result) {
        $documents[$id] = $this->solrDocumentFactory->create($result);
      }
    }
    catch (SearchApiException $e) {
      // Couldn't load items from server, return an empty array.
    }
    return $documents;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = [];
    $config['id_field'] = '';
    $config['request_handler'] = '';
    $config['label_field'] = '';
    $config['language_field'] = '';
    $config['url_field'] = '';
    $config['default_query'] = '*:*';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Get the available fields from the server (if a server has already been
    // set).
    $fields = $single_valued_fields = [];
    foreach ($this->getPropertyDefinitions() as $name => $property) {
      $fields[$name] = $property->getLabel();
      if (!$property->isMultivalued()) {
        $single_valued_fields[$name] = $property->getLabel();
      }
    }

    $form['id_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID field'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the name of the field from your Solr schema that contains unique ID values.'),
      '#default_value' => $this->configuration['id_field'],
    ];
    // If there is already a valid server, we can transform the text field into
    // a select box.
    if ($single_valued_fields) {
      $form['id_field']['#type'] = 'select';
      $form['id_field']['#options'] = $single_valued_fields;
      $form['id_field']['#description'] = $this->t('Select the Solr index field that contains unique ID values.');
    }
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced configuration'),
      '#open' => FALSE,
    ];
    $form['advanced']['request_handler'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request handler'),
      '#description' => $this->t("Enter the name of a requestHandler from the core's solrconfig.xml file.  This should only be necessary if you need to specify a handler to use other than the default."),
      '#default_value' => $this->configuration['request_handler'],
    ];
    $form['advanced']['default_query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default query'),
      '#description' => $this->t("Enter a default query parameter. This is only necessary if a default query cannot be specified in the solrconfig.xml file."),
      '#default_value' => $this->configuration['default_query'],
    ];
    $form['advanced']['label_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label field'),
      '#description' => $this->t('Enter the name of the field from your Solr schema that should be considered the label (if any).'),
      '#default_value' => $this->configuration['label_field'],
    ];
    $form['advanced']['language_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language field'),
      '#description' => $this->t('Enter the name of the field from your Solr schema that should be considered the label (if any).'),
      '#default_value' => $this->configuration['language_field'],
    ];
    $form['advanced']['url_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL field'),
      '#description' => $this->t('Enter the name of the field from your Solr schema that should be considered the label (if any).'),
      '#default_value' => $this->configuration['url_field'],
    ];
    // If there is already a valid server, we can transform the text fields into
    // select boxes.
    if ($fields) {
      $fields = [
        '' => $this->t('None'),
      ] + $fields;
      $form['advanced']['label_field']['#type'] = 'select';
      $form['advanced']['label_field']['#options'] = $fields;
      $form['advanced']['label_field']['#description'] = $this->t('Select the Solr index field that should be considered the label (if any).');
      $form['advanced']['language_field']['#type'] = 'select';
      $form['advanced']['language_field']['#options'] = $fields;
      $form['advanced']['language_field']['#description'] = $this->t("Select the Solr index field that contains the document's language code (if any).");
      $form['advanced']['url_field']['#type'] = 'select';
      $form['advanced']['url_field']['#options'] = $fields;
      $form['advanced']['url_field']['#description'] = $this->t("Select the Solr index field that contains the document's URL (if any).");
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // We want the form fields displayed inside an "Advanced configuration"
    // fieldset, but we don't want them to be actually stored inside a nested
    // "advanced" key. (This could also be done via "#parents", but that's
    // pretty tricky to get right in a subform.)
    $values = &$form_state->getValues();
    $values += $values['advanced'];
    unset($values['advanced']);
  }

}
