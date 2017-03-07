<?php

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Datasource\DatasourcePluginBase;

/**
 * Represents a datasource which exposes external Solr Documents.
 *
 * @SearchApiDatasource(
 *   id = "solr_document",
 *   label = @Translation("Solr Document"),
 *   description = @Translation("Exposes external Solr Documents as a datasource."),
 * )
 */
class SolrDocument extends DatasourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getItemId(ComplexDataInterface $item) {
    ;
  }

}
