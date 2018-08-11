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
   * Deletes all Solr Field Type and re-installs them from their yml files.
   */
  public function reinstallFieldtypesCommand() {
    search_api_solr_delete_and_reinstall_all_field_types();
  }

  /**
   * Gets the config far a Solr search server.
   *
   * @param string $server_id
   *   The ID of the server.
   * @param string $file_name
   *   The file name of the config zip that should be created.
   * @param string $solr_version
   *   The targeted Solr version.
   *
   * @throws \Drupal\search_api\SearchApiException
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

}
