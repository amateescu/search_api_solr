<?php

namespace Drupal\search_api_solr\Solr;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\Utility as SearchApiUtility;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\Utility\Utility as SearchApiSolrUtility;
use Solarium\Client;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\Request;
use Solarium\Core\Query\Helper as SolariumHelper;
use Solarium\Exception\HttpException;
use Solarium\Exception\OutOfBoundsException;
use Solarium\QueryType\Select\Query\Query;

/**
 * Contains helper methods for working with Solr.
 */
class SolrHelper {

  /**
   * A connection to the Solr server.
   *
   * @var \Solarium\Client
   */
  protected $solr;

  /**
   * A connection to the Solr server.
   *
   * @var array
   */
  protected $configuration;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * Sets the solr connection.
   *
   * @param \Solarium\Client $solr
   *   The solarium connection object.
   */
  public function setSolr(Client $solr) {
    $this->solr = $solr;
    try {
      $this->solr->getEndpoint('server');
    }
    catch (OutOfBoundsException $e) {
      $this->attachServerEndpoint();
    }
  }

  /**
   * Extract and format highlighting information for a specific item.
   *
   * Will also use highlighted fields to replace retrieved field data, if the
   * corresponding option is set.
   *
   * @param array $data
   *   The data extracted from a Solr result.
   * @param string $solr_id
   *   The ID of the result item.
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The fields of the result item.
   * @param array $field_mapping
   *   Mapping from search_api field names to Solr field names.
   *
   * @return bool|string
   *   FALSE if no excerpt is returned from Solr, the excerpt string otherwise.
   */
  public function getExcerpt($data, $solr_id, ItemInterface $item, array $field_mapping) {
    if (!isset($data['highlighting'][$solr_id])) {
      return FALSE;
    }
    $output = '';
    // @todo using the spell field is not the optimal solution.
    if (!empty($this->configuration['excerpt']) && !empty($data['highlighting'][$solr_id]['spell'])) {
      foreach ($data['highlighting'][$solr_id]['spell'] as $snippet) {
        $snippet = strip_tags($snippet);
        $snippet = preg_replace('/^.*>|<.*$/', '', $snippet);
        $snippet = SearchApiSolrUtility::formatHighlighting($snippet);
        // The created fragments sometimes have leading or trailing punctuation.
        // We remove that here for all common cases, but take care not to remove
        // < or > (so HTML tags stay valid).
        $snippet = trim($snippet, "\00..\x2F:;=\x3F..\x40\x5B..\x60");
        $output .= $snippet . ' … ';
      }
    }
    if (!empty($this->configuration['highlight_data'])) {
      $item_fields = $item->getFields();
      foreach ($field_mapping as $search_api_property => $solr_property) {
        if ((strpos($solr_property, 'ts_') === 0 || strpos($solr_property, 'tm_') === 0) && !empty($data['highlighting'][$solr_id][$solr_property])) {
          $snippets = [];
          foreach ($data['highlighting'][$solr_id][$solr_property] as $value) {
            // Contrary to above, we here want to preserve HTML, so we just
            // replace the [HIGHLIGHT] tags with the appropriate format.
            $snippets[] = [
              'raw' => preg_replace('#\[(/?)HIGHLIGHT\]#', '', $value),
              'replace' => SearchApiSolrUtility::formatHighlighting($value),
            ];
          }
          if ($snippets) {
            $values = $item_fields[$search_api_property]->getValues();
            foreach ($values as $value) {
              foreach ($snippets as $snippet) {
                if ($value->getText() === $snippet['raw']) {
                  $value->setText($snippet['replace']);
                }
              }
            }
            $item_fields[$search_api_property]->setValues($values);
          }
        }
      }
    }

    return $output;
  }

  /**
   * Flatten a keys array into a single search string.
   *
   * @param array $keys
   *   The keys array to flatten, formatted as specified by
   *   \Drupal\search_api\Query\QueryInterface::getKeys().
   *
   * @return string
   *   A Solr query string representing the same keys.
   */
  public function flattenKeys(array $keys) {
    $k = [];
    $pre = ($keys['#conjunction'] == 'OR') ? '' : '+';
    $neg = empty($keys['#negation']) ? '' : '-';

    foreach ($keys as $key_nr => $key) {
      // We cannot use \Drupal\Core\Render\Element::children() anymore because
      // $keys is not a valid render array.
      if ($key_nr[0] === '#' || !$key) {
        continue;
      }
      if (is_array($key)) {
        $subkeys = $this->flattenKeys($key);
        if ($subkeys) {
          $nested_expressions = TRUE;
          $k[] = "($subkeys)";
        }
      }
      else {
        $solariumHelper = new SolariumHelper();
        $k[] = $solariumHelper->escapePhrase(trim($key));
      }
    }
    if (!$k) {
      return '';
    }

    // Formatting the keys into a Solr query can be a bit complex. Keep in mind
    // that the default operator is OR. The following code will produce filters
    // that look like this:
    //
    // #conjunction | #negation | return value
    // ----------------------------------------------------------------
    // AND          | FALSE     | (+A +B +C)
    // AND          | TRUE      | -(+A +B +C)
    // OR           | FALSE     | (A B C)
    // OR           | TRUE      | -(A B C)
    //
    // If there was just a single, unnested key, we can ignore all this.
    if (count($k) == 1 && empty($nested_expressions)) {
      return $neg . reset($k);
    }

    return $neg . '(' . $pre . implode(' ' . $pre, $k) . ')';
  }

  /**
   * Sets the highlighting parameters.
   *
   * (The $query parameter currently isn't used and only here for the potential
   * sake of subclasses.)
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The Solarium select query object.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query object.
   */
  public function setHighlighting(Query $solarium_query, QueryInterface $query) {
    $excerpt = !empty($this->configuration['excerpt']);
    $highlight = !empty($this->configuration['highlight_data']);

    if ($highlight || $excerpt) {
      $highlighter = \Drupal::config('search_api_solr.standard_highlighter');

      $hl = $solarium_query->getHighlighting();
      $hl->setSimplePrefix('[HIGHLIGHT]');
      $hl->setSimplePostfix('[/HIGHLIGHT]');
      if ($highlighter->get('maxAnalyzedChars') != $highlighter->getOriginal('maxAnalyzedChars')) {
        $hl->setMaxAnalyzedChars($highlighter->get('maxAnalyzedChars'));
      }
      if ($highlighter->get('fragmenter') != $highlighter->getOriginal('fragmenter')) {
        $hl->setFragmenter($highlighter->get('fragmenter'));
      }
      if ($highlighter->get('usePhraseHighlighter') != $highlighter->getOriginal('usePhraseHighlighter')) {
        $hl->setUsePhraseHighlighter($highlighter->get('usePhraseHighlighter'));
      }
      if ($highlighter->get('highlightMultiTerm') != $highlighter->getOriginal('highlightMultiTerm')) {
        $hl->setHighlightMultiTerm($highlighter->get('highlightMultiTerm'));
      }
      if ($highlighter->get('preserveMulti') != $highlighter->getOriginal('preserveMulti')) {
        $hl->setPreserveMulti($highlighter->get('preserveMulti'));
      }
      if ($highlighter->get('regex.slop') != $highlighter->getOriginal('regex.slop')) {
        $hl->setRegexSlop($highlighter->get('regex.slop'));
      }
      if ($highlighter->get('regex.pattern') != $highlighter->getOriginal('regex.pattern')) {
        $hl->setRegexPattern($highlighter->get('regex.pattern'));
      }
      if ($highlighter->get('regex.maxAnalyzedChars') != $highlighter->getOriginal('regex.maxAnalyzedChars')) {
        $hl->setRegexMaxAnalyzedChars($highlighter->get('regex.maxAnalyzedChars'));
      }
      if ($excerpt) {
        $excerpt_field = $hl->getField('spell');
        $excerpt_field->setSnippets($highlighter->get('excerpt.snippets'));
        $excerpt_field->setFragSize($highlighter->get('excerpt.fragsize'));
        $excerpt_field->setMergeContiguous($highlighter->get('excerpt.mergeContiguous'));
      }
      if ($highlight) {
        // It regrettably doesn't seem to be possible to set hl.fl to several
        // values, if one contains wild cards, i.e., "ts_*,tm_*,spell" wouldn't
        // work.
        $hl->setFields('*');
        // @todo the amount of snippets need to be increased to get highlighting
        //   of multi value fields to work.
        // @see hhtps://drupal.org/node/2753635
        $hl->setSnippets(1);
        $hl->setFragSize(0);
        $hl->setMergeContiguous($highlighter->get('highlight.mergeContiguous'));
        $hl->setRequireFieldMatch($highlighter->get('highlight.requireFieldMatch'));
      }
    }
  }

  /**
   * Changes the query to a "More Like This" query.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The solr query to add MLT for.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search api query to add MLT for.
   * @param array $mlt_options
   *   The mlt options.
   * @param array $index_fields
   *   The fields in the index to add mlt for.
   * @param array $fields
   *   The fields to add mlt for.
   */
  public function setMoreLikeThis(Query &$solarium_query, QueryInterface $query, $mlt_options = array(), $index_fields = array(), $fields = array()) {
    $solarium_query = $this->solr->createMoreLikeThis(array('handler' => 'select'));
    // The fields to look for similarities in.
    if (empty($mlt_options['fields'])) {
      return;
    }

    $mlt_fl = array();
    foreach ($mlt_options['fields'] as $mlt_field) {
      // Solr 4 has a bug which results in numeric fields not being supported
      // in MLT queries.
      // Date fields don't seem to be supported at all.
      $version = $this->getSolrVersion();
      if ($fields[$mlt_field][0] === 'd' || (version_compare($version, '4', '==') && in_array($fields[$mlt_field][0], array('i', 'f')))) {
        continue;
      }

      $mlt_fl[] = $fields[$mlt_field];
      // For non-text fields, set minimum word length to 0.
      if (isset($index_fields[$mlt_field]) && !SearchApiUtility::isTextType($index_fields[$mlt_field]->getType())) {
        $solarium_query->addParam('f.' . $fields[$mlt_field] . '.mlt.minwl', 0);
      }
    }

    //$solarium_query->setHandler('mlt');
    $solarium_query->setMltFields($mlt_fl);
    /** @var \Solarium\Plugin\CustomizeRequest\CustomizeRequest $customizer */
    $customizer = $this->solr->getPlugin('customizerequest');
    $customizer->createCustomization('id')
      ->setType('param')
      ->setName('qt')
      ->setValue('mlt');
    // @todo Make sure these configurations are correct
    $solarium_query->setMinimumDocumentFrequency(1);
    $solarium_query->setMinimumTermFrequency(1);
  }

  /**
   * Adds spatial features to the search query.
   *
   * @param \Solarium\QueryType\Select\Query\Query $solarium_query
   *   The solr query.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search api query.
   * @param array $spatial_options
   *   The spatial options to add.
   * @param $field_names
   *   The field names, to add the spatial options for.
   */
  public function setSpatial(Query $solarium_query, QueryInterface $query, $spatial_options = array(), $field_names = array()) {
    foreach ($spatial_options as $i => $spatial) {
      // Reset radius for each option.
      unset($radius);

      if (empty($spatial['field']) || empty($spatial['lat']) || empty($spatial['lon'])) {
        continue;
      }

      $field = $field_names[$spatial['field']];
      $point = ((float) $spatial['lat']) . ',' . ((float) $spatial['lon']);

      // Prepare the filter settings.
      if (isset($spatial['radius'])) {
        $radius = (float) $spatial['radius'];
      }

      $spatial_method = 'geofilt';
      if (isset($spatial['method']) && in_array($spatial['method'], array('geofilt', 'bbox'))) {
        $spatial_method = $spatial['method'];
      }

      $filter_queries = $solarium_query->getFilterQueries();
      // Change the fq facet ranges to the correct fq.
      foreach ($filter_queries as $key => $filter_query) {
        // If the fq consists only of a filter on this field, replace it with
        // a range.
        $preg_field = preg_quote($field, '/');
        if (preg_match('/^' . $preg_field . ':\["?(\*|\d+(?:\.\d+)?)"? TO "?(\*|\d+(?:\.\d+)?)"?\]$/', $filter_query, $matches)) {
          unset($filter_queries[$key]);
          if ($matches[1] && is_numeric($matches[1])) {
            $min_radius = isset($min_radius) ? max($min_radius, $matches[1]) : $matches[1];
          }
          if (is_numeric($matches[2])) {
            // Make the radius tighter accordingly.
            $radius = isset($radius) ? min($radius, $matches[2]) : $matches[2];
          }
        }
      }

      // If either a radius was given in the option, or a filter was
      // encountered, set a filter for the lowest value. If a lower boundary
      // was set (too), we can only set a filter for that if the field name
      // doesn't contains any colons.
      if (isset($min_radius) && strpos($field, ':') === FALSE) {
        $upper = isset($radius) ? " u=$radius" : '';
        $solarium_query->createFilterQuery($field)->setQuery("{!frange l=$min_radius$upper}geodist($field,$point)");
      }
      elseif (isset($radius)) {
        $solarium_query->createFilterQuery($field)->setQuery("{!$spatial_method pt=$point sfield=$field d=$radius}");
      }

      // @todo: Check if this object returns the correct value
      $sorts = $solarium_query->getSorts();
      // Change sort on the field, if set (and not already changed).
      if (isset($sorts[$spatial['field']]) && substr($sorts[$spatial['field']], 0, strlen($field)) === $field) {
        $sorts[$spatial['field']] = str_replace($field, "geodist($field,$point)", $sorts[$spatial['field']]);
      }

      // Change the facet parameters for spatial fields to return distance
      // facets.
      $facets = $solarium_query->getFacetSet();
      // @todo: Fix this so it takes it from the solarium query
      if (!empty($facets)) {
        if (!empty($facet_params['facet.field'])) {
          $facet_params['facet.field'] = array_diff($facet_params['facet.field'], array($field));
        }
        foreach ($facets as $delta => $facet) {
          if ($facet['field'] != $spatial['field']) {
            continue;
          }
          $steps = $facet['limit'] > 0 ? $facet['limit'] : 5;
          $step = (isset($radius) ? $radius : 100) / $steps;
          for ($k = $steps - 1; $k > 0; --$k) {
            $distance = $step * $k;
            $key = "spatial-$delta-$distance";
            $facet_params['facet.query'][] = "{!$spatial_method pt=$point sfield=$field d=$distance key=$key}";
          }
          foreach (array('limit', 'mincount', 'missing') as $setting) {
            unset($facet_params["f.$field.facet.$setting"]);
          }
        }
      }
    }

    // Normal sorting on location fields isn't possible.
    foreach (array_keys($solarium_query->getSorts()) as $sort) {
      if (substr($sort, 0, 3) === 'loc') {
        $solarium_query->removeSort($sort);
      }
    }
  }

  /**
   * Sets sorting for the query.
   */
  public function setSorts(Query $solarium_query, QueryInterface $query, $field_names = []) {
    $new_schema_version = version_compare($this->getSchemaVersion(), '4.4', '>=');
    foreach ($query->getSorts() as $field => $order) {
      $f = '';
      // First wee need to handle special fields which are prefixed by
      // 'search_api_'. Otherwise they will erroneously be treated as dynamic
      // string fields by the next detection below because they start with an
      // 's'. This way we for example ensure that search_api_relevance isn't
      // modified at all.
      if (strpos($field, 'search_api_') === 0) {
        if ($field == 'search_api_random') {
          // The default Solr schema provides a virtual field named "random_*"
          // that can be used to randomly sort the results; the field is
          // available only at query-time. See schema.xml for more details about
          // how the "seed" works.
          $params = $query->getOption('search_api_random_sort', []);
          // Random seed: getting the value from parameters or computing a new
          // one.
          $seed = !empty($params['seed']) ? $params['seed'] : mt_rand();
          $f = $field_names[$field] . '_' . $seed;
        }
      }
      elseif ($new_schema_version) {
        // @todo Both detections are redundant to some parts of
        //   SearchApiSolrBackend::getDocuments(). They should be combined in a
        //   single place to avoid errors in the future.
        if (strpos($field_names[$field], 't') === 0 || strpos($field_names[$field], 's') === 0) {
          // For fulltext fields use the dedicated sort field for faster alpha
          // sorts. Use the same field for strings to sort on a normalized
          // value.
          $f = 'sort_' . $field;
        }
        elseif (preg_match('/^([a-z]+)m(_.*)/', $field_names[$field], $matches)) {
          // For other multi-valued fields (which aren't sortable by nature) we
          // use the same hackish workaround like the DB backend: just copy the
          // first value in a single value field for sorting.
          $f = $matches[1] . 's' . $matches[2];
        }
      }

      if (!$f) {
        $f = $field_names[$field];
      }

      $solarium_query->addSort($f, strtolower($order));
    }
  }

  /**
   * Sets grouping for the query.
   */
  public function setGrouping(Query $solarium_query, QueryInterface $query, $grouping_options = array(), $index_fields = array(), $field_names = array()) {
    $group_params['group'] = 'true';
    // We always want the number of groups returned so that we get pagers done
    // right.
    $group_params['group.ngroups'] = 'true';
    if (!empty($grouping_options['truncate'])) {
      $group_params['group.truncate'] = 'true';
    }
    if (!empty($grouping_options['group_facet'])) {
      $group_params['group.facet'] = 'true';
    }
    foreach ($grouping_options['fields'] as $collapse_field) {
      $type = $index_fields[$collapse_field]['type'];
      // Only single-valued fields are supported.
      if (SearchApiUtility::isTextType($type)) {
        $warnings[] = $this->t('Grouping is not supported for field @field. Only single-valued fields not indexed as "Fulltext" are supported.',
          array('@field' => $index_fields[$collapse_field]['name']));
        continue;
      }
      $group_params['group.field'][] = $field_names[$collapse_field];
    }
    if (empty($group_params['group.field'])) {
      unset($group_params);
    }
    else {
      if (!empty($grouping_options['group_sort'])) {
        foreach ($grouping_options['group_sort'] as $group_sort_field => $order) {
          if (isset($fields[$group_sort_field])) {
            $f = $fields[$group_sort_field];
            if (substr($f, 0, 3) == 'ss_') {
              $f = 'sort_' . substr($f, 3);
            }
            $order = strtolower($order);
            $group_params['group.sort'][] = $f . ' ' . $order;
          }
        }
        if (!empty($group_params['group.sort'])) {
          $group_params['group.sort'] = implode(', ', $group_params['group.sort']);
        }
      }
      if (!empty($grouping_options['group_limit']) && ($grouping_options['group_limit'] != 1)) {
        $group_params['group.limit'] = $grouping_options['group_limit'];
      }
    }
    foreach ($group_params as $param_id => $param_value) {
      $solarium_query->addParam($param_id, $param_value);
    }
  }

}
