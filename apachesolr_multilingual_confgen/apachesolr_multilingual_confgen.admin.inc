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
function apachesolr_multilingual_confgen_admin_form($form_state) {
  $variable_language = variable_realm_params('language');

  $form['vt'] = array(
    '#type' => 'vertical_tabs',
  );

  $form['vt']['apachesolr_multilingual_stemmer_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Stemming'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $var_name = 'apachesolr_multilingual_stemmer_language';
  $variable_info = variable_get_info($var_name);
  $available_stemmers = array_values(apachesolr_multilingual_confgen_get_stemmer());
  $stemmer_options = array_combine($available_stemmers, array_map('t', $available_stemmers));
  asort($stemmer_options);
  $stemmer_options = array_merge(array('-none-' => t('(No stemming)')), $stemmer_options);
  $default = apachesolr_multilingual_confgen_variable_get($var_name, $variable_language);
  $form['vt']['apachesolr_multilingual_stemmer_settings'][$var_name] = array(
    '#type' => 'select',
    '#title' => check_plain($variable_info['title']),
    '#options' => $stemmer_options,
    '#default_value' => !empty($default) ? $default : apachesolr_multilingual_confgen_get_stemmer($variable_language),
    '#description' => filter_xss($variable_info['description']),
  );

  $form['vt']['apachesolr_multilingual_advanced'] = array(
    '#type' => 'fieldset',
    '#title' => t('Advanced Language Settings'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description' => t('Advanced settings for every language. If there are missing some languages here you have to activate them above and save this form first.'),
  );

  // language-specific stuff
  $form['vt']['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory'] = array(
    '#type' => 'fieldset',
    '#title' => 'WordDelimiterFilterFactory',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['vt']['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['index'] = array(
    '#type' => 'fieldset',
    '#title' => 'Index',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['vt']['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['query'] = array(
    '#type' => 'fieldset',
    '#title' => 'Query',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['vt']['apachesolr_multilingual_advanced']['LengthFilterFactory'] = array(
    '#type' => 'fieldset',
    '#title' => 'LengthFilterFactory',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['vt']['apachesolr_multilingual_advanced']['LengthFilterFactory']['index'] = array(
    '#type' => 'fieldset',
    '#title' => 'Index',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['vt']['apachesolr_multilingual_advanced']['LengthFilterFactory']['query'] = array(
    '#type' => 'fieldset',
    '#title' => 'Query',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  // language-specific parameters available for index and query
  $structure = array(
    'WordDelimiterFilterFactory' => array(
      'index' => array(
        'apachesolr_multilingual_splitOnCaseChange_index',
        'apachesolr_multilingual_splitOnNumerics_index',
        'apachesolr_multilingual_stemEnglishPossessive_index',
        'apachesolr_multilingual_generateWordParts_index',
        'apachesolr_multilingual_generateNumberParts_index',
        'apachesolr_multilingual_catenateWords_index',
        'apachesolr_multilingual_catenateNumbers_index',
        'apachesolr_multilingual_catenateAll_index',
        'apachesolr_multilingual_preserveOriginal_index',
      ),
      'query' => array(
        'apachesolr_multilingual_splitOnCaseChange_query',
        'apachesolr_multilingual_splitOnNumerics_query',
        'apachesolr_multilingual_stemEnglishPossessive_query',
        'apachesolr_multilingual_generateWordParts_query',
        'apachesolr_multilingual_generateNumberParts_query',
        'apachesolr_multilingual_catenateWords_query',
        'apachesolr_multilingual_catenateNumbers_query',
        'apachesolr_multilingual_catenateAll_query',
        'apachesolr_multilingual_preserveOriginal_query',
        ),

    ),
    'LengthFilterFactory' => array(
      'index' => array(
        'apachesolr_multilingual_min_index',
        'apachesolr_multilingual_max_index',
      ),
      'query' => array(
        'apachesolr_multilingual_min_query',
        'apachesolr_multilingual_max_query',
      ),
    )
  );

  foreach ($structure as $component => $arr) {
    foreach ($arr as $type => $var_names) {
      foreach ($var_names as $var_name) {
        // example: $form['vt']['apachesolr_multilingual_advanced']['WordDelimiterFilterFactory']['index']['apachesolr_multilingual_splitOnCaseChange_index']
        $form['vt']['apachesolr_multilingual_advanced'][$component][$type][$var_name] = apachesolr_multilingual_variable_definition_to_form_element($var_name);
      }
    }
  }


  $form['vt']['apachesolr_multilingual_advanced']['StopFilterFactory'] = array(
    '#type' => 'fieldset',
    '#title' => 'StopFilterFactory',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $var_name = 'apachesolr_multilingual_ignoreCase_stopwords';
  $form['vt']['apachesolr_multilingual_advanced']['StopFilterFactory'][$var_name] = apachesolr_multilingual_variable_definition_to_form_element($var_name);


  $form['vt']['apachesolr_multilingual_advanced']['SynonymFilterFactory'] = array(
    '#type' => 'fieldset',
    '#title' => 'SynonymFilterFactory',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $var_name = 'apachesolr_multilingual_ignoreCase_synonyms';
  $form['vt']['apachesolr_multilingual_advanced']['SynonymFilterFactory'][$var_name] = apachesolr_multilingual_variable_definition_to_form_element($var_name);


  $form['vt']['apachesolr_multilingual_advanced']['SpellLengthFilterFactory'] = array(
    '#type' => 'fieldset',
    '#title' => 'Spell LengthFilterFactory',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $var_name = 'apachesolr_multilingual_lengthMin_spell';
  $form['vt']['apachesolr_multilingual_advanced']['SpellLengthFilterFactory'][$var_name] = apachesolr_multilingual_variable_definition_to_form_element($var_name);

  $var_name = 'apachesolr_multilingual_lengthMax_spell';
  $form['vt']['apachesolr_multilingual_advanced']['SpellLengthFilterFactory'][$var_name] = apachesolr_multilingual_variable_definition_to_form_element($var_name);


  $form['vt']['apachesolr_multilingual_advanced']['DictionaryCompoundWordTokenFilterFactory'] = array(
    '#type' => 'fieldset',
    '#title' => 'DictionaryCompoundWordTokenFilterFactory',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $var_name = 'apachesolr_multilingual_minWordSize';
  $form['vt']['apachesolr_multilingual_advanced']['DictionaryCompoundWordTokenFilterFactory'][$var_name] = apachesolr_multilingual_variable_definition_to_form_element($var_name);

  $var_name = 'apachesolr_multilingual_minSubwordSize';
  $form['vt']['apachesolr_multilingual_advanced']['DictionaryCompoundWordTokenFilterFactory'][$var_name] = apachesolr_multilingual_variable_definition_to_form_element($var_name);

  $var_name = 'apachesolr_multilingual_maxSubwordSize';
  $form['vt']['apachesolr_multilingual_advanced']['DictionaryCompoundWordTokenFilterFactory'][$var_name] = apachesolr_multilingual_variable_definition_to_form_element($var_name);

  $var_name = 'apachesolr_multilingual_onlyLongestMatch';
  $form['vt']['apachesolr_multilingual_advanced']['DictionaryCompoundWordTokenFilterFactory'][$var_name] = apachesolr_multilingual_variable_definition_to_form_element($var_name);

  return system_settings_form($form);
}


function apachesolr_multilingual_confgen_admin_form_validate($form, $form_state) {
  $length_min = $form_state['values']['apachesolr_multilingual_lengthMin_spell'];
  $length_max = $form_state['values']['apachesolr_multilingual_lengthMax_spell'];

  if (!ctype_digit($length_min)) {
    form_set_error('apachesolr_multilingual_lengthMin_spell', t('Minimum number of characters needs to be numeric.'));
  }

  if (!ctype_digit($length_max)) {
    form_set_error('apachesolr_multilingual_lengthMax_spell', t('Maximum number of characters needs to be numeric.'));
  }

  if ($length_min < 0 || $length_min > $length_max) {
    form_set_error('apachesolr_multilingual_lengthMax_spell', t('Minimum number of characters need to be less than maximum number of characters.'));
  }
}
