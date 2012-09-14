<?php

/**
 * @file
 *  TODO
 */

/**
 * Implements hook_variable_group_info().
 */
function apachesolr_multilingual_variable_group_info() {
  $groups['apachesolr_multilingual'] = array(
    'title' => t('Apache Solr Multilingual'),
    'description' => t('Fine tune multilingual and non-English search with Apache Solr'),
    'access' => 'administer search',
    'path' => array('admin/config/search/apachesolr/multilingual'),
  );

  return $groups;
}

/**
 * Implements hook_variable_info().
 */
function apachesolr_multilingual_variable_info($options) {

  $variables['apachesolr_multilingual_auto_language_filter'] = array(
    'type' => 'boolean',
    'title' => t('Limit search to current language by default', array(), $options),
    'default' => TRUE,
    'description' => t('Show only search results that match the current language.', array(), $options),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_auto_language_filter_detachable'] = array(
    'type' => 'boolean',
    'title' => t('Limit search to current language could be disabled by user', array(), $options),
    'default' => FALSE,
    'description' => t('Displays a checkbox on search result page to search over all languages if "%limit_search" is active.', array('%limit_search' => t('Limit search to current language by default', array(), $options)), $options),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_show_language_undefined_results'] = array(
    'type' => 'boolean',
    'title' => t('Show language-neutral/undefined results by default', array(), $options),
    'default' => FALSE,
    'description' => t('Show language-neutral/undefined results on search result page if "%limit_search" is active.', array('%limit_search' => t('Limit search to current language by default', array(), $options)), $options),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_map_language_neutral'] = array(
    'type' => 'string',
    'title' => t('Map language-neutral', array(), $options),
    'default' => LANGUAGE_NONE,
    'description' => t('Select a language to treat content marked as language-neutral. Language-neutral will not occur as filter option any more.', array(), $options),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_index_translations'] = array(
    'type' => 'boolean',
    'title' => t('Index node translations', array(), $options),
    'default' => FALSE,
    'description' => t('All translations of a node will be added to the node itself.', array(), $options),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_index_unpublished_translations'] = array(
    'type' => 'boolean',
    'title' => t('Index unpublished node translations', array(), $options),
    'default' => FALSE,
    'description' => t('Index unpublished translations, e.g. machine translations.', array(), $options),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_index_term_translations'] = array(
    'type' => 'boolean',
    'title' => t('Index taxonomy term translations', array(), $options),
    'default' => FALSE,
    'description' => t('Index translations of the taxonomy terms. Requires module Taxonomy Translation which is part of !link.', array('!link' => l(t('Internationalization'), 'http://drupal.org/project/i18n')), $options),
    'group' => 'apachesolr_multilingual',
  );

  return $variables;
}
