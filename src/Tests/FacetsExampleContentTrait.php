<?php

namespace Drupal\search_api_solr\Tests;

/**
 * Contains helpers to create data that can be used by tests.
 */
trait FacetsExampleContentTrait {

  use \Drupal\facets\Tests\ExampleContentTrait {
    indexItems as doIndexItems;
  }

  /**
   * Indexes all (unindexed) items on the specified index.
   *
   * @param string $index_id
   *   The ID of the index on which items should be indexed.
   *
   * @return int
   *   The number of successfully indexed items.
   */
  protected function indexItems($index_id) {
    $index_status = $this->doIndexItems($index_id);
    sleep(2);
    return $index_status;
  }

}
