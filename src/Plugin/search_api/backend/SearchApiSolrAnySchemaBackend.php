<?php

namespace Drupal\search_api_solr\Plugin\search_api\backend;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Solarium\Core\Query\QueryInterface as SolariumQueryInterface;

/**
 * A read-only backend for any non-drupal schema.
 *
 * @SearchApiBackend(
 *   id = "search_api_solr_any_schema",
 *   label = @Translation("Any Schema Solr"),
 *   description = @Translation("Read-only connection to any Solr server.")
 * )
 */
class SearchApiSolrAnySchemaBackend extends SearchApiSolrBackend {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $conf = parent::defaultConfiguration();
    $conf['retrieve_data'] = TRUE;
    $conf['skip_schema_check'] = TRUE;
    return $conf;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['advanced']['retrieve_data']['#disabled'] = TRUE;
    $form['advanced']['skip_schema_check']['#disabled'] = TRUE;
    $form['multisite']['site_hash']['#title'] = $this->t('Retrieve results for one site only');
    $form['multisite']['site_hash']['#description'] = $this->t('Automatically filter all searches to only retrieve results for one Drupal site as configured per multisite index.');
    // @todo force read-only

    return $form;
  }

}
