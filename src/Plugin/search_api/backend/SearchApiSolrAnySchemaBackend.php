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
    // @todo force read-only

    return $form;
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
    if (!$index->isValidDatasource('solr_document')) {
      return;
    }

    // Remove the filter queries that limit the results based on site and index.
    $solarium_query->removeFilterQuery('index_filter');

    // Set requestHandler for the query type.
    $config = $index->getDatasource('solr_document')->getConfiguration();
    if (!empty($config['request_handler'])) {
      $solarium_query->addParam('qt', $config['request_handler']);
    }

    // Set the default query, if necessary and configured.
    if (!$solarium_query->getQuery() && !empty($config['default_query'])) {
      $solarium_query->setQuery($config['default_query']);
    }

    $backend = $index->getServerInstance()->getBackend();
    if ($backend instanceof SearchApiSolrBackend) {
      $solr_config = $backend->getConfiguration();
      // @todo Should we maybe not even check that setting and use this to
      //   auto-enable fields retrieval from Solr?
      if (!empty($solr_config['retrieve_data'])) {
        $fields_list = [];
        foreach ($backend->getSolrFieldNames($index) as $solr_field_name) {
          $fields_list[] = $solr_field_name;
        }
        $extra_fields = [
          'language_field',
          'label_field',
          'url_field',
        ];
        foreach ($extra_fields as $config_key) {
          if (!empty($config[$config_key])) {
            $fields_list[] = $config[$config_key];
          }
        }
        $solarium_query->setFields(array_unique($fields_list));
      }
    }
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

    // Do not alter the results if the index does not use the solr_document
    // datasource.
    $datasources = $query->getIndex()->getDatasources();
    if (!isset($datasources['solr_document'])) {
      return;
    }

    /** @var \Drupal\search_api_solr\SolrDocumentFactoryInterface $solr_document_factory */
    $solr_document_factory = \Drupal::getContainer()->get('solr_document.factory');

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
      $id_property->setValue($item, 'solr_document/' . $item->getId());
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

      // Do not alter mappings if the index does not use the solr_document
      // datasource.
      $datasources = $index->getDatasources();
      if (isset($datasources['solr_document'])) {
        // Set the ID field.
        $config = $index->getDatasource('solr_document')->getConfiguration();
        $this->fieldNames[$index->id()]['search_api_id'] = $config['id_field'];
        $this->fieldNames[$index->id()]['search_api_language'] = $config['language_field'];

        /** @var \Drupal\search_api\Item\FieldInterface[] $index_fields */
        $index_fields = $index->getFields();

        // Re-map the indexed fields.
        foreach ($this->fieldNames[$index->id()] as $raw => $name) {
          // Ignore the Search API fields.
          if (strpos($raw, 'search_api_') === 0
            || empty($index_fields[$raw])
            || $index_fields[$raw]->getDatasourceId() !== 'solr_document'
          ) {
            continue;
          }
          $this->fieldNames[$index->id()][$raw] = $index_fields[$raw]->getPropertyPath();
        }
      }
    }
    return $this->fieldNames[$index->id()];
  }

}
