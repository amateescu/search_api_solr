<?php

namespace Drupal\search_api_solr_datasource\Plugin\search_api\datasource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Datasource\DatasourcePluginBase;
use Drupal\search_api\Plugin\PluginFormTrait;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $datasource */
    $datasource = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    return $datasource;
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
    $form['advanced']['default_query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default query'),
      '#description' => $this->t("Enter a default query parameter. This may only be necessary if a default query cannot be specified in the solrconfig.xml."),
      '#default_value' => $this->configuration['advanced']['default_query'],
    ];
    return $form;
  }

}
