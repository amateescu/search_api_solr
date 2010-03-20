<?php
// $Id$

/**
 * @file
 * Schema generator for multilingual search
 *
 * @see apachesolr_multilingual.module
 * @see apachesolr.module
 *
 * @author Matthias Huder (mhuder) | Cocomore AG
 *   @see http://drupal.org/user/753332
 *   @see http://drupal.cocomore.com
 *
 * @author Markus Kalkbrenner (mkalkbrenner) | Cocomore AG
 *   @see http://drupal.org/user/124705
 *   @see http://drupal.cocomore.com
 */


/**
 * Implements hook_form().
 *
 * @param unknown_type $form_state
 * @return unknown_type
 */
function schema_generator_form($form_state) {
  $active_languages = locale_language_list();

  $form['schema_file_select'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Activated languages'),
    '#options' => $active_languages,
    '#description' => t('Select languages to be handled by multilingual serarch'),
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Schema generieren'),
  );

  return $form;
}


/**
 * Implements hook_form_validate.
 *
 * @param $form
 * @param $form_state
 * @return unknown_type
 */
function schema_generator_form_validate($form, &$form_state) {
  $all_languages = language_list();
  $options = $form_state['values']['schema_file_select'];

  // check for something selected
  if (!in_array(TRUE, $options)){
    form_set_error('schema_file_select', t('You have to select at least one language.'));
    return;
  }
  else {
    ob_clean();
    ob_start();

    $schema_str_complete ='';
    $schema_source_path = dirname(__FILE__) . '/resources/';
    $schema_source_file = $schema_source_path . 'schema.xml';
    $schema_str_complete = file_get_contents($schema_source_file);

    // read the block fieldType_text_LANGUAGE.xml
    $schema_source_file = $schema_source_path . 'fieldType_text_LANGUAGE.xml';
    $fieldType_text_LANGUAGE = file_get_contents($schema_source_file);

    // read the block fieldType_textTight_LANGUAGE.xml
    $schema_source_file = $schema_source_path . 'fieldType_textTight_LANGUAGE.xml';
    $fieldType_textTight_LANGUAGE = file_get_contents($schema_source_file);

    $blocks = array();
    $blocks['<!-- fieldType_text_LANGUAGE -->'] = $fieldType_text_LANGUAGE;
    $blocks['<!-- fieldType_textTight_LANGUAGE -->'] = $fieldType_textTight_LANGUAGE;
    $blocks['<!-- field_title_LANGUAGE -->'] = '   <field name="title[LANGUAGE_ID]" type="text[LANGUAGE_ID]" indexed="true" stored="true" termVectors="true" omitNorms="true"/>';
    $blocks['<!-- field_body_LANGUAGE -->'] = '   <field name="body[LANGUAGE_ID]" type="text[LANGUAGE_ID]" indexed="true" stored="false" termVectors="true"/>';
    $tse = array('[LANGUAGE_NAME]', '[LANGUAGE_ID]');
    // work through the lang.
    foreach ($options as $lg => $language) {
      if ($language){
        // set the vars to replace in the blocks
        $LANGUAGE_NAME = $all_languages[$language]->name;
        $LANGUAGE_ID = '_'.$language;
        $trp = array($LANGUAGE_NAME, $LANGUAGE_ID);
        // replace the vars in the blocks
        foreach ($blocks as $search => $replace) {
          $schema_str_complete = str_replace($search, $search."\n".str_replace($tse, $trp, $replace), $schema_str_complete);
        }
      }
    }

    ob_end_clean();

    drupal_set_header('Content-Type: text/xml; charset=utf-8');
    drupal_set_header('Content-Disposition: attachment; filename=schema.xml');
    print $schema_str_complete;

    exit();
  }
}
