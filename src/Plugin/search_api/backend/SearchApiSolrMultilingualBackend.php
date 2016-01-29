<?php

/**
 * @file
 * Contains \Drupal\as_search\Plugin\search_api\backend\ASSearchApiSolrBackend.
 */

namespace Drupal\apachesolr_multilingual\Plugin\search_api\backend;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Language\LanguageInterface;
use Drupal\apachesolr_multilingual\Entity\SolrFieldType;
use Drupal\apachesolr_multilingual\Utility\Utility as SearchApiSolrMultilingualUtility;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Drupal\search_api_solr\Utility\Utility as SearchApiSolrUtility;
use Drupal\search_api\SearchApiException;
use Solarium\Core\Client\Response;
use Solarium\Core\Query\Result\ResultInterface;
use Solarium\QueryType\Select\Query\FilterQuery;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Result;

/**
 * The name of the language field might be change in future releases of
 * search_api. @see https://www.drupal.org/node/2641392 for details.
 * Therefor we define a constant here that could be easily changed.
 */
define('SEARCH_API_LANGUAGE_FIELD_NAME', 'search_api_language');

/**
 * @SearchApiBackend(
 *   id = "search_api_solr_multilingual",
 *   label = @Translation("Solr Multilingual"),
 *   description = @Translation("Index items using an Apache Solr Multilingual search server.")
 * )
 */
class SearchApiSolrMultilingualBackend extends SearchApiSolrBackend {

  /**
   * Modify the query before it is sent to solr.
   *
   * Replace all language unspecific fulltext query fields by language specific
   * ones and add a language filter if required.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The Solarium select query object.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   */
  protected function preQuery(Query $solarium_query, QueryInterface $query) {
    parent::preQuery($solarium_query, $query);

    $language_ids = $this->getLanguageIdFiltersFromQuery($solarium_query, $query, TRUE);

    // @todo the configuration doesn't exist yet.
    $this->configuration['asm_limit_search_to_content_language'] = TRUE;

    if (empty($language_ids)) {
      if ($this->configuration['asm_limit_search_to_content_language']) {
        $language_ids[] = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
      }
      else {
        foreach (\Drupal::languageManager()->getLangauges() as $language) {
          $language_ids[] = $language->getId();
        }
      }
    }

    if (!empty($language_ids)) {
      $edismax = $solarium_query->getEDisMax();
      $query_fields = $edismax->getQueryFields();

      $index = $query->getIndex();
      $fulltext_fields = $index->getFulltextFields();
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
      }

      $fq = new FilterQuery();
      $fq->setKey('asm_language_filter');
      $fq->setQuery($single_field_names[SEARCH_API_LANGUAGE_FIELD_NAME] . ':("' . implode('" OR "', $language_ids) . '")');
      $solarium_query->addFilterQuery($fq);
    }
  }

  /**
   * @inheritdoc
   */
  protected function extractResults(QueryInterface $query, ResultInterface $result) {
    if ($this->configuration['retrieve_data']) {
      $language_ids = $this->getLanguageIdFiltersFromQuery($result->getQuery(), $query);
      $index = $query->getIndex();
      $single_field_names = $this->getFieldNames($index, TRUE);
      $data = $result->getData();
      foreach ($data['response']['docs'] as &$doc) {
        $language_id = $doc[$single_field_names[SEARCH_API_LANGUAGE_FIELD_NAME]];
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
   * Replaces language unspecific fulltext fields by language specific ones.
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

    $fulltext_fields = $index->getFulltextFields();
    $multiple_field_names = $this->getFieldNames($index);
    $single_field_names = $this->getFieldNames($index, TRUE);
    $fulltext_field_names = array_filter(array_flip($multiple_field_names) + array_flip($single_field_names),
      function($value) use ($fulltext_fields) {
        return in_array($value, $fulltext_fields);
      }
    );

    $field_name_map_per_language = [];
    foreach ($documents as $document) {
      $fields = $document->getFields();
      $language_id = $fields[$single_field_names[SEARCH_API_LANGUAGE_FIELD_NAME]];
      foreach ($fields as $monolingual_solr_field_name => $field_value) {
        if (array_key_exists($monolingual_solr_field_name, $fulltext_field_names)) {
          $multilingual_solr_field_name = SearchApiSolrMultilingualUtility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($monolingual_solr_field_name, $language_id);
          $field_name_map_per_language[$language_id][$monolingual_solr_field_name] = $multilingual_solr_field_name;
          $document->addField($multilingual_solr_field_name, $field_value, $document->getFieldBoost($monolingual_solr_field_name));
          // @todo removal should be configurable
          $document->removeField($monolingual_solr_field_name);
        }
      }
    }
    $this->ensureAllMultilingualFieldsExist($field_name_map_per_language, $index);
  }

  /**
   * Get all languages a solarium query is filtered by.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The solarium query.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search_api query the solarium query has been built from.
   * @param bool $remove
   *   Wether the language filters should be removed from the solarium query or
   *   not. Default is FALSE.
   * @return array
   *   Array of language ids.
   */
  protected function getLanguageIdFiltersFromQuery(Query $solarium_query, QueryInterface $query, $remove = FALSE) {
    $language_ids = [];
    $multiple_field_names = $this->getFieldNames($query->getIndex());
    $single_field_names = $this->getFieldNames($query->getIndex(), TRUE);
    $filter_queries = $solarium_query->getFilterQueries();
    foreach ($filter_queries as $filter_query_name => $filter_query) {
      $query_string = $filter_query->getQuery();
      foreach ([$single_field_names[SEARCH_API_LANGUAGE_FIELD_NAME], $multiple_field_names[SEARCH_API_LANGUAGE_FIELD_NAME]] as $field_name) {
        if (preg_match_all('@' . preg_quote($field_name, '@') . ':"(.+?)"@', $query_string, $matches)) {
          foreach ($matches[1] as $match) {
            $language_ids[] = trim($match, '"');
          }
          if ($remove) {
            $solarium_query->removeFilterQuery($filter_query_name);
          }
        }
      }
    }
    return array_unique($language_ids);
  }

  protected function ensureAllMultilingualFieldsExist(array $field_name_map_per_language, IndexInterface $index) {
    foreach ($field_name_map_per_language as $language_id => $map) {
      $field_type_name = 'text' . '_' . $language_id;
      $solr_field_type_name = SearchApiSolrUtility::encodeSolrDynamicFieldName($field_type_name);

      // handle field type
      $this->ensureMultilingualFieldTypeExists($field_type_name, $solr_field_type_name, $index);

      // handle dynamic fields for multilingual tm and ts
      $multilingual_solr_field_name = SearchApiSolrUtility::encodeSolrDynamicFieldName('tm;' . $language_id . ';') . '*';
      $this->ensureMultilingualFieldExists($multilingual_solr_field_name, $solr_field_type_name, $index);
      $multilingual_solr_field_name = SearchApiSolrUtility::encodeSolrDynamicFieldName('ts;' . $language_id . ';') . '*';
      $this->ensureMultilingualFieldExists($multilingual_solr_field_name, $solr_field_type_name, $index);

      foreach ($map as $monolingual_solr_field_name => $multilingual_solr_field_name) {
        // do something field-specific if needed
      }
    }
  }

  protected function ensureMultilingualFieldTypeExists($field_type_name, $solr_field_type_name, IndexInterface $index) {
    if (!$this->solrFieldTypeExists($solr_field_type_name, $index)) {
      $this->createSolrMultilingualFieldType($field_type_name, $solr_field_type_name, $index);
    }
  }

  protected function ensureMultilingualFieldExists($multilingual_solr_field_name, $solr_field_type_name, IndexInterface $index) {
    if (!$this->solrDynamicFieldExists($multilingual_solr_field_name, $index)) {
      $this->createSolrDynamicField($multilingual_solr_field_name, $solr_field_type_name, $index);
    }
  }

  protected function solrDynamicFieldExists($solr_field_name, IndexInterface $index) {
    $response = $this->solrRestGet('schema/dynamicfields', $index);
    $found = FALSE;
    foreach ($response['dynamicFields'] as $dynamic_field) {
      if ($dynamic_field['name'] == $solr_field_name) {
        $found = TRUE;
        break;
      }
    }
    return $found;
  }

  protected function solrFieldTypeExists($solr_field_type_name, IndexInterface $index) {
    $response = $this->solrRestGet('schema/fieldtypes', $index);
    $found = FALSE;
    foreach ($response['fieldTypes'] as $field_type) {
      if ($field_type['name'] == $solr_field_type_name) {
        $found = TRUE;
        break;
      }
    }
    return $found;
  }

  protected function createSolrDynamicField($solr_field_name, $solr_field_type_name, IndexInterface $index) {
    $command = ['add-dynamic-field' => [
      'name' => $solr_field_name,
      'type' => $solr_field_type_name,
      'stored' => TRUE,
      'indexed' => TRUE,
      'multiValued' => strpos($solr_field_name, 'tm_') === 0 ? TRUE : FALSE,
    ]];
    return $this->solrRestPost('schema', Json::encode($command), $index);
  }

  protected function createSolrMultilingualFieldType($field_type_name, $solr_field_type_name, IndexInterface $index) {
    // Get the field type definition from Drupal.
    $field_type_entity = SolrFieldType::load($field_type_name);
    $field_type_definition = $field_type_entity->getFieldType();
    $field_type_definition['name'] = $solr_field_type_name;
    $this->tweakFilterConfig($field_type_definition['indexAnalyzer']['filters']);
    $this->tweakFilterConfig($field_type_definition['queryAnalyzer']['filters']);

    // Send the config to Solr.
    $command_json = '{ "add-field-type": ' . Json::encode($field_type_definition) . '}';
    $command_json = str_replace('"'.$field_type_name.'"', '"'.$solr_field_type_name.'"', $command_json);
    return $this->solrRestPost('schema', $command_json, $index);
  }

  /**
   *  (temporarily) apply tweaks to the config until Solr's
   *  Managed* classes support all parameters
   *
   * @param array $filters The filters to act upon.
   */
  protected function tweakFilterConfig(&$filters) {
    foreach ($filters as &$filter) {
      if ($filter['class'] == 'solr.ManagedSynonymFilterFactory') {
        unset($filter['expand']);
        unset($filter['ignoreCase']);
      }
      if ($filter['class'] == 'solr.ManagedStopFilterFactory') {
        unset($filter['ignoreCase']);
      }
    }
  }

  /**
   * Sends a REST GET request and return the result.
   *
   * @param string $path The path to append to the base URI
   * @param IndexInterface $index The index whose server the request should be sent to
   * @return string The decoded response
   */
  protected function solrRestGet($path, IndexInterface $index) {
    $uri = $this->solr->getEndpoint()->getBaseUri() . $path;
    $client = \Drupal::service('http_client');
    $result = $client->get($uri, ['Accept' => 'application/json']);
    $output = Json::decode($result->getBody());
    // \Drupal::logger('apachesolr_multilingual')->info(print_r($output, true));
    if (!empty($output['errors'])) {
      throw new SearchApiException("Error trying to send a REST GET request to '$uri'" .
        "\nError message(s):" . print_r($output['errors'], TRUE));
    }
    return $output;
  }

  /**
   * Sends a REST POST request and return the result.
   *
   * @param string $path The path to append to the base URI
   * @param string $command_json The JSON-encoded data.
   * @param IndexInterface $index The index whose server the request should be sent to
   * @return string The decoded response
   * @see https://cwiki.apache.org/confluence/display/solr/Schema+API
   */
  protected function solrRestPost($path, $command_json, IndexInterface $index) {
    $uri = $this->solr->getEndpoint()->getBaseUri() . $path;
    /** @var \GuzzleHttp\Client $client */
    $client = \Drupal::service('http_client');
    $result = $client->post($uri, [
      'body' => $command_json,
      'headers' => [
        'Accept' => 'application/json',
        'Content-type' => 'application/json'
      ],
    ]);
    $output = Json::decode($result->getBody());
    // \Drupal::logger('apachesolr_multilingual')->info(print_r($output, true));
    if (!empty($output['errors'])) {
      throw new SearchApiException("Error trying to send the following JSON to Solr (REST POST request to '$uri'): " . $command_json .
          "\nError message(s):" . print_r($output['errors'], TRUE));
    }
    return $output;
  }

}
