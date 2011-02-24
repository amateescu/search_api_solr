<?php
// $Id$

/**
 * @file
 * Hooks provided by the Search API Solr search module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Lets modules alter a Solr search request before sending it.
 *
 * Apache_Solr_Service::search() is called afterwards with these parameters.
 * Please see this method for details on what should be altered where and what
 * is set afterwards.
 *
 * @param array $call_args
 *   An associative array containing all four arguments to the
 *   Apache_Solr_Service::search() call ("query", "offset", "limit" and
 *   "params") as references.
 * @param SearchApiQueryInterface $query
 *   The SearchApiQueryInterface object representing the executed search query.
 */
function hook_search_api_solr_query_alter(array &$call_args, SearchApiQueryInterface $query) {
  if ($query->getOption('foobar')) {
    $call_args['params']['foo'] = 'bar';
  }
}

/**
 * Lets modules alter the search results returned from a Solr search, based on
 * the original Solr response.
 *
 * @param array $results
 *   The results array that will be returned for the search.
 * @param SearchApiQueryInterface $query
 *   The SearchApiQueryInterface object representing the executed search query.
 * @param Apache_Solr_Response $response
 *   The response object returned by Solr.
 */
function hook_search_api_solr_search_results_alter(array &$results, SearchApiQueryInterface $query, Apache_Solr_Response $response) {
  if (isset($response->facet_counts->facet_fields->custom_field)) {
    // Do something with $results.
  }
}

/**
 * Lets modules alter a Solr search request for a multi-index search before
 * sending it.
 *
 * Apache_Solr_Service::search() is called afterwards with these parameters.
 * Please see this method for details on what should be altered where and what
 * is set afterwards.
 *
 * @param array $call_args
 *   An associative array containing all four arguments to the
 *   Apache_Solr_Service::search() call ("query", "offset", "limit" and
 *   "params") as references.
 * @param SearchApiMultiQueryInterface $query
 *   The object representing the executed search query.
 */
function hook_search_api_solr_multi_query_alter(array &$call_args, SearchApiMultiQueryInterface $query) {
  if ($query->getOption('foobar')) {
    $call_args['params']['foo'] = 'bar';
  }
}

/**
 * @} End of "addtogroup hooks".
 */
