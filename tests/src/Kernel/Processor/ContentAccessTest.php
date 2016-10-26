<?php

namespace Drupal\Tests\search_api_solr\Kernel\Processor;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Database\Database;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\ResultsTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the "Content access" processor.
 *
 * @group search_api_solr
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\ContentAccess
 */
class ContentAccessTest extends \Drupal\Tests\search_api\Kernel\Processor\ContentAccessTest  {

  use SolrBackendTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'search_api_solr',
    'search_api_solr_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL) {
    parent::setUp();
    $this->enableSolrServer('search_api_solr_test', '/config/install/search_api.server.solr_search_server.yml');
  }

}
