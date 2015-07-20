<?php

/**
 * @file
 * Contains Drupal\apachesolr_multilingual\Entity\TextFile.
 */

namespace Drupal\apachesolr_multilingual\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\apachesolr_multilingual\TextFileInterface;

/**
 * Defines the TextFile entity.
 *
 * @ConfigEntityType(
 *   id = "asm_text_file",
 *   label = @Translation("TextFile"),
 *   handlers = {
 *     "list_builder" = "Drupal\apachesolr_multilingual\Controller\TextFileListBuilder",
 *     "form" = {
 *       "add" = "Drupal\apachesolr_multilingual\Form\TextFileForm",
 *       "edit" = "Drupal\apachesolr_multilingual\Form\TextFileForm",
 *       "delete" = "Drupal\apachesolr_multilingual\Form\TextFileDeleteForm"
 *     }
 *   },
 *   config_prefix = "asm_text_file",
 *   admin_permission = "administer search_api",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/search/search-api/server/{search_api_server}/multilingual/asm_text_file/{asm_text_file}",
 *     "delete-form" = "/admin/config/search/search-api/server/{search_api_server}/multilingual/asm_text_file/{asm_text_file}/delete",
 *     "collection" = "/admin/config/search/search-api/server/{search_api_server}/multilingual/asm_text_file"
 *   }
 * )
 */
class TextFile extends ConfigEntityBase implements TextFileInterface {
  /**
   * The TextFile ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The TextFile label.
   *
   * @var string
   */
  protected $label;

}
