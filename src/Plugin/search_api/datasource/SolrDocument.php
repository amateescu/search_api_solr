<?php

namespace Drupal\search_api_solr_datasource\Plugin\search_api\datasource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Datasource\DatasourcePluginBase;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api_solr_datasource\SolrDocumentFactoryInterface;
use Drupal\search_api_solr_datasource\SolrFieldManagerInterface;
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

  /**
   * The Solr document factory.
   *
   * @var \Drupal\search_api_solr_datasource\SolrDocumentFactoryInterface
   */
  protected $solrDocumentFactory;

  /**
   * The Solr field manager.
   *
   * @var \Drupal\search_api_solr_datasource\SolrFieldManagerInterface
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
   * @param \Drupal\search_api_solr_datasource\SolrDocumentFactoryInterface $factory
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
   * @return \Drupal\search_api_solr_datasource\SolrDocumentFactoryInterface
   *   The Solr document factory.
   */
  public function getSolrDocumentFactory() {
    return $this->solrDocumentFactory ?: \Drupal::getContainer()->get('solr_document.factory');
  }

  /**
   * Sets the Solr field manager.
   *
   * @param \Drupal\search_api_solr_datasource\SolrFieldManagerInterface $solr_field_manager
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
   * @return \Drupal\search_api_solr_datasource\SolrFieldManagerInterface
   *   The Solr field manager.
   */
  public function getSolrFieldManager() {
    return $this->solrFieldManager ?: \Drupal::getContainer()->get('solr_field.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getItemId(ComplexDataInterface $item) {
    return $item->get($this->configuration['id_field'])->getValue();
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
    // Query the index for the Solr documents.
    $query = $this->index->query(['limit' => 1]);
    foreach ($ids as $id) {
      $query->addCondition('search_api_id', $id);
    }
    $query->execute();
    $results = $query->getResults()->getResultItems();
    $documents = [];
    foreach ($results as $id => $result) {
      $documents[$id] = $this->solrDocumentFactory->create($result);
    }
    return $documents;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = [];
    $config['id_field'] = '';
    $config['advanced']['request_handler'] = '';
    $config['advanced']['default_query'] = '*:*';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['id_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID field'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the name of a field from your Solr schema that contains unique ID values.'),
      '#default_value' => $this->configuration['id_field'],
    ];
    // If there is already a valid server, we can transform the text field into
    // a select box.
    $field_options = [];
    foreach ($this->getPropertyDefinitions() as $name => $property) {
      if (!$property->isMultivalued()) {
        $field_options[$name] = $property->getLabel();
      }
    }
    if ($field_options) {
      $form['id_field']['#type'] = 'select';
      $form['id_field']['#options'] = $field_options;
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
      '#default_value' => $this->configuration['advanced']['request_handler'],
    ];
    // @todo Figure out if we actually need this setting.  It was copied over
    //   from Sarnia, but it seems like in D8 Search API Solr defaults to
    //   selecting all records.  It may not be necessary.
    /*$form['advanced']['default_query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default query'),
      '#description' => $this->t("Enter a default query parameter. This may only be necessary if a default query cannot be specified in the solrconfig.xml."),
      '#default_value' => $this->configuration['advanced']['default_query'],
    ];*/
    return $form;
  }

}
