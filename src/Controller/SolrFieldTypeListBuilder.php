<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\SolrMultilingualBackendInterface;
use ZipStream\ZipStream;

/**
 * Provides a listing of SolrFieldType.
 */
class SolrFieldTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * @var \Drupal\search_api_solr\SolrMultilingualBackendInterface
   */
  protected $backend;

  /**
   * @var string
   *   A Solr version string.
   */
  protected $assumed_minimum_version = '';

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Solr Field Type'),
      'minimum_solr_version' => $this->t('Minimum Solr Version'),
      'managed_schema' => $this->t('Managed Schema Required'),
      'langcode' => $this->t('Language'),
      'domains' => $this->t('Domains'),
      'id' => $this->t('Machine name'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $solr_field_type) {
    /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type */
    $domains = $solr_field_type->getDomains();
    if (empty($domains)) {
      $domains = ['generic'];
    }
    $row = [
      'label' => $solr_field_type->label(),
      'minimum_solr_version' => $solr_field_type->getMinimumSolrVersion(),
      // @todo format
      'managed_schema' => $solr_field_type->isManagedSchema(),
      // @todo format
      'langcode' => $solr_field_type->getFieldTypeLanguageCode(),
      // @todo format
      'domains' => implode(', ', $domains),
      'id' => $solr_field_type->id(),
    ];
    return $row + parent::buildRow($solr_field_type);
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    static $entities;

    if (!$entities) {
      $solr_version = '9999.0.0';
      $operator = '>=';
      $domain = 'generic';
      $multilingual = FALSE;
      $warning = FALSE;
      try {
        /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
        $backend = $this->getBackend();
        $domain = $backend->getDomain();
        $multilingual = ($backend instanceof SolrMultilingualBackendInterface);
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
      // We need the whole list to work on
      $this->limit = FALSE;
      $entity_ids = $this->getEntityIds();
      /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
      $storage = $this->getStorage();
      $entities = $storage->loadMultipleOverrideFree($entity_ids);

      // We filter to those field types that are relevant for the current server.
      // There are multiple entities having the same field_type.name but different
      // values for managed_schema, minimum_solr_version and domains.
      $selection = [];
      foreach ($entities as $key => $solr_field_type) {
        if ($multilingual || 'und' == $solr_field_type->getFieldTypeLanguageCode()) {
          /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type */
          $version = $solr_field_type->getMinimumSolrVersion();
          $domains = $solr_field_type->getDomains();
          if (
            $solr_field_type->isManagedSchema() != $this->getBackend()
              ->isManagedSchema() ||
            version_compare($version, $solr_version, '>') ||
            (!in_array($domain, $domains) && !in_array('generic', $domains))
          ) {
            unset($entities[$key]);
          }
          else {
            $name = $solr_field_type->getFieldTypeName();
            if (isset($selection[$name])) {
              // The more specific domain has precedence over a newer version.
              if (
                // Current selection domain is 'generic' but something more
                // specific is found.
                ('generic' != $domain && 'generic' == $selection[$name]['domain'] && in_array($domain, $domains)) ||
                // A newer version of the current selection domain is found.
                (version_compare($version, $selection[$name]['version'], $operator) && in_array($selection[$name]['domain'], $domains))
              ) {
                unset($entities[$selection[$name]['key']]);
                $selection[$name] = [
                  'version' => $version,
                  'key' => $key,
                  'domain' => in_array($domain, $domains) ? $domain : 'generic',
                ];
              }
              else {
                unset($entities[$key]);
              }
            }
            else {
              $selection[$name] = [
                'version' => $version,
                'key' => $key,
                'domain' => in_array($domain, $domains) ? $domain : 'generic',
              ];
            }
          }
        }
        else {
          unset($entities[$key]);
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
            'Unable to reach the Solr server (yet). Therefore the lowest supported Solr version %version is assumed.' .
            ' Once the connection works and the real Solr version could be detected it might be necessary to deploy an adjusted config to the server to get the best search results.' .
            ' If the server does not start using the downloadable config, you should edit the server and manually set the Solr version override temporarily that fits your server best and download the config again. But it is recommended to remove this override once the server is running.',
            ['%version' => $this->assumed_minimum_version])
        );
      }

      // Sort the entities using the entity class's sort() method.
      // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
      uasort($entities, [$this->entityType->getClass(), 'sort']);
    }

    return $entities;
  }

  /**
   * @inheritdoc
   */
  public function getDefaultOperations(EntityInterface $solr_field_type) {
    /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type */
    $operations = parent::getDefaultOperations($solr_field_type);

    if ($solr_field_type->access('view') && $solr_field_type->hasLinkTemplate('export-form')) {
      $operations['export'] = [
        'title' => $this->t('Export'),
        'weight' => 10,
        'url' => $solr_field_type->toUrl('export-form'),
      ];
    }

    return $operations;
  }

  /**
   *
   */
  public function getSchemaExtraTypesXml() {
    $xml = '';
    /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type */
    foreach ($this->load() as $solr_field_type) {
      if (!$solr_field_type->isManagedSchema()) {
        $xml .= $solr_field_type->getFieldTypeAsXml();
      }
    }
    return $xml;
  }

  /**
   *
   */
  public function getSchemaExtraFieldsXml() {
    $multilingual = ($this->getBackend() instanceof SolrMultilingualBackendInterface);
    $xml = '';
    /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type */
    foreach ($this->load() as $solr_field_type) {
      if (!$solr_field_type->isManagedSchema()) {
        foreach ($solr_field_type->getDynamicFields($multilingual) as $dynamic_field) {
          $xml .= '<dynamicField ';
          foreach ($dynamic_field as $attribute => $value) {
            $xml .= $attribute . '="' . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . '" ';
          }
          $xml .= "/>\n";
        }
        foreach ($solr_field_type->getCopyFields() as $copy_field) {
          $xml .= '<copyField ';
          foreach ($copy_field as $attribute => $value) {
            $xml .= $attribute . '="' . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . '" ';
          }
          $xml .= "/>\n";
        }
      }
    }
    return $xml;
  }

  /**
   *
   */
  public function getSolrconfigExtraXml() {
    $search_components = [];
    /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type */
    foreach ($this->load() as $solr_field_type) {
      if (!$solr_field_type->isManagedSchema()) {
        $xml = $solr_field_type->getSolrConfigsAsXml();
        if (preg_match_all('@(<searchComponent name="[^"]+"[^>]*?>)(.*?)</searchComponent>@sm', $xml, $matches)) {
          foreach ($matches[1] as $key => $search_component) {
            $search_components[$search_component][] = $matches[2][$key];
          }
        }
      }
    }

    $xml = '';
    foreach ($search_components as $search_component => $details) {
      $xml .= $search_component;
      foreach ($details as $detail) {
        $xml .= $detail;
      }
      $xml .= "</searchComponent>\n";
    }
    return $xml;
  }

  /**
   * @return \ZipStream\ZipStream
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \ZipStream\Exception\FileNotFoundException
   * @throws \ZipStream\Exception\FileNotReadableException
   */
  public function getConfigZip() {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $this->getBackend();
    $connector = $backend->getSolrConnector();
    $solr_branch = $connector->getSolrBranch($this->assumed_minimum_version);
    $search_api_solr_conf_path = drupal_get_path('module', 'search_api_solr') . '/solr-conf/' . $solr_branch;
    $solrcore_properties = parse_ini_file($search_api_solr_conf_path . '/solrcore.properties', FALSE, INI_SCANNER_RAW);

    $zip = new ZipStream('solr_' . $solr_branch . '_config.zip');
    $zip->addFile('schema_extra_types.xml', $this->getSchemaExtraTypesXml());
    $zip->addFile('schema_extra_fields.xml', $this->getSchemaExtraFieldsXml());
    $zip->addFile('solrconfig_extra.xml', $this->getSolrconfigExtraXml());

    // Add language specific text files.
    $solr_field_types = $this->load();
    /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type */
    foreach ($solr_field_types as $solr_field_type) {
      $text_files = $solr_field_type->getTextFiles();
      foreach ($text_files as $text_file_name => $text_file) {
        if ($custom_code = $solr_field_type->getCustomCode()) {
          $text_file_name .= '_' . $custom_code;
        }
        $text_file_name .= '_' . $solr_field_type->getFieldTypeLanguageCode() . '.txt';
        $zip->addFile($text_file_name, $text_file);
        $solrcore_properties['solr.replication.confFiles'] .= ',' . $text_file_name;
      }
    }

    $solrcore_properties['solr.luceneMatchVersion'] = $connector->getLuceneMatchVersion($this->assumed_minimum_version ?: '');
    // @todo
    // $solrcore_properties['solr.replication.masterUrl']
    $solrcore_properties_string = '';
    foreach ($solrcore_properties as $property => $value) {
      $solrcore_properties_string .= $property . '=' . $value . "\n";
    }
    $zip->addFile('solrcore.properties', $solrcore_properties_string);

    // @todo provide a hook to add more things.

    // Now add all remaining static files from the conf dir that have not been
    // generated dynamically above.
    foreach (scandir($search_api_solr_conf_path) as $file) {
      if (strpos($file, '.') !== 0) {
        foreach ($zip->files as $zipped_file) {
          /* @see \ZipStream\ZipStream::addToCdr() */
          if ($file == $zipped_file[0]) {
            continue(2);
          }
        }
        $zip->addFileFromPath($file, $search_api_solr_conf_path . '/' . $file);
      }
    }

    return $zip;
  }

  /**
   * Sets the Search API server and calls setBackend() afterwards.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function setServer(ServerInterface $server) {
    $this->setBackend($server->getBackend());

  }

  /**
   * Sets the Search API server backend.
   *
   * @param \Drupal\search_api_solr\SolrBackendInterface $backend
   */
  public function setBackend(SolrBackendInterface $backend) {
    $this->backend = $backend;

  }

  /**
   * @return \Drupal\search_api_solr\SolrBackendInterface
   */
  protected function getBackend() {
    return $this->backend;
  }

}
