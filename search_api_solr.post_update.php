<?php

/**
 * Installs the standard highlighter config.
 */
function search_api_solr_post_update_install_standard_highlighter_config() {
  // Removed.
}

/**
 * Reinstalls the solr field types.
 */
function search_api_solr_post_update_8200_reinstall_field_types() {
  // Removed.
}

/**
 * Deletes potential left over configs from multilingual to alpha1 migration.
 */
function search_api_solr_post_update_8201_delete_multilingual_migration_left_over_configs() {
  $config_factory = \Drupal::configFactory();
  foreach ($config_factory->listAll('search_api_solr_multilingual') as $config_name) {
    $config_factory->getEditable($config_name)->delete();
  }
}

/**
 * Installs new optional Solr field types.
 */
function search_api_solr_post_update_8203_install_new_optional_field_types() {
  // Removed.
}

/**
 * Replace Solr 4 and 5 field types by Solr 6 versions.
 */
function search_api_solr_post_update_8204_replace_solr_4_field_types() {
  $storage = \Drupal::entityTypeManager()->getStorage('solr_field_type');
  $storage->delete($storage->loadMultiple([
    'm_text_und_5_2_0',
    'text_und_4_5_0',
    'm_text_de_5_2_0',
    'm_text_en_5_2_0',
    'm_text_nl_5_2_0',
    'text_cs_5_0_0',
    'text_de_4_5_0',
    'text_de_5_0_0',
    'text_de_scientific_5_0_0',
    'text_el_4_5_0',
    'text_en_4_5_0',
    'text_es_4_5_0',
    'text_fi_4_5_0',
    'text_fr_4_5_0',
    'text_it_4_5_0',
    'text_nl_4_5_0',
    'text_ru_4_5_0',
    'text_uk_4_5_0',
  ]));
}
