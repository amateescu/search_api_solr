<?php

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
  $variables['apachesolr_multilingual_advanced_splitOnCaseChange_index'] = array(
    'type' => 'boolean',
    'title' => t('splitOnCaseChange', array(), $options),
    'default' => TRUE,
    'description' => t('Causes lowercase => uppercase transitions to generate a new part.<br />"PowerShot" => "Power" "Shot"<br />"TransAM" => "Trans" "AM"', array(), $options),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_splitOnNumerics_index'] = array(
    'type' => 'boolean',
    'title' => t('splitOnNumerics', array(), $options),
    'default' => TRUE,
    'description' => t('Causes alphabet => number transitions to generate a new part.<br />"j2se" => "j" "2" "se"', array(), $options),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_stemEnglishPossessive_index'] = array(
      '#type' => 'checkbox',
      '#title' => 'stemEnglishPossessive',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_stemEnglishPossessive_index', 1),
      '#description' => t('Causes trailing "\'s" to be removed for each subword.<br />"Doug\'s" => "Doug"<br />"O\'Neil\'s" -> "O", "Neil"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['apachesolr_multilingual_advanced_generateWordParts_index'] = array(
      '#type' => 'checkbox',
      '#title' => 'generateWordParts',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_generateWordParts_index', 1),
      '#description' => t('Causes parts of words to be generated.<br />"PowerShot" => "Power" "Shot" (if splitOnCaseChange=1)<br />"Power-Shot" => "Power" "Shot"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['apachesolr_multilingual_advanced_generateNumberParts_index'] = array(
      '#type' => 'checkbox',
      '#title' => 'generateNumberParts',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_generateNumberParts_index', 1),
      '#description' => t('Causes number subwords to be generated.<br />"500-42" => "500" "42"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['apachesolr_multilingual_advanced_catenateWords_index'] = array(
      '#type' => 'checkbox',
      '#title' => 'catenateWords',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateWords_index', 1),
      '#description' => t('Causes maximum runs of word parts to be catenated.<br />"wi-fi" => "wifi"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['apachesolr_multilingual_advanced_catenateNumbers_index'] = array(
      '#type' => 'checkbox',
      '#title' => 'catenateNumbers',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateNumbers_index', 1),
      '#description' => t('Causes maximum runs of number parts to be catenated.<br />"500-42" => "50042"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['apachesolr_multilingual_advanced_catenateAll_index'] = array(
      '#type' => 'checkbox',
      '#title' => 'catenateAll',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateAll_index', 0),
      '#description' => t('Causes all subword parts to be catenated.<br />"wi-fi-4000" => "wifi4000"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['apachesolr_multilingual_advanced_preserveOriginal_index'] = array(
      '#type' => 'checkbox',
      '#title' => 'preserveOriginal',
      '#default_value' => variable_get('apachesolr_multilingual_preserveOriginal_index', 1),
      '#description' => t('Causes the original token to be indexed without modifications (in addition to the tokens produced due to other options).'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );



  $variables['WordDelimiterFilterFactory']['query'] = array(
      '#type' => 'fieldset',
      '#title' => 'Query',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
  );

  $variables['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_splitOnCaseChange_query'] = array(
      '#type' => 'checkbox',
      '#title' => 'splitOnCaseChange',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_splitOnCaseChange_query', 1),
      '#description' => t('Causes lowercase => uppercase transitions to generate a new part.<br />"PowerShot" => "Power" "Shot"<br />"TransAM" => "Trans" "AM"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_splitOnNumerics_query'] = array(
      '#type' => 'checkbox',
      '#title' => 'splitOnNumerics',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_splitOnNumerics_query', 1),
      '#description' => t('Causes alphabet => number transitions to generate a new part.<br />"j2se" => "j" "2" "se"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_stemEnglishPossessive_query'] = array(
      '#type' => 'checkbox',
      '#title' => 'stemEnglishPossessive',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_stemEnglishPossessive_query', 1),
      '#description' => t('Causes trailing "\'s" to be removed for each subword.<br />"Doug\'s" => "Doug"<br />"O\'Neil\'s" -> "O", "Neil"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_generateWordParts_query'] = array(
      '#type' => 'checkbox',
      '#title' => 'generateWordParts',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_generateWordParts_query', 1),
      '#description' => t('Causes parts of words to be generated.<br />"PowerShot" => "Power" "Shot" (if splitOnCaseChange=1)<br />"Power-Shot" => "Power" "Shot"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_generateNumberParts_query'] = array(
      '#type' => 'checkbox',
      '#title' => 'generateNumberParts',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_generateNumberParts_query', 1),
      '#description' => t('Causes number subwords to be generated.<br />"500-42" => "500" "42"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_catenateWords_query'] = array(
      '#type' => 'checkbox',
      '#title' => 'catenateWords',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateWords_query', 0),
      '#description' => t('Causes maximum runs of word parts to be catenated.<br />"wi-fi" => "wifi"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_catenateNumbers_query'] = array(
      '#type' => 'checkbox',
      '#title' => 'catenateNumbers',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateNumbers_query', 0),
      '#description' => t('Causes maximum runs of number parts to be catenated.<br />"500-42" => "50042"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_catenateAll_query'] = array(
      '#type' => 'checkbox',
      '#title' => 'catenateAll',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_catenateAll_query', 0),
      '#description' => t('Causes all subword parts to be catenated.<br />"wi-fi-4000" => "wifi4000"'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['WordDelimiterFilterFactory']['query']['apachesolr_multilingual_advanced_preserveOriginal_query'] = array(
      '#type' => 'checkbox',
      '#title' => 'preserveOriginal',
      '#default_value' => variable_get('apachesolr_multilingual_preserveOriginal_query', 0),
      '#description' => t('Causes the original token to be queried without modifications (in addition to the tokens produced due to other options).'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['LengthFilterFactory'] = array(
      '#type' => 'fieldset',
      '#title' => 'LengthFilterFactory',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
  );

  $variables['LengthFilterFactory']['index'] = array(
      '#type' => 'fieldset',
      '#title' => 'Index',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
  );

  $variables['LengthFilterFactory']['index']['apachesolr_multilingual_advanced_min_index'] = array(
      '#type' => 'textfield',
      '#title' => 'min',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_min_index', 2),
      '#description' => t('TODO'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['LengthFilterFactory']['index']['apachesolr_multilingual_advanced_max_index'] = array(
      '#type' => 'textfield',
      '#title' => 'max',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_max_index', 100),
      '#description' => t('TODO'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['LengthFilterFactory']['query'] = array(
      '#type' => 'fieldset',
      '#title' => 'Qery',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
  );

  $variables['LengthFilterFactory']['query']['apachesolr_multilingual_advanced_min_query'] = array(
      '#type' => 'textfield',
      '#title' => 'min',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_min_query', 2),
      '#description' => t('TODO'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['LengthFilterFactory']['query']['apachesolr_multilingual_advanced_max_query'] = array(
      '#type' => 'textfield',
      '#title' => 'max',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_max_query', 100),
      '#description' => t('TODO'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['StopFilterFactory'] = array(
      '#type' => 'fieldset',
      '#title' => 'StopFilterFactory',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
  );

  $variables['StopFilterFactory']['apachesolr_multilingual_advanced_ignoreCase_stopwords'] = array(
      '#type' => 'checkbox',
      '#title' => 'ignoreCase',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_stopwords', 1),
      '#description' => t('Causes to ignore the case of tokens when comparing to the stopword list.'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['SynonymFilterFactory'] = array(
      '#type' => 'fieldset',
      '#title' => 'SynonymFilterFactory',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
  );

  $variables['SynonymFilterFactory']['apachesolr_multilingual_advanced_ignoreCase_synonyms'] = array(
      '#type' => 'checkbox',
      '#title' => 'ignoreCase',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_ignoreCase_synonyms', 1),
      '#description' => t('If ignoreCase is true, matching will lowercase before checking equality.'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['SpellLengthFilterFactory'] = array(
      '#type' => 'fieldset',
      '#title' => 'Spell LengthFilterFactory',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
  );

  $variables['SpellLengthFilterFactory']['apachesolr_multilingual_advanced_lengthMin_spell'] = array(
      '#type' => 'textfield',
      '#title' => 'min',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_lengthMin_spell', 4),
      '#description' => t('Minimum number of characters to add a word to a spell checker.'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  $variables['SpellLengthFilterFactory']['apachesolr_multilingual_advanced_lengthMax_spell'] = array(
      '#type' => 'textfield',
      '#title' => 'max',
      '#default_value' => variable_get('apachesolr_multilingual_advanced_lengthMax_spell', 20),
      '#description' => t('Maximum number of characters to add a word to a spell checker.'),
      '#element_validate' => array('apachesolr_confgen_validate_new_config'),
  );

  return $variables;
}
