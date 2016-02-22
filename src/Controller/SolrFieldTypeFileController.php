<?php

/**
 * @file
 * Contains Drupal\search_api_solr_multilingual\Controller\SolrFieldTypeFileController.
 */

namespace Drupal\search_api_solr_multilingual\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides a listing of SolrFieldType.
 */
class SolrFieldTypeFileController extends ControllerBase {

  /**
   * @inheritdoc
   */
  public function getSchemaExtraTypesXml() {
    return $this->entityManager()->getListBuilder('solr_field_type')->getSchemaExtraTypesXml();
  }

  /**
   * @inheritdoc
   */
  public function getSchemaExtraFieldsXml() {
    return $this->entityManager()->getListBuilder('solr_field_type')->getSchemaExtraFieldsXml();
  }

}
