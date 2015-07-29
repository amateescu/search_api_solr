<?php

/**
 * @file
 * Contains \Drupal\as_search\Plugin\search_api\backend\ASSearchApiSolrBackend.
 */

namespace Drupal\apachesolr_multilingual\Plugin\search_api\backend;

use Drupal\Core\Language\LanguageInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Solarium\Core\Client\Response;
use Solarium\QueryType\Select\Query\FilterQuery;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Result;

define('SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR', '_' . bin2hex('|') . '_');

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
      $query_fields = str_replace(
        $field_names[$fulltext_field],
        $this->getMultilingualSolrFieldName($field_names[$fulltext_field], $language_id),
        $query_fields
      );
    }

    $edismax->setQueryFields($query_fields);

    $fq = new FilterQuery();
    $fq->setKey('i18n_' . $language_id);
    $fq->setQuery($field_names['search_api_language'] . ':' . $language_id);
    $solarium_query->addFilterQuery($fq);
  }

  /**
   * @inheritdoc
   */
  protected function extractResults(QueryInterface $query, Result $result) {
    if ($this->configuration['retrieve_data']) {
      $data = $result->getData();
      foreach ($data['docs'] as &$doc) {
        foreach (array_keys($doc) as $field_name) {
          $new_field_name = preg_replace('/^([a-z]+)' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . '.*?' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . '/', '$1', $field_name);
          if ($new_field_name != $field_name) {
            $doc[$new_field_name] = $doc[$field_name];
            unset($doc[$field_name]);
          }
        }
      }

      $new_response = new Response(json_encode($data), $result->getResponse()->getHeaders());
      $result = new Result(NULL, $result->getQuery(), $new_response);
    }

    return parent::extractResults($query, $result);
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

   /**
   * Applies custom modifications to indexed Solr documents.
   *
   * This method allows subclasses to easily apply custom changes before the
   * documents are sent to Solr. The method is empty by default.
   *
   * @param \Solarium\QueryType\Update\Query\Document\Document[] $documents
   *   An array of \Solarium\QueryType\Update\Query\Document\Document objects
   *   ready to be indexed, generated from $items array.
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index for which items are being indexed.
   * @param array $items
   *   An array of items being indexed.
   *
   * @see hook_search_api_solr_documents_alter()
   */
  protected function alterSolrDocuments(array &$documents, IndexInterface $index, array $items) {
    parent::alterSolrDocuments($documents, $index, $items);

    $fulltext_fields = $index->getFulltextFields(TRUE);
    $field_names = $this->getFieldNames($index);
    $fulltext_field_names = array_flip(array_filter(array_flip($field_names),
      function($key) use ($fulltext_fields) {
        return in_array($key, $fulltext_fields);
      }));

    foreach ($documents as $document) {
      $fields = $document->getFields();
      $language_id = $fields[$field_names['search_api_language']];
      foreach ($fields as $field_name => $field_value) {
        if (in_array($field_name, $fulltext_field_names)) {
          $document->addField($this->getMultilingualSolrFieldName($field_name, $language_id), $field_value, $document->getFieldBoost($field_name));
        }
      }
    }
  }

  /**
   * Maps a solr field name to its language specific equivalent.
   *
   * For example the dynamic field tm_* will become tm_|_i18n_en_|_* for English.
   * Following this pattern we also have fallbacks automatically:
   * - tm_|_i18n_de_AT_|_*
   * - tm_|_i18n_de_|_*
   * - tm_*
   * This concept bases on the fact that "longer patterns will be matched first.
   * If equal size patterns both match,the first appearing in the schema will be
   * used." See https://wiki.apache.org/solr/SchemaXml#Dynamic_fields
   *
   * @param type $field_name
   * @param type $language_id
   * @return type
   */
  protected function getMultilingualSolrFieldName($field_name, $language_id) {
    return preg_replace('/^([a-z]+)/', '$1' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . 'i18n_' . $language_id . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR, $field_name);
  }
}
