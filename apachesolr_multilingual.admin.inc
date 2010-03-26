<?php
// $Id$

/**
 * @file
 * Admin settings for Apache Solr Multilingual.
 *
 * @see apachesolr_multilingual.module
 * @see apachesolr.module
 *
 * @author Markus Kalkbrenner (mkalkbrenner) | Cocomore AG
 *   @see http://drupal.org/user/124705
 *   @see http://drupal.cocomore.com
 */

function apachesolr_multilingual_admin_form($form_state) {
  $active_languages = locale_language_list();

  $form['apachesolr_multilingual_index_translations'] = array(
    '#type' => 'checkbox',
    '#title' => t('Index translations'),
    '#default_value' => variable_get('apachesolr_multilingual_index_translations', 0),
    '#description' => t('All translations of a node will be added to the node itself ...'),
  );

  $form['apachesolr_multilingual_languages'] = array(
    '#type' => 'checkboxes',
    '#title' => '',
    '#options' => $active_languages,
    '#default_value' => variable_get('apachesolr_multilingual_languages', array()),
    '#description' => t('Select languages to be handled by multilingual search'),
  );

  return system_settings_form($form);
}
