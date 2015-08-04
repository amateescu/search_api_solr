<?php
/**
 * @file
 * Contains \Drupal\example\Access\CustomAccessCheck.
 */

namespace Drupal\apachesolr_multilingual\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\apachesolr_multilingual\Plugin\search_api\backend\SearchApiSolrMultilingualBackend;
use Drupal\search_api\ServerInterface;

/**
 * Checks access for displaying configuration translation page.
 */
class LocalActionAccessCheck implements AccessInterface {

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function access(AccountInterface $account, ServerInterface $search_api_server = NULL) {
    if ($search_api_server && $search_api_server->getBackend() instanceof SearchApiSolrMultilingualBackend) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }
}
