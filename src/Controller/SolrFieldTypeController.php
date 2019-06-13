<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrFieldTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipStream\Option\Archive;

/**
 * Provides different listings of SolrFieldType.
 */
class SolrFieldTypeController extends ControllerBase {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * Constructs a SolrFieldTypeController object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * Provides the listing page.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return array
   *   A render array as expected by drupal_render().
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function listing(ServerInterface $search_api_server) {
    return $this->getListBuilder($search_api_server)->render();
  }

  /**
   * Provides an XML snippet containing all extra Solr field types.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSchemaExtraTypesXml(ServerInterface $search_api_server) {
    return new Response(
      $this->getListBuilder($search_api_server)->getSchemaExtraTypesXml(),
      200,
      [
        'Content-Type' => 'application/xml',
        'Content-Disposition' => 'attachment; filename=schema_extra_types.xml',
      ]
    );
  }

  /**
   * Provides an XML snippet containing all extra Solr fields.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSchemaExtraFieldsXml(ServerInterface $search_api_server) {
    return new Response(
      $this->getListBuilder($search_api_server)->getSchemaExtraFieldsXml(),
      200,
      [
        'Content-Type' => 'application/xml',
        'Content-Disposition' => 'attachment; filename=schema_extra_fields.xml',
      ]
    );
  }

  /**
   * Provides a zip archive containing a complete Solr configuration.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return array|void
   *   A render array as expected by drupal_render().
   */
  public function getConfigZip(ServerInterface $search_api_server) {
    try {
      $archive_options = new Archive();
      $archive_options->setSendHttpHeaders(TRUE);

      @ob_clean();
      // If you are using nginx as a webserver, it will try to buffer the
      // response. We have to disable this with a custom header.
      // @see https://github.com/maennchen/ZipStream-PHP/wiki/nginx
      header('X-Accel-Buffering: no');
      $zip = $this->getListBuilder($search_api_server)->getConfigZip($archive_options);
      $zip->finish();
      @ob_end_flush();

      exit();
    }
    catch (\Exception $e) {
      watchdog_exception('search_api', $e);
      $this->messenger->addError($this->t('An error occured during the creation of the config.zip. Look at the logs for details.'));
    }

    return [];
  }

  /**
   * Gets the list builder for 'solr_field_type'.
   *
   * Ensures that the list builder uses the correct Solr backend.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return \Drupal\search_api_solr\Controller\SolrFieldTypeListBuilder
   *   The SolrFieldType list builder object.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getListBuilder(ServerInterface $search_api_server) {
    /** @var SolrFieldTypeListBuilder $list_builder */
    $list_builder = $this->entityTypeManager()->getListBuilder('solr_field_type');
    $list_builder->setServer($search_api_server);
    return $list_builder;
  }

  /**
   * Disables a Solr Field Type on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   * @param \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function disableOnServer(ServerInterface $search_api_server, SolrFieldTypeInterface $solr_field_type) {
    $backend_config = $search_api_server->getBackendConfig();
    $backend_config['disabled_field_types'][] = $solr_field_type->id();
    $backend_config['disabled_field_types'] = array_unique($backend_config['disabled_field_types']);
    $search_api_server->setBackendConfig($backend_config);
    $search_api_server->save();
    return new RedirectResponse(Url::fromRoute('entity.solr_field_type.collection', ['search_api_server' => $search_api_server->id()], ['query' => ['time' => \Drupal::time()->getRequestTime()]])->toString());
  }

  /**
   * Disables a Solr Field Type on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   * @param \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function enableOnServer(ServerInterface $search_api_server, SolrFieldTypeInterface $solr_field_type) {
    $backend_config = $search_api_server->getBackendConfig();
    $backend_config['disabled_field_types'] = array_values(array_diff($backend_config['disabled_field_types'], [$solr_field_type->id()]));
    $search_api_server->setBackendConfig($backend_config);
    $search_api_server->save();
    return new RedirectResponse(Url::fromRoute('entity.solr_field_type.collection', ['search_api_server' => $search_api_server->id()], ['query' => ['time' => \Drupal::time()->getRequestTime()]])->toString());
  }

}
