<?php

namespace Drupal\search_api_solr\Plugin\SolrConnector;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrCloudConnectorInterface;
use Solarium\Core\Client\Endpoint;
use Solarium\Exception\HttpException;
use Solarium\QueryType\Graph\Query as GraphQuery;
use Solarium\QueryType\Ping\Query as PingQuery;
use Solarium\QueryType\Stream\Query as StreamQuery;

/**
 * Standard Solr Cloud connector.
 *
 * @SolrConnector(
 *   id = "solr_cloud",
 *   label = @Translation("Solr Cloud"),
 *   description = @Translation("A standard connector for a Solr Cloud.")
 * )
 */
class StandardSolrCloudConnector extends StandardSolrConnector implements SolrCloudConnectorInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'checkpoints_collection' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['host']['#title'] = $this->t('Solr node');
    $form['host']['#description'] = $this->t('The host name or IP of a Solr node, e.g. <code>localhost</code> or <code>www.example.com</code>.');

    $form['path']['#description'] = $this->t('The path that identifies the Solr instance to use on the node.');

    $form['core']['#title'] = $this->t('Solr collection');
    $form['core']['#description'] = $this->t('The name that identifies the Solr collection to use.');

    $form['timeout']['#description'] = $this->t('The timeout in seconds for search queries sent to the Solr collection.');

    $form['index_timeout']['#description'] = $this->t('The timeout in seconds for indexing requests to the Solr collection.');

    $form['optimize_timeout']['#description'] = $this->t('The timeout in seconds for background index optimization queries on the Solr collection.');

    $form['advanced']['checkpoints_collection'] = [
      '#type' => 'textfield',
      '#title' => $this->t('checkpoints_collection'),
      '#description' => $this->t("The collection where topic checkpoints are stored. Not required if you don't work with topic() streaming expressions."),
      '#default_value' => isset($this->configuration['checkpoints_collection']) ? $this->configuration['checkpoints_collection'] : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function isCloud() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatsSummary() {
    $summary = parent::getStatsSummary();
    $summary['@collection_name'] = '';

    $query = $this->solr->createPing();
    $query->setResponseWriter(PingQuery::WT_PHPS);
    $query->setHandler('admin/mbeans?stats=true');
    $stats = $this->execute($query)->getData();
    if (!empty($stats)) {
      $solr_version = $this->getSolrVersion(TRUE);
      if (version_compare($solr_version, '7.0', '>=')) {
        $summary['@collection_name'] = $stats['solr-mbeans']['CORE']['core']['stats']['CORE.collection'];
      }
      else {
        $summary['@core_name'] = $stats['solr-mbeans']['CORE']['core']['stats']['collection'];
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->configuration['core'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCheckpointsCollectionName() {
    return $this->configuration['checkpoints_collection'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionLink() {
    return $this->getCoreLink();
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionInfo($reset = FALSE) {
    return $this->getCoreInfo();
  }

  /**
   * {@inheritdoc}
   */
  public function pingCollection() {
    return parent::pingCore(['distrib' => FALSE]);
  }

  /**
   * {@inheritdoc}
   */
  public function pingCore(array $options = []) {
    return parent::pingCore(['distrib' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  public function getStreamQuery() {
    $this->connect();
    return $this->solr->createStream();
  }

  /**
   * {@inheritdoc}
   */
  public function stream(StreamQuery $query, Endpoint $endpoint = NULL) {
    return $this->execute($query, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function getGraphQuery() {
    $this->connect();
    return $this->solr->createGraph();
  }

  /**
   * {@inheritdoc}
   */
  public function graph(GraphQuery $query, Endpoint $endpoint = NULL) {
    return $this->execute($query, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function getTermsQuery() {
    $query = parent::getTermsQuery();
    return $query->setDistrib(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getSpellcheckQuery() {
    $query = parent::getSpellcheckQuery();
    return $query->setDistrib(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggesterQuery() {
    $query = parent::getSuggesterQuery();
    return $query->setDistrib(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getAutocompleteQuery() {
    $query = parent::getAutocompleteQuery();
    return $query->setDistrib(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function reloadCore() {
    return $this->reloadCollection();
  }

  /**
   * Reloads collection.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function reloadCollection() {
    $this->connect();

    try {
      $collection = $this->configuration['core'];

      $query = $this->solr->createCollections();
      $action = $query->createReload(['name' => $collection]);
      $query->setAction($action);

      $response = $this->solr->collections($query);
      return $response->getWasSuccessful();
    }
    catch (HttpException $e) {
      throw new SearchApiSolrException("Reloading collection $collection failed with error code " . $e->getCode() . '.', $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterConfigFiles(array &$files, string $lucene_match_version, string $server_id = '') {
    parent::alterConfigFiles($files, $lucene_match_version, $server_id);

    // Leverage the implicit Solr request handlers with default settings for
    // Solr Cloud.
    // @see https://lucene.apache.org/solr/guide/8_0/implicit-requesthandlers.html
    $files['solrconfig.xml'] = preg_replace("@<requestHandler\s+name=\"/replication\".*?</requestHandler>@ms", '', $files['solrconfig.xml']);
    $files['solrconfig.xml'] = preg_replace("@<requestHandler\s+name=\"/get\".*?</requestHandler>@ms", '', $files['solrconfig.xml']);
    $files['solrcore.properties'] = preg_replace("/solr\.replication.*\n/", '', $files['solrcore.properties']);
  }

}
