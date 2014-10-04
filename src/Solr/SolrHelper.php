<?php

/**
 * @file
 * Contains \Drupal\search_api_solr\Solr\SolrHelper.
 */

namespace Drupal\search_api_solr\Solr;

use Drupal\Core\Url;
use Solarium\Client;
use Drupal\search_api_solr\Utility\Utility as SearchApiSolrUtility;
use Solarium\Core\Query\Helper as SolariumHelper;

class SolrHelper {

  /**
   * A connection to the Solr server.
   *
   * @var \Solarium\Client
   */
  protected $solr;

  /**
   * A connection to the Solr server.
   *
   * @var array
   */
  protected $configuration;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * Returns a link to the Solr server, if the necessary options are set.
   */
  public function getServerLink() {
    if (!$this->configuration) {
      return '';
    }
    $host = $this->configuration['host'];
    if ($host == 'localhost' && !empty($_SERVER['SERVER_NAME'])) {
      $host = $_SERVER['SERVER_NAME'];
    }
    $url_path = $this->configuration['scheme'] . '://' . $host . ':' . $this->configuration['port'] . $this->configuration['path'];
    $url = Url::fromUri($url_path);

    return \Drupal::l($url_path, $url);
  }

  /**
   * Extract and format highlighting information for a specific item from a Solr response.
   *
   * Will also use highlighted fields to replace retrieved field data, if the
   * corresponding option is set.
   */
  public function getExcerpt($response, $id, array $fields, array $field_mapping) {
    if (!isset($response->highlighting->$id)) {
      return FALSE;
    }
    $output = '';

    if (!empty($this->configuration['excerpt']) && !empty($response->highlighting->$id->spell)) {
      foreach ($response->highlighting->$id->spell as $snippet) {
        $snippet = strip_tags($snippet);
        $snippet = preg_replace('/^.*>|<.*$/', '', $snippet);
        $snippet = SearchApiSolrUtility::formatHighlighting($snippet);
        // The created fragments sometimes have leading or trailing punctuation.
        // We remove that here for all common cases, but take care not to remove
        // < or > (so HTML tags stay valid).
        $snippet = trim($snippet, "\00..\x2F:;=\x3F..\x40\x5B..\x60");
        $output .= $snippet . ' … ';
      }
    }
    if (!empty($this->configuration['highlight_data'])) {
      foreach ($field_mapping as $search_api_property => $solr_property) {
        if (substr($solr_property, 0, 3) == 'tm_' && !empty($response->highlighting->$id->$solr_property)) {
          // Contrary to above, we here want to preserve HTML, so we just
          // replace the [HIGHLIGHT] tags with the appropriate format.
          $fields[$search_api_property] = SearchApiSolrUtility::formatHighlighting($response->highlighting->$id->$solr_property);
        }
      }
    }

    return $output;
  }

  /**
   * Flatten a keys array into a single search string.
   *
   * @param array $keys
   *   The keys array to flatten, formatted as specified by
   *   \Drupal\search_api\Query\QueryInterface::getKeys().
   * @param bool $is_nested
   *   (optional) Whether the function is called for a nested condition.
   *   Defaults to FALSE.
   *
   * @return string
   *   A Solr query string representing the same keys.
   */
  public function flattenKeys(array $keys, $is_nested = FALSE) {
    $k = array();
    $or = $keys['#conjunction'] == 'OR';
    $neg = !empty($keys['#negation']);
    foreach ($keys as $key_nr => $key) {
      // We cannot use \Drupal\Core\Render\Element::children() anymore because
      // $keys is not a valid render array.
      if ($key_nr[0] === '#' || !$key) {
        continue;
      }
      if (is_array($key)) {
        $subkeys = $this->flattenKeys($key, TRUE);
        if ($subkeys) {
          $nested_expressions = TRUE;
          // If this is a negated OR expression, we can't just use nested keys
          // as-is, but have to put them into parantheses.
          if ($or && $neg) {
            $subkeys = "($subkeys)";
          }
          $k[] = $subkeys;
        }
      }
      else {
        $solariumHelper = new SolariumHelper();
        $key = $solariumHelper->escapePhrase(trim($key));
        $k[] = $key;
      }
    }
    if (!$k) {
      return '';
    }

    // Formatting the keys into a Solr query can be a bit complex. The following
    // code will produce filters that look like this:
    //
    // #conjunction | #negation | return value
    // ----------------------------------------------------------------
    // AND          | FALSE     | A B C
    // AND          | TRUE      | -(A AND B AND C)
    // OR           | FALSE     | ((A) OR (B) OR (C))
    // OR           | TRUE      | -A -B -C

    // If there was just a single, unnested key, we can ignore all this.
    if (count($k) == 1 && empty($nested_expressions)) {
      $k = reset($k);
      return $neg ? "*:* AND -$k" : $k;
    }

    if ($or) {
      if ($neg) {
        return '*:* AND -' . implode(' AND -', $k);
      }
      return '((' . implode(') OR (', $k) . '))';
    }
    $k = implode($neg || $is_nested ? ' AND ' : ' ', $k);
    return $neg ? "*:* AND -($k)" : $k;
  }

}