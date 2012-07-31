<?php

/**
 * @file
 * Schema generator for multilingual search
 *
 * @see apachesolr_multilingual.module
 * @see apachesolr.module
 *
 * @author Markus Kalkbrenner (mkalkbrenner) | bio.logis GmbH
 *   @see http://drupal.org/user/124705
 */


function apachesolr_multilingual_modify_schema($qp_schema, $solr_version) {
  $selected_languages = variable_get('apachesolr_multilingual_languages', array());
  foreach ($selected_languages as $language => $active) {
    if (!$active) {
      continue;
    }
    $qp_schema_language_specific = apachesolr_confgen_clone_qp($qp_schema);
    $stemmer = apachesolr_multilingual_get_stemmer($language);
    if ($stemmer) {
      $qp_schema_language_specific
        ->find(':root')->xpath("//filter[@class='solr.SnowballPorterFilterFactory']")->attr('language', $stemmer)->attr('protected', 'protwords_' . $language . '.txt');
    }
    else {
      $qp_schema_language_specific
        ->find(':root')->xpath("//filter[@class='solr.SnowballPorterFilterFactory']")->detach();
    }

    $qp_schema_language_specific
      //->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='index']/filter[@class='solr.StopFilterFactory']")->attr('words', 'stopwords_' . $language . '.txt')
      //TODO handle all the txt files and and all language specific attributes
      ->find(':root')->xpath("types/fieldType[@name='text']")->attr('name', 'text_' . $language)
      ->insertAfter($qp_schema->find(':root')->xpath("types/fieldType[@name='text']"));

    $dynamic_fields = $qp_schema_language_specific->find(':root')->xpath("fields/dynamicField[@type='text']");
    foreach ($dynamic_fields as $dynamic_field) {
      $name = $dynamic_field->attr('name');
      $dynamic_field
        ->attr('type', 'text_' . $language)
        ->attr('name', preg_replace('/(.*)\*/', '$1' . $language . '_*', $name))
        ->insertAfter($qp_schema->find(':root')->xpath("fields/dynamicField[@name='$name']"));
    }

    $fields = $qp_schema_language_specific->find(':root')->xpath("fields/field[@type='text']");
    foreach ($fields as $field) {
      $name = $field->attr('name');
      $field
        ->attr('type', 'text_' . $language)
        ->attr('name', $name . '_' . $language)
        ->insertAfter($qp_schema->find(':root')->xpath("fields/field[@name='$name']"));
    }
  }
}


function apachesolr_multilingual_modify_solrconfig($qp_solrconfig, $solr_version) {
#  $solrconfig_str_complete = preg_replace("@<!--\s*(<dataDir>.*?</dataDir>)\s*-->@s", "$1", $solrconfig_str_complete);

  if (variable_get('apachesolr_multilingual_multicore', 1)) {
#    $qp_solrconfig->find(':root dataDir')->detach();
  }

  $qp_solrconfig->find(':root maxTime')->text(variable_get('apachesolr_multilingual_autoCommit_maxTime', 120000));
}



/**
 * Replaces placeholders by values
 * configured by the user
 *
 * @param $language
 *
 * @param $tse
 *   placeholder
 *
 * @param $trp
 *   replacement
 *
 * @param $tx
 *   the source text containing the placeholders
 *
 * @return
 *   the source text including the replacements
 */
function apachesolr_multilingual_replace_language_vars($language, $text) {

  $bl['[DATA_DIR]'] = variable_get('apachesolr_multilingual_dataDir', '${solr.data.dir:./solr/data}');

  $bl['[SPELL_LENGTH_MIN]'] = variable_get('apachesolr_multilingual_advanced_lengthMin_spell_' . $language, 4);
  $bl['[SPELL_LENGTH_MAX]'] = variable_get('apachesolr_multilingual_advanced_lengthMax_spell_' . $language, 20);

  $bl['[IGNORE_CASE_STOPWORDS]'] = variable_get('apachesolr_multilingual_advanced_stopwords_' . $language, 1);
  $bl['[IGNORE_CASE_SYNONYMS]'] = variable_get('apachesolr_multilingual_advanced_ignoreCase_synonyms_' . $language, 1);

  $bl['[SPLIT_ON_CASE_CHANGE_INDEX]'] = variable_get('apachesolr_multilingual_advanced_splitOnCaseChange_index_' . $language, 1);
  $bl['[SPILT_ON_NUMERICS_INDEX]'] = variable_get('apachesolr_multilingual_advanced_splitOnNumerics_index_' . $language, 1);
  $bl['[STEM_ENGLISH_POSSESSIVE_INDEX]'] = variable_get('apachesolr_multilingual_advanced_stemEnglishPossessive_index_' . $language, 1);
  $bl['[GENERATE_WORD_PARTS_INDEX]'] = variable_get('apachesolr_multilingual_advanced_generateWordParts_index_' . $language, 1);
  $bl['[GENERATE_NUMBER_PARTS_INDEX]'] = variable_get('apachesolr_multilingual_advanced_generateNumberParts_index_' . $language, 1);
  $bl['[CATENATE_WORDS_INDEX]'] = variable_get('apachesolr_multilingual_advanced_catenateWords_index_' . $language, 1);
  $bl['[CATENATE_NUMBERS_INDEX]'] = variable_get('apachesolr_multilingual_advanced_catenateNumbers_index_' . $language, 1);
  $bl['[CATENATE_ALL_INDEX]'] = variable_get('apachesolr_multilingual_advanced_catenateAll_index_' . $language, 0);
  $bl['[PRESERVE_ORGINAL_INDEX]'] = variable_get('apachesolr_multilingual_preserveOriginal_index_' . $language, 1);

  $bl['[SPLIT_ON_CASE_CHANGE_QUERY]'] = variable_get('apachesolr_multilingual_advanced_splitOnCaseChange_query_' . $language, 1);
  $bl['[SPILT_ON_NUMERICS_QUERY]'] = variable_get('apachesolr_multilingual_advanced_splitOnNumerics_query_' . $language, 1);
  $bl['[STEM_ENGLISH_POSSESSIVE_QUERY]'] = variable_get('apachesolr_multilingual_advanced_stemEnglishPossessive_query_' . $language, 1);
  $bl['[GENERATE_WORD_PARTS_QUERY]'] = variable_get('apachesolr_multilingual_advanced_generateWordParts_query_' . $language, 1);
  $bl['[GENERATE_NUMBER_PARTS_QUERY]'] = variable_get('apachesolr_multilingual_advanced_generateNumberParts_query_' . $language, 1);
  $bl['[CATENATE_WORDS_QUERY]'] = variable_get('apachesolr_multilingual_advanced_catenateWords_query_' . $language, 1);
  $bl['[CATENATE_NUMBERS_QUERY]'] = variable_get('apachesolr_multilingual_advanced_catenateNumbers_query_' . $language, 1);
  $bl['[CATENATE_ALL_QUERY]'] = variable_get('apachesolr_multilingual_advanced_catenateAll_query_' . $language, 0);
  $bl['[PRESERVE_ORGINAL_QUERY]'] = variable_get('apachesolr_multilingual_preserveOriginal_query_' . $language, 1);

  // replace the vars in the blocks
  foreach ($bl as $search => $replace) {
    $text = str_replace($search, $replace, $text);
  }

  return $text;
}
