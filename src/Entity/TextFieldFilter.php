<?php

/**
 * @file
 * Contains Drupal\apachesolr_multilingual\Entity\TextFieldFilter.
 */

namespace Drupal\apachesolr_multilingual\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\apachesolr_multilingual\TextFieldFilterInterface;

/**
 * Defines the TextFieldFilter entity.
 *
 * @ConfigEntityType(
 *   id = "asm_text_field_filter",
 *   label = @Translation("TextFieldFilter"),
 *   handlers = {
 *     "list_builder" = "Drupal\apachesolr_multilingual\Controller\TextFieldFilterListBuilder",
 *     "form" = {
 *       "add" = "Drupal\apachesolr_multilingual\Form\TextFieldFilterForm",
 *       "edit" = "Drupal\apachesolr_multilingual\Form\TextFieldFilterForm",
 *       "delete" = "Drupal\apachesolr_multilingual\Form\TextFieldFilterDeleteForm"
 *     }
 *   },
 *   config_prefix = "asm_text_field_filter",
 *   admin_permission = "administer search_api",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/search/search-api/server/{search_api_server}/multilingual/asm_text_field_filter/{asm_text_field_filter}",
 *     "delete-form" = "/admin/config/search/search-api/server/{search_api_server}/multilingual/asm_text_field_filter/{asm_text_field_filter}/delete",
 *     "collection" = "/admin/config/search/search-api/server/{search_api_server}/multilingual/asm_text_field_filter"
 *   }
 * )
 */
class TextFieldFilter extends ConfigEntityBase implements TextFieldFilterInterface {
  /**
   * The TextFieldFilter ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The TextFieldFilter label.
   *
   * @var string
   */
  protected $label;

  /**
   * Gets an array of placeholders for this entity.
   *
   * Individual entity classes may override this method to add additional
   * placeholders if desired. If so, they should be sure to replicate the
   * property caching logic.
   *
   * @param string $rel
   *   The link relationship type, for example: canonical or edit-form.
   *
   * @return array
   *   An array of URI placeholders.
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    $uri_route_parameters['search_api_server'] = \Drupal::routeMatch()->getRawParameter('search_api_server');

    return $uri_route_parameters;
  }

}
