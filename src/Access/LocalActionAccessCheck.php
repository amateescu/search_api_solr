<?php
/**
 * @file
 * Contains \Drupal\example\Access\CustomAccessCheck.
 */

namespace Drupal\apachesolr_multilingual\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\search_api\Entity\Server;
use Drupal\apachesolr_multilingual\Plugin\search_api\backend\SearchApiSolrMultilingualBackend;

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
  public function access(AccountInterface $account) {
    if ($search_api_server = \Drupal::routeMatch()->getParameter('search_api_server')) {
      if ($search_api_server->getBackend() instanceof SearchApiSolrMultilingualBackend) {
        return new AccessResultAllowed();
      }
    }
    return new AccessResultForbidden();
  }
}
