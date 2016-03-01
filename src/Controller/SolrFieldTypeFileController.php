<?php

/**
 * @file
 * Contains Drupal\search_api_solr_multilingual\Controller\SolrFieldTypeFileController.
 */

namespace Drupal\search_api_solr_multilingual\Controller;

use Drupal\Core\Controller\ControllerBase;
use ZipStream\ZipStream;

/**
 * Provides a listing of SolrFieldType.
 */
class SolrFieldTypeFileController extends ControllerBase {

  public function getSchemaExtraTypesXml() {
    return $this->entityManager()->getListBuilder('solr_field_type')->getSchemaExtraTypesXml();
  }

  public function getSchemaExtraFieldsXml() {
    return $this->entityManager()->getListBuilder('solr_field_type')->getSchemaExtraFieldsXml();
  }

  public function getConfigZip() {
    ob_clean();

    /** @var ZipStream $zip */
    $zip = $this->entityManager()->getListBuilder('solr_field_type')->getConfigZip();
    $zip->finish();

    ob_end_flush();
    exit();
  }

}
