<?php

namespace Drupal\search_api_solr\Solarium\Autocomplete;

use Solarium\Core\Query\AbstractResponseParser;
use Solarium\Core\Query\ResponseParserInterface;

/**
 * Autocomplete response parser.
 */
class ResponseParser extends AbstractResponseParser implements ResponseParserInterface {

  /**
   * Get result data for the response.
   *
   *
   * @param Result $result
   *
   * @throws \RuntimeException
   *
   * @return array
   */
  public function parse($result) {
    $data = $result->getData();
    /** @var Query $query */
    $query = $result->getQuery();

    $components = [];
    foreach ($query->getComponents() as $component) {
      $componentParser = $component->getResponseParser();
      if ($componentParser) {
        $components[$component->getType()] = $componentParser->parse($query, $component, $data);
      }
    }

    return $this->addHeaderInfo(
      $data,
      [
        'components' => $components,
      ]
    );
  }
}
