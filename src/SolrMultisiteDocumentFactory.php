<?php

namespace Drupal\search_api_solr;

use Drupal\search_api\Item\ItemInterface;

/**
 * Defines a class for a Solr Document factory.
 */
class SolrMultisiteDocumentFactory extends SolrDocumentFactory {

  protected static $solr_document = 'solr_multisite_document';

}
