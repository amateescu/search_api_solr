<?php

namespace Drupal\search_api_solr\Solarium;

use Solarium\Component\ComponentAwareQueryInterface;
use Solarium\Component\QueryTraits\TermsTrait;
use Solarium\QueryType\Select\Query\Query;

class AutocompleteQuery extends Query
{
  use TermsTrait;

  public function __construct($options = null)
  {
    parent::__construct($options);

    $this->componentTypes[ComponentAwareQueryInterface::COMPONENT_TERMS] = 'Solarium\Component\Terms';

    $this->setHandler('autocomplete');
  }
}
