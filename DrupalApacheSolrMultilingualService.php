<?php

/**
 * Class DrupalApacheSolrMultilingualService extends DrupalApacheSolrService
 * and overrides the internal method _makeHttpRequest to deal with multilingual
 * spell checking.
 */
class DrupalApacheSolrMultilingualService extends DrupalApacheSolrService {

  /**
   * Central method for making the actual http request to the Solr Server
   *
   * This is just a wrapper around drupal_http_request().
   */
  protected function _makeHttpRequest($url, array $options = array()) {
    if (!isset($options['method']) || $options['method'] == 'GET' || $options['method'] == 'HEAD') {
      // Make sure we are not sending a request body.
      $options['data'] = NULL;
    }

    $result = drupal_http_request($url, $options);

    if (!isset($result->code) || $result->code < 0) {
      $result->code = 0;
      $result->status_message = 'Request failed';
      $result->protocol = 'HTTP/1.0';
    }
    // Additional information may be in the error property.
    if (isset($result->error)) {
      $result->status_message .= ': ' . check_plain($result->error);
    }

    if (!isset($result->data)) {
      $result->data = '';
      $result->response = NULL;
    }
    else {
      // @see http://wiki.apache.org/solr/SolJSON
      // "Using a JSON object (essentially a map or hash) for a NamedList results
      // in the loss of some information."
      // That's the reason why the multiple language specific spell check results
      // get lost during json_decode(), because they are all named "spellcheck".
      // Therefor we rename the the language specific spell checks.
      // The language unspecific spellcheck is the last one in the list
      // and therefor not touched.
      $language_ids = array_keys(apachesolr_multilingual_language_list());
      foreach ($language_ids as $language_id) {
        $result->data = preg_replace(
          '@"spellcheck"@',
          '"spellcheck_' . $language_id . '"',
          $result->data,
          /* Limit */ 1
        );
      }

      $response = json_decode($result->data);
      if (is_object($response)) {
        foreach ($response as $key => $value) {
          $result->$key = $value;
        }
      }
    }
    return $result;
  }
}
