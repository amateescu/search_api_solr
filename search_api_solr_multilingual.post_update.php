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
