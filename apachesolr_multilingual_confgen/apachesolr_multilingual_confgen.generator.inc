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

    $qp_schema_language_specific
      ->find(':root')->xpath("fields/field[@name='path']")->attr('name', 'i18n_path_' . $language_id)
      ->insertAfter($qp_schema->find(':root')->xpath("fields/field[@name='path']"));

    foreach (array('text', 'text_und') as $type) {
      if ('text_und' != $type) {
        $qp_schema_language_specific
          ->find(':root')->xpath("types/fieldType[@name='$type']//charFilter[@class='solr.MappingCharFilterFactory']")
          ->attr('mapping', 'mapping-ISOLatin1Accent_' . $language_id . '.txt');
      }

      $qp_schema_language_specific
        ->find(':root')->xpath("types/fieldType[@name='$type']//filter[@class='solr.StopFilterFactory']")
        ->attr('ignoreCase', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_ignoreCase_stopwords', $language_id))
        ->attr('words', 'stopwords_' . $language_id . '.txt');

      $qp_schema_language_specific
        ->find(':root')->xpath("types/fieldType[@name='$type']/analyzer[@type='index']/filter[@class='solr.WordDelimiterFilterFactory']")
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
        ->find(':root')->xpath("types/fieldType[@name='$type']/analyzer[@type='index']/filter[@class='solr.LengthFilterFactory']")
        ->attr('min', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_min_index', $language_id))
        ->attr('max', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_max_index', $language_id));

      $qp_schema_language_specific
        ->find(':root')->xpath("types/fieldType[@name='$type']//filter[@class='solr.LowerCaseFilterFactory']")
        ->after('<filter class="solr.DictionaryCompoundWordTokenFilterFactory" dictionary="compoundwords_' . $language_id . '.txt" />');

      $qp_schema_language_specific
        ->find(':root')->xpath("types/fieldType[@name='$type']//filter[@class='solr.DictionaryCompoundWordTokenFilterFactory']")
        ->attr('minWordSize', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_minWordSize', $language_id))
        ->attr('minSubwordSize', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_minSubwordSize', $language_id))
        ->attr('maxSubwordSize', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_maxSubwordSize', $language_id))
        ->attr('onlyLongestMatch', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_onlyLongestMatch', $language_id));

      if ('text_und' != $type) {
        $stemmer_language = apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_stemmer_language', $language_id);
        if (!empty($stemmer_language) && $stemmer_language != '-none-') {
          $qp_schema_language_specific
            ->find(':root')->xpath("types/fieldType[@name='$type']//filter[@class='solr.SnowballPorterFilterFactory']")
            ->attr('language', $stemmer_language)
            ->attr('protected', 'protwords_' . $language_id . '.txt');
        }
        else {
          $qp_schema_language_specific
            ->find(':root')->xpath("types/fieldType[@name='$type']//filter[@class='solr.SnowballPorterFilterFactory']")
            ->detach();
        }
      }

      $qp_schema_language_specific
        ->find(':root')->xpath("types/fieldType[@name='$type']//filter[@class='solr.SynonymFilterFactory']")
        ->attr('synonyms', 'synonyms_' . $language_id . '.txt')
        ->attr('ignoreCase', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_ignoreCase_synonyms', $language_id));

      $qp_schema_language_specific
        ->find(':root')->xpath("types/fieldType[@name='$type']/analyzer[@type='query']/filter[@class='solr.WordDelimiterFilterFactory']")
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
        ->find(':root')->xpath("types/fieldType[@name='$type']/analyzer[@type='query']/filter[@class='solr.LengthFilterFactory']")
        ->attr('min', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_min_query', $language_id))
        ->attr('max', apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_max_query', $language_id));

      if (!apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_lowerCase', $language_id)) {
        $qp_schema_language_specific
          ->find(':root')->xpath("types/fieldType[@name='$type']//filter[@class='solr.LowerCaseFilterFactory']")->detach();
      }
    }

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


    foreach (array('text_ws', 'edge_n2_kw_text') as $type) {
      if (!apachesolr_multilingual_confgen_variable_get('apachesolr_multilingual_lowerCase_' .  $type, $language_id)) {
        $qp_schema_language_specific
          ->find(':root')->xpath("types/fieldType[@name='$type']//filter[@class='solr.LowerCaseFilterFactory']")->detach();
      }
    }


    // add language specific types and fields to schema

    // If a find() returns zero matches, then a subsequent find()
    // will also return zero matches, even if that find has a selector like :root.
    // The reason for this is that the QueryPathCssEventHandler does not set the
    // root of the document tree if it cannot find any elements from which to
    // determine what the root is.
    // The workaround is to use top() to select the root element again.

    $types = array_unique(array_values(apachesolr_multilingual_get_dynamic_text_field_prefixes_and_types()));
    $types[] = 'textSpell'; // i18n_spell_de
    $types[] = 'sortString'; // i18n_sort_label_de

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

    foreach ($names as $src_name => $src_type) {
      $copy_fields = array();
      if ('dynamicField' == $src_type) {
        $copy_fields = $qp_schema_language_specific->branch()->find(':root fields copyField[source^="' . trim($src_name, '*') . '"]');
      }
      else {
        $copy_fields = $qp_schema_language_specific->branch()->find(':root fields copyField[source="' . $src_name . '"]');
      }
      $qp_schema_language_specific->top(); // workaround, see above
      foreach ($copy_fields as $copy_field) {
        $dst_original = $dst = $copy_field->attr('dest');
        $src_original = $copy_field->attr('source');
        foreach ($names as $field_name => $field_type) {
          if ('dynamicField' == $field_type && strpos($dst, trim($field_name, '*')) === 0) {
            $dst_original = $field_name;
            break;
          }
        }

        // The type of the destination field needs to be verified in the original schema,
        // because the type in $qp_schema_language_specific has been renamed already.
        $dst_fields = array();
        if ('dynamicField' == $names[$dst_original]) {
          $dst_fields = $qp_schema->branch()->find(':root fields dynamicField[name^="' . trim($dst_original, '*') . '"]');
        }
        else {
          $dst_fields = $qp_schema->branch()->find(':root fields field[name="' . $dst_original . '"]');
        }
        $qp_schema->top(); // workaround, see above
        $do_copy = FALSE;
        foreach ($dst_fields as $dst_field) {
          if (in_array($dst_field->attr('type'), $types)) {
            // Only add additional copy fields if the destination is of
            // a multilingual text type.
            $do_copy = TRUE;
            break;
          }
        }
        if ($do_copy) {
          $src_suffix = '';
          if (strpos($src_name, '_*') !== FALSE) {
            // If $src_original is 'tm_vid_*' $suffix becomes '_vid_*'.
            // If $src_original is 'tm_*' $suffix becomes '_*'.
            $src_suffix = str_replace(trim($src_name, '_*'), '', trim($src_original, '_*')) . '_*';
          }

          $dst_suffix = '';
          if ('dynamicField' == $names[$dst_original]) {
            // If $src_original is 'tm_vid_*' $suffix becomes '_vid_*'.
            // If $src_original is 'tm_*' $suffix becomes '_*'.
            $dst_suffix = str_replace(trim($dst_original, '_*'), '', trim($dst, '_*'));
            if (strpos($dst, '_*') !== FALSE) {
              $dst_suffix .= '_*';
            }
          }

          $copy_field
            ->attr('source', 'i18n_' . trim($src_name, '_*') . '_' . $language_id . $src_suffix)
            ->attr('dest', 'i18n_' . trim($dst_original, '_*') . '_' . $language_id . $dst_suffix)
            ->insertAfter($qp_schema->find(':root')->xpath("fields/copyField[@source='$src_original' and @dest='$dst']"));
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
