<?php

/**
 * @file
 * Contains Drupal\apachesolr_multilingual\Form\TextFileForm.
 */

namespace Drupal\apachesolr_multilingual\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class TextFileForm.
 *
 * @package Drupal\apachesolr_multilingual\Form
 */
class TextFileForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $solr_text_file = $this->entity;
    $form['label'] = array(
      '#type' => 'TextFieldFilter',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $solr_text_file->label(),
      '#description' => $this->t("Label for the TextFile."),
      '#required' => TRUE,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $solr_text_file->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\apachesolr_multilingual\Entity\TextFile::load',
      ),
      '#disabled' => !$solr_text_file->isNew(),
    );

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $solr_text_file = $this->entity;
    $status = $solr_text_file->save();

    if ($status) {
      drupal_set_message($this->t('Saved the %label TextFile.', array(
        '%label' => $solr_text_file->label(),
      )));
    }
    else {
      drupal_set_message($this->t('The %label TextFile was not saved.', array(
        '%label' => $solr_text_file->label(),
      )));
    }
    $form_state->setRedirectUrl($solr_text_file->urlInfo('collection'));
  }

}
