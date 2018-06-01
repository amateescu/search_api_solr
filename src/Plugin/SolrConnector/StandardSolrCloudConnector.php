<?php

namespace Drupal\search_api_solr\Plugin\SolrConnector;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api_solr\SolrCloudConnectorInterface;
use Solarium\Core\Client\Endpoint;
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

    return $form;
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
    return $this->pingCore();
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

}
