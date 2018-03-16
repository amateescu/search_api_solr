<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api_solr\Utility\Utility;

/**
 * Provides tests for various utility functions.
 *
 * @group search_api_solr
 */
class UtilitiesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'search_api',
    'search_api_solr',
    'user',
  ];

  /**
   * Tests encoding and decoding of Solr field names.
   */
  public function testFieldNameEncoder() {
    $allowed_characters_pattern = '/[a-zA-Z\d_]/';
    $forbidden_field_name = 'forbidden$field_nameÜöÄ*:/;#last_XMas';
    $expected_encoded_field_name = 'forbidden_X24_field_name_Xc39c__Xc3b6__Xc384__X2a__X3a__X2f__X3b__X23_last_X5f58_Mas';
    $encoded_field_name = Utility::encodeSolrName($forbidden_field_name);

    $this->assertEquals($encoded_field_name, $expected_encoded_field_name);

    preg_match_all($allowed_characters_pattern, $encoded_field_name, $matches);
    $this->assertEquals(count($matches[0]), strlen($encoded_field_name), 'Solr field name consists of allowed characters.');

    $decoded_field_name = Utility::decodeSolrName($encoded_field_name);

    $this->assertEquals($decoded_field_name, $forbidden_field_name);

    $this->assertEquals('ss_field_foo', Utility::encodeSolrName('ss_field_foo'));
  }

  /**
   * Tests language-specific Solr field names.
   */
  public function testLanguageSpecificFieldTypeNames() {
    $this->assertEquals('text_de', Utility::encodeSolrName('text_de'));

    // Drupal-like locale for Austria.
    $encoded = Utility::encodeSolrName('text_de-at');
    $this->assertEquals('text_de_X2d_at', $encoded);
    $this->assertEquals('text_de-at', Utility::decodeSolrName($encoded));

    // Traditional Chinese as used in Hong Kong.
    $encoded = Utility::encodeSolrName('text_zh-Hant-HK');
    $this->assertEquals('text_zh_X2d_Hant_X2d_HK', $encoded);
    $this->assertEquals('text_zh-Hant-HK', Utility::decodeSolrName($encoded));

    // The variant of German orthography dating from the 1901 reforms, as seen
    // in Switzerland.
    $encoded = Utility::encodeSolrName('text_de-CH-1901');
    $this->assertEquals('text_de_X2d_CH_X2d_1901', $encoded);
    $this->assertEquals('text_de-CH-1901', Utility::decodeSolrName($encoded));
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
    // tm_body.
    $dynamic_field_name = $prefix . '_' . $field;
    // tm;de_.
    $language_specific_prefix = $prefix . $sep . $langcode . '_';
    // tm;de_body.
    $language_specific_field_name = $language_specific_prefix . $field;

    $this->assertEquals(Utility::getLanguageSpecificSolrDynamicFieldPrefix($prefix, $langcode), $language_specific_prefix);

    // tm_body => tm;de_body.
    $language_specific_dynamic_field_name = Utility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($dynamic_field_name, $langcode);
    $this->assertEquals($language_specific_dynamic_field_name, $language_specific_field_name);

    // tm;de_body => tm_body.
    $language_unspecific_dynamic_field_name = Utility::getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($language_specific_dynamic_field_name);
    $this->assertEquals($language_unspecific_dynamic_field_name, $dynamic_field_name);

    // tm;de_body => tm_X3b_de_body => tm_body.
    $encoded_language_specific_dynamic_field_name = Utility::encodeSolrName($language_specific_dynamic_field_name);
    $encoded_language_unspecific_dynamic_field_name = Utility::getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($encoded_language_specific_dynamic_field_name);
    $decoded_language_unspecific_dynamic_field_name = Utility::decodeSolrName($encoded_language_unspecific_dynamic_field_name);
    $this->assertEquals($decoded_language_unspecific_dynamic_field_name, $dynamic_field_name);

    // tm_X3b_de_body => tm_X3b_de_*.
    $field_definition = Utility::extractLanguageSpecificSolrDynamicFieldDefinition($encoded_language_specific_dynamic_field_name);
    $this->assertEquals($field_definition, Utility::encodeSolrName($language_specific_prefix) . '*');

    // tm;de_body => de.
    $this->assertEquals(Utility::getLanguageIdFromLanguageSpecificSolrDynamicFieldName($language_specific_field_name), $langcode);

    // tm_body => FALSE.
    $this->assertFalse(Utility::getLanguageIdFromLanguageSpecificSolrDynamicFieldName($dynamic_field_name));
  }

}
