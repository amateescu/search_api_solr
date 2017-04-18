<?php

namespace Drupal\search_api_solr_multilingual\Utility;

use Drupal\search_api_solr\Utility\Utility as SearchApiSolrUtility;

/**
 * The separator to indicate the start of a language ID. We must not use any
 * character that has a special meaning within regular expressions. Additionally
 * we have to avoid characters that are valid for Drupal machine names.
 * The end of a language ID is indicated by an underscore '_' which could not
 * occur within the language ID itself because Drupal uses lanague tags.
 *
 * @see http://de2.php.net/manual/en/regexp.reference.meta.php
 * @see https://www.w3.org/International/articles/language-tags/
 */
define('SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR', ';');

/**
 * Provides various helper functions for multilingual Solr backends.
 */
class Utility {

  /**
   * Maps a Solr field name to its language-specific equivalent.
   *
   * For example the dynamic field tm_* will become tm;en* for English.
   * Following this pattern we also have fall backs automatically:
   * - tm;de-AT_*
   * - tm;de_*
   * - tm_*
   * This concept bases on the fact that "longer patterns will be matched first.
   * If equal size patterns both match,the first appearing in the schema will be
   * used." This is not obvious from the example above. But you need to take
   * into account that the real field name for solr will be encoded. So the real
   * values for the example above are:
   * - tm_X3b_de_X2d_AT_*
   * - tm_X3b_de_*
   * - tm_*
   *
   * @see Drupal\search_api_solr\Utility\Utility::encodeSolrName()
   * @see https://wiki.apache.org/solr/SchemaXml#Dynamic_fields
   *
   * @param string $field_name
   *   The field name.
   * @param string $language_id
   *   The Drupal langauge code.
   *
   * @return string
   *   The language-specific name.
   */
  public static function getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($field_name, $language_id) {
    return Utility::modifySolrDynamicFieldName($field_name, '@^([a-z]+)_@', '$1' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . $language_id . '_');
  }

  /**
   * Maps a language-specific Solr field name to its unspecific equivalent.
   *
   * For example the dynamic field tm;en_* for English will become tm_*.
   *
   * @see Drupal\search_api_solr_multilingual\Utility\Utility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName()
   * @see Drupal\search_api_solr\Utility\Utility::encodeSolrName()
   * @see https://wiki.apache.org/solr/SchemaXml#Dynamic_fields
   *
   * @param string $field_name
   *   The field name.
   * @param string $language_id
   *   The Drupal langauge code.
   *
   * @return string
   *   The language-specific name.
   */
  public static function getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($field_name) {
    return Utility::modifySolrDynamicFieldName($field_name, '@^([a-z]+)' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . '[^_]+?_@', '$1_');
  }

  /**
   * Modifies a dynamic Solr field's name using a regular expression.
   *
   * If the field name is encoded it will be decoded before the regular
   * expression runs and encoded again before the modified is returned.
   *
   * @see Drupal\search_api_solr\Utility\Utility::encodeSolrName()
   *
   * @param string $field_name
   *   The dynamic Solr field name.
   * @param $pattern
   *   The regex.
   * @param $replacement
   *   The replacement for the pattern match.
   *
   * @return string
   *   The modified dynamic Solr field name.
   */
  protected static function modifySolrDynamicFieldName($field_name, $pattern, $replacement) {
    $decoded_field_name = SearchApiSolrUtility::decodeSolrName($field_name);
    $modified_field_name = preg_replace($pattern, $replacement, $decoded_field_name);
    if ($decoded_field_name != $field_name) {
      $modified_field_name = SearchApiSolrUtility::encodeSolrName($modified_field_name);
    }
    return $modified_field_name;
  }

  /**
   * Gets the language-specific prefix for a dynamic Solr field.
   *
   * @param string $prefix
   *   The language-unspecific prefix.
   * @param string $language_id
   *   The Drupal language code.
   *
   * @return string
   *   The language-specific prefix.
   */
  public static function getLanguageSpecificSolrDynamicFieldPrefix($prefix, $language_id) {
    return $prefix . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . $language_id . '_';
  }

  /**
   * Extracts the language code from a language-specific dynamic Solr field.
   *
   * @param string $field_name
   *   The language-specific dynamic Solr field name.
   *
   * @return mixed
   *   The Drupal language code as string or boolean FALSE if no language code
   *   could be extracted.
   */
  public static function getLanguageIdFromLanguageSpecificSolrDynamicFieldName($field_name) {
    $decoded_field_name = SearchApiSolrUtility::decodeSolrName($field_name);
    if (preg_match('@^[a-z]+' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . '([^_]+?)_@', $decoded_field_name, $matches)) {
      return $matches[1];
    }
    return FALSE;
  }

  /**
   * Extracts the language-specific definition from a dynamic Solr field.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return mixed
   *   The language-specific prefix as string or boolean FALSE if no prefix
   *   could be extracted.
   */
  public static function extractLanguageSpecificSolrDynamicFieldDefinition($field_name) {
    $decoded_field_name = SearchApiSolrUtility::decodeSolrName($field_name);
    if (preg_match('@^[a-z]+' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . '[^_]+?_@', $decoded_field_name, $matches)) {
      return SearchApiSolrUtility::encodeSolrName($matches[0]) . '*';
    }
    return FALSE;
  }

}
