<?php

namespace Drupal\search_api_solr\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api_solr\Utility\CommandHelper;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerInterface;

/**
 * Defines Drush commands for the Search API Solr.
 */
class SearchApiSolrCommands extends DrushCommands {

  /**
   * The command helper.
   *
   * @var \Drupal\search_api_solr\Utility\CommandHelper
   */
  protected $commandHelper;

  /**
   * Constructs a SearchApiCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->commandHelper = new CommandHelper($entityTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(LoggerInterface $logger) {
    parent::setLogger($logger);
    $this->commandHelper->setLogger($logger);
  }

  /**
   * Re-install Solr Field Types from their yml files.
   *
   * @command search-api-solr:reinstall-fieldtypes
   *
   * @usage drush search-api-solr:reinstall-fieldtypes
   *   Deletes all Solr Field Type and re-installs them from their yml files.
   *
   * @aliases solr-reinstall-ft,sasm-reinstall-ft,search-api-solr-delete-and-reinstall-all-field-types,search-api-solr-multilingual-delete-and-reinstall-all-field-types
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an index has a server which couldn't be loaded.
   */
  public function reinstallFieldtypes() {
    $this->commandHelper->reinstallFieldtypesCommand();
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
   * @command search-api-solr:get-server-config
   *
   * @usage drush search-api-solr:get-server-config server_id file_name
   *   Get the config files for a solr server and save it as zip file.
   *
   * @aliases solr-gsc,sasm-gsc,search-api-solr-get-server-config,search-api-solr-multilingual-get-server-config
   *
   * @throws \Drupal\search_api\ConsoleException
   *   Thrown if no indexes could be loaded.
   */
  public function getServerConfig($server_id, $file_name, $solr_version = NULL) {
    $this->commandHelper->getServerConfigCommand($server_id, $file_name, $solr_version);
  }


  /**
   * Indexes items for one or all enabled search indexes.
   *
   * @param string $indexId
   *   (optional) A search index ID, or NULL to index items for all enabled
   *   indexes.
   *
   * @command search-api-solr:finalize-index
   *
   * @option force
   *   Force the finalization, even if the index isn't "dirty".
   *   Defaults to FALSE.
   *
   * @usage drush search-api-solr:finalize-index
   *   Finalize all enabled indexes.
   * @usage drush search-api-solr:finalize-index node_index
   *   Finalize the index with the ID node_index.
   * @usage drush search-api-solr:finalize-index node_index force
   *   Index a maximum number of 100 items for the index with the ID node_index.
   *
   * @aliases solr-finalize
   *
   * @throws \Exception
   *   If a batch process could not be created.
   */
  public function finalizeIndex($indexId = NULL, array $options = ['force' => FALSE]) {
    $force = (bool) $options['force'];
    $this->commandHelper->finalizeIndexCommand($indexId ? [$indexId] : $indexId, $force);
  }
}
