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
  foreach (apachesolr_multilingual_language_list() as $language_id => $language) {
    $qp_schema_language_specific = apachesolr_confgen_clone_qp($qp_schema);

    // type text

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//charFilter[@class='solr.MappingCharFilterFactory']")
      ->attr('mapping', 'mapping-ISOLatin1Accent_' . $language_id . '.txt');

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']//filter[@class='solr.StopFilterFactory']")
      ->attr('ignoreCase', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_ignoreCase_stopwords', $language_id))
      ->attr('words', 'stopwords_' . $language_id . '.txt');

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='index']/filter[@class='solr.WordDelimiterFilterFactory']")
      ->attr('protected', 'protwords_' . $language_id . '.txt')
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
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='query']/filter[@class='solr.WordDelimiterFilterFactory']")
      ->attr('protected', 'protwords_' . $language_id . '.txt')
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

    // type textSpell

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='textSpell']//filter[@class='solr.StopFilterFactory']")
      ->attr('ignoreCase', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_ignoreCase_stopwords_spell', $language_id))
      ->attr('words', 'stopwords_' . $language_id . '.txt');

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='textSpell']//filter[@class='solr.StopFilterFactory']")
      ->after('<filter class="solr.WordDelimiterFilterFactory"  protected="protwords_' . $language_id . '.txt" />');

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='textSpell']//filter[@class='solr.WordDelimiterFilterFactory']")
      ->attr('splitOnCaseChange', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_splitOnCaseChange_spell', $language_id))
      ->attr('splitOnNumerics', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_splitOnNumerics_spell', $language_id))
      ->attr('stemEnglishPossessive', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_stemEnglishPossessive_spell', $language_id))
      ->attr('generateWordParts', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_generateWordParts_spell', $language_id))
      ->attr('generateNumberParts', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_generateNumberParts_spell', $language_id))
      ->attr('catenateWords', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_catenateWords_spell', $language_id))
      ->attr('catenateNumbers', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_catenateNumbers_spell', $language_id))
      ->attr('catenateAll', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_catenateAll_spell', $language_id))
      ->attr('preserveOriginal', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_preserveOriginal_spell', $language_id));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='textSpell']//filter[@class='solr.LengthFilterFactory']")
      ->attr('min', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_lengthMin_spell', $language_id))
      ->attr('max', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_lengthMax_spell', $language_id));

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='textSpell']//filter[@class='solr.LowerCaseFilterFactory']")
      ->after('<filter class="solr.DictionaryCompoundWordTokenFilterFactory" dictionary="compoundwords_' . $language_id . '.txt" />');

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='textSpell']//filter[@class='solr.DictionaryCompoundWordTokenFilterFactory']")
      ->attr('minWordSize', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_minWordSize_spell', $language_id))
      ->attr('minSubwordSize', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_minSubwordSize_spell', $language_id))
      ->attr('maxSubwordSize', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_maxSubwordSize_spell', $language_id))
      ->attr('onlyLongestMatch', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_onlyLongestMatch_spell', $language_id));

    if (!apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_lowerCase_spell', $language_id)) {
      $qp_schema_language_specific
        ->find(':root')->xpath("types/fieldType[@name='textSpell']//filter[@class='solr.LowerCaseFilterFactory']")->detach();
    }

    // add language specific types and fields to schema

    // If a find() returns zero matches, then a subsequent find()
    // will also return zero matches, even if that find has a selector like :root.
    // The reason for this is that the QueryPathCssEventHandler does not set the
    // root of the document tree if it cannot find any elements from which to
    // determine what the root is.
    // The workaround is to use top() to select the root element again.

    $types = array('text', 'textSpell');
    $names = array();

    foreach ($types as $type) {
      $qp_schema_language_specific
        ->find(':root types fieldType[name="' . $type . '"]')->attr('name', $type . '_' . $language_id)
        ->insertAfter($qp_schema->find(':root')->xpath("types/fieldType[@name='$type']"));

      $fields = $qp_schema_language_specific->branch()->find(':root fields field[type="' . $type . '"]');
      $qp_schema_language_specific->top(); // workaround, see above
      foreach ($fields as $field) {
        $name = $field->attr('name');
        $names[$name] = 'field';
        $field
          ->attr('type', $type . '_' . $language_id)
          ->attr('name', 'i18n_' . $name . '_' . $language_id)
          ->insertAfter($qp_schema->find(':root')->xpath("fields/field[@name='$name']"));
      }

      $dynamic_fields = $qp_schema_language_specific->branch()->find(':root fields dynamicField[type="' . $type . '"]');
      $qp_schema_language_specific->top(); // workaround, see above
      foreach ($dynamic_fields as $dynamic_field) {
        $name = $dynamic_field->attr('name');
        $names[$name] = 'dynamicField';
        $dynamic_field
          ->attr('type', $type . '_' . $language_id)
          ->attr('name', preg_replace('/(.*)\*/', 'i18n_$1' . $language_id . '_*', $name))
          ->insertAfter($qp_schema->find(':root')->xpath("fields/dynamicField[@name='$name']"));
      }
    }

    foreach (array_keys($names) as $name) {
      $copy_fields = $qp_schema_language_specific->branch()->find(':root fields copyField[source="' . $name . '"]');
      $qp_schema_language_specific->top(); // workaround, see above
      foreach ($copy_fields as $copy_field) {
        $dest_original = $dest = $copy_field->attr('dest');
        foreach ($names as $field_name => $field_type) {
          if ('dynamicField' == $field_type && strpos($dest, trim($field_name, '*')) === 0) {
            $dest_original = $field_name;
          }
        }
        // The type of the destination field needs to be verified in the original schema,
        // because the type in $qp_schema_language_specific has been renamed already.
        $dest_fields = $qp_schema->branch()->find(':root fields field[name="' . $dest_original . '"]');
        $qp_schema->top(); // workaround, see above
        $do_copy = FALSE;
        foreach ($dest_fields as $dest_field) {
          if (in_array($dest_field->attr('type'), $types)) {
            // Only add additional copy fields if the destination is of
            // a multilingual text type.
            $do_copy = TRUE;
            break;
          }
        }
        if ($do_copy) {
          $copy_field
            ->attr('source', 'i18n_' . $name . '_' . $language_id)
            ->attr('dest', 'i18n_' . $dest . '_' . $language_id)
            ->insertAfter($qp_schema->find(':root')->xpath("fields/copyField[@source='$name' and @dest='$dest']"));
        }
      }
    }
  }
}


function apachesolr_multilingual_confgen_modify_solrconfig($qp_solrconfig, $solr_version) {
  foreach (apachesolr_multilingual_language_list() as $language_id => $language) {
    $qp_solrconfig_language_specific = apachesolr_confgen_clone_qp($qp_solrconfig);

    $spellcheck = $qp_solrconfig_language_specific
      ->find(':root searchComponent[name="spellcheck"]')
      ->attr('name', 'spellcheck_' . $language_id);

    $query_analyzer_field_type = $spellcheck->branch()->find('str[name="queryAnalyzerFieldType"]');
    $query_analyzer_field_type->text($query_analyzer_field_type->text() . '_' . $language_id);

    $fields = $spellcheck->branch()->find('str[name="field"]');
    foreach ($fields as $field) {
      $field->text('i18n_spell_' . $language_id);
    }

    $spellcheck_index_dirs = $spellcheck->branch()->find('str[name="spellcheckIndexDir"]');
    foreach ($spellcheck_index_dirs as $spellcheck_index_dir) {
      $spellcheck_index_dir->text($spellcheck_index_dir->text() . '_' . $language_id);
    }

    $spellcheck->insertBefore($qp_solrconfig->find(':root searchComponent[name="spellcheck"]'));

    $qp_solrconfig->find(':root requestHandler[default="true"] arr[name="last-components"] str:last-child')
      ->before('<str>spellcheck_' . $language_id . '</str>');
  }
}
