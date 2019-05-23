<?php

/**
 * Delete Solr 4 and 5 field types.
 */
function search_api_solr_post_update_8320() {
  $storage = \Drupal::entityTypeManager()->getStorage('solr_field_type');
  $storage->delete($storage->loadMultiple([
    'm_text_und_5_2_0',
    'text_und_4_5_0',
    'm_text_de_5_2_0',
    'm_text_en_5_2_0',
    'm_text_nl_5_2_0',
    'text_cs_5_0_0',
    'text_de_4_5_0',
    'text_de_5_0_0',
    'text_de_scientific_5_0_0',
    'text_el_4_5_0',
    'text_en_4_5_0',
    'text_es_4_5_0',
    'text_fi_4_5_0',
    'text_fr_4_5_0',
    'text_it_4_5_0',
    'text_nl_4_5_0',
    'text_ru_4_5_0',
    'text_uk_4_5_0',
  ]));
}
