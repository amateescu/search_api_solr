<?php

namespace Drupal\search_api_solr_multilingual\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\ServerInterface;
use ZipStream\Exception as ZipStreamException;

/**
 * Provides different listings of SolrFieldType.
 */
class SolrFieldTypeController extends ControllerBase {

  /**
   * Provides the listing page.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function listing(ServerInterface $search_api_server) {
    return $this->getListBuilder($search_api_server)->render();
  }

  /**
   * Provides an XML snippet containing all extra Solr field types.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function getSchemaExtraTypesXml(ServerInterface $search_api_server) {
    return $this->getListBuilder($search_api_server)->getSchemaExtraTypesXml();
  }

  /**
   * Provides an XML snippet containing all extra Solr fields.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function getSchemaExtraFieldsXml(ServerInterface $search_api_server) {
    return $this->getListBuilder($search_api_server)->getSchemaExtraFieldsXml();
  }

  /**
   * Provides a zip archive containing a complete Solr configuration.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function getConfigZip(ServerInterface $search_api_server) {
    ob_clean();

    try {
      /** @var \ZipStream\ZipStream $zip */
      $zip = $this->getListBuilder($search_api_server)->getConfigZip();
      $zip->finish();

      ob_end_flush();
      exit();
    }
    catch (ZipStreamException $e) {
      watchdog_exception('search_api_solr_multilingual', $e);
      drupal_set_message($this->t('An error occured during the creation of the config.zip. Look at the logs for details.'), 'error');
    }

    return [];
  }

  /**
   * Gets the list builder for 'solr_field_type'.
   *
   * Ensures that the list builder uses the correct Solr backend.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *
   * @return \Drupal\search_api_solr_multilingual\Controller\SolrFieldTypeListBuilder
   */
  protected function getListBuilder(ServerInterface $search_api_server) {
    /** @var SolrFieldTypeListBuilder $list_builder */
    $list_builder = $this->entityTypeManager()->getListBuilder('solr_field_type');
    $list_builder->setServer($search_api_server);
    return $list_builder;
  }

}
