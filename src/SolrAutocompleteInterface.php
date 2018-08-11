<?php

namespace Drupal\search_api_solr;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_solr\Solarium\Autocomplete\Query as AutocompleteQuery;

/**
 * Defines an autocomplete interface for Solr search backend plugins.
 */
interface SolrAutocompleteInterface {

  /**
   * Autocompletion suggestions for some user input using Terms component.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the base search, with all completely entered words
   *   in the user input so far as the search keys.
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   An object containing details about the search the user is on, and
   *   settings for the autocompletion. See the class documentation for details.
   *   Especially $search->getOptions() should be checked for settings, like
   *   whether to try and estimate result counts for returned suggestions.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed. Might be empty, in which case all user input up to now was
   *   considered completed. Then, additional keywords for the search could be
   *   suggested.
   * @param string $user_input
   *   The complete user input for the fulltext search keywords so far.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of autocomplete suggestions.
   */
  public function getTermsSuggestions(QueryInterface $query, $search, $incomplete_key, $user_input);

  /**
   * Allow custom changes to the Solarium Terms autocomplete query.
   *
   * This is an object oriented equivalent to
   * hook_search_api_solr_terms_autocomplete_query_alter() to avoid that
   * any logic needs to be split between the backend class and a module file.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query object representing the executed search query.
   *
   * @see hook_search_api_query_alter()
   */
  public function alterTermsAutocompleteQuery(QueryInterface $query);

  /**
   * Autocompletion suggestions for some user input using Spellcheck component.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the base search, with all completely entered words
   *   in the user input so far as the search keys.
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   An object containing details about the search the user is on, and
   *   settings for the autocompletion. See the class documentation for details.
   *   Especially $search->getOptions() should be checked for settings, like
   *   whether to try and estimate result counts for returned suggestions.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed. Might be empty, in which case all user input up to now was
   *   considered completed. Then, additional keywords for the search could be
   *   suggested.
   * @param string $user_input
   *   The complete user input for the fulltext search keywords so far.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of autocomplete suggestions.
   */
  public function getSpellcheckSuggestions(QueryInterface $query, $search, $incomplete_key, $user_input);

  /**
   * Allow custom changes to the Solarium Spellcheck autocomplete query.
   *
   * This is an object oriented equivalent to
   * hook_search_api_solr_spellcheck_autocomplete_query_alter() to avoid that
   * any logic needs to be split between the backend class and a module file.
   *
   * @param \Drupal\search_api_solr\Solarium\Autocomplete\Query $solarium_query
   *   The Solarium query object, as generated from the Search API query.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query object representing the executed search query.
   *
   * @see hook_search_api_query_alter()
   */
  public function alterSpellcheckAutocompleteQuery(AutocompleteQuery $solarium_query, QueryInterface $query);

  /**
   * Autocompletion suggestions for some user input using Suggester component.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the base search, with all completely entered words
   *   in the user input so far as the search keys.
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   An object containing details about the search the user is on, and
   *   settings for the autocompletion. See the class documentation for details.
   *   Especially $search->getOptions() should be checked for settings, like
   *   whether to try and estimate result counts for returned suggestions.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed. Might be empty, in which case all user input up to now was
   *   considered completed. Then, additional keywords for the search could be
   *   suggested.
   * @param string $user_input
   *   The complete user input for the fulltext search keywords so far.
   * @param array $options
   *   'dictionary' as string, 'context_filter_tags' as array of strings.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of autocomplete suggestions.
   */
  public function getSuggesterSuggestions(QueryInterface $query, $search, $incomplete_key, $user_input, $options = []);

  /**
   * Allow custom changes to the Solarium Suggester autocomplete query.
   *
   * This is an object oriented equivalent to
   * hook_search_api_solr_suggester_autocomplete_query_alter() to avoid that
   * any logic needs to be split between the backend class and a module file.
   *
   * @param \Drupal\search_api_solr\Solarium\Autocomplete\Query $solarium_query
   *   The Solarium query object, as generated from the Search API query.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query object representing the executed search query.
   *
   * @see hook_search_api_query_alter()
   */
  public function alterSuggesterAutocompleteQuery(AutocompleteQuery $solarium_query, QueryInterface $query);
}
