<?php

/**
 * Re-installs all Solr Field Types to be compatible to the latest structure.
 */
function search_api_solr_multilingual_post_update_replace_solr_field_types() {
  // Removed due to race condition with
  // search_api_solr_multilingual_update_8001().
}

/**
 * Fixes erroneous backend IDs.
 */
function search_api_solr_multilingual_post_update_fix_backend_ids() {
  $storage = \Drupal::entityTypeManager()->getStorage('search_api_server');
  /** @var \Drupal\search_api\ServerInterface[] $servers */
  $servers = $storage->loadByProperties(['backend' => 'search_api_solr.multilingual']);
  foreach ($servers as $server) {
    $server->set('backend', 'search_api_solr_multilingual');
    $server->save();
  }
}

/**
 * Re-installs all Solr Field Types to be compatible to the latest structure.
 */
function search_api_solr_multilingual_post_update_replace_solr_field_types_2() {
  $version = \Drupal::keyValue('system.schema')->get('search_api_solr_multilingual');
  if ($version < 8002) {
    search_api_solr_multilingual_delete_and_reinstall_all_field_types();
  }
}

/**
 * Re-installs Greek Solr Field Type.
 */
function search_api_solr_multilingual_post_update_replace_solr_field_type_el2() {
  $storage = \Drupal::entityTypeManager()->getStorage('solr_field_type');
  if ($field_type = $storage->load('text_el_4_5_0')) {
    $storage->delete([$field_type]);
  }

  /** @var \Drupal\Core\Config\ConfigInstallerInterface $config_installer */
  $config_installer = \Drupal::service('config.installer');
  $config_installer->installDefaultConfig('module', 'search_api_solr_multilingual');
  $restrict_by_dependency = [
    'module' => 'search_api_solr_multilingual',
  ];
  $config_installer->installOptionalConfig(NULL, $restrict_by_dependency);
}
