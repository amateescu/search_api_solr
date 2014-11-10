<?php

/**
 * @file
 *  TODO
 */

/**
 * Implements hook_variable_group_info().
 */
function apachesolr_multilingual_confgen_variable_group_info() {
  $groups['apachesolr_multilingual_confgen'] = array(
    'title' => t('Apache Solr Multilingual Config Generator'),
    'description' => t('Fine tune multilingual and non-English indexing with Apache Solr'),
    'access' => 'administer search',
    'path' => array('admin/config/search/apachesolr/confgen/multilingual'),
  );

  return $groups;
}

/**
 * Implements hook_variable_info().
 */
function apachesolr_multilingual_confgen_variable_info($options) {

  $variables['apachesolr_multilingual_stemmer_language'] = array(
    'type' => 'string',
    'title' => t('Stemmer Language'),
    'default' => '',
    'description' => t('Please select the language to be used by the Porter stemmer.'),
    'group' => 'apachesolr_multilingual_confgen',
    // TODO
    // 'solr_version_dependent' => TRUE,
  );

  $variables['apachesolr_multilingual_splitOnCaseChange_spell'] =
  $variables['apachesolr_multilingual_splitOnCaseChange_query'] =
  $variables['apachesolr_multilingual_splitOnCaseChange_index'] = array(
    'type' => 'boolean',
    'solr_format' => 'numeric',
    'title' => t('splitOnCaseChange', array(), $options),
    'default' => TRUE,
    'description' => t('Causes lowercase => uppercase transitions to generate a new part.<br />"PowerShot" => "Power" "Shot"<br />"TransAM" => "Trans" "AM"', array(), $options),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_splitOnNumerics_spell'] =
  $variables['apachesolr_multilingual_splitOnNumerics_query'] =
  $variables['apachesolr_multilingual_splitOnNumerics_index'] = array(
    'type' => 'boolean',
    'solr_format' => 'numeric',
    'title' => t('splitOnNumerics', array(), $options),
    'default' => TRUE,
    'description' => t('Causes alphabet => number transitions to generate a new part.<br />"j2se" => "j" "2" "se"', array(), $options),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_stemEnglishPossessive_spell'] =
  $variables['apachesolr_multilingual_stemEnglishPossessive_query'] =
  $variables['apachesolr_multilingual_stemEnglishPossessive_index'] = array(
    'type' => 'boolean',
    'solr_format' => 'numeric',
    'title' => t('stemEnglishPossessive', array(), $options),
    'default' => TRUE,
    'description' => t('Causes trailing "\'s" to be removed for each subword.<br />"Doug\'s" => "Doug"<br />"O\'Neil\'s" -> "O", "Neil"'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_generateWordParts_spell'] =
  $variables['apachesolr_multilingual_generateWordParts_query'] =
  $variables['apachesolr_multilingual_generateWordParts_index'] = array(
    'type' => 'boolean',
    'solr_format' => 'numeric',
    'title' => t('generateWordParts', array(), $options),
    'default' => TRUE,
    'description' => t('Causes parts of words to be generated.<br />"PowerShot" => "Power" "Shot" (if splitOnCaseChange=1)<br />"Power-Shot" => "Power" "Shot"'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_generateNumberParts_spell'] =
  $variables['apachesolr_multilingual_generateNumberParts_query'] =
  $variables['apachesolr_multilingual_generateNumberParts_index'] = array(
    'type' => 'boolean',
    'solr_format' => 'numeric',
    'title' => t('generateNumberParts', array(), $options),
    'default' => TRUE,
    'description' => t('Causes number subwords to be generated.<br />"500-42" => "500" "42"'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_catenateWords_spell'] =
  $variables['apachesolr_multilingual_catenateWords_query'] =
  $variables['apachesolr_multilingual_catenateWords_index'] = array(
    'type' => 'boolean',
    'solr_format' => 'numeric',
    'title' => t('catenateWords', array(), $options),
    'default' => TRUE,
    'description' => t('Causes maximum runs of word parts to be catenated.<br />"wi-fi" => "wifi"'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_catenateNumbers_spell'] =
  $variables['apachesolr_multilingual_catenateNumbers_query'] =
  $variables['apachesolr_multilingual_catenateNumbers_index'] = array(
    'type' => 'boolean',
    'solr_format' => 'numeric',
    'title' => t('catenateNumbers', array(), $options),
    'default' => TRUE,
    'description' => t('Causes maximum runs of number parts to be catenated.<br />"500-42" => "50042"'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_catenateAll_spell'] =
  $variables['apachesolr_multilingual_catenateAll_query'] =
  $variables['apachesolr_multilingual_catenateAll_index'] = array(
    'type' => 'boolean',
    'solr_format' => 'numeric',
    'title' => t('catenateAll', array(), $options),
    'default' => TRUE,
    'description' => t('Causes all subword parts to be catenated.<br />"wi-fi-4000" => "wifi4000"'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_preserveOriginal_spell'] =
  $variables['apachesolr_multilingual_preserveOriginal_query'] =
  $variables['apachesolr_multilingual_preserveOriginal_index'] = array(
    'type' => 'boolean',
    'solr_format' => 'numeric',
    'title' => t('preserveOriginal', array(), $options),
    'default_value' => TRUE,
    'description' => t('Causes the original token to be indexed without modifications (in addition to the tokens produced due to other options).'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_catenateWords_query']['default_value'] = FALSE;
  $variables['apachesolr_multilingual_catenateNumbers_query']['default_value'] = FALSE;
  $variables['apachesolr_multilingual_catenateAll_query']['default_value'] = FALSE;
  $variables['apachesolr_multilingual_preserveOriginal_query']['default_value'] = FALSE;



  $variables['apachesolr_multilingual_min_query'] =
  $variables['apachesolr_multilingual_min_index'] = array(
    'type' => 'number',
    'title' => t('min', array(), $options),
    'default' => '2',
    'description' => t('TODO'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_max_query'] =
  $variables['apachesolr_multilingual_max_index'] = array(
    'type' => 'number',
    'title' => t('max', array(), $options),
    'default' => '100',
    'description' => t('TODO'),
    'group' => 'apachesolr_multilingual_confgen',
  );



  $variables['apachesolr_multilingual_ignoreCase_stopwords_spell'] =
  $variables['apachesolr_multilingual_ignoreCase_stopwords'] = array(
    'type' => 'boolean',
    'solr_format' => 'string',
    'title' => t('ignoreCase', array(), $options),
    'default' => TRUE,
    'description' => t('Causes Solr to ignore the case of tokens when comparing to the stopword list.'),
    'group' => 'apachesolr_multilingual_confgen',
  );



  $variables['apachesolr_multilingual_ignoreCase_synonyms'] = array(
    'type' => 'boolean',
    'solr_format' => 'string',
    'title' => t('ignoreCase', array(), $options),
    'default' => TRUE,
    'description' => t('If ignoreCase is true, matching will lowercase before checking equality.'),
    'group' => 'apachesolr_multilingual_confgen',
  );



  $variables['apachesolr_multilingual_lengthMin_spell'] = array(
    'type' => 'number',
    'title' => t('min', array(), $options),
    'default' => '4',
    'description' => t('Minimum number of characters to add a word to a spell checker.'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_lengthMax_spell'] = array(
    'type' => 'number',
    'title' => t('max', array(), $options),
    'default' => '20',
    'description' => t('Maximum number of characters to add a word to a spell checker.'),
    'group' => 'apachesolr_multilingual_confgen',
  );


  $variables['apachesolr_multilingual_minWordSize_spell'] =
  $variables['apachesolr_multilingual_minWordSize'] = array(
    'type' => 'number',
    'title' => t('minWordSize', array(), $options),
    'default' => '5',
    'description' => t('TODO'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_minSubwordSize_spell'] =
  $variables['apachesolr_multilingual_minSubwordSize'] = array(
    'type' => 'number',
    'title' => t('minSubwordSize', array(), $options),
    'default' => '4',
    'description' => t('TODO'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_maxSubwordSize_spell'] =
  $variables['apachesolr_multilingual_maxSubwordSize'] = array(
    'type' => 'number',
    'title' => t('maxSubwordSize', array(), $options),
    'default' => '15',
    'description' => t('TODO'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_onlyLongestMatch_spell'] =
  $variables['apachesolr_multilingual_onlyLongestMatch'] = array(
    'type' => 'boolean',
    'solr_format' => 'string',
    'title' => t('onlyLongestMatch', array(), $options),
    'default' => TRUE,
    'description' => t('TODO'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  $variables['apachesolr_multilingual_lowerCase_text_ws'] =
  $variables['apachesolr_multilingual_lowerCase_edge_n2_kw_text'] =
  $variables['apachesolr_multilingual_lowerCase_spell'] =
  $variables['apachesolr_multilingual_lowerCase'] = array(
    'type' => 'boolean',
    'title' => t('lowerCase', array(), $options),
    'default' => TRUE,
    'description' => t('Convert all characters to lower case.'),
    'group' => 'apachesolr_multilingual_confgen',
  );

  return $variables;
}
