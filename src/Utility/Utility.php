<?php

/**
 * @file
 * Contains \Drupal\apachesolr_multilingual\Utility.
 */

namespace Drupal\apachesolr_multilingual\Utility;

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

class Utility {

  /**
   * Maps a solr field name to its language specific equivalent.
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
   * See Drupal\search_api_solr\Utility\Utility::encodeSolrDynamicFieldName().
   *
   * @param type $field_name
   * @param type $language_id
   * @return type
   */
  public static function getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($field_name, $language_id) {
    return Utility::_modifySolrDynamicFieldName($field_name, '@^([a-z]+)_@', '$1' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . $language_id . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR);
  }

  public static function getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($field_name) {
    return Utility::_modifySolrDynamicFieldName($field_name, '@' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . '.+?' . SEARCH_API_SOLR_MULTILINGUAL_LANGUAGE_SEPARATOR . '@', '_');
  }

  public static function _modifySolrDynamicFieldName($field_name, $pattern, $replacement) {
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

}


