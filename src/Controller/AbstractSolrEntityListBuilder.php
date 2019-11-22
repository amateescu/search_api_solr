<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\search_api_solr\SearchApiSolrConflictingEntitiesException;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrConfigInterface;

/**
 * Provides a listing of Solr Entities.
 */
abstract class AbstractSolrEntityListBuilder extends ConfigEntityListBuilder {

  use BackendTrait;

  /**
   * @var string
   */
  protected $label = '';

  /**
   * @var string
   */
  protected $option_label = 'Environment';

  /**
   * @var string
   */
  protected $default_option = 'default';

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t($this->label),
      'minimum_solr_version' => $this->t('Minimum Solr Version'),
      'option' => $this->t($this->option_label),
      'id' => $this->t('Machine name'),
      'enabled' => $this->t('Enabled'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $solr_entity) {
    /** @var SolrConfigInterface $solr_entity */
    $options = $solr_entity->getOptions();
    if (empty($options)) {
      $options = [$this->default_option];
    }

    $enabled_label = $solr_entity->disabledOnServer ? $this->t('Disabled') : $this->t('Enabled');
    $enabled_icon = [
      '#theme' => 'image',
      '#uri' => !$solr_entity->disabledOnServer ? 'core/misc/icons/73b355/check.svg' : 'core/misc/icons/e32700/error.svg',
      '#width' => 18,
      '#height' => 18,
      '#alt' => $enabled_label,
      '#title' => $enabled_label,
    ];

    $row = [
      'label' => $solr_entity->label(),
      'minimum_solr_version' => $solr_entity->getMinimumSolrVersion(),
      // @todo format
      'options' => implode(', ', $options),
      'id' => $solr_entity->id(),
      'enabled' => [
        'data' => $enabled_icon,
        'class' => ['checkbox'],
      ],
    ];
    return $row + parent::buildRow($solr_entity);
  }

  /**
   * Returns a list of all enabled caches for current server.
   *
   * @return array
   *
   * @throws SearchApiSolrConflictingEntitiesException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getEnabledEntities(): array {
    $solr_entities = [];
    /** @var SolrConfigInterface[] $entities */
    $entities = $this->load();
    foreach ($this->load() as $solr_entity) {
      if (!$solr_entity->disabledOnServer) {
        $solr_entities[] = $solr_entity;
      }
    }

    if ($conflicting_entities = $this->getConflictingEntities($solr_entities)) {
      $exception = new SearchApiSolrConflictingEntitiesException();
      $exception->setConflictingEntities($conflicting_entities);
      throw $exception;
    }

    return $solr_entities;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getDefaultOperations(EntityInterface $solr_entity) {
    /** @var SolrConfigInterface $solr_entity */
    $operations = parent::getDefaultOperations($solr_entity);
    unset($operations['delete']);

    if (!$solr_entity->disabledOnServer && $solr_entity->access('view') && $solr_entity->hasLinkTemplate('disable-for-server')) {
      $operations['disable_for_server'] = [
        'title' => $this->t('Disable'),
        'weight' => 10,
        'url' => $solr_entity->toUrl('disable-for-server'),
      ];
    }

    if ($solr_entity->disabledOnServer && $solr_entity->access('view') && $solr_entity->hasLinkTemplate('enable-for-server')) {
      $operations['enable_for_server'] = [
        'title' => $this->t('Enable'),
        'weight' => 10,
        'url' => $solr_entity->toUrl('enable-for-server'),
      ];
    }

    return $operations;
  }

  abstract protected function getDisabledEntities() : array;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function load() {
    static $entities;

    if (!$entities || $this->reset) {
      $solr_version = '9999.0.0';
      $operator = '>=';
      $option = $this->default_option;
      $warning = FALSE;
      $disabled_entities = [];
      try {
        /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
        $backend = $this->getBackend();
        $disabled_entities = $this->getDisabledEntities();
        $option = $backend->getEnvironment();
        $solr_version = $backend->getSolrConnector()->getSolrVersion();
        if (version_compare($solr_version, '0.0.0', '==')) {
          $solr_version = '9999.0.0';
          throw new SearchApiSolrException();
        }
      }
      catch (SearchApiSolrException $e) {
        $operator = '<=';
        $warning = TRUE;
      }

      // We need the whole list to work on.
      $this->limit = FALSE;
      $entity_ids = $this->getEntityIds();
      /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
      $storage = $this->getStorage();
      /** @var \Drupal\search_api_solr\Entity\SolrCache[] $entities */
      $entities = $storage->loadMultipleOverrideFree($entity_ids);

      // We filter those caches that are relevant for the current server. There
      // are multiple entities having the same cache.name but different values
      // for minimum_solr_version and environment.
      $selection = [];
      foreach ($entities as $key => $entity) {
        $entities[$key]->disabledOnServer = in_array($entity->id(), $disabled_entities);
        /** @var \Drupal\search_api_solr\SolrConfigInterface $entity
         */
        $version = $entity->getMinimumSolrVersion();
        $environments = $entity->getEnvironments();
        if (
          version_compare($version, $solr_version, '>') ||
          (!in_array($option, $environments) && !in_array($this->default_option, $environments))
        ) {
          unset($entities[$key]);
        }
        else {
          $name = $entity->getName();
          if (isset($selection[$name])) {
            // The more specific environment has precedence over a newer version.
            if (
              // Current selection environment is 'default' but something more
              // specific is found.
              ($this->default_option !== $option && $this->default_option === $selection[$name]['option'] && in_array($option, $environments)) ||
              // A newer version of the current selection domain is found.
              (version_compare($version, $selection[$name]['version'], $operator) && in_array($selection[$name]['option'], $environments))
            ) {
              $this->mergeSolrConfigs($entities[$key], $entities[$selection[$name]['key']]);
              unset($entities[$selection[$name]['key']]);
              $selection[$name] = [
                'version' => $version,
                'key' => $key,
                'option' => in_array($option, $environments) ? $option : $this->default_option,
              ];
            }
            else {
              $this->mergeSolrConfigs($entities[$selection[$name]['key']], $entities[$key]);
              unset($entities[$key]);
            }
          }
          else {
            $selection[$name] = [
              'version' => $version,
              'key' => $key,
              'option' => in_array($option, $environments) ? $option : $this->default_option,
            ];
          }
        }
      }

      if ($warning) {
        $this->assumed_minimum_version = array_reduce($selection, function ($version, $item) {
          if (version_compare($item['version'], $version, '<')) {
            return $item['version'];
          }
          return $version;
        }, $solr_version);

        \Drupal::messenger()->addWarning(
          $this->t(
            'Unable to reach the Solr server (yet). Therefore the lowest supported Solr version %version is assumed. Once the connection works and the real Solr version could be detected it might be necessary to deploy an adjusted config to the server to get the best search results. If the server does not start using the downloadable config, you should edit the server and manually set the Solr version override temporarily that fits your server best and download the config again. But it is recommended to remove this override once the server is running.',
            ['%version' => $this->assumed_minimum_version])
        );
      }

      // Sort the entities using the entity class's sort() method.
      // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
      uasort($entities, [$this->entityType->getClass(), 'sort']);
      $this->reset = FALSE;
    }

    return $entities;
  }

  /**
   * @param \Drupal\search_api_solr\SolrCacheInterface $target
   * @param \Drupal\search_api_solr\SolrCacheInterface $source
   */
  protected function mergeSolrConfigs($target, $source) {
    if (empty($target->getSolrConfigs()) && !empty($source->getSolrConfigs())) {
      $target->setSolrConfigs($source->getSolrConfigs());
    }
  }

  /**
   * Returns the formatted XML for the entities.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getXml() {
    $xml = '';
    /** @var \Drupal\search_api_solr\SolrCacheInterface $solr_entities */
    foreach ($this->getEnabledEntities() as $solr_entities) {
      $xml .= $solr_entities->getAsXml();
    }
    foreach ($this->load() as $solr_entities) {
      $xml .= $solr_entities->getSolrConfigsAsXml();
    }
    return $xml;
  }

  /**
   * @return array
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getRecommendedEntities(): array {
    $entities = $this->load();
    foreach ($entities as $key => $entity) {
      if (!$entity->isRecommended()) {
        unset($entities[$key]);
      }
    }
    return $entities;
  }

  /**
   * @return array
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getNotRecommendedEntities(): array {
    $entities = $this->load();
    foreach ($entities as $key => $entity) {
      if ($entity->isRecommended()) {
        unset($entities[$key]);
      }
    }
    return $entities;
  }

  /**
   * @return array
   */
  public function getAllRecommendedEntities(): array {
    $entities = ConfigEntityListBuilder::load();
    foreach ($entities as $key => $entity) {
      if (!$entity->isRecommended()) {
        unset($entities[$key]);
      }
    }
    return $entities;
  }

  /**
   * @return array
   */
  public function getAllNotRecommendedEntities(): array {
    $entities = ConfigEntityListBuilder::load();
    foreach ($entities as $key => $entity) {
      if ($entity->isRecommended()) {
        unset($entities[$key]);
      }
    }
    return $entities;
  }

  /**
   * @return array
   */
  public function getConflictingEntities(array $entities): array {
    $conflicting_entities = [];
    $purpose_ids = [];
    foreach ($entities as $key => $entity) {
      $purpose_id = $entity->getPurposeId();
      if (!in_array($purpose_id, $purpose_ids)) {
        $purpose_ids[] = $purpose_id;
      }
      else {
        $conflicting_entities[$key] = $entity;
      }
    }
    return $conflicting_entities;
  }

}
