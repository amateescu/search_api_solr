<?php

/**
 * @file
 * Contains \Drupal\as_search\Plugin\search_api\backend\ASSearchApiSolrBackend.
 */

namespace Drupal\apachesolr_multilingual\Plugin\search_api\backend;

use Drupal\Core\Language\LanguageInterface;
use Drupal\apachesolr_multilingual\Utility\Utility as SearchApiSolrMultilingualUtility;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Solarium\Core\Client\Response;
use Solarium\QueryType\Select\Query\FilterQuery;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Result;

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

    $language_ids = $this->getLanguageIdFiltersFromQuery($solarium_query, $query);

    // @todo the configuration doesn't exist yet.
    $this->configuration['asm_limit_search_to_content_language'] = TRUE;

    if (empty($language_ids) && $this->configuration['asm_limit_search_to_content_language']) {
      $language_ids[] = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    }

    if (!empty($language_ids)) {
      $edismax = $solarium_query->getEDisMax();
      $query_fields = $edismax->getQueryFields();

      $index = $query->getIndex();
      $fulltext_fields = $index->getFulltextFields(TRUE);
      $multiple_field_names = $this->getFieldNames($index);
      $single_field_names = $this->getFieldNames($index, TRUE);

      foreach($fulltext_fields as $fulltext_field) {
        foreach ([$single_field_names[$fulltext_field], $multiple_field_names[$fulltext_field]] as $field_name) {
          $boost = '';
          if (preg_match('@' . $field_name . '(^[\d.]?)@', $query_fields, $matches)) {
            $boost = $matches[1];
          }

          $language_specific_fields = [];
          foreach ($language_ids as $language_id) {
            $language_specific_fields[] = SearchApiSolrMultilingualUtility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($field_name, $language_id) . $boost;
          }

          $query_fields = str_replace(
            $field_name,
            implode(',', $language_specific_fields),
            $query_fields
          );
        }

        $edismax->setQueryFields($query_fields);

        $language_filters = [];
        foreach ($language_ids as $language_id) {
          $language_filters[] = '+' . $single_field_names['search_api_language'] . ':' . $language_id;
        }
        $fq = new FilterQuery();
        $fq->setKey('asm_language_filter');
        $fq->setQuery(implode(' ', $language_filters));
        $solarium_query->addFilterQuery($fq);
      }
    }
  }

  /**
   * @inheritdoc
   */
  protected function extractResults(QueryInterface $query, Result $result) {
    if ($this->configuration['retrieve_data']) {
      $language_ids = $this->getLanguageIdFiltersFromQuery($result->getQuery(), $query);
      $index = $query->getIndex();
      $single_field_names = $this->getFieldNames($index, TRUE);
      $data = $result->getData();
      foreach ($data['response']['docs'] as &$doc) {
        $language_id = $doc[$single_field_names['search_api_language']];
        foreach (array_keys($doc) as $language_specific_field_name) {
          $field_name = SearchApiSolrMultilingualUtility::getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($language_specific_field_name);
          if ($field_name != $language_specific_field_name) {
            if (SearchApiSolrMultilingualUtility::getLangaugeIdFromLanguageSpecificSolrDynamicFieldName($language_specific_field_name) == $language_id) {
              $doc[$field_name] = $doc[$language_specific_field_name];
            }
            unset($doc[$language_specific_field_name]);
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
    $multiple_field_names = $this->getFieldNames($index);
    $single_field_names = $this->getFieldNames($index, TRUE);
    $fulltext_field_names = array_filter(array_flip($multiple_field_names) + array_flip($single_field_names),
      function($value) use ($fulltext_fields) {
        return in_array($value, $fulltext_fields);
      });

    foreach ($documents as $document) {
      $fields = $document->getFields();
      $language_id = $fields[$single_field_names['search_api_language']];
      foreach ($fields as $field_name => $field_value) {
        if (array_key_exists($field_name, $fulltext_field_names)) {
          $document->addField(SearchApiSolrMultilingualUtility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($field_name, $language_id), $field_value, $document->getFieldBoost($field_name));
          // @todo removal should be configurable
          $document->removeField($field_name);
        }
      }
    }
  }

  protected function getLanguageIdFiltersFromQuery(Query $solarium_query, QueryInterface $query) {
    $language_ids = [];
    $multiple_field_names = $this->getFieldNames($query->getIndex());
    $single_field_names = $this->getFieldNames($query->getIndex(), TRUE);
    $filter_queries = $solarium_query->getFilterQueries();
    foreach ($filter_queries as $filter_query) {
      $query_string = $filter_query->getQuery();
      foreach ([$single_field_names['search_api_language'], $multiple_field_names['search_api_language']] as $field_name) {
        if (preg_match_all('@' . $field_name . ':(.+?)\b@', $query_string, $matches)) {
          foreach ($matches as $match) {
            $language_ids[] = trim($match[1], '"');
          }
        }
      }
    }
    return array_unique($language_ids);
  }

}
