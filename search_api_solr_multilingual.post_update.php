<?php

/**
 * Re-installs all Solr Field Types to be compatible to the latest structure.
 */
function search_api_solr_multilingual_post_update_replace_solr_field_types() {
  $storage = \Drupal::entityTypeManager()->getStorage('solr_field_type');
  $storage->delete($storage->loadMultiple());

  /** @var \Drupal\Core\Config\ConfigInstallerInterface $config_installer */
  $config_installer = \Drupal::service('config.installer');
  $config_installer->installDefaultConfig('module', 'search_api_solr_multilingual');
  $restrict_by_dependency = [
    'module' => 'search_api_solr_multilingual',
  ];
  $config_installer->installOptionalConfig(NULL, $restrict_by_dependency);
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
