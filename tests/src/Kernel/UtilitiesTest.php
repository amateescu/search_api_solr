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
   * Tests language-specific Solr field names.
   */
  public function testLanguageSpecificFieldTypeNames() {
    $this->assertEquals('text_de', SASUtility::encodeSolrName('text_de'));

    // Drupal-like locale for Austria.
    $encoded = SASUtility::encodeSolrName('text_de-at');
    $this->assertEquals('text_de_X2d_at', $encoded);
    $this->assertEquals('text_de-at', SASUtility::decodeSolrName($encoded));

    // Traditional Chinese as used in Hong Kong.
    $encoded = SASUtility::encodeSolrName('text_zh-Hant-HK');
    $this->assertEquals('text_zh_X2d_Hant_X2d_HK', $encoded);
    $this->assertEquals('text_zh-Hant-HK', SASUtility::decodeSolrName($encoded));

    // The variant of German orthography dating from the 1901 reforms, as seen
    // in Switzerland.
    $encoded = SASUtility::encodeSolrName('text_de-CH-1901');
    $this->assertEquals('text_de_X2d_CH_X2d_1901', $encoded);
    $this->assertEquals('text_de-CH-1901', SASUtility::decodeSolrName($encoded));
  }

  /**
   * Tests language-specific Solr field names.
   */
  public function testLanguageSpecificDynamicFieldNames() {
    $this->doDynamicFieldNameConversions();
    // Traditional Chinese as used in Hong Kong.
    $this->doDynamicFieldNameConversions('ts', 'zh-Hant-HK', 'a_longer_field_name');
    // The variant of German orthography dating from the 1901 reforms, as seen
    // in Switzerland.
    $this->doDynamicFieldNameConversions('tm', 'de-CH-1901', 'sophisticated/field;NAME');

    $this->doDynamicFieldNameConversions('tm', 'en', 'tm;en_tm_en_repeated_sequences');
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
  protected function doDynamicFieldNameConversions($prefix = 'tm', $langcode = 'de', $field = 'body') {
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
    $encoded_language_specific_dynamic_field_name = SASUtility::encodeSolrName($language_specific_dynamic_field_name);
    $encoded_language_unspecific_dynamic_field_name = Utility::getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($encoded_language_specific_dynamic_field_name);
    $decoded_language_unspecific_dynamic_field_name = SASUtility::decodeSolrName($encoded_language_unspecific_dynamic_field_name);
    $this->assertEquals($decoded_language_unspecific_dynamic_field_name, $dynamic_field_name);

    // tm_X3b_de_body => tm_X3b_de_*
    $field_definition = Utility::extractLanguageSpecificSolrDynamicFieldDefinition($encoded_language_specific_dynamic_field_name);
    $this->assertEquals($field_definition, SASUtility::encodeSolrName($language_specific_prefix) . '*');

    // tm;de_body => de
    $this->assertEquals(Utility::getLanguageIdFromLanguageSpecificSolrDynamicFieldName($language_specific_field_name), $langcode);

    // tm_body => FALSE
    $this->assertFalse(Utility::getLanguageIdFromLanguageSpecificSolrDynamicFieldName($dynamic_field_name));
  }

}
