<?php

namespace Drupal\search_api_solr_multilingual\Plugin\search_api\backend;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr_multilingual\Entity\SolrFieldType;
use Drupal\search_api_solr_multilingual\SearchApiSolrMultilingualException;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Drupal\search_api_solr\Utility\Utility as SearchApiSolrUtility;
use Drupal\search_api_solr_multilingual\SolrMultilingualBackendInterface;
use Drupal\search_api_solr_multilingual\Utility\Utility;
use Solarium\Core\Query\QueryInterface as SolariumQueryInterface;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\ResponseParser\Component\FacetSet;

/**
 * The name of the language field might be change in future releases of
 * search_api. @see https://www.drupal.org/node/2641392 for details.
 * Therefor we define a constant here that could be easily changed.
 */
define('SEARCH_API_LANGUAGE_FIELD_NAME', 'search_api_language');

/**
 * A abstract base class for all multilingual Solr Search API backends.
 */
abstract class AbstractSearchApiSolrMultilingualBackend extends SearchApiSolrBackend implements SolrMultilingualBackendInterface {

  /**
   * Creates and deploys a missing dynamic Solr field if the server supports it.
   *
   * @param string $solr_field_name
   *   The name of the new dynamic Solr field.
   *
   * @param string $solr_field_type_name
   *   The name of the Solr Field Type to be used for the new dynamic Solr
   *   field.
   */
  abstract protected function createSolrDynamicField($solr_field_name, $solr_field_type_name);

  /**
   * Creates and deploys a missing Solr Field Type if the server supports it.
   *
   * @param string $solr_field_type_name
   *   The name of the Solr Field Type.
   */
  abstract protected function createSolrMultilingualFieldType($solr_field_type_name);

  /**
   * {@inheritdoc}
   */
  public function isManagedSchema() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['multilingual'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Multilingual'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
    $form['multilingual']['sasm_limit_search_page_to_content_language'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Limit to current content language.'),
      '#description' => $this->t('Limit all search results to current content language.'),
      '#default_value' => isset($this->configuration['sasm_limit_search_page_to_content_language']) ? $this->configuration['sasm_limit_search_page_to_content_language'] : FALSE,
    );
    $form['multilingual']['sasm_language_unspecific_fallback_on_schema_issues'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Use language fallbacks.'),
      '#description' => $this->t('This option is suitable for two use-cases. First, if you have languages like "de" and "de-at", both could be handled by a shared configuration for "de". Second, new languages will be handled by language-unspecific fallback configuration until the schema gets updated on your Solr server.'),
      '#default_value' => isset($this->configuration['sasm_language_unspecific_fallback_on_schema_issues']) ? $this->configuration['sasm_language_unspecific_fallback_on_schema_issues'] : TRUE,
    );
    $domains = SolrFieldType::getAvailableDomains();
    $form['multilingual']['sasm_domain'] = array(
      '#type' => 'select',
      '#options' => array_combine($domains, $domains),
      '#title' => $this->t('Targeted content domain'),
      '#description' => $this->t('For example "UltraBot3000" would be indexed as "Ultra" "Bot" "3000" in a generic domain, "CYP2D6" has to stay like it is in a scientific domain.'),
      '#default_value' => isset($this->configuration['sasm_domain']) ? $this->configuration['sasm_domain'] : 'generic',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Since the form is nested into another, we can't simply use #parents for
    // doing this array restructuring magic. (At least not without creating an
    // unnecessary dependency on internal implementation.)
    foreach ($values['multilingual'] as $key => $value) {
      $form_state->setValue($key, $value);
    }

    // Clean-up the form to avoid redundant entries in the stored configuration.
    $form_state->unsetValue('multilingual');

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Adjusts the language filter before converting the query into a Solr query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object.
   */
  protected function alterSearchApiQuery(QueryInterface $query) {
    // Do not modify 'Server index status' queries.
    // @see https://www.drupal.org/node/2668852
    if ($query->hasTag('server_index_status') || $query->hasTag('mlt')) {
      return;
    }

    parent::alterSearchApiQuery($query);

    $language_ids = $query->getLanguages();

    if (empty($language_ids)) {
      // If the query is generated by views and the query isn't limited by any
      // languages we have to search for all languages using their specific
      // fields.
      if (!$query->hasTag('views') && $this->configuration['sasm_limit_search_page_to_content_language']) {
        $query->setLanguages([
          \Drupal::languageManager()
            ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
            ->getId()
        ]);
      }
      else {
        $language_ids = [LanguageInterface::LANGCODE_NOT_SPECIFIED];
        foreach (\Drupal::languageManager()->getLanguages() as $language) {
          $language_ids[] = $language->getId();
        }
        $query->setLanguages($language_ids);
      }
    }
    elseif (1 == count($language_ids)) {
      // @todo At this point we don't know if someone explicitly searches for
      //   language unspecific content or if he searches for all languages.
      //   Probably we have to apply some logic here or introduce a
      //   configuration option.
      // @see https://www.drupal.org/node/2717591
      switch (reset($language_ids)) {
        case LanguageInterface::LANGCODE_NOT_SPECIFIED:
        case LanguageInterface::LANGCODE_NOT_APPLICABLE:
          break;
      }
    }
  }

  /**
   * Modify the query before it is sent to solr.
   *
   * Replaces all language unspecific fulltext query fields by language specific
   * ones.
   *
   * @param \Solarium\Core\Query\QueryInterface $solarium_query
   *   The Solarium select query object.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   */
  protected function preQuery(SolariumQueryInterface $solarium_query, QueryInterface $query) {
    // Do not modify 'Server index status' queries.
    // @see https://www.drupal.org/node/2668852
    if ($query->hasTag('server_index_status')) {
      return;
    }

    parent::preQuery($solarium_query, $query);

    $language_ids = $query->getLanguages();

    if (!empty($language_ids)) {
      $mlt = $query->hasTag('mlt');
      $edismax = NULL;
      $solr_fields = NULL;
      if ($mlt) {
        /** @var \Solarium\QueryType\MoreLikeThis\Query $solarium_query */
        $solr_fields = implode(' ', $solarium_query->getMltFields());
      }
      else {
        /** @var \Solarium\QueryType\Select\Query\Query $solarium_query */
        $edismax = $solarium_query->getEDisMax();
        $solr_fields = $edismax->getQueryFields();
      }
      $index = $query->getIndex();
      $fulltext_fields = $index->getFulltextFields();
      $field_names = $this->getSolrFieldNames($index);

      foreach ($fulltext_fields as $fulltext_field) {
        $field_name = $field_names[$fulltext_field];
        $boost = '';
        if (preg_match('@' . $field_name . '(\^[\d.]+)@', $solr_fields, $matches)) {
          $boost = $matches[1];
        }

        $language_specific_fields = [];
        foreach ($language_ids as $language_id) {
          $language_specific_field = SearchApiSolrUtility::encodeSolrName(Utility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($field_name, $language_id));
          $language_specific_fields[] = $language_specific_field . $boost;
        }

        $solr_fields = str_replace(
          $field_name . $boost,
          implode(' ', array_unique($language_specific_fields)),
          $solr_fields
        );
      }
      if ($mlt) {
        $solarium_query->setMltFields(explode(' ', $solr_fields));
      }
      else {
        $edismax->setQueryFields($solr_fields);
      }

      if (empty($this->configuration['retrieve_data'])) {
        // We need the language to be part of the result to modify the result
        // accordingly in extractResults().
        $solarium_query->addField($field_names[SEARCH_API_LANGUAGE_FIELD_NAME]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilterQueries(QueryInterface $query, array $solr_fields, array $index_fields, array &$options) {
    $condition_group = $query->getConditionGroup();
    $conditions = $condition_group->getConditions();
    if (empty($conditions) || empty($query->getLanguages())) {
      return parent::getFilterQueries($query, $solr_fields, $index_fields, $options);
    }

    $fq = [];
    foreach ($conditions as $condition) {
      $language_fqs = [];
      foreach ($query->getLanguages() as $langcode) {
        $language_specific_condition_group = $query->createConditionGroup();
        $language_specific_condition_group->addCondition(SEARCH_API_LANGUAGE_FIELD_NAME, $langcode);
        $language_specific_conditions = &$language_specific_condition_group->getConditions();
        $language_specific_conditions[] = $condition;
        $language_fqs = array_merge($language_fqs, $this->reduceFilterQueries(
          $this->createFilterQueries($language_specific_condition_group, $this->getLanguageSpecificSolrFieldNames($langcode, $solr_fields, reset($index_fields)->getIndex()), $index_fields, $options),
          $condition_group
        ));
      }
      $language_aware_condition_group = $query->createConditionGroup('OR');
      $fq = array_merge($fq, $this->reduceFilterQueries($language_fqs, $language_aware_condition_group, TRUE));
    }

    return $fq;
  }

  /**
   * Gets a language-specific mapping from Drupal to Solr field names.
   *
   * @param string $langcode
   *   The lanaguage to get the mapping for.
   * @param array $solr_fields
   *   The mapping from Drupal to Solr field names.
   * @param \Drupal\search_api\IndexInterface $index_fields
   *   The fields handled by the curent index.
   *
   * @return array
   *   The language-specific mapping from Drupal to Solr field names.
   */
  protected function getLanguageSpecificSolrFieldNames($lancgcode, array $solr_fields, IndexInterface $index) {
    // @todo Caching.
    foreach ($index->getFulltextFields() as $fulltext_field) {
      $solr_fields[$fulltext_field] = SearchApiSolrUtility::encodeSolrName(Utility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($solr_fields[$fulltext_field], $lancgcode));
    }
    return $solr_fields;
  }

  /**
   * @inheritdoc
   */
  protected function alterSolrResponseBody(&$body, QueryInterface $query) {
    $data = json_decode($body);

    $index = $query->getIndex();
    $field_names = $this->getSolrFieldNames($index, TRUE);
    $doc_languages = [];

    if (isset($data->response)) {
      foreach ($data->response->docs as $doc) {
        $language_id = $doc_languages[$this->createId($index->id(), $doc->{SEARCH_API_ID_FIELD_NAME})] = $doc->{$field_names[SEARCH_API_LANGUAGE_FIELD_NAME]};
        foreach (array_keys(get_object_vars($doc)) as $language_specific_field_name) {
          $field_name = Utility::getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($language_specific_field_name);
          if ($field_name != $language_specific_field_name) {
            if (Utility::getLanguageIdFromLanguageSpecificSolrDynamicFieldName($language_specific_field_name) == $language_id) {
              $doc->{$field_name} = $doc->{$language_specific_field_name};
              unset($doc->{$language_specific_field_name});
            }
          }
        }
      }
    }

    if (isset($data->highlighting)) {
      foreach ($data->highlighting as $solr_id => &$item) {
        foreach (array_keys(get_object_vars($item)) as $language_specific_field_name) {
          $field_name = Utility::getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($language_specific_field_name);
          if ($field_name != $language_specific_field_name) {
            if (Utility::getLanguageIdFromLanguageSpecificSolrDynamicFieldName($language_specific_field_name) == $doc_languages[$solr_id]) {
              $item->{$field_name} = $item->{$language_specific_field_name};
              unset($item->{$language_specific_field_name});
            }
          }
        }
      }
    }

    if (isset($data->facet_counts)) {
      $facet_set_helper = new FacetSet();
      foreach (get_object_vars($data->facet_counts->facet_fields) as $language_specific_field_name => $facet_terms) {
        $field_name = Utility::getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($language_specific_field_name);
        if ($field_name != $language_specific_field_name) {
          if (isset($data->facet_counts->facet_fields->{$field_name})) {
            // @todo this simple merge of all language specific fields to one
            //   language unspecific fields should be configurable.
            $key_value = $facet_set_helper->convertToKeyValueArray($data->facet_counts->facet_fields->{$field_name}) +
              $facet_set_helper->convertToKeyValueArray($facet_terms);
            $facet_terms = [];
            foreach ($key_value as $key => $value) {
              // @todo check for NULL key of "missing facets".
              $facet_terms[] = $key;
              $facet_terms[] = $value;
            }
          }
          $data->facet_counts->facet_fields->{$field_name} = $facet_terms;
        }
      }
    }

    $body = json_encode($data);
  }

  /**
   * {@inheritdoc}
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
    $multiple_field_names = $this->getSolrFieldNames($index);
    $field_names = $this->getSolrFieldNames($index, TRUE);
    $fulltext_field_names = array_filter(array_flip($multiple_field_names) + array_flip($field_names),
      function ($value) use ($fulltext_fields) {
        return in_array($value, $fulltext_fields);
      }
    );

    $field_name_map_per_language = [];
    foreach ($documents as $document) {
      $fields = $document->getFields();
      $language_id = $fields[$field_names[SEARCH_API_LANGUAGE_FIELD_NAME]];
      foreach ($fields as $monolingual_solr_field_name => $field_value) {
        if (array_key_exists($monolingual_solr_field_name, $fulltext_field_names)) {
          $multilingual_solr_field_name = Utility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($monolingual_solr_field_name, $language_id);
          $field_name_map_per_language[$language_id][$monolingual_solr_field_name] = SearchApiSolrUtility::encodeSolrName($multilingual_solr_field_name);
        }
      }
    }
    foreach ($field_name_map_per_language as $language_id => $map) {
      $solr_field_type_name = SearchApiSolrUtility::encodeSolrName('text' . '_' . $language_id);
      if (!$this->isPartOfSchema('fieldTypes', $solr_field_type_name) &&
        !$this->createSolrMultilingualFieldType($solr_field_type_name) &&
        !$this->hasLanguageUndefinedFallback()
      ) {
        throw new SearchApiSolrMultilingualException('Missing field type ' . $solr_field_type_name . ' in schema.');
      }

      // Handle dynamic fields for multilingual tm and ts.
      foreach (['ts', 'tm'] as $prefix) {
        $multilingual_solr_field_name = SearchApiSolrUtility::encodeSolrName(Utility::getLanguageSpecificSolrDynamicFieldPrefix($prefix, $language_id)) . '*';
        if (!$this->isPartOfSchema('dynamicFields', $multilingual_solr_field_name) &&
          !$this->createSolrDynamicField($multilingual_solr_field_name, $solr_field_type_name) &&
          !$this->hasLanguageUndefinedFallback()
        ) {
          throw new SearchApiSolrMultilingualException('Missing dynamic field ' . $multilingual_solr_field_name . ' in schema.');
        }
      }
    }

    foreach ($documents as $document) {
      $fields = $document->getFields();
      foreach ($field_name_map_per_language as $language_id => $map) {
        if (/* @todo CLIR || */
          $fields[$field_names[SEARCH_API_LANGUAGE_FIELD_NAME]] == $language_id
        ) {
          foreach ($fields as $monolingual_solr_field_name => $value) {
            if (isset($map[$monolingual_solr_field_name])) {
              $document->addField($map[$monolingual_solr_field_name], $value, $document->getFieldBoost($monolingual_solr_field_name));
              // @todo removal should be configurable
              $document->removeField($monolingual_solr_field_name);
            }
          }
        }
      }
    }
  }

  /**
   * Indicates if an 'element' is part of the Solr server's schema.
   *
   * @param string $kind
   *   The kind of the element, for example 'dynamicFields' or 'fieldTypes'.
   *
   * @param string $name
   *   The name of the element.
   *
   * @return bool
   *   True if an element of the given kind and name exists, false otherwise.
   *
   * @throws \Drupal\search_api_solr_multilingual\SearchApiSolrMultilingualException
   */
  protected function isPartOfSchema($kind, $name) {
    static $previous_calls;

    $state_key = 'sasm.' . $this->getServer()->id() . '.schema_parts';
    $state = \Drupal::state();
    $schema_parts = $state->get($state_key);
    // @todo reset that drupal state from time to time

    if (
      !is_array($schema_parts) || empty($schema_parts[$kind]) ||
      (!in_array($name, $schema_parts[$kind]) && !isset($previous_calls[$kind]))
    ) {
      $response = $this->getSolrConnector()
        ->coreRestGet('schema/' . strtolower($kind));
      if (empty($response[$kind])) {
        throw new SearchApiSolrException('Missing information about ' . $kind . ' in response to REST request.');
      }
      // Delete the old state.
      $schema_parts[$kind] = [];
      foreach ($response[$kind] as $row) {
        $schema_parts[$kind][] = $row['name'];
      }
      $state->set($state_key, $schema_parts);
      $previous_calls[$kind] = TRUE;
    }

    return in_array($name, $schema_parts[$kind]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaLanguageStatistics() {
    $available = $this->getSolrConnector()->pingCore();
    $stats = [];
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $solr_field_type_name = SearchApiSolrUtility::encodeSolrName('text' . '_' . $language->getId());
      $stats[$language->getId()] = $available ? $this->isPartOfSchema('fieldTypes', $solr_field_type_name) : FALSE;
    }
    return $stats;
  }

  /**
   * {@inheritdoc}
   */
  public function hasLanguageUndefinedFallback() {
    return isset($this->configuration['sasm_language_unspecific_fallback_on_schema_issues']) ?
      $this->configuration['sasm_language_unspecific_fallback_on_schema_issues'] : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDomain() {
    return (isset($this->configuration['sasm_domain']) && !empty($this->configuration['sasm_domain'])) ? $this->configuration['sasm_domain'] : 'generic';
  }

  /**
   * {@inheritdoc}
   */
  protected function setFacets(QueryInterface $query, Query $solarium_query, array $field_names) {
    parent::setFacets($query, $solarium_query, $field_names);

    if ($languages = $query->getLanguages()) {
      foreach ($languages as $language) {
        $language_specific_field_names = $this->getLanguageSpecificSolrFieldNames($language, $field_names, $query->getIndex());
        parent::setFacets($query, $solarium_query, array_diff_assoc($language_specific_field_names, $field_names));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    $info = parent::viewSettings();

    $info[] = [
      'label' => $this->t('Targeted content domain'),
      'info' => $this->getDomain(),
    ];

    return $info;
  }
}
