<?php

namespace Drupal\search_api_solr_cloud_test\Plugin\SolrConnector;

use Drupal\search_api_solr\Plugin\SolrConnector\BasicAuthSolrCloudConnector;
use Drupal\search_api_solr\Utility\Utility;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\Request;
use Solarium\Core\Client\Response;
use Solarium\Core\Query\QueryInterface;
use Solarium\Core\Query\Result\Result;

/**
 * Basic auth Solr test connector.
 *
 * @SolrConnector(
 *   id = "solr_cloud_basic_auth_test",
 *   label = @Translation("Solr Cloud Basic Auth Test"),
 *   description = @Translation("A connector usable for Solr installations protected by basic authentication.")
 * )
 */
class BasicAuthTestSolrCloudConnector extends BasicAuthSolrCloudConnector {

  /** @var QueryInterface $query */
  protected static $query;

  /** @var \Solarium\Core\Client\Request $request */
  protected static $request;

  /** @var bool $intercept */
  protected $intercept = FALSE;

  /**
   * {@inheritdoc}
   */
  public function execute(QueryInterface $query, Endpoint $endpoint = NULL) {
    self::$query = $query;

    if ($this->intercept) {
      /** @var \Solarium\Core\Query\AbstractQuery $query */
      return new Result($query, new Response(''));
    }

    return parent::execute($query, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function executeRequest(Request $request, Endpoint $endpoint = NULL) {
    self::$request = $request;

    if ($this->intercept) {
      return new Response('');
    }

    return parent::executeRequest($request, $endpoint);
  }

  public function getQuery() {
    return self::$query;
  }

  public function getRequest() {
    return self::$request;
  }

  public function getRequestParams() {
    return Utility::parseRequestParams(self::$request);
  }

  public function setIntercept(bool $intercept) {
    $this->intercept = $intercept;
  }

}
