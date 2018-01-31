<?php

/**
 * Implements hook_drush_command().
 */
function search_api_solr_drush_command() {
  $items = [];

  $items['search-api-solr-delete-and-reinstall-all-field-types'] = [
    'description' => 'Deletes all Solr Field Type and re-installs them from their yml files.',
    'examples' => [
      'drush search-api-solr-delete-and-reinstall-all-field-types' => dt('Deletes all Solr Field Type and re-installs them from their yml files.'),
      'drush solr-reinstall-ft' => dt('Alias to delete all Solr Field Type and to re-install them from their yml files.'),
    ],
    'aliases' => [
      'solr-reinstall-ft',
    // Backward compatibility.
      'sasm-reinstall-ft',
    // Backward compatibility.
      'search-api-solr-multilingual-delete-and-reinstall-all-field-types',
    ],
  ];

  $items['search-api-solr-get-server-config'] = [
    'description' => 'Get Solr config files as zip for a search server.',
    'examples' => [
      'drush search-api-solr-get-server-config my_solr_server' => dt('Get the config files for @server search server.', ['@server' => 'my_solr_server']),
      'drush solr-gsc my_solr_server' => dt('Alias to get the config files for @server search server.', ['@server' => 'my_solr_server']),
    ],
    'arguments' => [
      'server_id' => dt('The numeric ID or machine name of a search server to enable.'),
      'file_name' => dt('The file name of the config zip.'),
      'solr_version' => dt('The targeted Solr version.'),
    ],
    'aliases' => [
      'solr-gsc',
    // Backward compatibility.
      'sasm-gsc',
    // Backward compatibility.
      'search-api-solr-multilingual-get-server-config',
    ],
  ];

  return $items;
}

/**
 * Deletes all Solr Field Type and re-installs them from their yml files.
 */
function drush_search_api_solr_delete_and_reinstall_all_field_types() {
  $command_helper = _search_api_solr_drush_command_helper();
  $command_helper->reinstallFieldtypesCommand();
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
function drush_search_api_solr_get_server_config($server_id, $file_name, $solr_version = NULL) {
  $command_helper = _search_api_solr_drush_command_helper();
  $command_helper->getServerConfigCommand($server_id, $file_name, $solr_version);
}

/**
 * Returns an instance of the command helper.
 *
 * @return \Drupal\search_api_solr\Utility\CommandHelper
 *   An instance of the command helper class.
 */
function _search_api_solr_drush_command_helper() {
  $command_helper = new \Drupal\search_api_solr\Utility\CommandHelper(\Drupal::entityTypeManager());
  $command_helper->setLogger(\Drupal::logger('search_api'));
  return $command_helper;
}
