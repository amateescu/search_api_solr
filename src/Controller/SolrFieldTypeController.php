<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\search_api\ServerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

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
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function getConfigZip(ServerInterface $search_api_server) {
    @ob_clean();

    try {
      /** @var \ZipStream\ZipStream $zip */
      $zip = $this->getListBuilder($search_api_server)->getConfigZip();
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

}
