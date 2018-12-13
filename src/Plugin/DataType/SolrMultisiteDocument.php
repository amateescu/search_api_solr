<?php

namespace Drupal\search_api_solr\Plugin\DataType;

/**
 * Defines the "Solr document" data type.
 *
 * Instances of this class wrap Search API Item objects and allow to deal with
 * items based upon the Typed Data API.
 *
 * @DataType(
 *   id = "solr_multisite_document",
 *   label = @Translation("Solr multisite document"),
 *   description = @Translation("Records from a Solr multisite index."),
 *   definition_class = "\Drupal\search_api_solr\TypedData\SolrDocumentDefinition"
 * )
 */
class SolrMultisiteDocument extends SolrDocument {

  protected $solr_field = 'solr_multisite_field';
  protected $solr_document = 'solr_multisite_document';

}
