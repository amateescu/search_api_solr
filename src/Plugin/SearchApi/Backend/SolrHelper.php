<?php
/**
 * Created by PhpStorm.
 * User: mattias
 * Date: 3/10/14
 * Time: 16:16
 */

namespace Drupal\search_api_solr\Plugin\SearchApi\Backend;


use Solarium\Core\Client\Client;
use Solarium\Core\Query\Helper;
use Solarium\Exception\HttpException;
use Solarium\QueryType\Select\Query\Query;

class SolrHelper {

  /**
   * Request handler to use for this search query.
   *
   * @var string
   */
  protected $configuration = array();

  /**
   * A connection to the Solr server.
   *
   * @var \Solarium\Client
   */
  protected $solr;

  /**
   * A Solarium Update query.
   *
   * @var \Solarium\QueryType\Update\Query\Query
   */
  protected $updateQuery;

  /**
   * A Solarium query helper.
   *
   * @var \Solarium\Core\Query\Helper
   */
  protected $queryHelper;

  /**
   * Saves whether a commit operation was already scheduled for this server.
   *
   * @var bool
   */
  protected $commitScheduled = FALSE;

  public function __construct(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * Creates a connection to the Solr server as configured in $this->configuration.
   */
  public function connect() {
    if (!$this->solr) {
      $this->solr = new Client();
      $this->solr->createEndpoint($this->configuration, TRUE);
    }
  }

  /**
   * Ping the Solr server to tell whether it can be accessed.
   *
   * Uses the admin/ping request handler.
   */
  public function ping() {
    $this->connect();
    $query = $this->solr->createPing();

    try {
      $start = microtime(TRUE);
      $result = $this->solr->ping($query);
      if ($result->getResponse()->getStatusCode() == 200) {
        // Add 1 µs to the ping time so we never return 0.
        return (microtime(TRUE) - $start) + 1E-6;
      }
    }
    catch (HttpException $e) {
      // @todo Show a message with the exception?
    }
    return FALSE;
  }

  /**
   * Sends a commit command to the Solr server.
   */
  public function commit() {
    try {
      if ($this->updateQuery) {
        $this->connect();
        $this->getUpdateQuery()->addCommit();
        $this->solr->update($this->getUpdateQuery());
      }
    }
    catch (SearchApiException $e) {
      watchdog_exception('search_api_solr', $e,
        '%type while trying to commit on server @server: !message in %function (line %line of %file).',
        array('@server' => $this->server->label()), WATCHDOG_WARNING);
    }
  }

  /**
   * Schedules a commit operation for this server.
   *
   * The commit will be sent at the end of the current page request. Multiple
   * calls to this method will still only result in one commit operation.
   */
  public function scheduleCommit() {
    if (!$this->commitScheduled) {
      $this->commitScheduled = TRUE;
      drupal_register_shutdown_function(array($this, 'commit'));
    }
  }

  /**
   * Gets the currently used Solr connection object.
   *
   * @return \Solarium\Client
   *   The solr connection object used by this server.
   */
  public function getSolrConnection() {
    $this->connect();
    return $this->solr;
  }

  /**
   * Get metadata about fields in the Solr/Lucene index.
   *
   * @param int $num_terms
   *   Number of 'top terms' to return.
   *
   * @return array
   *   An array of SearchApiSolrField objects.
   *
   * @see SearchApiSolrConnectionInterface::getFields()
   */
  public function getFields($num_terms = 0) {
    $this->connect();
    return $this->solr->getFields($num_terms);
  }

  /**
   * Retrieves a config file or file list from the Solr server.
   *
   * Uses the admin/file request handler.
   *
   * @param string|null $file
   *   (optional) The name of the file to retrieve. If the file is a directory,
   *   the directory contents are instead listed and returned. NULL represents
   *   the root config directory.
   *
   * @return \Solarium\Core\Client\Response
   *   A Solarium response object containing either the file contents or a file
   *   list.
   */
  public function getFile($file = NULL) {
    $this->connect();

    $query = $this->solr->createPing();
    $query->setHandler('admin/file');
    $query->addParam('contentType', 'text/xml;charset=utf-8');
    if ($file) {
      $query->addParam('file', $file);
    }

    return $this->solr->ping($query)->getResponse();
  }

  /**
   * Gets the current Solarium update query, creating one if necessary.
   *
   * @return \Solarium\QueryType\Update\Query\Query
   *   The Update query.
   */
  public function getUpdateQuery() {
    if (!$this->updateQuery) {
      $this->connect();
      $this->updateQuery = $this->solr->createUpdate();
    }
    return $this->updateQuery;
  }

  /**
   * Sets the Solarium update query.
   *
   * @param \Solarium\QueryType\Update\Query\Query $query
   */
  public function setUpdateQuery(\Solarium\QueryType\Update\Query\Query $query = NULL) {
    $this->updateQuery = $query;
  }

  /**
   * Returns a Solarium query helper object.
   *
   * @param \Solarium\Core\Query\Query|null $query
   *   (optional) A Solarium query object.
   *
   * @return \Solarium\Core\Query\Helper
   *   A Solarium query helper.
   */
  public function getQueryHelper(Query $query = NULL) {
    if (!$this->queryHelper) {
      if ($query) {
        $this->queryHelper = $query->getHelper();
      }
      else {
        $this->queryHelper = new Helper();
      }
    }

    return $this->queryHelper;
  }

  /**
   * Gets the current Solr version.
   *
   * @return int
   *   1, 3 or 4. Does not give a more detailed version, for that you need to
   *   use getSystemInfo().
   */
  public function getSolrVersion() {
    // Allow for overrides by the user.
    if (!empty($this->configuration['solr_version'])) {
      return $this->configuration['solr_version'];
    }

    $system_info = $this->getSystemInfo();
    // Get our solr version number
    if (isset($system_info['lucene']['solr-spec-version'])) {
      return $system_info['lucene']['solr-spec-version'];
    }
    return 0;
  }

  /**
   * Gets information about the Solr Core.
   *
   * @return object
   *   A response object with system information.
   */
  public function getSystemInfo() {
    // @todo Add back persistent cache?
    if (!isset($this->systemInfo)) {
      // @todo Finish https://github.com/basdenooijer/solarium/pull/155 and stop
      // abusing the ping query for this.
      $query = $this->solr->createPing();
      $query->setHandler('admin/system');
      $this->systemInfo = $this->solr->ping($query)->getData();
    }

    return $this->systemInfo;
  }

  /**
   * Gets meta-data about the index.
   *
   * @return object
   *   A response object filled with data from Solr's Luke.
   */
  public function getLuke() {
    // @todo Write a patch for Solarium to have a separate Luke query and stop
    // abusing the ping query for this.
    $query = $this->solr->createPing();
    $query->setHandler('admin/luke');
    return $this->solr->ping($query)->getData();
  }


}
