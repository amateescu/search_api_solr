<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SearchApiSolrConflictingEntitiesException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\Utility\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

/**
 * Provides different listings of SolrFieldType.
 */
class SolrConfigSetController extends ControllerBase {

  use BackendTrait;

  /**
   * Provides an XML snippet containing all extra Solr field types.
   *
   * @param \Drupal\search_api\ServerInterface|null $search_api_server
   *   The Search API server entity.
   *
   * @return string
   *   XML snippet containing all extra Solr field types.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSchemaExtraTypesXml(?ServerInterface $search_api_server = NULL): string {
    /** @var \Drupal\search_api_solr\Controller\SolrFieldTypeListBuilder $list_builder */
    $list_builder = $this->getListBuilder('solr_field_type', $search_api_server);
    return $list_builder->getSchemaExtraTypesXml();
  }

  /**
   * Streams schema_extra_types.xml.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function streamSchemaExtraTypesXml(ServerInterface $search_api_server): Response {
    try {
      return $this->streamXml('schema_extra_types.xml', $this->getSchemaExtraTypesXml($search_api_server));
    }
    catch (SearchApiSolrConflictingEntitiesException $e) {
      $this->messenger()->addError($this->t('Some enabled parts of the configuration conflict with others: :conflicts', ['@conflicts' => new FormattableMarkup($e, [])]));
    }
    return new RedirectResponse($search_api_server->toUrl('canonical')->toString());
  }

  /**
   * Provides an XML snippet containing all extra Solr fields.
   *
   * @param \Drupal\search_api\ServerInterface|null $search_api_server
   *   The Search API server entity.
   *
   * @return string
   *   XML snippet containing all extra Solr fields.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSchemaExtraFieldsXml(?ServerInterface $search_api_server = NULL): string {
    /** @var \Drupal\search_api_solr\Controller\SolrFieldTypeListBuilder $list_builder */
    $list_builder = $this->getListBuilder('solr_field_type', $search_api_server);
    return $list_builder->getSchemaExtraFieldsXml();
  }

  /**
   * Streams schema_extra_fields.xml.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function streamSchemaExtraFieldsXml(ServerInterface $search_api_server): Response {
    try {
      return $this->streamXml('schema_extra_fields.xml', $this->getSchemaExtraFieldsXml($search_api_server));
    }
    catch (SearchApiSolrConflictingEntitiesException $e) {
      $this->messenger()->addError($this->t('Some enabled parts of the configuration conflict with others: @conflicts', ['@conflicts' => new FormattableMarkup($e, [])]));
    }
    return new RedirectResponse($search_api_server->toUrl('canonical')->toString());
  }

  /**
   * Provides an XML snippet containing all extra solrconfig.
   *
   * @param \Drupal\search_api\ServerInterface|null $search_api_server
   *   The Search API server entity.
   *
   * @return string
   *   XML snippet containing all extra solrconfig.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSolrconfigExtraXml(?ServerInterface $search_api_server = NULL): string {
    /** @var \Drupal\search_api_solr\Controller\SolrFieldTypeListBuilder $solr_field_type_list_builder */
    $solr_field_type_list_builder = $this->getListBuilder('solr_field_type', $search_api_server);

    /** @var \Drupal\search_api_solr\Controller\SolrRequestHandlerListBuilder $solr_request_handler_list_builder */
    $solr_request_handler_list_builder = $this->getListBuilder('solr_request_handler', $search_api_server);

    return $solr_field_type_list_builder->getSolrconfigExtraXml() . $solr_request_handler_list_builder->getXml();
  }

  /**
   * Streams solrconfig_extra.xml.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function streamSolrconfigExtraXml(ServerInterface $search_api_server): Response {
    try {
      return $this->streamXml('solrconfig_extra.xml', $this->getSolrconfigExtraXml($search_api_server));
    }
    catch (SearchApiSolrConflictingEntitiesException $e) {
      $this->messenger()->addError($this->t('Some enabled parts of the configuration conflict with others: @conflicts', ['@conflicts' => new FormattableMarkup($e, [])]));
    }
    return new RedirectResponse($search_api_server->toUrl('canonical')->toString());
  }

  /**
   * Provides an XML snippet containing all query cache settings as XML.
   *
   * @param \Drupal\search_api\ServerInterface|null $search_api_server
   *   The Search API server entity.
   *
   * @return string
   *   XML snippet containing all query cache settings.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSolrconfigQueryXml(?ServerInterface $search_api_server = NULL): string {
    /** @var \Drupal\search_api_solr\Controller\SolrCacheListBuilder $list_builder */
    $list_builder = $this->getListBuilder('solr_cache', $search_api_server);
    return $list_builder->getXml();
  }

  /**
   * Streams solrconfig_query.xml.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function streamSolrconfigQueryXml(ServerInterface $search_api_server): Response {
    try {
      return $this->streamXml('solrconfig_query.xml', $this->getSolrconfigQueryXml($search_api_server));
    }
    catch (SearchApiSolrConflictingEntitiesException $e) {
      $this->messenger()->addError($this->t('Some enabled parts of the configuration conflict with others: @conflicts', ['@conflicts' => new FormattableMarkup($e, [])]));
    }
    return new RedirectResponse($search_api_server->toUrl('canonical')->toString());
  }

  /**
   * Provides an XML snippet containing all request dispatcher settings as XML.
   *
   * @param \Drupal\search_api\ServerInterface|null $search_api_server
   *   The Search API server entity.
   *
   * @return string
   *   The XML snippet containing all request dispatcher settings.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSolrconfigRequestDispatcherXml(?ServerInterface $search_api_server = NULL): string {
    /** @var \Drupal\search_api_solr\Controller\SolrRequestDispatcherListBuilder $list_builder */
    $list_builder = $this->getListBuilder('solr_request_dispatcher', $search_api_server);
    return $list_builder->getXml();
  }

  /**
   * Streams solrconfig_requestdispatcher.xml.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function streamSolrconfigRequestDispatcherXml(ServerInterface $search_api_server): Response {
    try {
      return $this->streamXml('solrconfig_requestdispatcher.xml', $this->getSolrconfigRequestDispatcherXml($search_api_server));
    }
    catch (SearchApiSolrConflictingEntitiesException $e) {
      $this->messenger()->addError($this->t('Some enabled parts of the configuration conflict with others: @conflicts', ['@conflicts' => new FormattableMarkup($e, [])]));
    }
    return new RedirectResponse($search_api_server->toUrl('canonical')->toString());
  }

  /**
   * Returns the configuration files names and content.
   *
   * @return array
   *   An associative array of files names and content.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getConfigFiles(): array {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $this->getBackend();
    $connector = $backend->getSolrConnector();
    $solr_branch = $real_solr_branch = $connector->getSolrBranch($this->assumedMinimumVersion);

    // Solr 8.x uses the same schema and solrconf as 7.x. So we can use the same
    // templates and only adjust luceneMatchVersion to 8.
    if ('8.x' === $solr_branch) {
      $solr_branch = '7.x';
    }

    $search_api_solr_conf_path = drupal_get_path('module', 'search_api_solr') . '/solr-conf-templates/' . $solr_branch;
    $solrcore_properties = parse_ini_file($search_api_solr_conf_path . '/solrcore.properties', FALSE, INI_SCANNER_RAW);

    $files = [
      'schema_extra_types.xml' => $this->getSchemaExtraTypesXml(),
      'schema_extra_fields.xml' => $this->getSchemaExtraFieldsXml(),
      'solrconfig_extra.xml' => $this->getSolrconfigExtraXml(),
    ];

    if ('6.x' !== $solr_branch) {
      $files['solrconfig_query.xml'] = $this->getSolrconfigQueryXml();
      $files['solrconfig_requestdispatcher.xml'] = $this->getSolrconfigRequestDispatcherXml();
    }

    // Add language specific text files.
    $list_builder = $this->getListBuilder('solr_field_type');
    $solr_field_types = $list_builder->getEnabledEntities();

    /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type */
    foreach ($solr_field_types as $solr_field_type) {
      $text_files = $solr_field_type->getTextFiles();
      foreach ($text_files as $text_file_name => $text_file) {
        $text_file_name = Utility::completeTextFileName($text_file_name, $solr_field_type);
        $files[$text_file_name] = $text_file;
        $solrcore_properties['solr.replication.confFiles'] .= ',' . $text_file_name;
      }
    }

    $solrcore_properties['solr.luceneMatchVersion'] = $connector->getLuceneMatchVersion($this->assumedMinimumVersion ?: '');
    // @todo
    // $solrcore_properties['solr.replication.masterUrl']
    $solrcore_properties_string = '';
    foreach ($solrcore_properties as $property => $value) {
      $solrcore_properties_string .= $property . '=' . $value . "\n";
    }
    $files['solrcore.properties'] = $solrcore_properties_string;

    // Now add all remaining static files from the conf dir that have not been
    // generated dynamically above.
    foreach (scandir($search_api_solr_conf_path) as $file) {
      if (strpos($file, '.') !== 0) {
        foreach (array_keys($files) as $existing_file) {
          if ($file == $existing_file) {
            continue 2;
          }
        }
        $files[$file] = str_replace(
          ['SEARCH_API_SOLR_MIN_SCHEMA_VERSION', 'SEARCH_API_SOLR_BRANCH'],
          [SolrBackendInterface::SEARCH_API_SOLR_MIN_SCHEMA_VERSION, $real_solr_branch],
          file_get_contents($search_api_solr_conf_path . '/' . $file)
        );
      }
    }

    $connector->alterConfigFiles($files, $solrcore_properties['solr.luceneMatchVersion'], $this->serverId);
    $this->moduleHandler()->alter('search_api_solr_config_files', $files, $solrcore_properties['solr.luceneMatchVersion'], $this->serverId);
    return $files;
  }

  /**
   * Returns a ZipStream of all configuration files.
   *
   * @param \ZipStream\Option\Archive $archive_options
   *   Archive options.
   *
   * @return \ZipStream\ZipStream
   *   The ZipStream that contains all configuration files.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \ZipStream\Exception\FileNotFoundException
   * @throws \ZipStream\Exception\FileNotReadableException
   */
  public function getConfigZip(Archive $archive_options): ZipStream {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $this->getBackend();
    $connector = $backend->getSolrConnector();
    $solr_branch = $connector->getSolrBranch($this->assumedMinimumVersion);

    $zip = new ZipStream('solr_' . $solr_branch . '_config.zip', $archive_options);

    $files = $this->getConfigFiles();

    foreach ($files as $name => $content) {
      $zip->addFile($name, $content);
    }

    return $zip;
  }

  /**
   * Streams a zip archive containing a complete Solr configuration.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function streamConfigZip(ServerInterface $search_api_server): Response {
    $this->setServer($search_api_server);

    try {
      $archive_options = new Archive();
      $archive_options->setSendHttpHeaders(TRUE);

      @ob_clean();
      // If you are using nginx as a webserver, it will try to buffer the
      // response. We have to disable this with a custom header.
      // @see https://github.com/maennchen/ZipStream-PHP/wiki/nginx
      header('X-Accel-Buffering: no');
      $zip = $this->getConfigZip($archive_options);
      $zip->finish();
      @ob_end_flush();

      exit();
    }
    catch (SearchApiSolrConflictingEntitiesException $e) {
      $this->messenger()->addError($this->t('Some enabled parts of the configuration conflict with others: @conflicts', ['@conflicts' => new FormattableMarkup($e, [])]));
    }
    catch (\Exception $e) {
      watchdog_exception('search_api', $e);
      $this->messenger()->addError($this->t('An error occured during the creation of the config.zip. Look at the logs for details.'));
    }

    return new RedirectResponse($search_api_server->toUrl('canonical')->toString());
  }

  /**
   * Provides an XML snippet containing all query cache settings as XML.
   *
   * @param \Drupal\search_api_solr\Controller\string $file_name
   *   The file name.
   * @param \Drupal\search_api_solr\Controller\string $xml
   *   The XML.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   */
  protected function streamXml(string $file_name, string $xml): Response {
    return new Response(
      $xml,
      200,
      [
        'Content-Type' => 'application/xml',
        'Content-Disposition' => 'attachment; filename=' . $file_name,
      ]
    );
  }

  /**
   * Returns a ListBuilder.
   *
   * @param string $entity_type_id
   *   Entity type id.
   * @param \Drupal\search_api\ServerInterface|null $search_api_server
   *   Search API Server.
   *
   * @return \Drupal\search_api_solr\Controller\AbstractSolrEntityListBuilder
   *   A ListBuilder.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getListBuilder(string $entity_type_id, ?ServerInterface $search_api_server = NULL): AbstractSolrEntityListBuilder {
    /** @var \Drupal\search_api_solr\Controller\AbstractSolrEntityListBuilder $list_builder */
    $list_builder = $this->entityTypeManager()->getListBuilder($entity_type_id);
    if ($search_api_server) {
      $list_builder->setServer($search_api_server);
    }
    else {
      $list_builder->setBackend($this->getBackend());
    }
    return $list_builder;
  }

}
