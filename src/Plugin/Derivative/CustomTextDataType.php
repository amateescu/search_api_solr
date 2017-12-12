<?php

namespace Drupal\search_api_solr\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api_solr\Entity\SolrFieldType;
use Drupal\search_api_solr\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for custom full text data types.
 *
 * @see \Drupal\search_api_solr\Plugin\search_api\data_type\CustomTextDataType
 */
class CustomTextDataType extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach (SolrFieldType::getAvailableCustomCodes() as $custom_code) {
      $this->derivatives[$custom_code] = $base_plugin_definition;
      $this->derivatives[$custom_code]['label'] =
        $this->t('Fulltext Custom ":custom_code"', [':custom_code' => $custom_code]);
      $this->derivatives[$custom_code]['prefix'] =
        Utility::getCustomCodeSpecificSolrDynamicFieldPrefix(
          $this->derivatives[$custom_code]['prefix'], $custom_code);
    }
    return $this->derivatives;
  }

}
