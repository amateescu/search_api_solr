<?php

/**
 * @file
 * Contains Drupal\apachesolr_multilingual\Form\SolrFieldTypeForm.
 */

namespace Drupal\apachesolr_multilingual\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SolrFieldTypeForm.
 *
 * @package Drupal\apachesolr_multilingual\Form
 */
class SolrFieldTypeForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $solr_field_type = $this->entity;
    $form['label'] = array(
      '#type' => 'SolrFieldType',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $solr_field_type->label(),
      '#description' => $this->t("Label for the SolrFieldType."),
      '#required' => TRUE,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $solr_field_type->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\apachesolr_multilingual\Entity\SolrFieldType::load',
      ),
      '#disabled' => !$solr_field_type->isNew(),
    );

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $solr_field_type = $this->entity;
    $status = $solr_field_type->save();

    if ($status) {
      drupal_set_message($this->t('Saved the %label Solr Field Type.', array(
        '%label' => $solr_field_type->label(),
      )));
    }
    else {
      drupal_set_message($this->t('The %label Solr Field Type was not saved.', array(
        '%label' => $solr_field_type->label(),
      )));
    }
    $form_state->setRedirectUrl($solr_field_type->urlInfo('collection'));
  }

}
