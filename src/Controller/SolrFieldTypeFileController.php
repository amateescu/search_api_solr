<?php

/**
 * @file
 * Contains Drupal\search_api_solr_multilingual\Controller\SolrFieldTypeListController.
 */

namespace Drupal\search_api_solr_multilingual\Controller;

use Drupal\Core\Entity\Controller\EntityListController;

/**
 * Provides a listing of SolrFieldType.
 */
class SolrFieldTypeListController extends EntityListController {

  /**
   * @inheritdoc
   */
  public function listing($entity_type) {
    return $this->entityManager()->getListBuilder($entity_type)->getSchemaExtraTypesXml();
  }

}
