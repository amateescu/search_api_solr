<?php

namespace Drupal\search_api_solr_multilingual\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the form to export a SolrFieldType.
 */
class SolrFieldTypeExportForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\search_api_solr_multilingual\Entity\SolrFieldType $solr_field_type */
    $solr_field_type = $this->entity;

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $solr_field_type->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\search_api_solr_multilingual\Entity\SolrFieldType::load',
      ),
      '#disabled' => TRUE,
    );

    $form['field_type'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Field Type'),
    );

    $form['field_type']['json'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('JSON'),
      '#description' => $this->t('JSON representation to be used for solr REST API and managed schemas.'),
      '#default_value' => $solr_field_type->getFieldTypeAsJson(),
      '#disabled' => TRUE,
    );

    $form['field_type']['xml'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('XML'),
      '#description' => $this->t('XML representation to be used as part of schema.xml.'),
      '#default_value' => $solr_field_type->getFieldTypeAsXml(),
      '#disabled' => FALSE,
    );

    $form['text_files'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Text Files'),
    );

    $text_files = $solr_field_type->getTextFiles();
    foreach ($text_files as $text_file_name => $text_file) {
      $form['text_files'][$text_file_name] = array(
        '#type' => 'textarea',
        '#title' => $text_file_name,
        '#default_value' => $text_file,
        '#disabled' => TRUE,
      );
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\search_api_solr_multilingual\Entity\SolrFieldType $solr_field_type */
    $solr_field_type = $this->entity;

    $form_state->setRedirectUrl($solr_field_type->urlInfo('collection'));
  }

}
