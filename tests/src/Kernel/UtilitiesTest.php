<?php

namespace Drupal\Tests\search_api_solr_multilingual\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api_solr\Utility\Utility as SASUtility;
use Drupal\search_api_solr_multilingual\Utility\Utility;

/**
 * Provides tests for various utility functions.
 *
 * @group search_api_solr_multilingual
 */
class UtilitiesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'search_api_solr',
    'search_api_solr_multilingual',
  );

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests langauge-specific Solr field names.
   */
  public function testLanguageSpecificFieldNames() {
    $this->doFieldNameConvertions();
    // Traditional Chinese as used in Hong Kong.
    $this->doFieldNameConvertions('ts', 'zh-Hant-HK', 'a_longer_field_name');
    // The variant of German orthography dating from the 1901 reforms, as seen
    // in Switzerland.
    $this->doFieldNameConvertions('tm', 'de-CH-1901', 'sophisticated/field;NAME');

    $this->doFieldNameConvertions('tm', 'en', 'tm;en_tm_en_repeated_sequences');
  }

  /**
   * Tests all conversion and extraction functions.
   *
   * @param string $prefix
   *   The Solr field prefix.
   * @param string $langcode
   *   The Drupal language ID.
   * @param string $field
   *   The Drupal field name.
   */
  protected function doFieldNameConvertions($prefix = 'tm', $langcode = 'de', $field = 'body') {
    $sep = ';';
    // tm_body
    $dynamic_field_name = $prefix . '_' . $field;
    // tm;de_
    $language_specific_prefix = $prefix . $sep . $langcode . '_';
    // tm;de_body
    $language_specific_field_name = $language_specific_prefix . $field;

    $this->assertEquals(Utility::getLanguageSpecificSolrDynamicFieldPrefix($prefix, $langcode), $language_specific_prefix);

    // tm_body => tm;de_body
    $language_specific_dynamic_field_name = Utility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($dynamic_field_name, $langcode);
    $this->assertEquals($language_specific_dynamic_field_name, $language_specific_field_name);

    // tm;de_body => tm_body
    $language_unspecific_dynamic_field_name = Utility::getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($language_specific_dynamic_field_name);
    $this->assertEquals($language_unspecific_dynamic_field_name, $dynamic_field_name);

    // tm;de_body => tm_X3b_de_body => tm_body
    $encoded_language_specific_dynamic_field_name = SASUtility::encodeSolrDynamicFieldName($language_specific_dynamic_field_name);
    $encoded_language_unspecific_dynamic_field_name = Utility::getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($encoded_language_specific_dynamic_field_name);
    $decoded_language_unspecific_dynamic_field_name = SASUtility::decodeSolrDynamicFieldName($encoded_language_unspecific_dynamic_field_name);
    $this->assertEquals($decoded_language_unspecific_dynamic_field_name, $dynamic_field_name);

    // tm_X3b_de_body => tm_X3b_de_*
    $field_definition = Utility::extractLanguageSpecificSolrDynamicFieldDefinition($encoded_language_specific_dynamic_field_name);
    $this->assertEquals($field_definition, SASUtility::encodeSolrDynamicFieldName($language_specific_prefix) . '*');

    // tm;de_body => de
    $this->assertEquals(Utility::getLanguageIdFromLanguageSpecificSolrDynamicFieldName($language_specific_field_name), $langcode);

    // tm_body => FALSE
    $this->assertFalse(Utility::getLanguageIdFromLanguageSpecificSolrDynamicFieldName($dynamic_field_name));
  }
}
