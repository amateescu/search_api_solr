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
   * @param Solarium\Core\Query\QueryInterface $query
   *   The Solarium query.
   *
   * @return \Solarium\Core\Client\Request
   *   The Solarium request.
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
