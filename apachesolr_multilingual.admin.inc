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


/**
 * Implemtens hook_form().
 *
 * @param $form_state
 * @return void
 */
function apachesolr_multilingual_admin_form($form_state) {
  $active_languages = locale_language_list();

  foreach ($active_languages as $id => $language) {
    if (!apachesolr_multilingual_get_stemmer($id)) {
      $active_languages[$id] .= ' ' . t('(without stemming)');
    }
  }

  $form['apachesolr_multilingual_language_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Languages'),
    '#collapsible' => TRUE,
  );

  $form['apachesolr_multilingual_language_settings']['apachesolr_multilingual_languages'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Languages'),
    '#options' => $active_languages,
    '#default_value' => variable_get('apachesolr_multilingual_languages', array()),
    '#description' => t('Choose languages to be handled by multilingual search. You can only choose from languges actived !link.', array('!link' => l(t('here'), 'admin/settings/language'))),
    '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
  );

  $form['apachesolr_multilingual_language_settings']['apachesolr_multilingual_map_language_neutral'] = array(
    '#type' => 'radios',
    '#title' => t('Map language neutral'),
    '#options' => array_merge(array(LANGUAGE_NONE => t('No mapping')), $active_languages),
    '#default_value' => variable_get('apachesolr_multilingual_map_language_neutral', ''),
    '#description' => t('Select a language to treat content marked as language neutral. Language neutral will not occur as an filter option any more.'),
    '#element_validate' => array('apachesolr_multilingual_admin_form_validate_reindex'),
  );

  $form['apachesolr_multilingual_language_settings']['apachesolr_multilingual_auto_language_filter'] = array(
    '#type' => 'checkbox',
    '#title' => t("Limit search to current language by default"),
    '#default_value' => variable_get('apachesolr_multilingual_auto_language_filter', 0),
    '#description' => t("Show only search results that match the current language."),
  );

  $form['apachesolr_multilingual_language_settings']['apachesolr_multilingual_auto_language_filter_detachable'] = array(
    '#type' => 'checkbox',
    '#title' => t("Limit search to current language could be disabled by user"),
    '#default_value' => variable_get('apachesolr_multilingual_auto_language_filter_detachable', 0),
    '#description' => t("Displays a checkbox on search result page to search over all languages if \"Limit search to current language by default\" is active."),
  );

  $form['apachesolr_multilingual_clir'] = array(
    '#type' => 'fieldset',
    '#title' => t('CLIR'),
    '#collapsible' => TRUE,
    '#description' => t('<b>Cross-language information retrieval</b>. As a special feature Apache Solr Multilingual could be configured to deal with the translations of nodes and taxonomies on multilingual sites. That means that you can find content in any language no matter which language was used to enter the search phrase.'),
  );

  $form['apachesolr_multilingual_clir']['apachesolr_multilingual_index_translations'] = array(
    '#type' => 'checkbox',
    '#title' => t('Index node translations'),
    '#default_value' => variable_get('apachesolr_multilingual_index_translations', 0),
    '#description' => t('All translations of a node will be added to the node itself.'),
    '#element_validate' => array('apachesolr_multilingual_admin_form_validate_reindex'),
  );

  $form['apachesolr_multilingual_clir']['apachesolr_multilingual_index_term_translations'] = array(
    '#type' => 'checkbox',
    '#title' => t('Index taxonomy term translations'),
    '#default_value' => variable_get('apachesolr_multilingual_index_term_translations', 0),
    '#description' => t('Index translations of the taxonomy terms. Requires module Taxonomy Translation which is part of !link.', array('!link' => l(t('Internationalization'), 'http://drupal.org/project/i18n'))),
    '#disabled' => !module_exists('i18ntaxonomy'),
    '#element_validate' => array('apachesolr_multilingual_admin_form_validate_reindex'),
  );

  $form['apachesolr_multilingual_advanced'] = array(
    '#type' => 'fieldset',
    '#title' => t('Advanced Language Settings'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description' => t('Advanced settings for every language. If there are missing some languages here you have to activate them above and save this form first.'),
  );

  foreach (array_keys($active_languages) as $language) {
    $form['apachesolr_multilingual_advanced'][$language] = array(
      '#type' => 'fieldset',
      '#title' => $active_languages[$language],
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('Language specific advanced settings.'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory'] = array(
      '#type' => 'fieldset',
      '#title' => 'WordDelimiterFilterFactory',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['index'] = array(
      '#type' => 'fieldset',
      '#title' => 'Index',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['index']['apachesolr_multilingual_advanced_splitOnCaseChange_index_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'splitOnCaseChange',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_splitOnCaseChange_index_' . $language, 1),
      '#description' => t('Causes lowercase => uppercase transitions to generate a new part.<br />"PowerShot" => "Power" "Shot"<br />"TransAM" => "Trans" "AM"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['index']['apachesolr_multilingual_advanced_splitOnNumerics_index_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'splitOnNumerics',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_splitOnNumerics_index_' . $language, 1),
      '#description' => t('Causes alphabet => number transitions to generate a new part.<br />"j2se" => "j" "2" "se"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['index']['apachesolr_multilingual_advanced_stemEnglishPossessive_index_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'stemEnglishPossessive',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_stemEnglishPossessive_index_' . $language, 1),
      '#description' => t('Causes trailing "\'s" to be removed for each subword.<br />"Doug\'s" => "Doug"<br />"O\'Neil\'s" -> "O", "Neil"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['index']['apachesolr_multilingual_advanced_generateWordParts_index_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'generateWordParts',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_generateWordParts_index_' . $language, 1),
      '#description' => t('Causes parts of words to be generated.<br />"PowerShot" => "Power" "Shot" (if splitOnCaseChange=1)<br />"Power-Shot" => "Power" "Shot"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['index']['apachesolr_multilingual_advanced_generateNumberParts_index_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'generateNumberParts',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_generateNumberParts_index_' . $language, 1),
      '#description' => t('Causes number subwords to be generated.<br />"500-42" => "500" "42"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['index']['apachesolr_multilingual_advanced_catenateWords_index_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'catenateWords',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateWords_index_' . $language, 1),
      '#description' => t('Causes maximum runs of word parts to be catenated.<br />"wi-fi" => "wifi"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['index']['apachesolr_multilingual_advanced_catenateNumbers_index_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'catenateNumbers',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateNumbers_index_' . $language, 1),
      '#description' => t('Causes maximum runs of number parts to be catenated.<br />"500-42" => "50042"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['index']['apachesolr_multilingual_advanced_catenateAll_index_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'catenateAll',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateAll_index_' . $language, 0),
      '#description' => t('Causes all subword parts to be catenated.<br />"wi-fi-4000" => "wifi4000"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['index']['apachesolr_multilingual_advanced_preserveOriginal_index_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'preserveOriginal',
      '#default_value' => variable_get('apachesolr_multilingual_preserveOriginal_index_' . $language, 1),
      '#description' => t('Causes the original token to be indexed without modifications (in addition to the tokens produced due to other options).'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['query'] = array(
      '#type' => 'fieldset',
      '#title' => 'Query',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_splitOnCaseChange_query_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'splitOnCaseChange',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_splitOnCaseChange_query_' . $language, 1),
      '#description' => t('Causes lowercase => uppercase transitions to generate a new part.<br />"PowerShot" => "Power" "Shot"<br />"TransAM" => "Trans" "AM"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_splitOnNumerics_query_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'splitOnNumerics',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_splitOnNumerics_query_' . $language, 1),
      '#description' => t('Causes alphabet => number transitions to generate a new part.<br />"j2se" => "j" "2" "se"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_stemEnglishPossessive_query_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'stemEnglishPossessive',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_stemEnglishPossessive_query_' . $language, 1),
      '#description' => t('Causes trailing "\'s" to be removed for each subword.<br />"Doug\'s" => "Doug"<br />"O\'Neil\'s" -> "O", "Neil"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_generateWordParts_query_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'generateWordParts',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_generateWordParts_query_' . $language, 1),
      '#description' => t('Causes parts of words to be generated.<br />"PowerShot" => "Power" "Shot" (if splitOnCaseChange=1)<br />"Power-Shot" => "Power" "Shot"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_generateNumberParts_query_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'generateNumberParts',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_generateNumberParts_query_' . $language, 1),
      '#description' => t('Causes number subwords to be generated.<br />"500-42" => "500" "42"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_catenateWords_query_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'catenateWords',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateWords_query_' . $language, 0),
      '#description' => t('Causes maximum runs of word parts to be catenated.<br />"wi-fi" => "wifi"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_catenateNumbers_query_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'catenateNumbers',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateNumbers_query_' . $language, 0),
      '#description' => t('Causes maximum runs of number parts to be catenated.<br />"500-42" => "50042"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_catenateAll_query_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'catenateAll',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateAll_query_' . $language, 0),
      '#description' => t('Causes all subword parts to be catenated.<br />"wi-fi-4000" => "wifi4000"'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_preserveOriginal_query_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'preserveOriginal',
      '#default_value' => variable_get('apachesolr_multilingual_preserveOriginal_query_' . $language, 0),
      '#description' => t('Causes the original token to be queried without modifications (in addition to the tokens produced due to other options).'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['StopFilterFactory'] = array(
      '#type' => 'fieldset',
      '#title' => 'StopFilterFactory',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['apachesolr_multilingual_advanced'][$language]['StopFilterFactory']['apachesolr_multilingual_advanced_ignoreCase_stopwords_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'ignoreCase',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_stopwords_' . $language, 1),
      '#description' => t('Causes to ignore the case of tokens when comparing to the stopword list.'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['SynonymFilterFactory'] = array(
      '#type' => 'fieldset',
      '#title' => 'SynonymFilterFactory',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['apachesolr_multilingual_advanced'][$language]['SynonymFilterFactory']['apachesolr_multilingual_advanced_ignoreCase_synonyms_' . $language] = array(
      '#type' => 'checkbox',
      '#title' => 'ignoreCase',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_ignoreCase_synonyms_' . $language, 1),
      '#description' => t('If ignoreCase is true, matching will lowercase before checking equality.'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['SpellLengthFilterFactory'] = array(
      '#type' => 'fieldset',
      '#title' => 'Spell LengthFilterFactory',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['apachesolr_multilingual_advanced'][$language]['SpellLengthFilterFactory']['apachesolr_multilingual_advanced_lengthMin_spell_' . $language] = array(
      '#type' => 'textfield',
      '#title' => 'min',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_lengthMin_spell_' . $language, 4),
      '#description' => t('Minimum number of characters to add a word to a spell checker.'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );

    $form['apachesolr_multilingual_advanced'][$language]['SpellLengthFilterFactory']['apachesolr_multilingual_advanced_lengthMax_spell_' . $language] = array(
      '#type' => 'textfield',
      '#title' => 'max',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_lengthMax_spell_' . $language, 20),
      '#description' => t('Maximum number of characters to add a word to a spell checker.'),
      '#element_validate' => array('apachesolr_multilingual_admin_form_validate_new_config'),
    );
  }

  return system_settings_form($form);
}


function apachesolr_multilingual_admin_form_validate($form, $form_state) {
  if (!empty($form_state['values']['apachesolr_multilingual_map_language_neutral']) && empty($form_state['values']['apachesolr_multilingual_languages'][$form_state['values']['apachesolr_multilingual_map_language_neutral']])) {
    form_set_error('apachesolr_multilingual_map_language_neutral', t('You need to map language neutral content to an activated language'));
  }

  $active_languages = locale_language_list();
  foreach (array_keys($active_languages) as $language) {
    $length_min = $form_state['values']['apachesolr_multilingual_advanced_lengthMin_spell_' . $language];
    $length_max = $form_state['values']['apachesolr_multilingual_advanced_lengthMax_spell_' . $language];

    if (!ctype_digit($length_min)) {
      form_set_error('apachesolr_multilingual_advanced_lengthMin_spell_' . $language, t('Minimum number of characters needs to be numeric.'));
    }

    if (!ctype_digit($length_max)) {
      form_set_error('apachesolr_multilingual_advanced_lengthMax_spell_' . $language, t('Maximum number of characters needs to be numeric.'));
    }

    if ($length_min < 0 || $length_min > $length_max) {
      form_set_error('apachesolr_multilingual_advanced_lengthMax_spell_' . $language, t('Minimum number of characters need to be less than maximum number of characters.'));
    }
  }
}

/**
 * Shows a hint to the user to use the new configurations
 *
 * @param $element
 * @param $form_state
 *
 * @return void
 */
function apachesolr_multilingual_admin_form_validate_new_config($element, $form_state) {
  static $msg_sent = FALSE;

  if (!$msg_sent) {
    if ($element['#value'] != $element['#default_value']) {
      drupal_set_message(
        t(
          'Changing "%title" requires you to !link_download a new set of configuration files, to update your solr configuration, to restart your solr server and to !link_delete the index.',
          array('%title' => $element['#title'], '!link_download' => l(t('download'), 'admin/config/search/apachesolr/schema_generator'), '!link_delete' => l(t('delete'), 'admin/config/search/apachesolr/index'))
        ),
        'warning'
      );
      $msg_sent = TRUE;
    }
  }
}

function apachesolr_multilingual_admin_form_validate_new_config_no_delete($element, $form_state) {
  static $msg_sent = FALSE;

  if (!$msg_sent) {
    if ($element['#value'] != $element['#default_value']) {
      drupal_set_message(
        t(
          'Changing "%title" requires you to !link_download a new set of configuration files, to update your solr configuration and to restart your solr server.',
          array('%title' => $element['#title'], '!link_download' => l(t('download'), 'admin/config/search/apachesolr/schema_generator'))
        ),
        'warning'
      );

      $msg_sent = TRUE;
    }
  }
}

/**
 * Shows a hint to the user that he has to reindex the content
 *
 * @param $element
 * @param $form_state
 *
 * @return void
 */
function apachesolr_multilingual_admin_form_validate_reindex($element, $form_state) {
  static $msg_sent = FALSE;

  if (!$msg_sent) {
    if ($element['#value'] != $element['#default_value']) {
      drupal_set_message(
        t(
          'Changing "%title" requires you to !link_reindex your content.',
          array('%title' => $element['#title'], '!link_reindex' => l(t('re-index'), 'admin/config/search/apachesolr/index'))
        ),
        'warning'
      );
      $msg_sent = TRUE;
    }
  }
}
