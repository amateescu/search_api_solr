<?php

/**
 * @file
 * Contains \Drupal\search_api_solr_multilingual\Utility.
 */

namespace Drupal\search_api_solr_multilingual\Utility;

use Drupal\search_api_solr\Utility\Utility as SearchApiSolrUtility;

/**
 * The separator to encapsultate the language id. We must not use any character
 * that has a special meaning within a regular expression. See
 * http://de2.php.net/manual/en/regexp.reference.meta.php
 * Additionally we have to avoid ':', '_' and '/' which are already reserved by
 * search_api. '@' must not be used as well because it's common for locales like
 * "de_DE@euro".
 */
define('SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR', ';');

/**
 * Provides various helper functions for multilingual Solr backends.
 */
class Utility {

  /**
   * Maps a Solr field name to its language-specific equivalent.
   *
   * For example the dynamic field tm* will become tm;en;* for English.
   * Following this pattern we also have fallbacks automatically:
   * - tm;de_AT;*
   * - tm;de;*
   * - tm*
   * This concept bases on the fact that "longer patterns will be matched first.
   * If equal size patterns both match,the first appearing in the schema will be
   * used." See https://wiki.apache.org/solr/SchemaXml#Dynamic_fields
   * Note that the real field name for solr is encoded. So the real values for
   * the example above are:
   * - tm_3b_de_5f_AT_3b_*
   * - tm_3b_de_3b_*
   * - tm_*
   *
   * @param string $field_name
   *   The field name.
   *
   * @param string $language_id
   *   The Drupal langauge code.
   *
   * @return string
   *   The language-specific name.
   *
   * @see Drupal\search_api_solr\Utility\Utility::encodeSolrDynamicFieldName().
   * @see https://wiki.apache.org/solr/SchemaXml#Dynamic_fields
   */
  public static function getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($field_name, $language_id) {
    return Utility::modifySolrDynamicFieldName($field_name, '@^([a-z]+)_@', '$1' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . $language_id . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR);
  }

  /**
   * Maps a language-specific Solr field name to its unspecific equivalent.
   *
   * For example the dynamic field tm;en;* for English will become tm*.
   *
   * @param string $field_name
   *   The field name.
   *
   * @param string $language_id
   *   The Drupal langauge code.
   *
   * @return string
   *   The language-specific name.
   *
   * @see Drupal\search_api_solr_multilingual\Utility\Utility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName().
   * @see Drupal\search_api_solr\Utility\Utility::encodeSolrDynamicFieldName().
   * @see https://wiki.apache.org/solr/SchemaXml#Dynamic_fields
   */
  public static function getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($field_name) {
    return Utility::modifySolrDynamicFieldName($field_name, '@' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . '.+?' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . '@', '_');
  }

  /**
   * Modifies a dynamic Solr field's name using a regular expression.
   *
   * If the field name is encoded it will be decoded before the regular
   * expression runs and encoded again before the modified is returned.
   *
   * @param string $field_name
   *   The dynamic Solr field name.
   *
   * @param $pattern
   *   The regex.
   * @param $replacement
   *   The replacement for the pattern match.
   *
   * @return string
   *   The modified dynamic Solr field name.
   *
   * @see Drupal\search_api_solr\Utility\Utility::encodeSolrDynamicFieldName().
   */
  protected static function modifySolrDynamicFieldName($field_name, $pattern, $replacement) {
    $encoded = strpos($field_name, SearchApiSolrUtility::encodeSolrDynamicFieldName('_')) | strpos($field_name, SearchApiSolrUtility::encodeSolrDynamicFieldName(SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR));
    if ($encoded) {
      $field_name = SearchApiSolrUtility::decodeSolrDynamicFieldName($field_name);
    }
    $field_name = preg_replace($pattern, $replacement, $field_name);
    if ($encoded) {
      $field_name = SearchApiSolrUtility::encodeSolrDynamicFieldName($field_name);
    }
    return $field_name;
  }

  /**
   * Gets the language-specific prefix for a dynamic Solr field.
   *
   * @param string $prefix
   *   The language-unspecific prefix.
   *
   * @param string $language_id
   *   The Drupal language code.
   *
   * @return string
   *   The language-specific prefix.
   */
  public static function getLanguageSpecificSolrDynamicFieldPrefix($prefix, $language_id) {
    return $prefix . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . $language_id . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR;
  }

  /**
   * Extracts the language code from a language-specific dynamic Solr field.
   *
   * @param string  $field_name
   *  The language-specific dynamic Solr field name.
   *
   * @return mixed
   *   The Drupal language code as string or boolean FALSE if no langauge code
   *   could be extracted.
   */
  public static function getLangaugeIdFromLanguageSpecificSolrDynamicFieldName($field_name) {
    $encoded = strpos($field_name, SearchApiSolrUtility::encodeSolrDynamicFieldName(SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR));
    if ($encoded) {
      $field_name = SearchApiSolrUtility::decodeSolrDynamicFieldName($field_name);
    }
    if (preg_match('@' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . '(.+?)' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . '@', $field_name, $matches)) {
      return $matches[1];
    }
    return FALSE;
  }

  /**
   * Extracts the language-specific prefix from a dynamic Solr field.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The language-specific prefix.
   */
  public static function extractLanguageSpecificSolrDynamicFieldName($field_name) {
    $separator = SearchApiSolrUtility::encodeSolrDynamicFieldName(SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR);
    preg_match('@.*' . $separator . '(.+?)' .$separator . '@', $field_name, $matches);
    return $matches[0] . '*';
  }

}


