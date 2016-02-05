<?php
/**
 * @file
 * Contains \Drupal\search_api_solr\Solr\StatsJspResult.
 *
 * Implement a helper class to handle responses from Solr 3.x.
 *
 * The /admin/stats.jsp (e.g. http://localhost/solr/admin/stats.jsp) handler in
 * Solr 3.5 responds with an XML response. This class provides a getData() method
 * which is using the SimpleXML library can parse this response.
 *
 * @see \Drupal\search_api_solr\Solr\StatsJspResult::getData()
 */

namespace Drupal\search_api_solr\Solr;

use Solarium\Exception\HttpException;
use Solarium\Core\Query\Result\ResultInterface;
use Solarium\Core\Client\Client;
use Solarium\Core\Query\AbstractQuery;
use Solarium\Core\Client\Response;

/**
 * Provides a result handler and XML parser for Solr 3.5.
 */
class StatsJspResult implements ResultInterface {
  /**
   * The response object.
   *
   * @var \Solarium\Core\Client\Response
   */
  protected $response;

  /**
   * The decoded, lazy loaded response data.
   *
   * @see \Drupal\search_api_solr\Solr\StatsJspResult::getData()
   *
   * @var array
   */
  protected $data;

  /**
   * The query used for this request.
   *
   * @var \Solarium\Core\Query\AbstractQuery
   */
  protected $query;

  /**
   * The Solarium client instance.
   *
   * @var \Solarium\Core\Client\Client
   */
  protected $client;

  /**
   * Constructs an instance of StatsJSPResult.
   *
   * @param \Solarium\Core\Client\Client $client
   *   The Solr client object.
   * @param \Solarium\Core\Query\AbstractQuery $query
   *   The query that is being executed.
   * @param \Solarium\Core\Client\Response $response
   *   The response object we are getting back from the endpoint.
   *
   * @throws \Solarium\Exception\HttpException
   *   Throws an error, when response has an error code starting with 4 or 5.
   */
  public function __construct(Client $client, AbstractQuery $query, Response $response) {
    $this->client = $client;
    $this->query = $query;
    $this->response = $response;

    // Checks the response status for error (range of 400 and 500).
    $status_num = floor($response->getStatusCode() / 100);
    if ($status_num == 4 || $status_num == 5) {
      throw new HttpException(
        $response->getStatusMessage(),
        $response->getStatusCode(),
        $response->getBody()
      );
    }
  }

  /**
   * Gets the response object.
   *
   * This is the raw HTTP response object, not the parsed data.
   *
   * @return \Solarium\Core\Client\Response
   *   The response returned from the query.
   */
  public function getResponse() {
    return $this->response;
  }

  /**
   * Gets the query instance.
   *
   * @return \Solarium\Core\Query\AbstractQuery
   *   The query defined for the request.
   */
  public function getQuery() {
    return $this->query;
  }

  /**
   * Gets the Solr response data.
   *
   * Includes a lazy loading mechanism: JSON body data is decoded on first use
   * and then saved for reuse.
   *
   * @return array
   *   The parsed response data.
   */
  public function getData() {
    if (NULL === $this->data) {
      $this->data = new \SimpleXMLElement($this->response->getBody());
    }
    return $this->data;
  }

  /**
   * Gets the core information.
   *
   * @return string
   *   The name of the core accessed by this query.
   */
  public function getCore() {
    return $this->getData()->core;
  }

  /**
   * Gets the core schema information.
   *
   * @return string
   *   The schema for the index accessed by this query.
   */
  public function getSchema() {
    return $this->getData()->schema;
  }

  /**
   * Gets the hostname for the index.
   *
   * @return string
   *   The name of the host for the index accessed by this query.
   */
  public function getHost() {
    return $this->getData()->host;
  }

  /**
   * Gets the response timestamp.
   *
   * @return string
   *   The timestamp for the request as captured by the response.
   */
  public function getNow() {
    return $this->getData()->now;
  }

  /**
   * Gets the timestamp when the request was started.
   *
   * @return string
   *   The start timestamp for the request.
   */
  public function getStart() {
    return $this->getData()->start;
  }

  /**
   * Gets the solr-info part of the response.
   *
   * @return \SimpleXMLElement
   *   The solr information as an XML object.
   */
  public function getSolrInfo() {
    return $this->getData()->{'solr-info'};
  }

}
