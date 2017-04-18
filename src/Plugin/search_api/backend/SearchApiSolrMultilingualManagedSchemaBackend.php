<?php

namespace Drupal\search_api_solr_multilingual\Plugin\search_api\backend;

use Drupal\Component\Serialization\Json;
use Drupal\search_api_solr\Utility\Utility;
use Drupal\search_api_solr_multilingual\Entity\SolrFieldType;
use Drupal\search_api_solr_multilingual\SearchApiSolrMultilingualException;

/**
 * @SearchApiBackend(
 *   id = "search_api_solr_multilingual_managed_schema",
 *   label = @Translation("Multilingual Solr Managed Schema (Experimental, don't use in production)."),
 *   description = @Translation("Index items using an Solr search server with managed schema for dynamic configuration for multilingual content.")
 * )
 */
class SearchApiSolrMultilingualManagedSchemaBackend extends AbstractSearchApiSolrMultilingualBackend {

  /**
   * {@inheritdoc}
   */
  public function isManagedSchema() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function createSolrDynamicField($solr_field_name, $solr_field_type_name) {
    // @todo leverage SolrFieldType::getDynamicFields().
    $command = [
      'add-dynamic-field' => [
        'name' => $solr_field_name,
        'type' => $solr_field_type_name,
        'stored' => TRUE,
        'indexed' => TRUE,
        'multiValued' => strpos($solr_field_name, 'tm_') === 0 ? TRUE : FALSE,
        'termVectors' => strpos($solr_field_name, 't') === 0 ? TRUE : FALSE,
      ],
    ];
    try {
      $this->solrHelper()->coreRestPost('schema', Json::encode($command));
    }
    catch (SearchApiSolrMultilingualException $e) {
      watchdog_exception('solr', $e);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function createSolrMultilingualFieldType($solr_field_type_name) {
    // @todo replace the hardcoded version extension.
    $field_type_name = 'm_' . Utility::decodeSolrName($solr_field_type_name) . '_5_2_0';

    // Get the field type definition from Drupal.
    /** @var \Drupal\search_api_solr_multilingual\Entity\SolrFieldType $field_type_entity */
    $field_type_entity = SolrFieldType::load($field_type_name);
    if (!$field_type_entity) {
      throw new SearchApiSolrMultilingualException("There's no field type $field_type_name.");
    }
    $field_type_definition = $field_type_entity->getFieldType();
    $field_type_definition['name'] = $solr_field_type_name;

    // Send the config to Solr.
    $command_json = '{ "add-field-type": ' . Json::encode($field_type_definition) . '}';
    $command_json = str_replace('"' . $field_type_name . '"', '"' . $solr_field_type_name . '"', $command_json);
    try {
      $this->solrHelper()->coreRestPost('schema', $command_json);
    }
    catch (SearchApiSolrMultilingualException $e) {
      watchdog_exception('solr', $e);
      return FALSE;
    }
    return TRUE;
  }

}
