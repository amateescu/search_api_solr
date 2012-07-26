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
 *
 * @author Matthias Huder (mhuder) | Cocomore AG
 *   @see http://drupal.org/user/753332
/


/**
 * Implements hook_form().
 *
 * @param unknown_type $form_state
 * @return unknown_type
 */
function apachesolr_multilingual_schema_generator_form($form_state) {
  $form = array();
  $active_languages = locale_language_list();
  $selected_languages = variable_get('apachesolr_multilingual_languages', array());
  $language_count = 0;
  foreach ($selected_languages as $lang) {
    if ($lang) {
      $language_count++;
    }
  }
  if ($language_count < 1) {
    drupal_set_message(t('No language selected! You have to !link at least one language.', array('!link' => l(t('select'), 'admin/config/search/apachesolr/multilingual'))), 'error');
  }
  else {
    $form['schema_file_select'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Selected languages'),
      '#options' => $active_languages,
      '#disabled' => TRUE,
      '#default_value' => $selected_languages,
      '#description' => t(
        'Current selected languages to be handled by multilingual search. You can add or remove languages !link.',
        array('!link' => l(t('here'), 'admin/config/search/apachesolr/multilingual'))
      ),
    );

    $form['set2'] = array(
      '#type' => 'fieldset',
      '#title' => t('Multilingual Configuration Download'),
      '#weight' => 2,
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    );
    $form['set2']['submit'] = array(
      '#type' => 'submit',
      '#name' => 'b2',
      '#value' => t('Download !file', array('!file' => 'schema.xml')),
    );

    $form['set2']['submitsc'] = array(
      '#type' => 'submit',
      '#name' => 'sc2',
      '#value' => t('Download !file', array('!file' => 'solrconfig.xml')),
    );

    $form['#submit'] = array('apachesolr_multilingual_schema_generator_form_submit');
  }

  return $form;
}

/**
 * Implements hook_form_validate()
 *
 * @param $form
 * @param $form_state
 *
 * @return void
 */
function apachesolr_multilingual_schema_generator_form_validate(&$form, &$form_state) {
  ob_clean();
  ob_start();

  $selected_languages = variable_get('apachesolr_multilingual_languages', array());
  $options = array();

  foreach ($selected_languages as $lg) {
    if ($lg) {
      $options[] = $lg;
    }
  }

  $solr_conf_path = drupal_get_path('module', 'apachesolr') . '/solr-conf/';
  $schema_str_complete = file_get_contents($solr_conf_path . 'schema.xml');
  $solrconfig_str_complete = file_get_contents($solr_conf_path . 'solrconfig.xml');
  // re-enable dataDir settings to be controlled by apachesolr_multilingual
  $solrconfig_str_complete = preg_replace("@<!--\s*(<dataDir>.*?</dataDir>)\s*-->@s", "$1", $solrconfig_str_complete);

  $qp_schema = qp($schema_str_complete, NULL, array('use_parser' => 'xml'));
  $qp_solrconfig = qp($solrconfig_str_complete, NULL, array('use_parser' => 'xml'));

  if (variable_get('apachesolr_multilingual_multicore', 1)) {
    $qp_solrconfig->find(':root dataDir')->detach();
  }

  $qp_solrconfig->find(':root maxTime')->text(variable_get('apachesolr_multilingual_autoCommit_maxTime', 120000));

  foreach ($options as $language) {
    $qp_schema_language_specific = qp($schema_str_complete, NULL, array('use_parser' => 'xml'));
    $language_name = apachesolr_multilingual_get_stemmer($language);

    $qp_schema_language_specific
      ->find(':root')->xpath("types/fieldType[@name='text']/analyzer[@type='index']/filter[@class='solr.StopFilterFactory']")->attr('words', 'stopwords_' . $language . '.txt')
      ->find(':root')->xpath("types/fieldType[@name='text']")->attr('name', 'text_' . $language)
      ->insertAfter($qp_schema->find(':root')->xpath("types/fieldType[@name='text']"))
    ;
//     $trp = array($LANGUAGE_NAME, $LANGUAGE_ID);

//     if ($LANGUAGE_NAME) {
//       $blocks['<!-- fieldType_text_LANGUAGE -->'] = $field_type_text_language;
//     }
//     else {
//       $blocks['<!-- fieldType_text_LANGUAGE -->'] = $field_type_text_no_stemming_language;
//     }

//     // schema.xml replace the vars in the blocks
//     // solrconfig.xml replace the vars in the blocks
//     foreach ($blocks as $search => $replace) {
//       $schema_str_complete = str_replace($search, $search . "\n" . apachesolr_multilingual_replace_language_vars($language, str_replace($tse, $trp, $replace)), $schema_str_complete);
//       $solrconfig_str_complete = str_replace($search, $search . "\n" . apachesolr_multilingual_replace_language_vars($language, str_replace($tse, $trp, $replace)), $solrconfig_str_complete);
//     }

//     $search = '<!-- gettableFiles_LANGUAGE  -->';
//     foreach ($gettable_files as $s => $replace) {
//       $solrconfig_str_complete = str_replace($search, $search . "\n" . apachesolr_multilingual_replace_language_vars($language, str_replace($tse, $trp, $replace)), $solrconfig_str_complete);
//     }
  }

//   // REVIEW
//   // unfortunately solr accepts only one stop word list to preprocess input for spellchecker.
//   // in multilingual setup it's better to have no stopwords than combine all languages
//   $schema_str_complete = preg_replace("/<!-- stopwords_spell -->.*$/m", '<!-- no stopwords -->', $schema_str_complete);

//   // read the block fieldType_text_LANGUAGE.xml
//   $schema_source_file = $schema_source_path . 'fieldType_text_LANGUAGE.xml';
//   $field_type_text_language = file_get_contents($schema_source_file);

//   // read the block fieldType_text_no_stemming_LANGUAGE.xml
//   $schema_source_file = $schema_source_path . 'fieldType_text_no_stemming_LANGUAGE.xml';
//   $field_type_text_no_stemming_language = file_get_contents($schema_source_file);

//   // read the block fieldType_textSpell_LANGUAGE.xml
//   $schema_source_file = $schema_source_path . 'fieldType_textSpell_LANGUAGE.xml';
//   $field_type_text_spell_language = file_get_contents($schema_source_file);

//   // read the block fieldType_textSpell_multilingual.xml
//   $schema_source_file = $schema_source_path . 'fieldType_textSpell_multilingual.xml';
//   $field_type_text_spell_multilingual = file_get_contents($schema_source_file);
//   $schema_str_complete = str_replace('<!-- fieldType_textSpell_multilingual_StopFilter -->', $field_type_text_spell_multilingual, $schema_str_complete);

//   // read the block lst_spellchecker_LANGUAGE.xml
//   $solrconfig_source_file = $solrconfig_source_path . 'lst_spellchecker_LANGUAGE.xml';
//   $lst_spellchecker_language = file_get_contents($solrconfig_source_file);

//   $blocks = array();
//   $blocks['<!-- fieldType_textSpell_LANGUAGE -->'] = $field_type_text_spell_language;
//   $blocks['<!-- lst_spellchecker_LANGUAGE -->'] = $lst_spellchecker_language;
//   $blocks['<!-- field_title_LANGUAGE -->'] = '<field name="title[LANGUAGE_ID]" type="text[LANGUAGE_ID]" indexed="true" stored="true" termVectors="true" omitNorms="true"/>';
//   $blocks['<!-- field_body_LANGUAGE -->'] = '<field name="body[LANGUAGE_ID]" type="text[LANGUAGE_ID]" indexed="true" stored="true" termVectors="true"/>';
//   $blocks['<!-- field_spell_LANGUAGE -->'] = '<field name="spell[LANGUAGE_ID]" type="textSpell[LANGUAGE_ID]" indexed="true" stored="true" multiValued="true"/>';
//   $blocks['<!-- copyField_title_LANGUAGE_spell_LANGUAGE -->'] = '<copyField source="title[LANGUAGE_ID]" dest="spell[LANGUAGE_ID]"/>';
//   $blocks['<!-- copyField_body_LANGUAGE_spell_LANGUAGE -->'] = '<copyField source="body[LANGUAGE_ID]" dest="spell[LANGUAGE_ID]"/>';
//   $blocks['<!-- field_taxonomy_names_LANGUAGE -->'] = '<field name="taxonomy_names[LANGUAGE_ID]" type="text[LANGUAGE_ID]" indexed="true" stored="false" termVectors="true" multiValued="true" omitNorms="true"/>';
//   $blocks['<!-- dynamicField_tags_LANGUAGE_* -->'] = '<dynamicField name="tags[LANGUAGE_ID]_*" type="text[LANGUAGE_ID]" indexed="true" stored="false" omitNorms="true"/>';
//   $blocks['<!-- fieldType_textSpell_multilingual_StopFilter -->'] = '<filter class="solr.StopFilterFactory" ignoreCase="true" words="stopwords_spell[LANGUAGE_ID].txt"/>';
//   $blocks['<!-- dynamicField_ts_LANGUAGE_* -->'] = '<dynamicField name="ts[LANGUAGE_ID]_*" type="text[LANGUAGE_ID]" indexed="true" stored="true" multiValued="false" termVectors="true"/>';

//   // solrconfig.xml gettablefiles
//   $apachesolr_multilingual_filetypes = variable_get('apachesolr_multilingual_filetypes', array('stopwords.txt' => 'stopwords.txt', 'synonyms.txt' => 'synonyms.txt', 'protwords.txt' => 'protwords.txt', 'compoundwords.txt' => 'compoundwords.txt'));
//   $apachesolr_multilingual_filetypes['stopwords_spell.txt'] = 'stopwords_spell.txt';

//   // FIXME add language specific config files to replication in solrconfig.xml

//   $gettable_files = array();
//   foreach ($apachesolr_multilingual_filetypes as $gettable_file) {
//     $gettable_files[] = str_replace('.txt', '[LANGUAGE_ID].txt', $gettable_file);
//   }

//   $tse = array('[LANGUAGE_NAME]', '[LANGUAGE_ID]');
//   // work through the lang.
//   foreach ($options as $language) {
//     // set the vars to replace in the blocks
//     $LANGUAGE_NAME = apachesolr_multilingual_get_stemmer($language);
//     $LANGUAGE_ID = '_' . $language;
//     $trp = array($LANGUAGE_NAME, $LANGUAGE_ID);

//     if ($LANGUAGE_NAME) {
//       $blocks['<!-- fieldType_text_LANGUAGE -->'] = $field_type_text_language;
//     }
//     else {
//       $blocks['<!-- fieldType_text_LANGUAGE -->'] = $field_type_text_no_stemming_language;
//     }

//     // schema.xml replace the vars in the blocks
//     // solrconfig.xml replace the vars in the blocks
//     foreach ($blocks as $search => $replace) {
//       $schema_str_complete = str_replace($search, $search . "\n" . apachesolr_multilingual_replace_language_vars($language, str_replace($tse, $trp, $replace)), $schema_str_complete);
//       $solrconfig_str_complete = str_replace($search, $search . "\n" . apachesolr_multilingual_replace_language_vars($language, str_replace($tse, $trp, $replace)), $solrconfig_str_complete);
//     }

//     $search = '<!-- gettableFiles_LANGUAGE  -->';
//     foreach ($gettable_files as $s => $replace) {
//       $solrconfig_str_complete = str_replace($search, $search . "\n" . apachesolr_multilingual_replace_language_vars($language, str_replace($tse, $trp, $replace)), $solrconfig_str_complete);
//     }
//   }

//   // set $language id to a value that doesn't exist. That will cause apachesolr_multilingual_replace_language_vars() to use default values.
//   $language = 'default';

//   $schema_str_complete = apachesolr_multilingual_replace_language_vars($language, $schema_str_complete);
//   $solrconfig_str_complete = apachesolr_multilingual_replace_language_vars($language, $solrconfig_str_complete);

  ob_end_clean();

  $form['schema_str_complete']['#type'] = 'value';
  $form['schema_str_complete']['#value'] = $qp_schema->find(':root')->xml();

  $form['solrconfig_str_complete']['#type'] = 'value';
  $form['solrconfig_str_complete']['#value'] = $qp_solrconfig->find(':root')->xml();

  if ($form_state['clicked_button']['#name'] === 'b1' || $form_state['clicked_button']['#name'] === 'b2') {
    drupal_add_http_header('Content-Type', 'text/xml; charset=utf-8');
    drupal_add_http_header('Content-Disposition', 'attachment; filename=schema.xml');
    print $form['schema_str_complete']['#value'];
    exit();
  }

  if ($form_state['clicked_button']['#name'] === 'sc1' || $form_state['clicked_button']['#name'] === 'sc2') {
    drupal_add_http_header('Content-Type', 'text/xml; charset=utf-8');
    drupal_add_http_header('Content-Disposition', 'attachment; filename=solrconfig.xml');
    print $form['solrconfig_str_complete']['#value'];
    exit();
  }
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
