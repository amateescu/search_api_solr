<?php

/**
 * @file
 * Contains Drupal\search_api_solr_multilingual\Controller\SolrFieldTypeListBuilder.
 */

namespace Drupal\search_api_solr_multilingual\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr_multilingual\SolrFieldTypeInterface;
use Drupal\search_api_solr_multilingual\SolrMultilingualBackendInterface;
use ZipStream\ZipStream;

/**
 * Provides a listing of SolrFieldType.
 */
class SolrFieldTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * @var SolrMultilingualBackendInterface
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
      'id' => $this->t('Machine name'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $solr_field_type) {
    /** @var \Drupal\search_api_solr_multilingual\SolrFieldTypeInterface $solr_field_type */
    $row = [
      'label' => $solr_field_type->label(),
      'minimum_solr_version' => $solr_field_type->getMinimumSolrVersion(),
      // @todo format
      'managed_schema' => $solr_field_type->isManagedSchema(),
      // @todo format
      'langcode' => $solr_field_type->language()->getId(),
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
      $warning = FALSE;
      try {
        $solr_version = $this->getBackend()->getSolrHelper()->getSolrVersion();
      } catch (SearchApiSolrException $e) {
        $operator = '<=';
        $warning = TRUE;
      }
      $entity_ids = $this->getEntityIds();
      /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
      $storage = $this->getStorage();
      $entities = $storage->loadMultipleOverrideFree($entity_ids);

      // We filter to those field types that are relevant for the current server.
      // There are multiple entities having the same field_type.name but different
      // values for managed_schema and minimum_solr_version.
      $minimium_versions = [];
      foreach ($entities as $key => $solr_field_type) {
        /** @var SolrFieldTypeInterface $solr_field_type */
        $version = $solr_field_type->getMinimumSolrVersion();
        if ($solr_field_type->isManagedSchema() != $this->getBackend()
            ->isManagedSchema() ||
          version_compare($version, $solr_version, '>')
        ) {
          unset($entities[$key]);
        }
        else {
          $name = $solr_field_type->getFieldTypeName();
          if (isset($minimium_versions[$name])) {
            if (version_compare($version, $minimium_versions[$name]['version'], $operator)) {
              unset($entities[$minimium_versions[$name]['key']]);
              $minimium_versions[$name] = [
                'version' => $version,
                'key' => $key
              ];
            }
          }
          else {
            $minimium_versions[$name] = ['version' => $version, 'key' => $key];
          }
        }
      }

      if ($warning) {
        $this->assumed_minimum_version = array_reduce($minimium_versions, function ($version, $item) {
          if (version_compare($item['version'], $version, '<')) {
            return $item['version'];
          }
          return $version;
        }, $solr_version);

        drupal_set_message(
          $this->t(
            'Unable to reach the Solr server (yet). Therefor the lowest supported Solr version %version is assumed.'.
            ' Once the connection works and the real Solr version could be detected it might be necessary to deploy an adjusted config to the server to get the best search results.' .
            ' If the server does not start using the downloadable config, you should edit the server add manually set the Solr version override temporarily that fits your server best and download the config again. But it is recommended to remove this override once the server is running.',
            ['%version' => $this->assumed_minimum_version]),
          'warning');
      }

      // Sort the entities using the entity class's sort() method.
      // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
      uasort($entities, array($this->entityType->getClass(), 'sort'));
    }

    return $entities;
  }

  /**
   * @inheritdoc
   */
  public function getDefaultOperations(EntityInterface $solr_field_type) {
    /** @var \Drupal\search_api_solr_multilingual\SolrFieldTypeInterface $solr_field_type */
    $operations = parent::getDefaultOperations($solr_field_type);

    if ($solr_field_type->access('view') && $solr_field_type->hasLinkTemplate('export-form')) {
      $operations['export'] = array(
        'title' => $this->t('Export'),
        'weight' => 10,
        'url' => $solr_field_type->toUrl('export-form'),
      );
    }

    return $operations;
  }

  public function getSchemaExtraTypesXml() {
    return $this->getPlainTextRenderArray($this->generateSchemaExtraTypesXml());
  }

  protected function generateSchemaExtraTypesXml() {
    $target_solr_version = $this->getBackend()->getServer()->getBackendConfig()['solr_version'];
    $indentation = '  ';
    if (version_compare($target_solr_version, '6.0.0', '>=')) {
      $indentation .= '  ';
    }
    $xml = $this->getExtraFileHead($target_solr_version, 'types');
    /** @var SolrFieldTypeInterface $solr_field_type */
    foreach ($this->load() as $solr_field_type) {
      if (!$solr_field_type->isManagedSchema()) {
        $xml .= "\n$indentation" . str_replace("\n", "\n$indentation", $solr_field_type->getFieldTypeAsXml());
      }
    }
    $xml .= "\n" . $this->getExtraFileFoot($target_solr_version, 'types');

    return $xml;
  }

  public function getSchemaExtraFieldsXml() {
    return $this->getPlainTextRenderArray($this->generateSchemaExtraFieldsXml());
  }

  protected function generateSchemaExtraFieldsXml() {
    $target_solr_version = $this->getBackend()->getServer()->getBackendConfig()['solr_version'];
    $xml = $this->getExtraFileHead($target_solr_version, 'fields');
    $indentation = '  ';
    if (version_compare($target_solr_version, '6.0.0', '>=')) {
      $indentation .= '  ';
    }

    /** @var SolrFieldTypeInterface $solr_field_type */
    foreach ($this->load() as $solr_field_type) {
      if (!$solr_field_type->isManagedSchema()) {
        foreach ($solr_field_type->getDynamicFields() as $dynamic_field) {
          $xml .= $indentation . '<dynamicField ';
          foreach ($dynamic_field as $attribute => $value) {
            $xml .= $attribute . '="' . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . '" ';
          }
          $xml .= " />\n";
        }
      }
    }
    $xml .= $this->getExtraFileFoot($target_solr_version, 'fields');

    return $xml;
  }

  protected function getPlainTextRenderArray($plain_text) {
    return ['file' => [
      '#plain_text' => $plain_text,
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ],];
  }

  /**
   * Creates the head part of an extra file XML (not wellformed on its own).
   *
   * @param $target_solr_version string The version string of the Solr version to
   *                                    create the file for.
   * @param $legacy_element string The XML element to use as a wrapper for versions of
   *                               Solr below 6.0.0.
   * @return string The created fragment.
   */
  protected function getExtraFileHead($target_solr_version, $legacy_element) {
    $head = '';
    if (version_compare($target_solr_version, '6.0.0', '<')) {
      $head = <<<'EOD'
<?xml version="1.0" encoding="UTF-8" ?>
EOD;
      $head .= "\n\n";
      $head .= "<$legacy_element>\n";
    }
    return $head;
  }

  /**
   * Creates the foot part of an extra file XML (not wellformed on its own).
   *
   * @param $target_solr_version string The version string of the Solr version to
   *                                    create the file for.
   * @param $legacy_element string The XML element to use as a wrapper for versions of
   *                               Solr below 6.0.0.
   * @return string The created fragment.
   */
  protected function getExtraFileFoot($target_solr_version, $legacy_element) {
    $foot = '';
    if (version_compare($target_solr_version, '6.0.0', '<')) {
      $foot .= "</$legacy_element>";
    }
    return $foot;
  }

  /**
   * @return \ZipStream\ZipStream
   */
  public function getConfigZip() {
    $solr_field_types = $this->load();

    $solr_helper = $this->getBackend()->getSolrHelper();
    $solr_branch = $solr_helper->getSolrBranch($this->assumed_minimum_version);
    $search_api_solr_conf_path = drupal_get_path('module', 'search_api_solr') . '/solr-conf/' . $solr_branch;
    $solrcore_properties = parse_ini_file($search_api_solr_conf_path . '/solrcore.properties', FALSE, INI_SCANNER_RAW);
    $schema = file_get_contents($search_api_solr_conf_path . '/schema.xml');
    $schema = preg_replace('@<fieldType name="text_und".*?</fieldType>@ms', '<!-- fieldType text_und is moved to schema_extra_types.xml by Search API Multilingual Solr -->', $schema);
    $schema = preg_replace('@<dynamicField name="([^"]*)".*?type="text_und".*?/>@', "<!-- dynamicField $1 is moved to schema_extra_fields.xml by Search API Multilingual Solr -->", $schema);

    $zip = new ZipStream('solr_' . $solr_branch . '_config.zip');
    $zip->addFile('schema.xml', $schema);
    $zip->addFile('schema_extra_types.xml', $this->generateSchemaExtraTypesXml());
    $zip->addFile('schema_extra_fields.xml', $this->generateSchemaExtraFieldsXml());

    // Add language specific text files.
    /** @var SolrFieldTypeInterface $solr_field_type */
    foreach ($solr_field_types as $solr_field_type) {
      $text_files = $solr_field_type->getTextFiles();
      foreach ($text_files as $text_file_name => $text_file) {
        $language_specific_text_file_name = $text_file_name . '_' . $solr_field_type->language()->getId() . '.txt';
        $zip->addFile($language_specific_text_file_name, $text_file);
        $solrcore_properties['solr.replication.confFiles'] .= ',' . $language_specific_text_file_name;
      }
    }

    $solrcore_properties['solr.luceneMatchVersion'] = $solr_helper->getLuceneMatchVersion($this->assumed_minimum_version ?: '');
    // @todo
    //$solrcore_properties['solr.replication.masterUrl']

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

  public function setServer(ServerInterface $server) {
    $this->backend = $server->getBackend();

  }

  /**
   * @return \Drupal\search_api_solr_multilingual\SolrMultilingualBackendInterface
   */
  protected function getBackend() {
    return $this->backend;
  }

}
