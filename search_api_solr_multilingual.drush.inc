<?php

use Drupal\search_api\Entity\Server;

/**
 * Implements hook_drush_command().
 */
function search_api_solr_multilingual_drush_command() {
  $items = array();

  $items['search-api-solr-multilingual-delete-and-reinstall-all-field-types'] = array(
    'description' => 'Deletes all Solr Field Type and re-installs them from their yml files.',
    'examples' => array(
      'drush search-api-solr-multilingual-delete-and-reinstall-all-field-types' => dt('Deletes all Solr Field Type and re-installs them from their yml files.'),
      'drush sasm-reinstall-ft' => dt('Alias to delete all Solr Field Type and to re-install them from their yml files.'),
    ),
    'aliases' => array('sasm-reinstall-ft'),
  );

  $items['search-api-solr-multilingual-get-server-config'] = array(
    'description' => 'Get Solr config files as zip for a search server.',
    'examples' => array(
      'drush search-api-solr-multilingual-get-server-config my_solr_server' => dt('Get the config files for @server search server.', array('@server' => 'my_solr_server')),
      'drush sasm-gsc my_solr_server' => dt('Alias to get the config files for @server search server.', array('@server' => 'my_solr_server')),
    ),
    'arguments' => array(
      'server_id' => dt('The numeric ID or machine name of a search server to enable.'),
      'file_name' => dt('The file name of the config zip.'),
      'solr_version' => dt('The targeted Solr version.'),
    ),
    'aliases' => array('sasm-gsc'),
  );

  return $items;
}

/**
 * Deletes all Solr Field Type and re-installs them from their yml files.
 */
function drush_search_api_solr_multilingual_delete_and_reinstall_all_field_types() {
  search_api_solr_multilingual_delete_and_reinstall_all_field_types();
}

/**
 * Gets the config far a Solr search server.
 *
 * @param string $server_id
 *   The ID of the server.
 * @param string $file_name
 *   The file name of the config zip that should be created.
 * @param string $solr_version
 *   The targeted Solr version.
 */
function drush_search_api_solr_multilingual_get_server_config($server_id, $file_name, $solr_version = NULL) {
  /** @var Drupal\search_api_solr_multilingual\Controller\SolrFieldTypeListBuilder $list_builder */
  $list_builder = Drupal::entityTypeManager()->getListBuilder('solr_field_type');
  $server = Server::load($server_id);
  if ($solr_version) {
    $config = $server->getBackendConfig();
    $config['connector_config']['solr_version'] = $solr_version;
    $server->setBackendConfig($config);
  }
  $list_builder->setServer($server);
  ob_end_clean();
  ob_start();
  $zip = $list_builder->getConfigZip();
  $zip->finish();
  file_put_contents($file_name, ob_get_clean());
}
