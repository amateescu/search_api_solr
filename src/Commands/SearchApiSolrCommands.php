<?php

namespace Drupal\search_api_solr\Commands;

use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\SolrCloudConnectorInterface;
use Drupal\search_api_solr\Utility\CommandHelper;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerInterface;

/**
 * Defines Drush commands for the Search API Solr.
 */
class SearchApiSolrCommands extends DrushCommands implements StdinAwareInterface {

  use StdinAwareTrait;

  /**
   * The command helper.
   *
   * @var \Drupal\search_api_solr\Utility\CommandHelper
   */
  protected $commandHelper;

  /**
   * Constructs a SearchApiSolrCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler) {
    parent::__construct();
    $this->commandHelper = new CommandHelper($entityTypeManager, $moduleHandler, 'dt');
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
   * Install missing Solr Field Types from their yml files.
   *
   * @command search-api-solr:install-missing-fieldtypes
   *
   * @usage drush search-api-solr:install-missing-fieldtypes
   *   Install missing Solr Field Types.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an index has a server which couldn't be loaded.
   */
  public function installMissingFieldtypes() {
    search_api_solr_install_missing_field_types();
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
   * @throws \Drupal\search_api\SearchApiException
   * @throws \ZipStream\Exception\FileNotFoundException
   * @throws \ZipStream\Exception\FileNotReadableException
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
   * @usage drush search-api-solr:finalize-index node_index --force
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

  /**
   * Executes a streaming expression from STDIN.
   *
   * @command search-api-solr:execute-raw-streaming-expression
   *
   * @param string $indexId
   *   A search index ID.
   * @param mixed $expression
   *   The streaming expression. Use '-' to read from STDIN.
   * @usage drush search-api-solr:execute-streaming-expression node_index - < streaming_expression.txt
   *  Execute the raw streaming expression in streaming_expression.txt
   *
   * @aliases solr-erse
   *
   * @return string
   *   The JSON encoded raw streaming expression result
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function executeRawStreamingExpression($indexId, $expression)
  {
    // Special flag indicating that the value has been passed via STDIN.
    if ($expression === '-') {
      $expression = $this->stdin()->contents();
    }

    if (!$expression) {
      throw new SearchApiSolrException('No streaming expression provided.');
    }

    $indexes = $this->commandHelper->loadIndexes([$indexId]);
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = reset($indexes);

    if (!$index) {
      throw new SearchApiSolrException('Failed to load index.');
    }

    if (!$index->status()) {
      throw new SearchApiSolrException('Index is not enabled.');
    }

    if ($server = $index->getServerInstance()) {
      /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
      $backend = $server->getBackend();

      if (!($backend instanceof SolrBackendInterface) || !($backend->getSolrConnector() instanceof SolrCloudConnectorInterface)) {
        throw new SearchApiSolrException('The index must be located on Solr Cloud to execute streaming expressions.');
      }

      $queryHelper = \Drupal::service('search_api_solr.streaming_expression_query_helper');
      $query = $queryHelper->createQuery($index);
      $queryHelper->setStreamingExpression($query,
        $expression,
        basename(__FILE__) . ':' . __LINE__
      );
      $result = $backend->executeStreamingExpression($query);

      return $result->getBody();
    }

    throw new SearchApiSolrException('Server could not be loaded.');
  }
}
