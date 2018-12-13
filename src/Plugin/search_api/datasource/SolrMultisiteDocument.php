<?php

namespace Drupal\search_api_solr\Plugin\search_api\datasource;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents a datasource which exposes external Solr Documents.
 *
 * @SearchApiDatasource(
 *   id = "solr_multisite_document",
 *   label = @Translation("Solr Multisite Document"),
 *   description = @Translation("Search through a different site's content. (Only works if this index is attached to a Solr-based server.)"),
 * )
 */
class SolrMultisiteDocument extends SolrDocument {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $datasource */
    $datasource = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $datasource->setSolrDocumentFactory($container->get('solr_multisite_document.factory'));
    $datasource->setSolrFieldManager($container->get('solr_multisite_field.manager'));

    return $datasource;
  }

  /**
   * Returns the Solr document factory.
   *
   * @return \Drupal\search_api_solr\SolrDocumentFactoryInterface
   *   The Solr document factory.
   */
  public function getSolrDocumentFactory() {
    return $this->solrDocumentFactory ?: \Drupal::getContainer()->get('solr_multisite_document.factory');
  }

  /**
   * Returns the Solr field manager.
   *
   * @return \Drupal\search_api_solr\SolrFieldManagerInterface
   *   The Solr field manager.
   */
  public function getSolrFieldManager() {
    return $this->solrFieldManager ?: \Drupal::getContainer()->get('solr_multisite_field.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = [];
    $config['id_field'] = 'id';
    $config['request_handler'] = '';
    $config['label_field'] = '';
    $config['language_field'] = 'ss_language';
    $config['url_field'] = 'site';
    $config['default_query'] = '*:*';

    $config['target_index'] = '';
    $config['target_hash'] = '';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['target_index'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Targeted index'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the machine name of the targeted index.'),
      '#default_value' => $this->configuration['target_index'],
    ];

    $form['target_hash'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Targeted site hash'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the hash of the targeted site.'),
      '#default_value' => $this->configuration['target_index'],
    ];

    $form['id_field'] = [
      '#type' => 'value',
      '#value' => $this->configuration['id_field'],
    ];
    $form['advanced']['request_handler'] = [
      '#type' => 'value',
      '#value' => $this->configuration['request_handler'],
    ];
    $form['advanced']['default_query'] = [
      '#type' => 'value',
      '#value' => $this->configuration['default_query'],
    ];
    $form['advanced']['language_field'] = [
      '#type' => 'value',
      '#value' => $this->configuration['language_field'],
    ];
    $form['advanced']['url_field'] = [
      '#type' => 'value',
      '#value' => $this->configuration['url_field'],
    ];

    return $form;
  }
}
