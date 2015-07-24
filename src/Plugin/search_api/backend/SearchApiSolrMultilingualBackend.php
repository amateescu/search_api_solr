<?php

/**
 * @file
 * Contains \Drupal\as_search\Plugin\search_api\backend\ASSearchApiSolrBackend.
 */

namespace Drupal\apachesolr_multilingual\Plugin\search_api\backend;

use Drupal\Core\Language\LanguageInterface;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Solarium\QueryType\Select\Query\FilterQuery;
use Solarium\QueryType\Select\Query\Query;

/**
 * @SearchApiBackend(
 *   id = "search_api_solr_multilingual",
 *   label = @Translation("Solr Multilingual"),
 *   description = @Translation("Index items using an Apache Solr Multilingual search server.")
 * )
 */
class SearchApiSolrMultilingualBackend extends SearchApiSolrBackend {

  /**
   * Modify the query before sent to solr.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The Solarium select query object.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   */
  protected function preQuery(Query $solarium_query, QueryInterface $query) {
    parent::preQuery($solarium_query, $query);

    // @todo $language_id needs to be set dynamically from filter, config,
    //   facet, whatever ...
    $language_id = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    $edismax = $solarium_query->getEDisMax();
    $query_fields = $edismax->getQueryFields();

    $index = $query->getIndex();
    $fulltext_fields = $index->getFulltextFields(TRUE);
    $field_names = $this->getFieldNames($index);

    foreach($fulltext_fields as $fulltext_field) {
      $query_fields = str_replace($field_names[$fulltext_field], 'i18n_' . $language_id . '_' . $field_names[$fulltext_field], $query_fields);
    }

    $edismax->setQueryFields($query_fields);

    $fq = new FilterQuery();
    $fq->setKey('i18n_language');
    $fq->setQuery($field_names['search_api_language'] . ':' . $language_id);
    $solarium_query->addFilterQuery($fq);
  }

  /**
   * Modify the the solr result set.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The results array that will be returned for the search.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   * @param object $response
   *   The response object returned by Solr.
   */
  protected function postQuery(ResultSetInterface $results, QueryInterface $query, $response) {
    parent::postQuery($results, $query, $response);
  }

}
