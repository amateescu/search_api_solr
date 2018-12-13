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
    $conf['site_hash'] = FALSE;
    return $conf;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['advanced']['retrieve_data']['#disabled'] = TRUE;
    $form['advanced']['skip_schema_check']['#disabled'] = TRUE;
    $form['multi_site']['site_hash']['#disabled'] = TRUE;
    // @todo force read-only

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexFilterQueryString(IndexInterface $index) {
    $fq = '';
    $config = $this->getDatasourceConfig($index);
    if (isset($config['target_index'])) {
      $fq = '+index_id:' . $this->queryHelper->escapeTerm($config['target_index']);

      // Set the site hash filter, if enabled.
      if ($config['target_hash']) {
        $fq .= ' +hash:' . $this->queryHelper->escapeTerm($config['target_hash']);
      }
    }
    return $fq;
  }

  /**
   * {@inheritdoc}
   */
  protected function preQuery(SolariumQueryInterface $solarium_query, QueryInterface $query) {
    parent::preQuery($solarium_query, $query);

    // Do not modify 'Server index status' queries.
    // @see https://www.drupal.org/node/2668852
    if ($query->hasTag('server_index_status')) {
      return;
    }

    // Do not alter the query if the index does not use the solr_document
    // datasource.
    $index = $query->getIndex();
    if ($index->isValidDatasource('solr_document')) {
      // Set requestHandler for the query type.
      $config = $index->getDatasource('solr_document')->getConfiguration();
      if (!empty($config['request_handler'])) {
        $solarium_query->addParam('qt', $config['request_handler']);
      }

      // Set the default query, if necessary and configured.
      if (!$solarium_query->getQuery() && !empty($config['default_query'])) {
        $solarium_query->setQuery($config['default_query']);
      }
    }
  }

  protected function getDatasourceConfig(IndexInterface $index) {
    $config = [];
    if ($index->isValidDatasource('solr_document')) {
      $config = $index->getDatasource('solr_document')->getConfiguration();
    }
    elseif ($index->isValidDatasource('solr_multisite_document')) {
      $config = $index->getDatasource('solr_multisite_document')->getConfiguration();
    }
    return $config;
  }

  /**
   * Get the list of fields Solr must return as result.
   *
   * @param array $field_names
   *   The field names.
   * @param array $fields_to_be_retrieved
   *   The field values to be retrieved from Solr.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   *
   * @return array
   */
  protected function getRequiredFields(array $field_names, QueryInterface $query = NULL) {
    $config = $this->getDatasourceConfig($query->getIndex());
    $required_fields = parent::getRequiredFields($field_names);

    $extra_fields = [
      'label_field',
      'url_field',
    ];
    foreach ($extra_fields as $config_key) {
      if (!empty($config[$config_key])) {
        $required_fields[] = $config[$config_key];
      }
    }

    return array_filter($required_fields);
  }

  /**
   * {@inheritdoc}
   */
  protected function applySearchWorkarounds(SolariumQueryInterface $solarium_query, QueryInterface $query) {
    parent::applySearchWorkarounds($solarium_query, $query);

    // Do not modify 'Server index status' queries.
    // @see https://www.drupal.org/node/2668852
    if ($query->hasTag('server_index_status')) {
      return;
    }

    // The query builder of Search API Solr Search bases on 'OR' which is the
    // default value for solr, too. But a foreign schema could have a
    // non-default config for q.op. Therefor we need to set it explicitly if not
    // set.
    $params = $solarium_query->getParams();
    if (!isset($params['q.op'])) {
      $solarium_query->addParam('q.op', 'OR');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function postQuery(ResultSetInterface $results, QueryInterface $query, $response) {
    parent::postQuery($results, $query, $response);

    $index = $query->getIndex();
    $datasource = '';

    if ($index->isValidDatasource('solr_document')) {
      $datasource = 'solr_document';
    }
    elseif ($index->isValidDatasource('solr_multisite_document')) {
      $datasource = 'solr_multisite_document';
    }
    else {
      // Do not alter the results if the index does not use the solr_document
      // datasource.
      return;
    }

    /** @var \Drupal\search_api_solr\SolrDocumentFactoryInterface $solr_document_factory */
    $solr_document_factory = \Drupal::getContainer()->get($datasource . '.factory');


    /** @var \Drupal\search_api\Item\Item $item */
    foreach ($results->getResultItems() as $item) {
      // Create the typed data object for the Item immediately after the query
      // has been run. Doing this now can prevent the Search API from having to
      // query for individual documents later.
      $item->setOriginalObject($solr_document_factory->create($item));

      // Prepend each item's itemId with the datasource ID. A lot of the Search
      // API assumes that the item IDs are formatted as
      // 'datasouce_id/entity_id'. Of course, the ID numbers of external Solr
      // documents will not have this pattern and the datasource must be added.
      // Reflect into the class to set the itemId.
      $reflection = new \ReflectionClass($item);
      $id_property = $reflection->getProperty('itemId');
      $id_property->setAccessible(TRUE);
      $id_property->setValue($item, $datasource . '/' . $item->getId());
    }
  }

  /**
   * Override the default fields that Search API Solr sets up.  In particular,
   * set the ID field to the one that is configured via the datasource config
   * form.
   *
   * Also, map the index's field names to the original property paths. Search
   * API Solr adds prefixes to the paths because it assumes that it has done the
   * indexing according to its schema.xml rules. Of course, in our case it
   * hasn't and we need it to use the raw paths. Any field machine names that
   * have been altered in the field list will have their mapping corrected by
   * this step too.
   *
   * @see \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend::getSolrFieldNames()
   *
   * {@inheritdoc}
   */
  public function getSolrFieldNames(IndexInterface $index, $reset = FALSE) {
    // @todo The field name mapping should be cached per index because custom
    //   queries needs to access it on every query. But we need to be aware of
    //   datasource additions and deletions.
    if (!isset($this->fieldNames[$index->id()]) || $reset) {
      parent::getSolrFieldNames($index, $reset);

      if ($config = $this->getDatasourceConfig($index)) {
        $this->fieldNames[$index->id()]['search_api_id'] = $config['id_field'];
        $this->fieldNames[$index->id()]['search_api_language'] = $config['language_field'];

        /** @var \Drupal\search_api\Item\FieldInterface[] $index_fields */
        $index_fields = $index->getFields();

        // Re-map the indexed fields.
        foreach ($this->fieldNames[$index->id()] as $search_api_name => $solr_name) {
          // Ignore the Search API fields.
          if (strpos($search_api_name, 'search_api_') === 0
            || empty($index_fields[$search_api_name])
            || strpos($index_fields[$search_api_name]->getDatasourceId(), 'solr_') !== 0
          ) {
            continue;
          }
          $this->fieldNames[$index->id()][$search_api_name] = $index_fields[$search_api_name]->getPropertyPath();
        }
      }
    }

    // Let modules adjust the field mappings.
    $this->moduleHandler->alter('search_api_solr_field_mapping', $index, $ret);

    return $this->fieldNames[$index->id()];
  }

}
