<?php

namespace Drupal\search_api_solr\Solarium\Autocomplete;

use Solarium\Core\Query\AbstractRequestBuilder;
use Solarium\Core\Query\QueryInterface;

/**
 * Autocomplete request builder.
 */
class RequestBuilder extends AbstractRequestBuilder {

  /**
   * Build request for an autocomplete query.
   *
   * @param \Solarium\Component\ComponentAwareQueryInterface $query
   *
   * @return \Solarium\Core\Client\Request
   */
  public function build(QueryInterface $query) {
    $request = parent::build($query);

    foreach ($query->getComponents() as $component) {
      $componentBuilder = $component->getRequestBuilder();
      if ($componentBuilder) {
        $request = $componentBuilder->buildComponent($component, $request);
      }
    }

    return $request;
  }
}
