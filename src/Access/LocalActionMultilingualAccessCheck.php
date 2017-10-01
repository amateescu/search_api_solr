<?php

namespace Drupal\search_api_solr\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrMultilingualBackendInterface;

/**
 * Checks access for displaying multilingual Solr configuration actions.
 */
class LocalActionMultilingualAccessCheck extends LocalActionAccessCheck {

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function access(AccountInterface $account, ServerInterface $search_api_server = NULL) {
    if ($search_api_server && $search_api_server->getBackend() instanceof SolrMultilingualBackendInterface) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
