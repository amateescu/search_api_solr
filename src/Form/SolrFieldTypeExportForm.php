<?php

namespace Drupal\search_api_solr\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api_solr\Utility\Utility;

/**
 * Builds the form to export a SolrFieldType.
 */
class SolrFieldTypeExportForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    \Drupal::messenger()->addWarning($this->t('In the future, this form will be used to export and push specific parts of the current SolrFieldType configuration to a Solr server based on a manged schema. But at the moment you can only see in which parts the SolrFiledType is split and how they\'re serialized.'));

    /** @var \Drupal\search_api_solr\Entity\SolrFieldType $solr_field_type */
    $solr_field_type = $this->entity;

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $solr_field_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\search_api_solr\Entity\SolrFieldType::load',
      ],
      '#disabled' => TRUE,
    ];

    $form['field_type'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field Type'),
    ];

    $form['field_type']['json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('JSON'),
      '#description' => $this->t('JSON representation to be used for solr REST API and managed schemas.'),
      '#default_value' => $solr_field_type->getFieldTypeAsJson(),
      '#disabled' => TRUE,
    ];

    $form['field_type']['xml'] = [
      '#type' => 'textarea',
      '#title' => $this->t('XML'),
      '#description' => $this->t('XML representation to be used as part of schema.xml.'),
      '#default_value' => $solr_field_type->getFieldTypeAsXml(),
      '#disabled' => TRUE,
    ];

    $form['solr_configs'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Solr configs'),
    ];

    $form['solr_configs']['xml'] = [
      '#type' => 'textarea',
      '#title' => $this->t('XML'),
      '#description' => $this->t('XML representation to be used as part of solrconfig.xml.'),
      '#default_value' => $solr_field_type->getSolrConfigsAsXml(),
      '#disabled' => TRUE,
    ];

    $form['text_files'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Text Files'),
    ];

    $text_files = $solr_field_type->getTextFiles();
    foreach ($text_files as $text_file_name => $text_file) {
      $form['text_files'][$text_file_name] = [
        '#type' => 'textarea',
        '#title' => Utility::completeTextFileName($text_file_name, $solr_field_type),
        '#default_value' => $text_file,
        '#disabled' => TRUE,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    // @todo add actions like 'push stopwords to Solr'
    return [];
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
    /** @var \Drupal\search_api_solr\Entity\SolrFieldType $solr_field_type */
    $solr_field_type = $this->entity;

    $form_state->setRedirectUrl($solr_field_type->urlInfo('collection'));
  }

}
