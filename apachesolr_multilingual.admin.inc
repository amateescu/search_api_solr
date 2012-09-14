<?php

/**
 * @file
 * Admin settings for Apache Solr Multilingual.
 *
 * @see apachesolr_multilingual.module
 * @see apachesolr.module
 *
 * @author Markus Kalkbrenner (mkalkbrenner) | bio.logis GmbH
 *   @see http://drupal.org/user/124705
 */


/**
 * Implements hook_form().
 *
 * @param $form_state
 */
function apachesolr_multilingual_admin_form($form_state) {
  $active_languages = locale_language_list();

  $form['vt'] = array(
    '#type' => 'vertical_tabs',
  );

  $form['vt']['apachesolr_multilingual_query_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Query Settings'),
    '#collapsible' => TRUE,
  );

  $var_name = 'apachesolr_multilingual_auto_language_filter';
  $form['vt']['apachesolr_multilingual_query_settings'][$var_name] =
    apachesolr_multilingual_variable_definition_to_form_element($var_name);

  $var_name = 'apachesolr_multilingual_auto_language_filter_detachable';
  $form['vt']['apachesolr_multilingual_query_settings'][$var_name] =
    apachesolr_multilingual_variable_definition_to_form_element($var_name);

  $var_name = 'apachesolr_multilingual_show_language_undefined_results';
  $form['vt']['apachesolr_multilingual_query_settings'][$var_name] =
    apachesolr_multilingual_variable_definition_to_form_element($var_name);

  $form['vt']['apachesolr_multilingual_index_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Index Settings'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $var_name = 'apachesolr_multilingual_map_language_neutral';
  $variable_info = variable_get_info($var_name);
  $form['vt']['apachesolr_multilingual_index_settings'][$var_name] = array(
    '#type' => 'radios',
    '#title' => check_plain($variable_info['title']),
    '#options' => array_merge(array(LANGUAGE_NONE => t('No mapping')), $active_languages),
    '#default_value' => variable_get_default($var_name),
    '#description' => filter_xss($variable_info['description']),
  );

  $form['vt']['apachesolr_multilingual_clir'] = array(
    '#type' => 'fieldset',
    '#title' => t('CLIR'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description' => t('<b>Cross-language information retrieval</b>. As a special feature Apache Solr Multilingual could be configured to deal with the translations of nodes and taxonomies on multilingual sites. That means that you can find content in any language no matter which language was used to enter the search phrase.'),
  );

  $var_name = 'apachesolr_multilingual_index_translations';
  $form['vt']['apachesolr_multilingual_clir'][$var_name] =
    apachesolr_multilingual_variable_definition_to_form_element($var_name);

  $var_name = 'apachesolr_multilingual_index_unpublished_translations';
  $form['vt']['apachesolr_multilingual_clir'][$var_name] =
    apachesolr_multilingual_variable_definition_to_form_element($var_name);

  $var_name = 'apachesolr_multilingual_index_term_translations';
  $form['vt']['apachesolr_multilingual_clir'][$var_name] =
    apachesolr_multilingual_variable_definition_to_form_element($var_name);
  $form['vt']['apachesolr_multilingual_clir'][$var_name]['#disabled'] = !module_exists('i18ntaxonomy');

  return system_settings_form($form);
}


function apachesolr_multilingual_admin_form_validate($form, $form_state) {
}

