<?php

/**
 * @file
 * Drush commands for Search API Multilingual Solr.
 */

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexBatchHelper;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;

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

  return $items;
}

/**
 * Deletes all Solr Field Type and re-installs them from their yml files.
 */
function drush_search_api_solr_multilingual_delete_and_reinstall_all_field_types() {
  search_api_solr_multilingual_delete_and_reinstall_all_field_types();
}
