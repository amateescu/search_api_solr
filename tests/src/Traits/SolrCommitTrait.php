<?php

namespace Drupal\Tests\search_api_solr\Traits;

use Drupal\search_api\ServerInterface;

defined('SOLR_INDEX_WAIT') || define('SOLR_INDEX_WAIT', getenv('SOLR_INDEX_WAIT') ?: 2);

/**
 * Helper to ensure that solr index is up to date.
 */
trait SolrCommitTrait {

  /**
   * Avoid random test failures due to race conditions.
   */
  protected function ensureCommit(ServerInterface $server) {
    $backend = $server->getBackend();
    /** @var \Drupal\search_api_solr\SolrConnectorInterface $connector */
    $connector = $backend->getSolrConnector();
    $update = $connector->getUpdateQuery();
    $update->addCommit(TRUE, TRUE, TRUE);
    $connector->update($update);
    sleep(SOLR_INDEX_WAIT);
  }
}
