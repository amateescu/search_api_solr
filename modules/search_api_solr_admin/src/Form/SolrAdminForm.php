<?php

namespace Drupal\search_api_solr_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Solr admin form.
 *
 * @package Drupal\search_api_solr_admin\Form
 */
class SolrAdminForm extends FormBase {

  /**
   * SolrAdminForm constructor.
   */
  public function __construct() {
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'solr_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

}
