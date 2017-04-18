<?php

namespace Drupal\search_api_solr_multilingual\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\search_api_solr_multilingual\Plugin\search_api\backend\AbstractSearchApiSolrMultilingualBackend;
use Drupal\search_api\ServerInterface;

/**
 * Checks access for displaying Solr configuration generator actions.
 */
class LocalActionAccessCheck implements AccessInterface {

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function access(AccountInterface $account, ServerInterface $search_api_server = NULL) {
    if ($search_api_server && $search_api_server->getBackend() instanceof AbstractSearchApiSolrMultilingualBackend) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
