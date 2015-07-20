<?php

/**
 * @file
 * Contains Drupal\apachesolr_multilingual\Form\TextFieldFilterForm.
 */

namespace Drupal\apachesolr_multilingual\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class TextFieldFilterForm.
 *
 * @package Drupal\apachesolr_multilingual\Form
 */
class TextFieldFilterForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $solr_text_field = $this->entity;
    $form['label'] = array(
      '#type' => 'TextFieldFilter',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $solr_text_field->label(),
      '#description' => $this->t("Label for the TextFieldFilter."),
      '#required' => TRUE,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $solr_text_field->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\apachesolr_multilingual\Entity\TextFieldFilter::load',
      ),
      '#disabled' => !$solr_text_field->isNew(),
    );

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $solr_text_field = $this->entity;
    $status = $solr_text_field->save();

    if ($status) {
      drupal_set_message($this->t('Saved the %label TextFieldFilter.', array(
        '%label' => $solr_text_field->label(),
      )));
    }
    else {
      drupal_set_message($this->t('The %label TextFieldFilter was not saved.', array(
        '%label' => $solr_text_field->label(),
      )));
    }
    $form_state->setRedirectUrl($solr_text_field->urlInfo('collection'));
  }

}
