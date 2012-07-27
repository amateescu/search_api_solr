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
      '#value' => t('Download !file for solr !version', array('!file' => 'schema.xml', '!version' => '1.4.x')),
    );

    $form['set2']['submitsc'] = array(
      '#type' => 'submit',
      '#name' => 'sc2',
      '#value' => t('Download !file for solr !version', array('!file' => 'solrconfig.xml', '!version' => '1.4.x')),
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
