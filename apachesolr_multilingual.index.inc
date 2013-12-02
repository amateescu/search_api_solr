<?php

/**
 * Callback that converts list module field into an array
 * For every multivalued value we also add a single value to be able to
 * use the stats
 *
 * @param object $entity
 * @param string $field_name
 * @param string $index_key
 * @param array $field_info
 * @return array $fields
 */
function apachesolr_multilingual_fields_default_indexing_callback_implementation($entity, $field_name, $index_key, array $field_info) {
  $fields = array();
  $numeric = TRUE;
  if (!empty($entity->{$field_name})) {
    $field = $entity->$field_name;
    $values = array();
    if (array_key_exists($entity->language, $field) && is_array($field[$entity->language])) {
      $values = $field[$entity->language];
    }
    else {
      list($lang, $values) = each($field);
      if (!is_array($values)) {
        $values = array();
      }
    }

    switch ($field_info['index_type']) {
      case 'integer':
      case 'half-int':
      case 'sint':
      case 'tint':
      case 'thalf-int':
      case 'boolean':
        $function = 'intval';
        break;
      case 'float':
      case 'double':
      case 'sfloat':
      case 'sdouble':
      case 'tfloat':
      case 'tdouble':
        $function = 'apachesolr_floatval';
        break;
      default:
        $numeric = FALSE;
        $function = 'apachesolr_clean_text';
    }
    for ($i = 0; $i < count($values); $i++) {
      $fields[] = array(
        'key' => $index_key,
        'value' => $function($values[$i]['value']),
      );
    }
    // Also store the first value of the field in a singular index for multi value fields
    if ($field_info['multiple'] && $numeric && !empty($values[0])) {
      $singular_field_info = $field_info;
      $singular_field_info['multiple'] = FALSE;
      $single_key = apachesolr_index_key($singular_field_info);
      $fields[] = array(
        'key' => $single_key,
        'value' => $function($values[0]['value']),
      );
    }
  }
  return $fields;
}

/**
 * This function is used during indexing to normalize the DATE and DATETIME
 * fields into the appropriate format for Apache Solr.
 *
 * @param object $entity
 * @param string $field_name
 * @param string $index_key
 * @param array $field_info
 * @return array $fields
 */
function apachesolr_multilingual_date_default_indexing_callback_implementation($entity, $field_name, $index_key, array $field_info) {
  $fields = array();
  if (!empty($entity->{$field_name})) {
    $field = $entity->$field_name;
    $values = array();
    if (array_key_exists($entity->language, $field) && is_array($field[$entity->language])) {
      $values = $field[$entity->language];
    }
    else {
      list($lang, $values) = each($field);
      if (!is_array($values)) {
        $values = array();
      }
    }

    // Construct a Solr-ready date string in UTC time zone based on the field's date string and time zone.
    $tz = new DateTimeZone(isset($field['timezone']) ? $field['timezone'] : 'UTC');

    // $fields may end up having two values; one for the start date
    // and one for the end date.
    foreach ($values as $value) {
      if ($date = date_create($value['value'], $tz)) {
        $index_value = apachesolr_date_iso($date->format('U'));
        $fields[] = array(
          'key' => $index_key,
          'value' => $index_value,
        );
      }

      if (isset($value['value2'])) {
        if ($date = date_create($value['value2'], $tz)) {
          $index_value = apachesolr_date_iso($date->format('U'));
          $fields[] = array(
            // The value2 element is the end date. Therefore it gets indexed
            // into its own Solr field.
            'key' => $index_key . '_end',
            'value' => $index_value,
          );
        }
      }
    }
  }
  return $fields;
}

/**
 * This function is used during indexing to normalize the DATESTAMP fields
 * into the appropriate format for Apache Solr.
 *
 * @param object $entity
 * @param string $field_name
 * @param string $index_key
 * @param array $field_info
 * @return array $fields
 */
function apachesolr_multilingual_datestamp_default_indexing_callback_implementation($entity, $field_name, $index_key, array $field_info) {
  $fields = array();
  if (!empty($entity->{$field_name})) {
    // $fields may end up having two values; one for the start date
    // and one for the end date.
    $field = $entity->$field_name;
    $values = array();
    if (array_key_exists($entity->language, $field) && is_array($field[$entity->language])) {
      $values = $field[$entity->language];
    }
    else {
      list($lang, $values) = each($field);
      if (!is_array($values)) {
        $values = array();
      }
    }

    foreach ($values as $value) {
      if (isset($value['value']) && $value['value'] != 0) {
        $index_value = apachesolr_date_iso($value['value']);
        $fields[] = array(
          'key' => $index_key,
          'value' => $index_value,
        );
      }
      if (isset($value['value2']) && $value['value'] != 0) {
        $index_value = apachesolr_date_iso($value['value2']);
        $fields[] = array(
          // The value2 element is the end date. Therefore it gets indexed
          // into its own Solr field.
          'key' => $index_key . '_end',
          'value' => $index_value,
        );
      }
    }
  }
  return $fields;
}

/**
 *  Indexing callback for the node_reference module
 *  by the references module
 *
 * @param object $entity
 * @param string $field_name
 * @param string $index_key
 * @param array $field_info
 * @return array $fields
 */
function apachesolr_multilingual_nodereference_indexing_callback_implementation($entity, $field_name, $index_key, array $field_info) {
  $fields = array();
  if (!empty($entity->{$field_name})) {
    if (array_key_exists($entity->language, $entity->$field_name)) {
      $index_key = apachesolr_index_key($field_info);
      foreach ($entity->{$field_name}[$entity->language] as $reference) {
        if ($index_value = (!empty($reference['nid'])) ? $reference['nid'] : FALSE) {
          $fields[] = array(
            'key' => $index_key,
            'value' => $index_value,
          );
        }
      }
    }
    else {
      return apachesolr_nodereference_indexing_callback($entity, $field_name, $index_key, $field_info);
    }
  }
  return $fields;
}

/**
 *  Indexing callback for the user_reference module
 *  by the references module
 *
 * @param object $entity
 * @param string $field_name
 * @param string $index_key
 * @param array $field_info
 * @return array $fields
 */
function apachesolr_multilingual_userreference_indexing_callback_implementation($entity, $field_name, $index_key, array $field_info) {
  $fields = array();
  if (!empty($entity->{$field_name})) {
    if (array_key_exists($entity->language, $entity->$field_name)) {
      $index_key = apachesolr_index_key($field_info);
      foreach ($entity->{$field_name}[$entity->language] as $reference) {
        if ($index_value = (isset($reference['uid']) && strlen($reference['uid'])) ? $reference['uid'] : FALSE) {
          $fields[] = array(
            'key' => $index_key,
            'value' => $index_value,
          );
        }
      }
    }
    else {
      return apachesolr_userreference_indexing_callback($entity, $field_name, $index_key, $field_info);
    }
  }
  return $fields;
}

/**
 * Indexing callback for entityreference fields.
 *
 * @param object $entity
 * @param string $field_name
 * @param string $index_key
 * @param array $field_info
 * @return array $fields
 *
 */
function apachesolr_multilingual_entityreference_indexing_callback_implementation($entity, $field_name, $index_key, $field_info) {
  $fields = array();
  if (!empty($entity->{$field_name})) {
    if (array_key_exists($entity->language, $entity->$field_name)) {

      // Gets entity type and index key. We need to prefix the ID with the entity
      // type so we know what entity we are dealing with in the mapping callback.
      $entity_type = $field_info['field']['settings']['target_type'];
      $index_key = apachesolr_index_key($field_info);

      // Iterates over all references and adds them to the fields.
      foreach ($entity->{$field_name}[$entity->language] as $reference) {
        if ($id = (!empty($reference['target_id'])) ? $reference['target_id'] : FALSE) {
          $fields[] = array(
            'key' => $index_key,
            'value' => $entity_type . ':' . $id,
          );
        }
      }
    }
    else {
      return apachesolr_entityreference_indexing_callback($entity, $field_name, $index_key, $field_info);
    }
  }
  return $fields;
}

/**
 * Callback that converts term_reference field into an array
 *
 * @param object $node
 * @param string $field_name
 * @param string $index_key
 * @param array $field_info
 * @return array $fields
 *   fields that will be indexed for this term reference
 */
function apachesolr_multilingual_term_reference_indexing_callback_implementation($node, $field_name, $index_key, array $field_info) {
  // Keep ancestors cached
  $ancestors = &drupal_static(__FUNCTION__, array());

  $fields = array();
  $vocab_names = array();
  if (!empty($node->{$field_name}) && function_exists('taxonomy_get_parents_all')) {
    $items = array();
    if (array_key_exists($node->language, $node->{$field_name}) && is_array($node->{$field_name}[$node->language])) {
      $items = $node->{$field_name}[$node->language];
    }
    else {
      list($lang, $items) = each($node->{$field_name});
      if (!is_array($items)) {
        $items = array();
      }
    }
    foreach ($items as $item) {
      // Triple indexing of tids lets us do efficient searches (on tid)
      // and do accurate per field or per-vocabulary faceting.

      // By including the ancestors to a term in the index we make
      // sure that searches for general categories match specific
      // categories, e.g. Fruit -> apple, a search for fruit will find
      // content categorized with apple.
      if (!isset($ancestors[$item['tid']])) {
        $ancestors[$item['tid']] = taxonomy_get_parents_all($item['tid']);
      }
      foreach ($ancestors[$item['tid']] as $ancestor) {
        // Index parent term against the field. Note that this happens
        // regardless of whether the facet is set to show as a hierarchy or not.
        // We would need a separate field if we were to index terms without any
        // hierarchy at all.
        // If the term is singular, then we cannot add another value to the
        // document as the field is single
        if ($field_info['multiple']) {
          $fields[] = array(
            'key' => $index_key,
            'value' => $ancestor->tid,
          );
        }
        $fields[] = array(
          'key' => 'tid',
          'value' => $ancestor->tid,
        );
        $fields[] = array(
          'key' => 'im_vid_' . $ancestor->vid,
          'value' => $ancestor->tid,
        );
        // We index each name as a string for cross-site faceting
        // using the vocab name rather than vid in field construction.
        // It's untranslated!
        $fields[] = array(
          'key' => 'sm_vid_' . apachesolr_vocab_name($ancestor->vid),
          'value' => apachesolr_clean_text($ancestor->name),
        );
        // Store the translated term for later use.
        $vocab_names[$ancestor->vid][] = apachesolr_clean_text(
          function_exists('i18n_taxonomy_term_name') ?
            i18n_taxonomy_term_name($ancestor, $node->language) :
            $ancestor->name
        );
      }
    }
    // Index the term names into a text field for MLT queries and keyword searching.
    foreach ($vocab_names as $vid => $names) {
      $fields[] = array(
        'key' => 'tm_vid_' . $vid . '_names',
        'value' => implode(' ', $names),
      );
    }
  }

  return $fields;
}
