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
 * Implemtens hook_form().
 *
 * @param $form_state
 * @return void
 */
function apachesolr_multilingual_admin_form($form_state) {
  $active_languages = locale_language_list();
  $custom_stemmer_languages = array();
  $stemmer = array_unique(apachesolr_multilingual_get_stemmer());
  sort($stemmer);

  foreach ($active_languages as $id => $language) {
    if (!apachesolr_multilingual_get_stemmer($id)) {
      $active_languages[$id] .= ' ' . t('(without stemming)');
      $custom_stemmer_languages[$id] = $language;
    }
    elseif (variable_get('apachesolr_multilingual_stemmer_' .  $id, '') != '') {
      $custom_stemmer_languages[$id] = $language;
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
    '#description' => t('Choose languages to be handled by multilingual search. You can only choose from languages activated !link.', array('!link' => l(t('here'), 'admin/config/regional/language'))),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_language_settings']['apachesolr_multilingual_map_language_neutral'] = array(
    '#type' => 'radios',
    '#title' => t('Map language neutral'),
    '#options' => array_merge(array(LANGUAGE_NONE => t('No mapping')), $active_languages),
    '#default_value' => variable_get('apachesolr_multilingual_map_language_neutral', ''),
    '#description' => t('Select a language to treat content marked as language neutral. Language neutral will not occur as filter option any more.'),
    '#element_validate' => array('apachesolr_confgen_validate_reindex'),
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

  if (!empty($custom_stemmer_languages)) {
    $form['apachesolr_multilingual_stemmer_settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('Stemmer'),
      '#collapsible' => TRUE,
      '#description' => t('Manually assign available stemmers to languages, where the stemmer auto-detection failed.'),
    );

    foreach ($custom_stemmer_languages as $id => $language) {
      $form['apachesolr_multilingual_stemmer_settings']['apachesolr_multilingual_stemmer_' .  $id] = array(
        '#type' => 'select',
        '#title' => $language,
        '#options' => array('' => t('No Stemming')) + array_combine($stemmer, $stemmer),
        '#default_value' => apachesolr_multilingual_get_stemmer($id) ? apachesolr_multilingual_get_stemmer($id) : '',
        '#description' => t('Select the stemmer.'),
      );
    }
  }

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
    '#element_validate' => array('apachesolr_confgen_validate_reindex'),
  );

  $form['apachesolr_multilingual_clir']['apachesolr_multilingual_index_term_translations'] = array(
    '#type' => 'checkbox',
    '#title' => t('Index taxonomy term translations'),
    '#default_value' => variable_get('apachesolr_multilingual_index_term_translations', 0),
    '#description' => t('Index translations of the taxonomy terms. Requires module Taxonomy Translation which is part of !link.', array('!link' => l(t('Internationalization'), 'http://drupal.org/project/i18n'))),
    '#disabled' => !module_exists('i18ntaxonomy'),
    '#element_validate' => array('apachesolr_confgen_validate_reindex'),
  );

  $form['apachesolr_multilingual_advanced'] = array(
    '#type' => 'fieldset',
    '#title' => t('Advanced Language Settings'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description' => t('Advanced settings for every language. If there are missing some languages here you have to activate them above and save this form first.'),
  );

  // language specific stuff
  $form['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory'] = array(
    '#type' => 'fieldset',
    '#title' => 'WordDelimiterFilterFactory',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['index'] = array(
    '#type' => 'fieldset',
    '#title' => 'Index',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $structure = array(
    'WordDelimiterFilterFactory' => array(
      'index' => array(
        'apachesolr_multilingual_advanced_splitOnCaseChange_index',
        'apachesolr_multilingual_advanced_splitOnNumerics_index',
        'apachesolr_multilingual_advanced_stemEnglishPossessive_index',
        'apachesolr_multilingual_advanced_generateWordParts_index',
        'apachesolr_multilingual_advanced_generateNumberParts_index',
        'apachesolr_multilingual_advanced_catenateWords_index',
        'apachesolr_multilingual_advanced_catenateNumbers_index',
        'apachesolr_multilingual_advanced_catenateAll_index',
'apachesolr_multilingual_advanced_preserveOriginal_index',

          ),
    ),
  );

  foreach ($structure as $component => $arr) {
    foreach ($arr as $type => $var_name) {
      // example: $form['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['index']['apachesolr_multilingual_advanced_splitOnCaseChange_index']
      $form['apachesolr_multilingual_advanced'][$component][$type][$var_name] = _apachesolr_multilingual_variable_definition_to_form_element($name);
    }
  }


  // 'apachesolr_multilingual_advanced_splitOnCaseChange_index';
  //    '#element_validate' => array('apachesolr_confgen_validate_new_config'),

  // 'apachesolr_multilingual_advanced_splitOnNumerics_index';
//    '#element_validate' => array('apachesolr_confgen_validate_new_config'),

  //$name = 'apachesolr_multilingual_advanced_stemEnglishPossessive_index';
//    '#element_validate' => array('apachesolr_confgen_validate_new_config'),

//  $name = 'apachesolr_multilingual_advanced_generateWordParts_index';
//    '#element_validate' => array('apachesolr_confgen_validate_new_config'),

  //'apachesolr_multilingual_advanced_generateNumberParts_index'] = array(
    //'#element_validate' => array('apachesolr_confgen_validate_new_config'),


 // 'apachesolr_multilingual_advanced_catenateWords_index'
 //   '#element_validate' => array('apachesolr_confgen_validate_new_config'),

  //'apachesolr_multilingual_advanced_catenateNumbers_index'
//    '#element_validate' => array('apachesolr_confgen_validate_new_config'),

  //'apachesolr_multilingual_advanced_catenateAll_index'] = array(
  //  '#element_validate' => array('apachesolr_confgen_validate_new_config'),

 //'apachesolr_multilingual_advanced_preserveOriginal_index'] = array(
//    '#element_validate' => array('apachesolr_confgen_validate_new_config'),

  $form['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_splitOnCaseChange_query'] = array(
    '#type' => 'checkbox',
    '#title' => 'splitOnCaseChange',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_splitOnCaseChange_query', 1),
    '#description' => t('Causes lowercase => uppercase transitions to generate a new part.<br />"PowerShot" => "Power" "Shot"<br />"TransAM" => "Trans" "AM"'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_splitOnNumerics_query'] = array(
    '#type' => 'checkbox',
    '#title' => 'splitOnNumerics',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_splitOnNumerics_query', 1),
    '#description' => t('Causes alphabet => number transitions to generate a new part.<br />"j2se" => "j" "2" "se"'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_stemEnglishPossessive_query'] = array(
    '#type' => 'checkbox',
    '#title' => 'stemEnglishPossessive',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_stemEnglishPossessive_query', 1),
    '#description' => t('Causes trailing "\'s" to be removed for each subword.<br />"Doug\'s" => "Doug"<br />"O\'Neil\'s" -> "O", "Neil"'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_generateWordParts_query'] = array(
    '#type' => 'checkbox',
    '#title' => 'generateWordParts',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_generateWordParts_query', 1),
    '#description' => t('Causes parts of words to be generated.<br />"PowerShot" => "Power" "Shot" (if splitOnCaseChange=1)<br />"Power-Shot" => "Power" "Shot"'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_generateNumberParts_query'] = array(
    '#type' => 'checkbox',
    '#title' => 'generateNumberParts',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_generateNumberParts_query', 1),
    '#description' => t('Causes number subwords to be generated.<br />"500-42" => "500" "42"'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_catenateWords_query'] = array(
    '#type' => 'checkbox',
    '#title' => 'catenateWords',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateWords_query', 0),
    '#description' => t('Causes maximum runs of word parts to be catenated.<br />"wi-fi" => "wifi"'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_catenateNumbers_query'] = array(
    '#type' => 'checkbox',
    '#title' => 'catenateNumbers',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateNumbers_query', 0),
    '#description' => t('Causes maximum runs of number parts to be catenated.<br />"500-42" => "50042"'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_catenateAll_query'] = array(
    '#type' => 'checkbox',
    '#title' => 'catenateAll',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateAll_query', 0),
    '#description' => t('Causes all subword parts to be catenated.<br />"wi-fi-4000" => "wifi4000"'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_preserveOriginal_query'] = array(
    '#type' => 'checkbox',
    '#title' => 'preserveOriginal',
    '#default_value' => variable_get('apachesolr_multilingual_preserveOriginal_query', 0),
    '#description' => t('Causes the original token to be queried without modifications (in addition to the tokens produced due to other options).'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['LengthFilterFactory'] = array(
    '#type' => 'fieldset',
    '#title' => 'LengthFilterFactory',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['apachesolr_multilingual_advanced']['LengthFilterFactory']['index'] = array(
    '#type' => 'fieldset',
    '#title' => 'Index',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['apachesolr_multilingual_advanced']['LengthFilterFactory']['index']['apachesolr_multilingual_advanced_min_index'] = array(
    '#type' => 'textfield',
    '#title' => 'min',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_min_index', 2),
    '#description' => t('TODO'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['LengthFilterFactory']['index']['apachesolr_multilingual_advanced_max_index'] = array(
    '#type' => 'textfield',
    '#title' => 'max',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_max_index', 100),
    '#description' => t('TODO'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['LengthFilterFactory']['query'] = array(
    '#type' => 'fieldset',
    '#title' => 'Qery',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['apachesolr_multilingual_advanced']['LengthFilterFactory']['query']['apachesolr_multilingual_advanced_min_query'] = array(
    '#type' => 'textfield',
    '#title' => 'min',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_min_query', 2),
    '#description' => t('TODO'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['LengthFilterFactory']['query']['apachesolr_multilingual_advanced_max_query'] = array(
    '#type' => 'textfield',
    '#title' => 'max',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_max_query', 100),
    '#description' => t('TODO'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['StopFilterFactory'] = array(
    '#type' => 'fieldset',
    '#title' => 'StopFilterFactory',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['apachesolr_multilingual_advanced']['StopFilterFactory']['apachesolr_multilingual_advanced_ignoreCase_stopwords'] = array(
    '#type' => 'checkbox',
    '#title' => 'ignoreCase',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_stopwords', 1),
    '#description' => t('Causes to ignore the case of tokens when comparing to the stopword list.'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['SynonymFilterFactory'] = array(
    '#type' => 'fieldset',
    '#title' => 'SynonymFilterFactory',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['apachesolr_multilingual_advanced']['SynonymFilterFactory']['apachesolr_multilingual_advanced_ignoreCase_synonyms'] = array(
    '#type' => 'checkbox',
    '#title' => 'ignoreCase',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_ignoreCase_synonyms', 1),
    '#description' => t('If ignoreCase is true, matching will lowercase before checking equality.'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['SpellLengthFilterFactory'] = array(
    '#type' => 'fieldset',
    '#title' => 'Spell LengthFilterFactory',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['apachesolr_multilingual_advanced']['SpellLengthFilterFactory']['apachesolr_multilingual_advanced_lengthMin_spell'] = array(
    '#type' => 'textfield',
    '#title' => 'min',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_lengthMin_spell', 4),
    '#description' => t('Minimum number of characters to add a word to a spell checker.'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $form['apachesolr_multilingual_advanced']['SpellLengthFilterFactory']['apachesolr_multilingual_advanced_lengthMax_spell'] = array(
    '#type' => 'textfield',
    '#title' => 'max',
    '#default_value' => variable_get('apachesolr_multilingual_advanced_lengthMax_spell', 20),
    '#description' => t('Maximum number of characters to add a word to a spell checker.'),
    '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  return system_settings_form($form);
}


function apachesolr_multilingual_admin_form_validate($form, $form_state) {
  if (!empty($form_state['values']['apachesolr_multilingual_map_language_neutral']) && empty($form_state['values']['apachesolr_multilingual_languages'][$form_state['values']['apachesolr_multilingual_map_language_neutral']])) {
    form_set_error('apachesolr_multilingual_map_language_neutral', t('You need to map language neutral content to an activated language'));
  }

  $active_languages = locale_language_list();
  foreach (array_keys($active_languages) as $language) {
    $length_min = $form_state['values']['apachesolr_multilingual_advanced_lengthMin_spell'];
    $length_max = $form_state['values']['apachesolr_multilingual_advanced_lengthMax_spell'];

    if (!ctype_digit($length_min)) {
      form_set_error('apachesolr_multilingual_advanced_lengthMin_spell', t('Minimum number of characters needs to be numeric.'));
    }

    if (!ctype_digit($length_max)) {
      form_set_error('apachesolr_multilingual_advanced_lengthMax_spell', t('Maximum number of characters needs to be numeric.'));
    }

    if ($length_min < 0 || $length_min > $length_max) {
      form_set_error('apachesolr_multilingual_advanced_lengthMax_spell', t('Minimum number of characters need to be less than maximum number of characters.'));
    }
  }
}


/**
 * A shorthand for variable_get_info and variable_form_element
 *
 * @param string $name
 * @return Array The generated form element
 */
function _apachesolr_multilingual_variable_definition_to_form_element($name) {
  module_load_include('form.inc', 'variable');
  $variable = variable_get_info($name, $options);
  return variable_form_element($variable, $options);
}


