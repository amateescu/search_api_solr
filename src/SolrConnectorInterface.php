<?php

namespace Drupal\search_api_solr;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Solarium\Client;

interface SolrConnectorInterface extends ConfigurablePluginInterface {

  /**
   * Apply adjustments to the solarium client before connection is established.
   *
   * @param \Solarium\Client $client
   */
  public function connect(Client $client);

  /**
   * Retrieves the configuration for a solarium endpoint.
   *
   * @return array
   *   The solarium endpoint configuration.
   */
  public function getQueryEndpointConfig();

  /**
   * Retrieves the configuration for a solarium endpoint.
   *
   * @return array
   *   The solarium endpoint configuration.
   */
  public function getIndexEndpointConfig();

  /**
   * Retrieves the configuration for a solarium endpoint.
   *
   * @return array
   *   The solarium endpoint configuration.
   */
  public function getOptimizeEndpointConfig();

}
