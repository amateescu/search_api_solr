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

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//charFilter[@class='solr.MappingCharFilterFactory']")
      ->attr('mapping', 'mapping-ISOLatin1Accent_' . $language . '.txt');

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.StopFilterFactory']")
      ->attr('ignoreCase', variable_get('apachesolr_multilingual_advanced_stopwords_' . $language, 1))
      ->attr('words', 'stopwords_' . $language . '.txt');

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='index']/filter[@class='solr.WordDelimiterFilterFactory']")
      ->attr('splitOnCaseChange', variable_get('apachesolr_multilingual_advanced_splitOnCaseChange_index_' . $language, 1))
      ->attr('splitOnNumerics', variable_get('apachesolr_multilingual_advanced_splitOnNumerics_index_' . $language, 1))
      ->attr('stemEnglishPossessive', variable_get('apachesolr_multilingual_advanced_stemEnglishPossessive_index_' . $language, 1))
      ->attr('generateWordParts', variable_get('apachesolr_multilingual_advanced_generateWordParts_index_' . $language, 1))
      ->attr('generateNumberParts', variable_get('apachesolr_multilingual_advanced_generateNumberParts_index_' . $language, 1))
      ->attr('catenateWords', variable_get('apachesolr_multilingual_advanced_catenateWords_index_' . $language, 1))
      ->attr('catenateNumbers', variable_get('apachesolr_multilingual_advanced_catenateNumbers_index_' . $language, 1))
      ->attr('catenateAll', variable_get('apachesolr_multilingual_advanced_catenateAll_index_' . $language, 0))
      ->attr('preserveOriginal', variable_get('apachesolr_multilingual_preserveOriginal_index_' . $language, 1));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='index']/filter[@class='solr.LengthFilterFactory']")
      ->attr('min', variable_get('apachesolr_multilingual_advanced_min_index_' . $language, 2))
      ->attr('max', variable_get('apachesolr_multilingual_advanced_max_index_' . $language, 100));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.LowerCaseFilterFactory']")
      ->after('<filter class="solr.DictionaryCompoundWordTokenFilterFactory" dictionary="compoundwords' . $language . '.txt" />');

    $stemmer = apachesolr_multilingual_get_stemmer($language);
    if ($stemmer) {
      $qp_schema_language_specific
        ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.SnowballPorterFilterFactory']")
        ->attr('language', $stemmer)
        ->attr('protected', 'protwords_' . $language . '.txt');
    }
    else {
      $qp_schema_language_specific
        ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.SnowballPorterFilterFactory']")
        ->detach();
    }

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.SynonymFilterFactory']")
      ->attr('synonyms', 'synonyms_' . $language . '.txt')
      ->attr('ignoreCase', variable_get('apachesolr_multilingual_advanced_ignoreCase_synonyms_' . $language, 1));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='query']//filter[@class='solr.WordDelimiterFilterFactory']")
      ->attr('splitOnCaseChange', variable_get('apachesolr_multilingual_advanced_splitOnCaseChange_query_' . $language, 1))
      ->attr('splitOnNumerics', variable_get('apachesolr_multilingual_advanced_splitOnNumerics_query_' . $language, 1))
      ->attr('stemEnglishPossessive', variable_get('apachesolr_multilingual_advanced_stemEnglishPossessive_query_' . $language, 1))
      ->attr('generateWordParts', variable_get('apachesolr_multilingual_advanced_generateWordParts_query_' . $language, 1))
      ->attr('generateNumberParts', variable_get('apachesolr_multilingual_advanced_generateNumberParts_query_' . $language, 1))
      ->attr('catenateWords', variable_get('apachesolr_multilingual_advanced_catenateWords_query_' . $language, 1))
      ->attr('catenateNumbers', variable_get('apachesolr_multilingual_advanced_catenateNumbers_query_' . $language, 1))
      ->attr('catenateAll', variable_get('apachesolr_multilingual_advanced_catenateAll_query_' . $language, 0))
      ->attr('preserveOriginal', variable_get('apachesolr_multilingual_preserveOriginal_query_' . $language, 1));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='query']/filter[@class='solr.LengthFilterFactory']")
      ->attr('min', variable_get('apachesolr_multilingual_advanced_min_query_' . $language, 2))
      ->attr('max', variable_get('apachesolr_multilingual_advanced_max_query_' . $language, 100));

    $qp_schema_language_specific
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
  // TODO spell checker
}
