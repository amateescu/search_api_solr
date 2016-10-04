<?php

namespace Drupal\search_api_solr\SolrConnector;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\ConfigurablePluginBase;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api_solr\Annotation\SolrConnector;
use Drupal\search_api_solr\SolrConnectorInterface;
use Solarium\Client;

/**
 * Defines a base class for Solr connector plugins.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_solr_connector_info_alter(). The definition includes the
 * following keys:
 * - id: The unique, system-wide identifier of the backend class.
 * - label: The human-readable name of the backend class, translated.
 * - description: A human-readable description for the backend class,
 *   translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SolrConnector(
 *   id = "my_connector",
 *   label = @Translation("My connector"),
 *   description = @Translation("Searches with SuperSearch™.")
 * )
 * @endcode
 *
 * @see \Drupal\search_api_solr\Annotation\SolrConnector
 * @see \Drupal\search_api_solr\SolrConnector\SolrConnectorPluginManager
 * @see \Drupal\search_api_solr\SolrConnectorInterface
 * @see plugin_api
 */
abstract class SolrConnectorPluginBase extends ConfigurablePluginBase implements SolrConnectorInterface {

  use PluginFormTrait {
    submitConfigurationForm as traitSubmitConfigurationForm;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'scheme' => 'http',
      'host' => 'localhost',
      'port' => '8983',
      'path' => '/solr',
      'core' => '',
      'timeout' => 5,
      'index_timeout' => 5,
      'optimize_timeout' => 10,
      'username' => '',
      'password' => '',
      'solr_version' => '',
      'http_method' => 'AUTO',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['scheme'] = array(
      '#type' => 'select',
      '#title' => $this->t('HTTP protocol'),
      '#description' => $this->t('The HTTP protocol to use for sending queries.'),
      '#default_value' => $this->configuration['scheme'],
      '#options' => array(
        'http' => 'http',
        'https' => 'https',
      ),
    );

    $form['host'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Solr host'),
      '#description' => $this->t('The host name or IP of your Solr server, e.g. <code>localhost</code> or <code>www.example.com</code>.'),
      '#default_value' => $this->configuration['host'],
      '#required' => TRUE,
    );

    $form['port'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Solr port'),
      '#description' => $this->t('The Jetty example server is at port 8983, while Tomcat uses 8080 by default.'),
      '#default_value' => $this->configuration['port'],
      '#required' => TRUE,
    );

    $form['path'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Solr path'),
      '#description' => $this->t('The path that identifies the Solr instance to use on the server.'),
      '#default_value' => $this->configuration['path'],
    );

    $form['core'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Solr core'),
      '#description' => $this->t('The name that identifies the Solr core to use on the server.'),
      '#default_value' => $this->configuration['core'],
    );

    $form['timeout'] = array(
      '#type' => 'number',
      '#min' => 1,
      '#max' => 180,
      '#title' => $this->t('Query timeout'),
      '#description' => $this->t('The timeout in seconds for search queries sent to the Solr server.'),
      '#default_value' => $this->configuration['timeout'],
      '#required' => TRUE,
    );

    $form['index_timeout'] = array(
      '#type' => 'number',
      '#min' => 1,
      '#max' => 180,
      '#title' => $this->t('Index timeout'),
      '#description' => $this->t('The timeout in seconds for indexing requests to the Solr server.'),
      '#default_value' => $this->configuration['index_timeout'],
      '#required' => TRUE,
    );

    $form['optimize_timeout'] = array(
      '#type' => 'number',
      '#min' => 1,
      '#max' => 180,
      '#title' => $this->t('Optimize timeout'),
      '#description' => $this->t('The timeout in seconds for background index optimization queries on a Solr server.'),
      '#default_value' => $this->configuration['optimize_timeout'],
      '#required' => TRUE,
    );

    $form['workarounds'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Connector Workarounds'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['workarounds']['solr_version'] = array(
      '#type' => 'select',
      '#title' => $this->t('Solr version override'),
      '#description' => $this->t('Specify the Solr version manually in case it cannot be retrived automatically. The version can be found in the Solr admin interface under "Solr Specification Version" or "solr-spec"'),
      '#options' => array(
        '' => $this->t('Determine automatically'),
        '4' => '4.x',
        '5' => '5.x',
        '6' => '6.x',
      ),
      '#default_value' => $this->configuration['solr_version'],
    );

    $form['workarounds']['http_method'] = array(
      '#type' => 'select',
      '#title' => $this->t('HTTP method'),
      '#description' => $this->t('The HTTP method to use for sending queries. GET will often fail with larger queries, while POST should not be cached. AUTO will use GET when possible, and POST for queries that are too large.'),
      '#default_value' => $this->configuration['http_method'],
      '#options' => array(
        'AUTO' => $this->t('AUTO'),
        'POST' => 'POST',
        'GET' => 'GET',
      ),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (isset($values['port']) && (!is_numeric($values['port']) || $values['port'] < 0 || $values['port'] > 65535)) {
      $form_state->setError($form['port'], $this->t('The port has to be an integer between 0 and 65535.'));
    }
    if (!empty($values['path']) && strpos($values['path'], '/') !== 0) {
      $form_state->setError($form['path'], $this->t('If provided the path has to start with "/".'));
    }
    if (!empty($values['core']) && strpos($values['core'], '/') === 0) {
      $form_state->setError($form['core'], $this->t('The core must not start with "/".'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Since the form is nested into another, we can't simply use #parents for
    // doing this array restructuring magic. (At least not without creating an
    // unnecessary dependency on internal implementation.)
    foreach ($values['workarounds'] as $key => $value) {
      $form_state->setValue($key, $value);
    }

    // Clean-up the form to avoid redundant entries in the stored configuration.
    $form_state->unsetValue('workarounds');

    $this->traitSubmitConfigurationForm($form, $form_state);
  }

  public function connect(Client $client) {}

  /**
   * Retrieves the configuration for a solarium endpoint.
   *
   * @return array
   *   The solarium endpoint configuration.
   */
  public function getQueryEndpointConfig() {
    return $this->defaultConfiguration();
  }

  /**
   * Retrieves the configuration for a solarium endpoint.
   *
   * @return array
   *   The solarium endpoint configuration.
   */
  public function getIndexEndpointConfig() {
    return [];
  }

  /**
   * Retrieves the configuration for a solarium endpoint.
   *
   * @return array
   *   The solarium endpoint configuration.
   */
  public function getOptimizeEndpointConfig() {
    return [];
  }

}
