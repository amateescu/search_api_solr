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

  $variables['apachesolr_multilingual_stemmer_language'] = array(
    'type' => 'string',
    'title' => t('Stemmer Language'),
    'default' => '',
    'description' => t('Please select the language to be used by the Porter stemmer.'),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_splitOnCaseChange_query'] =
  $variables['apachesolr_multilingual_advanced_splitOnCaseChange_index'] = array(
    'type' => 'boolean',
    'title' => t('splitOnCaseChange', array(), $options),
    'default' => TRUE,
    'description' => t('Causes lowercase => uppercase transitions to generate a new part.<br />"PowerShot" => "Power" "Shot"<br />"TransAM" => "Trans" "AM"', array(), $options),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_splitOnNumerics_query'] =
  $variables['apachesolr_multilingual_advanced_splitOnNumerics_index'] = array(
    'type' => 'boolean',
    'title' => t('splitOnNumerics', array(), $options),
    'default' => TRUE,
    'description' => t('Causes alphabet => number transitions to generate a new part.<br />"j2se" => "j" "2" "se"', array(), $options),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_stemEnglishPossessive_query'] =
  $variables['apachesolr_multilingual_advanced_stemEnglishPossessive_index'] = array(
    'type' => 'boolean',
    'title' => t('stemEnglishPossessive', array(), $options),
    'default' => TRUE,
    'description' => t('Causes trailing "\'s" to be removed for each subword.<br />"Doug\'s" => "Doug"<br />"O\'Neil\'s" -> "O", "Neil"'),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_generateWordParts_query'] =
  $variables['apachesolr_multilingual_advanced_generateWordParts_index'] = array(
    'type' => 'boolean',
    'title' => t('generateWordParts', array(), $options),
    'default' => TRUE,
    'description' => t('Causes parts of words to be generated.<br />"PowerShot" => "Power" "Shot" (if splitOnCaseChange=1)<br />"Power-Shot" => "Power" "Shot"'),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_generateNumberParts_query'] =
  $variables['apachesolr_multilingual_advanced_generateNumberParts_index'] = array(
    'type' => 'boolean',
    'title' => t('generateNumberParts', array(), $options),
    'default' => TRUE,
    'description' => t('Causes number subwords to be generated.<br />"500-42" => "500" "42"'),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_catenateWords_query'] =
  $variables['apachesolr_multilingual_advanced_catenateWords_index'] = array(
    'type' => 'boolean',
    'title' => t('catenateWords', array(), $options),
    'default' => TRUE,
    'description' => t('Causes maximum runs of word parts to be catenated.<br />"wi-fi" => "wifi"'),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_catenateNumbers_query'] =
  $variables['apachesolr_multilingual_advanced_catenateNumbers_index'] = array(
    'type' => 'boolean',
    'title' => t('catenateNumbers', array(), $options),
    'default' => TRUE,
    'description' => t('Causes maximum runs of number parts to be catenated.<br />"500-42" => "50042"'),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_catenateAll_query'] =
  $variables['apachesolr_multilingual_advanced_catenateAll_index'] = array(
    'type' => 'boolean',
    'title' => t('catenateAll', array(), $options),
    'default' => TRUE,
    'description' => t('Causes all subword parts to be catenated.<br />"wi-fi-4000" => "wifi4000"'),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_preserveOriginal_query'] =
  $variables['apachesolr_multilingual_advanced_preserveOriginal_index'] = array(
    'type' => 'boolean',
    'title' => t('preserveOriginal', array(), $options),
    'default_value' => TRUE,
    'description' => t('Causes the original token to be indexed without modifications (in addition to the tokens produced due to other options).'),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_catenateWords_query']['default_value'] = FALSE;
  $variables['apachesolr_multilingual_advanced_catenateNumbers_query']['default_value'] = FALSE;
  $variables['apachesolr_multilingual_advanced_catenateAll_query']['default_value'] = FALSE;
  $variables['apachesolr_multilingual_advanced_preserveOriginal_query']['default_value'] = FALSE;



  $variables['apachesolr_multilingual_advanced_min_query'] =
  $variables['apachesolr_multilingual_advanced_min_index'] = array(
    'type' => 'string',
    'title' => t('min', array(), $options),
    'default' => '2',
    'description' => t('TODO'),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_max_query'] =
  $variables['apachesolr_multilingual_advanced_max_index'] = array(
    'type' => 'string',
    'title' => t('max', array(), $options),
    'default' => '100',
    'description' => t('TODO'),
    'group' => 'apachesolr_multilingual',
  );



  $variables['apachesolr_multilingual_advanced_ignoreCase_stopwords'] = array(
    'type' => 'boolean',
    'title' => t('ignoreCase', array(), $options),
    'default' => TRUE,
    'description' => t('Causes Solr to ignore the case of tokens when comparing to the stopword list.'),
    'group' => 'apachesolr_multilingual',
  );



  $variables['apachesolr_multilingual_advanced_ignoreCase_synonyms'] = array(
    'type' => 'boolean',
    'title' => t('ignoreCase', array(), $options),
    'default' => TRUE,
    'description' => t('If ignoreCase is true, matching will lowercase before checking equality.'),
    'group' => 'apachesolr_multilingual',
  );


  $variables['apachesolr_multilingual_advanced_lengthMin_spell'] = array(
    'type' => 'string',
    'title' => t('min', array(), $options),
    'default' => '4',
    'description' => t('Minimum number of characters to add a word to a spell checker.'),
    'group' => 'apachesolr_multilingual',
  );

  $variables['apachesolr_multilingual_advanced_lengthMax_spell'] = array(
    'type' => 'string',
    'title' => t('max', array(), $options),
    'default' => '20',
    'description' => t('Maximum number of characters to add a word to a spell checker.'),
    'group' => 'apachesolr_multilingual',
  );

  return $variables;
}
