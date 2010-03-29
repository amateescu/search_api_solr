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
  $selected_languages = variable_get('apachesolr_multilingual_languages', array());
  $language_count = 0;
  foreach($selected_languages as $lang) {
    if ($lang) {
      $language_count++;
    }
  }
  if ($language_count < 1) {
    $form['info'] = array(
    '#type' => 'item',
    '#value' => t('No language selected! You have to !link at least one language.', array('!link' => l(t('select'), 'admin/settings/apachesolr/multilingual')))
    );
  } else {
    $form['schema_file_select'] = array(
	'#type' => 'checkboxes',
    '#title' => t('Selected languages'),
    '#options' => $active_languages,
    '#disabled' => true,
    '#default_value' => $selected_languages,
    '#description' => t('Current selected languages to be handled by multilingual search. You can add or remove languages !link.', array('!link' => l(t('here'), 'admin/settings/apachesolr/multilingual'))),
    );
  }
  if ($language_count > 0)
  {
    if ($language_count == 1 )
    {
      $form['set1'] = array(
      '#type' => 'fieldset',
      '#title' => t('Unique (Non-English) Language Configuration Download'),
      '#weight' => 1,
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      );

      $form['set1']['submit_2'] = array(
      '#type' => 'submit',
      '#name' => 'b1',
      '#value' => t('Download schema.xml'),
      );
    }
    $form['set2'] = array(
    '#type' => 'fieldset',
    '#title' => t('Multilingual Configuration Download'),
    '#weight' => 2,
    '#collapsible' => FALSE,
    '#collapsed' => FALSE,
    );
    $form['set2']['submit_1'] = array(
    '#type' => 'submit',
    '#name' => 'b2',
    '#value' => t('Download schema.xml'),
    );
  }

  return $form;
}


/**
 * Implements hook_form_validate.
 *
 * @param $form
 * @param $form_state
 * @return unknown_type
 */
function schema_generator_form_validate(&$form, &$form_state) {
  if ($form_state['clicked_button']['#name'] === 'b1' || $form_state['clicked_button']['#name'] === 'b2' || $form_state['clicked_button']['#name'] === 'b3') {
    $selected_languages = variable_get('apachesolr_multilingual_languages', array());
    foreach($selected_languages as $lg) {
      if ($lg) {
        $options[] = $lg;
      }
    }
    $all_languages = language_list();

    $schema_str_complete ='';
    $schema_source_path = dirname(__FILE__) . '/resources/';
    $schema_source_file = $schema_source_path . 'schema.xml';
    $schema_str_complete = file_get_contents($schema_source_file);

    ob_clean();
    ob_start();

    if ($form_state['clicked_button']['#name'] === 'b2' || $form_state['clicked_button']['#name'] === 'b3'){
      // read the block fieldType_text_LANGUAGE.xml
      $schema_source_file = $schema_source_path . 'fieldType_text_LANGUAGE.xml';
      $fieldType_text_LANGUAGE = file_get_contents($schema_source_file);

      // read the block fieldType_textTight_LANGUAGE.xml
      $schema_source_file = $schema_source_path . 'fieldType_textSpell_LANGUAGE.xml';
      $fieldType_textSpell_LANGUAGE = file_get_contents($schema_source_file);

      $blocks = array();
      $blocks['<!-- fieldType_text_LANGUAGE -->'] = $fieldType_text_LANGUAGE;
      $blocks['<!-- fieldType_textSpell_LANGUAGE -->'] = $fieldType_textSpell_LANGUAGE;
      $blocks['<!-- field_title_LANGUAGE -->'] = '<field name="title[LANGUAGE_ID]" type="text[LANGUAGE_ID]" indexed="true" stored="true" termVectors="true" omitNorms="true"/>';
      $blocks['<!-- field_body_LANGUAGE -->'] = '<field name="body[LANGUAGE_ID]" type="text[LANGUAGE_ID]" indexed="true" stored="false" termVectors="true"/>';
      $blocks['<!-- field_spell_LANGUAGE -->'] = '<field name="spell[LANGUAGE_ID]" type="textSpell[LANGUAGE_ID]" indexed="true" stored="true" multiValued="true"/>';
      $blocks['<!-- copyField_title_LANGUAGE_spell_LANGUAGE -->'] = '<copyField source="title[LANGUAGE_ID]" dest="spell[LANGUAGE_ID]"/>';
      $blocks['<!-- copyField_body_LANGUAGE_spell_LANGUAGE -->'] = '<copyField source="body[LANGUAGE_ID]" dest="spell[LANGUAGE_ID]"/>';
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
    } else {
      $schema_str_complete = str_replace('language="English', 'language="'.$all_languages[$options[0]]->name, $schema_str_complete);
    }
    ob_end_clean();
    if ($form_state['clicked_button']['#name'] === 'b3') {
      $form['schema_str_complete']['#type'] = 'value';
      $form['schema_str_complete']['#value'] = $schema_str_complete;
    } else {
      drupal_set_header('Content-Type: text/xml; charset=utf-8');
      drupal_set_header('Content-Disposition: attachment; filename=schema.xml');
      print $schema_str_complete;
      exit();
    }
  }
}

