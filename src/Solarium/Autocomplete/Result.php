<?php

namespace Drupal\search_api_solr\Solarium\Autocomplete;

use Solarium\Component\ComponentAwareQueryInterface;
use Solarium\Component\Result\Spellcheck\Result as SpellcheckResult;
use Solarium\Component\Result\Suggester\Result as SuggesterResult;
use Solarium\Component\Result\Terms\Result as TermsResult;
use Solarium\Core\Query\Result\QueryType as BaseResult;

/**
 * Autocomplete query result.
 */
class Result extends BaseResult {

  /**
   * Component results.
   */
  protected $components;

  /**
   * Get all component results.
   *
   * @return array
   */
  public function getComponents() {
    $this->parseResponse();

    return $this->components;
  }

  /**
   * Get a component result by key.
   *
   * @param string $key
   *
   * @return mixed
   */
  public function getComponent($key) {
    $this->parseResponse();

    if (isset($this->components[$key])) {
      return $this->components[$key];
    }

    return null;
  }

  /**
   * Get spellcheck component result.
   *
   * This is a convenience method that maps presets to getComponent
   *
   * @return SpellcheckResult|null
   */
  public function getSpellcheck() {
    return $this->getComponent(ComponentAwareQueryInterface::COMPONENT_SPELLCHECK);
  }

  /**
   * Get suggester component result.
   *
   * This is a convenience method that maps presets to getComponent
   *
   * @return SuggesterResult|null
   */
  public function getSuggester() {
    return $this->getComponent(ComponentAwareQueryInterface::COMPONENT_SUGGESTER);
  }

  /**
   * Get terms component result.
   *
   * This is a convenience method that maps presets to getComponent
   *
   * @return TermsResult|null
   */
  public function getTerms() {
    return $this->getComponent(ComponentAwareQueryInterface::COMPONENT_TERMS);
  }
}
