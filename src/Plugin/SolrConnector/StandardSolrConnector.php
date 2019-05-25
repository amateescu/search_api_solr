<?php

namespace Drupal\search_api_solr\Plugin\SolrConnector;

use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrConnector\SolrConnectorPluginBase;
use Solarium\Exception\HttpException;

/**
 * Standard Solr connector.
 *
 * @SolrConnector(
 *   id = "standard",
 *   label = @Translation("Standard"),
 *   description = @Translation("A standard connector usable for local installations of the standard Solr distribution.")
 * )
 */
class StandardSolrConnector extends SolrConnectorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function reloadCore() {
    $this->connect();

    try {
      $core = $this->configuration['core'];
      $core_admin_query = $this->solr->createCoreAdmin();
      $reload_action = $core_admin_query->createReload();
      $reload_action->setCore($core);
      $core_admin_query->setAction($reload_action);
      $response = $this->solr->coreAdmin($core_admin_query);
      return $response->getWasSuccessful();
    }
    catch (HttpException $e) {
      throw new SearchApiSolrException("Reloading core $core failed with error code " . $e->getCode() . '.', $e->getCode(), $e);
    }
  }

}
