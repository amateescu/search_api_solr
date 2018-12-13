<?php

namespace Drupal\search_api_solr\Plugin\search_api\datasource;

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

}
