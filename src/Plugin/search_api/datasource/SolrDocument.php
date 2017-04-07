<?php

namespace Drupal\search_api_solr_datasource\Plugin\search_api\datasource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Datasource\DatasourcePluginBase;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api_solr_datasource\SolrFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents a datasource which exposes external Solr Documents.
 *
 * @SearchApiDatasource(
 *   id = "solr_document",
 *   label = @Translation("Solr Document"),
 *   description = @Translation("Exposes external Solr Documents as a datasource."),
 * )
 */
class SolrDocument extends DatasourcePluginBase implements PluginFormInterface {

  use PluginFormTrait;

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

    $datasource->setSolrFieldManager($container->get('solr_field.manager'));

    return $datasource;
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
   */
  public function getSolrFieldManager() {
    return $this->solrFieldManager ?: \Drupal::getContainer()->get('solr_field.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getItemId(ComplexDataInterface $item) {
    ;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    // @todo Handle IndexInterface::getServerInstance() returning NULL.
    $fields = $this->getSolrFieldManager()->getFieldDefinitions($this->index->getServerInstance()->id());
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
      $documents[$id]  = $this->createTypedDataFromItem($result);
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
      '#description' => $this->t('Enter the name of a field from your Solr schema that contains unique ID values.'),
      '#default_value' => $this->configuration['id_field'],
    ];
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

  /**
   * {@inheritdoc}
   */
  protected function createTypedDataFromItem(ItemInterface $item) {
    $plugin = \Drupal::typedDataManager()->getDefinition('solr_document')['class'];
    return $plugin::createFromItem($item);
  }

}
