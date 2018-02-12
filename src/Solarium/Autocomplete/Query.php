<?php

namespace Drupal\search_api_solr\Solarium\Autocomplete;

use Solarium\Component\ComponentAwareQueryInterface;
use Solarium\Component\ComponentAwareQueryTrait;
use Solarium\Component\QueryTraits\SpellcheckTrait;
use Solarium\Component\QueryTraits\SuggesterTrait;
use Solarium\Component\QueryTraits\TermsTrait;
use Solarium\Core\Query\AbstractQuery;

/**
 * Autocomplete query.
 */
class Query extends AbstractQuery implements ComponentAwareQueryInterface {

  use ComponentAwareQueryTrait;
  use SpellcheckTrait;
  use SuggesterTrait;
  use TermsTrait;

  /**
   * Default options.
   *
   * @var array
   */
  protected $options = [
    'handler' => 'autocomplete',
    'resultclass' => 'Drupal\search_api_solr\Solarium\Autocomplete\Result',
  ];

  public function __construct($options = null) {
    $this->componentTypes = [
      ComponentAwareQueryInterface::COMPONENT_SPELLCHECK => 'Solarium\Component\Spellcheck',
      ComponentAwareQueryInterface::COMPONENT_SUGGESTER => 'Solarium\Component\Suggester',
      ComponentAwareQueryInterface::COMPONENT_TERMS => 'Solarium\Component\Terms',
    ];

    parent::__construct($options);
  }

  /**
   * Get type for this query.
   *
   * @return string
   */
  public function getType() {
    return 'autocomplete';
  }

  /**
   * Get a requestbuilder for this query.
   *
   * @return RequestBuilder
   */
  public function getRequestBuilder() {
    return new RequestBuilder();
  }

  /**
   * Get a response parser for this query.
   *
   * @return ResponseParser
   */
  public function getResponseParser() {
    return new ResponseParser();
  }
}
