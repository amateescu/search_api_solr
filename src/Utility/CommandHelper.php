<?php

namespace Drupal\search_api_solr\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Entity\Server;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Provides functionality to be used by CLI tools.
 */
class CommandHelper implements LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CommandHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the "search_api_index" or "search_api_server" entity types are
   *   unknown.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Re-install all Solr Field Types from their yml files.
   */
  public function reinstallFieldtypesCommand() {
    search_api_solr_reinstall_all_field_types();
  }

  /**
   * Gets the config for a Solr search server.
   *
   * @param string $server_id
   *   The ID of the server.
   * @param string $file_name
   *   The file name of the config zip that should be created.
   * @param string $solr_version
   *   The targeted Solr version.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \ZipStream\Exception\FileNotFoundException
   * @throws \ZipStream\Exception\FileNotReadableException
   */
  public function getServerConfigCommand($server_id, $file_name, $solr_version = NULL) {
    /** @var \Drupal\search_api_solr\Controller\SolrFieldTypeListBuilder $list_builder */
    $list_builder = $this->entityTypeManager->getListBuilder('solr_field_type');
    $server = Server::load($server_id);
    if ($solr_version) {
      $config = $server->getBackendConfig();
      // Temporarily switch the Solr version but don't save!
      $config['connector_config']['solr_version'] = $solr_version;
      $server->setBackendConfig($config);
    }
    $list_builder->setServer($server);
    @ob_end_clean();
    ob_start();
    $zip = $list_builder->getConfigZip();
    $zip->finish();
    file_put_contents($file_name, ob_get_clean());
  }

  /**
   * Finalizes one ore more indexes.
   *
   * @param string[]|null $indexIds
   *   (optional) An array of index IDs, or NULL if we should finalize all
   *   enabled indexes.
   * @param bool $force
   *   (optional) Force the finalization, even if the index isn't "dirty".
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function finalizeIndexCommand(array $indexIds = NULL, $force = FALSE) {
    $servers = search_api_solr_get_servers();

    if ($force) {
      // It's important to mark all indexes as "dirty" before the first
      // finalization runs because there might be dependencies between the
      // indexes. Therefor we do the loop two times.
      foreach ($servers as $server) {
        /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
        $backend = $server->getBackend();
        foreach ($server->getIndexes() as $index) {
          if ($index->status() && !$index->isReadOnly() && (!$indexIds || in_array($index->id(), $indexIds))) {
            \Drupal::state()->set('search_api_solr.' . $index->id() . '.last_update', \Drupal::time()->getRequestTime());
          }
        }
      }
    }

    foreach ($servers as $server) {
      /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
      $backend = $server->getBackend();
      foreach ($server->getIndexes() as $index) {
        var_dump($index->id());
        if ($index->status() && !$index->isReadOnly() && (!$indexIds || in_array($index->id(), $indexIds))) {
          $backend->finalizeIndex($index);
        }
      }
    }
  }

}
