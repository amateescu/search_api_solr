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


function apachesolr_multilingual_confgen_modify_schema($qp_schema, $solr_version) {
  foreach (locale_language_list() as $language_id => $language) {
    $qp_schema_language_specific = apachesolr_confgen_clone_qp($qp_schema);

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//charFilter[@class='solr.MappingCharFilterFactory']")
      ->attr('mapping', 'mapping-ISOLatin1Accent_' . $language_id . '.txt');

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.StopFilterFactory']")
      ->attr('ignoreCase', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_ignoreCase_stopwords', $language_id))
      ->attr('words', 'stopwords_' . $language_id . '.txt');

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='index']/filter[@class='solr.WordDelimiterFilterFactory']")
      ->attr('splitOnCaseChange', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_splitOnCaseChange_index', $language_id))
      ->attr('splitOnNumerics', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_splitOnNumerics_index', $language_id))
      ->attr('stemEnglishPossessive', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_stemEnglishPossessive_index', $language_id))
      ->attr('generateWordParts', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_generateWordParts_index', $language_id))
      ->attr('generateNumberParts', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_generateNumberParts_index', $language_id))
      ->attr('catenateWords', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_catenateWords_index', $language_id))
      ->attr('catenateNumbers', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_catenateNumbers_index', $language_id))
      ->attr('catenateAll', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_catenateAll_index', $language_id))
      ->attr('preserveOriginal', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_preserveOriginal_index', $language_id));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='index']/filter[@class='solr.LengthFilterFactory']")
      ->attr('min', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_min_index', $language_id))
      ->attr('max', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_max_index', $language_id));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.LowerCaseFilterFactory']")
      ->after('<filter class="solr.DictionaryCompoundWordTokenFilterFactory" dictionary="compoundwords_' . $language_id . '.txt" />');

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.DictionaryCompoundWordTokenFilterFactory']")
      ->attr('minWordSize', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_minWordSize', $language_id))
      ->attr('minSubwordSize', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_minSubwordSize', $language_id))
      ->attr('maxSubwordSize', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_maxSubwordSize', $language_id))
      ->attr('onlyLongestMatch', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_onlyLongestMatch', $language_id));

    $stemmer_language = apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_stemmer_language', $language_id);
    if (!empty($stemmer_language) && $stemmer_language != '-none-') {
      $qp_schema_language_specific
        ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.SnowballPorterFilterFactory']")
        ->attr('language', $stemmer_language)
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
      ->attr('ignoreCase', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_ignoreCase_synonyms', $language_id));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='query']//filter[@class='solr.WordDelimiterFilterFactory']")
      ->attr('splitOnCaseChange', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_splitOnCaseChange_query', $language_id))
      ->attr('splitOnNumerics', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_splitOnNumerics_query', $language_id))
      ->attr('stemEnglishPossessive', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_stemEnglishPossessive_query', $language_id))
      ->attr('generateWordParts', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_generateWordParts_query', $language_id))
      ->attr('generateNumberParts', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_generateNumberParts_query', $language_id))
      ->attr('catenateWords', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_catenateWords_query', $language_id))
      ->attr('catenateNumbers', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_catenateNumbers_query', $language_id))
      ->attr('catenateAll', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_catenateAll_query', $language_id))
      ->attr('preserveOriginal', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_preserveOriginal_query', $language_id));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='query']/filter[@class='solr.LengthFilterFactory']")
      ->attr('min', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_min_query', $language_id))
      ->attr('max', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_max_query', $language_id));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']")->attr('name', 'text_' . $language_id)
      ->insertAfter($qp_schema->find(':root')->xpath("types/fieldType[@name='text']"));

    $dynamic_fields = $qp_schema_language_specific->find(':root')->xpath("fields/dynamicField[@type='text']");
    foreach ($dynamic_fields as $dynamic_field) {
      $name = $dynamic_field->attr('name');
      $dynamic_field
        ->attr('type', 'text_' . $language_id)
        ->attr('name', preg_replace('/(.*)\*/', 'i18n_$1' . $language_id . '_*', $name))
        ->insertAfter($qp_schema->find(':root')->xpath("fields/dynamicField[@name='$name']"));
    }

    $fields = $qp_schema_language_specific->find(':root')->xpath("fields/field[@type='text']");
    foreach ($fields as $field) {
      $name = $field->attr('name');
      $field
        ->attr('type', 'text_' . $language_id)
        ->attr('name', 'i18n_' . $name . '_' . $language_id)
        ->insertAfter($qp_schema->find(':root')->xpath("fields/field[@name='$name']"));
    }
  }
}


function apachesolr_multilingual_confgen_modify_solrconfig($qp_solrconfig, $solr_version) {
  // TODO spell checker
}
