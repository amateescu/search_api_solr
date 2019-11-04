<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\Utility\Utility;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

/**
 * Provides a listing of SolrCache.
 */
class SolrCacheListBuilder extends ConfigEntityListBuilder {

  /**
   * The Search API server backend.
   *
   * @var \Drupal\search_api_solr\SolrBackendInterface
   */
  protected $backend;

  /**
   * The Search API server ID.
   *
   * @var string
   */
  protected $serverId = '';

  /**
   * The Solr minimum version string.
   *
   * @var string
   */
  protected $assumed_minimum_version = '';

  /**
   * @var bool
   */
  protected $reset = FALSE;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Solr Field Type'),
      'minimum_solr_version' => $this->t('Minimum Solr Version'),
      'environment' => $this->t('Environment'),
      'id' => $this->t('Machine name'),
      'enabled' => $this->t('Enabled'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $solr_cache) {
    /** @var \Drupal\search_api_solr\SolrCacheInterface $solr_cache */
    $environments = $solr_cache->getEnvironments();
    if (empty($environments)) {
      $domains = ['default'];
    }

    $enabled_label = $solr_cache->disabledOnServer ? $this->t('Disabled') : $this->t('Enabled');
    $enabled_icon = [
      '#theme' => 'image',
      '#uri' => !$solr_cache->disabledOnServer ? 'core/misc/icons/73b355/check.svg' : 'core/misc/icons/e32700/error.svg',
      '#width' => 18,
      '#height' => 18,
      '#alt' => $enabled_label,
      '#title' => $enabled_label,
    ];

    $row = [
      'label' => $solr_cache->label(),
      'minimum_solr_version' => $solr_cache->getMinimumSolrVersion(),
      // @todo format
      'domains' => implode(', ', $environments),
      'id' => $solr_cache->id(),
      'enabled' => [
        'data' => $enabled_icon,
        'class' => ['checkbox'],
      ],
    ];
    return $row + parent::buildRow($solr_cache);
  }

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
      $environment = 'default';
      $warning = FALSE;
      $disabled_caches = [];
      try {
        /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
        $backend = $this->getBackend();
        $disabled_caches = $backend->getDisabledCaches();
        $environment = $backend->getEnvironment();
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
      foreach ($entities as $key => $solr_cache) {
        $entities[$key]->disabledOnServer = in_array($solr_cache->id(), $disabled_caches);
        /** @var \Drupal\search_api_solr\SolrCacheInterface $solr_cache */
        $version = $solr_cache->getMinimumSolrVersion();
        $environments = $solr_cache->getEnvironments();
        if (
          version_compare($version, $solr_version, '>') ||
          (!in_array($environment, $environments) && !in_array('default', $environments))
        ) {
          unset($entities[$key]);
        }
        else {
          $name = $solr_cache->getCacheName();
          if (isset($selection[$name])) {
            // The more specific environment has precedence over a newer version.
            if (
              // Current selection environment is 'default' but something more
              // specific is found.
              ('default' !== $environment && 'default' === $selection[$name]['environment'] && in_array($environment, $environments)) ||
              // A newer version of the current selection domain is found.
              (version_compare($version, $selection[$name]['version'], $operator) && in_array($selection[$name]['environment'], $environments))
            ) {
              $this->mergeCaches($entities[$key], $entities[$selection[$name]['key']]);
              unset($entities[$selection[$name]['key']]);
              $selection[$name] = [
                'version' => $version,
                'key' => $key,
                'environment' => in_array($environment, $environments) ? $environment : 'default',
              ];
            }
            else {
              $this->mergeCaches($entities[$selection[$name]['key']], $entities[$key]);
              unset($entities[$key]);
            }
          }
          else {
            $selection[$name] = [
              'version' => $version,
              'key' => $key,
              'domain' => in_array($environment, $environments) ? $environment : 'default',
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
   * Returns a list of all enabled caches for current server.
   *
   * @return array
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getEnabledSolrCaches(): array {
    $solr_caches = [];
    foreach ($this->load() as $solr_cache) {
      if (!$solr_cache->disabledOnServer) {
        $solr_caches[] = $solr_cache;
      }
    }
    return $solr_caches;
  }

  /**
   * @param \Drupal\search_api_solr\SolrCacheInterface $target
   * @param \Drupal\search_api_solr\SolrCacheInterface $source
   */
  protected function mergeCaches($target, $source) {
    if (empty($target->getSolrConfigs()) && !empty($source->getSolrConfigs())) {
      $target->setSolrConfigs($source->getSolrConfigs());
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getDefaultOperations(EntityInterface $solr_cache) {
    /** @var \Drupal\search_api_solr\SolrCacheInterface $solr_cache */
    $operations = parent::getDefaultOperations($solr_cache);
    unset($operations['delete']);

    if (!$solr_cache->disabledOnServer && $solr_cache->access('view') && $solr_cache->hasLinkTemplate('disable-for-server')) {
      $operations['disable_for_server'] = [
        'title' => $this->t('Disable'),
        'weight' => 10,
        'url' => $solr_cache->toUrl('disable-for-server'),
      ];
    }

    if ($solr_cache->disabledOnServer && $solr_cache->access('view') && $solr_cache->hasLinkTemplate('enable-for-server')) {
      $operations['enable_for_server'] = [
        'title' => $this->t('Enable'),
        'weight' => 10,
        'url' => $solr_cache->toUrl('enable-for-server'),
      ];
    }

    return $operations;
  }

  /**
   * Returns the formatted XML for caches solrconfig.xml.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getCachesXml() {
    $xml = '';
    /** @var \Drupal\search_api_solr\SolrCacheInterface $solr_cache */
    foreach ($this->getEnabledSolrCaches() as $solr_cache) {
      $xml .= $solr_cache->getCacheAsXml();
      $xml .= $solr_cache->getSolrConfigsAsXml();
    }
    return $xml;
  }

  /**
   * Sets the Search API server and calls setBackend() afterwards.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   The Search API server entity.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function setServer(ServerInterface $server) {
    /** @var SolrBackendInterface $backend */
    $backend = $server->getBackend();
    $this->setBackend($backend);
    $this->serverId = $server->id();
  }

  /**
   * Sets the Search API server backend.
   *
   * @param \Drupal\search_api_solr\SolrBackendInterface $backend
   *   The Search API server backend.
   */
  public function setBackend(SolrBackendInterface $backend) {
    $this->backend = $backend;
    $this->reset = TRUE;
  }

  /**
   * Returns the Search API server backend.
   *
   * @return \Drupal\search_api_solr\SolrBackendInterface
   *   The Search API server backend.
   */
  protected function getBackend() {
    return $this->backend;
  }

}
