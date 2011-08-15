<?php

/**
 * @file
 * Admin settings for Apache Solr Multilingual Advanced Settings.
 *
 * @see apachesolr_multilingual_advanced_solr.module
 *
 * @author Markus Kalkbrenner (mkalkbrenner) | Cocomore AG
 *   @see http://drupal.org/user/124705
 *   @see http://drupal.cocomore.com
 */


function apachesolr_multilingual_advanced_solr_form($form_state) {
  // load the validators
  module_load_include('admin.inc', 'apachesolr_multilingual');

  $form['apachesolr_multilingual_multicore'] = array(
    '#type' => 'checkbox',
    '#title' => t('Disable Data Dir setting'),
    '#default_value' => variable_get('apachesolr_multilingual_multicore', 1),
    '#description' => t('Removes property dataDir from solrconfig.xml. This might be usefull for solr multi core setups or if you want to use solr\'s default data dir location. See !link for further instructions.', array('!link' => l(t('Core Administration'), 'http://wiki.apache.org/solr/CoreAdmin'))),
    '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
  );

  $form['apachesolr_multilingual_dataDir'] = array(
    '#type' => 'textfield',
    '#title' => t('Data Dir'),
    '#default_value' => variable_get('apachesolr_multilingual_dataDir', '${solr.data.dir:./solr/data}'),
    '#description' => t('Set an explicit path where solr stores the index like /var/sor/data for ubuntu linux'),
    '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
  );

  $form['apachesolr_multilingual_autoCommit_maxTime'] = array(
    '#type' => 'textfield',
    '#title' => t('Auto Commit Max Time'),
    '#default_value' => variable_get('apachesolr_multilingual_autoCommit_maxTime', 120000),
    '#description' => t('Time in ms before documents posted to Solr will be committed to the index. This option is described as "delay" at !link.', array('!link' => l(t('Search Index'), 'admin/config/search/apachesolr/index'))),
    '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config_no_delete'),
  );

  return system_settings_form($form);
}


function apachesolr_multilingual_advanced_solr_form_validate($form, $form_state) {
  if (!ctype_digit($form_state['values']['apachesolr_multilingual_autoCommit_maxTime'])) {
    form_set_error('apachesolr_multilingual_autoCommit_maxTime', t('You need to provide a valid number of ms.'));
  }
}
