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
  foreach (locale_language_list() as $language_id => $language) {
    $qp_schema_language_specific = apachesolr_confgen_clone_qp($qp_schema);

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//charFilter[@class='solr.MappingCharFilterFactory']")
      ->attr('mapping', 'mapping-ISOLatin1Accent_' . $language_id . '.txt');

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.StopFilterFactory']")
      ->attr('ignoreCase', i18n_variable_get('apachesolr_multilingual_advanced_ignoreCase_stopwords', $language_id, variable_get_default('apachesolr_multilingual_advanced_ignoreCase_stopwords')))
      ->attr('words', 'stopwords_' . $language_id . '.txt');

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='index']/filter[@class='solr.WordDelimiterFilterFactory']")
      ->attr('splitOnCaseChange', i18n_variable_get('apachesolr_multilingual_advanced_splitOnCaseChange_index', $language_id, variable_get_default('apachesolr_multilingual_advanced_splitOnCaseChange_index')))
      ->attr('splitOnNumerics', i18n_variable_get('apachesolr_multilingual_advanced_splitOnNumerics_index', $language_id, variable_get_default('apachesolr_multilingual_advanced_splitOnNumerics_index')))
      ->attr('stemEnglishPossessive', i18n_variable_get('apachesolr_multilingual_advanced_stemEnglishPossessive_index', $language_id, variable_get_default('apachesolr_multilingual_advanced_stemEnglishPossessive_index')))
#      ->attr('generateWordParts', i18n_variable_get('apachesolr_multilingual_advanced_generateWordParts_index', $language_id, variable_get_default('apachesolr_multilingual_advanced_generateWordParts_index')))
      ->attr('generateNumberParts', i18n_variable_get('apachesolr_multilingual_advanced_generateNumberParts_index', $language_id, variable_get_default('apachesolr_multilingual_advanced_generateNumberParts_index')))
      ->attr('catenateWords', i18n_variable_get('apachesolr_multilingual_advanced_catenateWords_index', $language_id, variable_get_default('apachesolr_multilingual_advanced_catenateWords_index')))
      ->attr('catenateNumbers', i18n_variable_get('apachesolr_multilingual_advanced_catenateNumbers_index', $language_id, variable_get_default('apachesolr_multilingual_advanced_catenateNumbers_index')))
      ->attr('catenateAll', i18n_variable_get('apachesolr_multilingual_advanced_catenateAll_index', $language_id, variable_get_default('apachesolr_multilingual_advanced_catenateAll_index')))
      ->attr('preserveOriginal', i18n_variable_get('apachesolr_multilingual_preserveOriginal_index', $language_id, variable_get_default('apachesolr_multilingual_preserveOriginal_index')));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='index']/filter[@class='solr.LengthFilterFactory']")
      ->attr('min', i18n_variable_get('apachesolr_multilingual_advanced_min_index', $language_id, variable_get_default('apachesolr_multilingual_advanced_min_index')))
      ->attr('max', i18n_variable_get('apachesolr_multilingual_advanced_max_index', $language_id, variable_get_default('apachesolr_multilingual_advanced_max_index')));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.LowerCaseFilterFactory']")
      ->after('<filter class="solr.DictionaryCompoundWordTokenFilterFactory" dictionary="compoundwords_' . $language_id . '.txt" />');

    $stemmer = apachesolr_multilingual_get_stemmer($language_id);
    if ($stemmer) {
      $qp_schema_language_specific
        ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.SnowballPorterFilterFactory']")
        ->attr('language', $stemmer)
        ->attr('protected', 'protwords_' . $language_id . '.txt');
    }
    else {
      $qp_schema_language_specific
        ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.SnowballPorterFilterFactory']")
        ->detach();
    }

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.SynonymFilterFactory']")
      ->attr('synonyms', 'synonyms_' . $language_id . '.txt')
      ->attr('ignoreCase', i18n_variable_get('apachesolr_multilingual_advanced_ignoreCase_synonyms', $language_id, variable_get_default('apachesolr_multilingual_advanced_ignoreCase_synonyms')));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='query']//filter[@class='solr.WordDelimiterFilterFactory']")
      ->attr('splitOnCaseChange', i18n_variable_get('apachesolr_multilingual_advanced_splitOnCaseChange_query', $language_id, variable_get_default('apachesolr_multilingual_advanced_splitOnCaseChange_query')))
      ->attr('splitOnNumerics', i18n_variable_get('apachesolr_multilingual_advanced_splitOnNumerics_query', $language_id, variable_get_default('apachesolr_multilingual_advanced_splitOnNumerics_query')))
      ->attr('stemEnglishPossessive', i18n_variable_get('apachesolr_multilingual_advanced_stemEnglishPossessive_query', $language_id, variable_get_default('apachesolr_multilingual_advanced_stemEnglishPossessive_query')))
#      ->attr('generateWordParts', i18n_variable_get('apachesolr_multilingual_advanced_generateWordParts_query', $language_id, variable_get_default('apachesolr_multilingual_advanced_generateWordParts_query')))
      ->attr('generateNumberParts', i18n_variable_get('apachesolr_multilingual_advanced_generateNumberParts_query', $language_id, variable_get_default('apachesolr_multilingual_advanced_generateNumberParts_query')))
      ->attr('catenateWords', i18n_variable_get('apachesolr_multilingual_advanced_catenateWords_query', $language_id, variable_get_default('apachesolr_multilingual_advanced_catenateWords_query')))
      ->attr('catenateNumbers', i18n_variable_get('apachesolr_multilingual_advanced_catenateNumbers_query', $language_id, variable_get_default('apachesolr_multilingual_advanced_catenateNumbers_query')))
      ->attr('catenateAll', i18n_variable_get('apachesolr_multilingual_advanced_catenateAll_query', $language_id, variable_get_default('apachesolr_multilingual_advanced_catenateAll_query')))
      ->attr('preserveOriginal', i18n_variable_get('apachesolr_multilingual_preserveOriginal_query', $language_id, variable_get_default('apachesolr_multilingual_preserveOriginal_query')));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='query']/filter[@class='solr.LengthFilterFactory']")
      ->attr('min', i18n_variable_get('apachesolr_multilingual_advanced_min_query', $language_id, variable_get_default('apachesolr_multilingual_advanced_min_query')))
      ->attr('max', i18n_variable_get('apachesolr_multilingual_advanced_max_query', $language_id, variable_get_default('apachesolr_multilingual_advanced_max_query')));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']")->attr('name', 'text_' . $language_id)
      ->insertAfter($qp_schema->find(':root')->xpath("types/fieldType[@name='text']"));

    $dynamic_fields = $qp_schema_language_specific->find(':root')->xpath("fields/dynamicField[@type='text']");
    foreach ($dynamic_fields as $dynamic_field) {
      $name = $dynamic_field->attr('name');
      $dynamic_field
        ->attr('type', 'text_' . $language_id)
        ->attr('name', preg_replace('/(.*)\*/', '$1' . $language_id . '_*', $name))
        ->insertAfter($qp_schema->find(':root')->xpath("fields/dynamicField[@name='$name']"));
    }

    $fields = $qp_schema_language_specific->find(':root')->xpath("fields/field[@type='text']");
    foreach ($fields as $field) {
      $name = $field->attr('name');
      $field
        ->attr('type', 'text_' . $language_id)
        ->attr('name', $name . '_' . $language_id)
        ->insertAfter($qp_schema->find(':root')->xpath("fields/field[@name='$name']"));
    }
  }
}


function apachesolr_multilingual_modify_solrconfig($qp_solrconfig, $solr_version) {
  // TODO spell checker
}
