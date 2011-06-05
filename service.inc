<?php

/**
 * Search service class using Solr server.
 */
class SearchApiSolrService extends SearchApiAbstractService {

  /**
   * The date format that Solr uses, in PHP date() syntax.
   */
  const SOLR_DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

  /**
   * A connection to the Solr server.
   *
   * @var SearchApiSolrConnection
   */
  protected $solr;

  /**
   * An array of all recognized types mapped to a prefix used for identifying
   * them in the Solr schema.
   *
   * @var array
   */
  protected static $type_prefixes = array(
    'text' => 't',
    'tokens' => 't',
    'string' => 's',
    'integer' => 'i',
    'decimal' => 'f',
    'date' => 'd',
    'duration' => 'i',
    'boolean' => 'b',
    'uri' => 's',
  );

  /**
   * @var array
   */
  protected $fieldNames = array();

  /**
   * Metadata describing fields on the Solr/Lucene index.
   * @see SearchApiSolrService::getFields().
   *
   * @var array
   */
  protected $fields;

  public function __construct(SearchApiServer $server) {
    parent::__construct($server);
  }

  public function configurationForm(array $form, array &$form_state) {
    if (empty($this->options)) {
      // First configuring this server
      $options = array(
        'host' => 'localhost',
        'port' => '8983',
        'path' => '/solr',
        'http_user' => '',
        'http_pass' => '',
      );
    }
    else {
      // Editing this server
      $options = $this->options;
      $url = 'http://' . $options['host'] . ':' . $options['port'] . $options['path'];
      $form['server_description'] = array(
        '#type' => 'item',
        '#title' => t('Solr server URI'),
        '#description' => l($url, $url),
      );
    }

    $form['host'] = array(
      '#type' => 'textfield',
      '#title' => t('Solr host'),
      '#description' => t('The host name or IP of your Solr server, e.g. <code>localhost</code> or <code>www.example.com</code>.'),
      '#default_value' => $options['host'],
      '#required' => TRUE,
    );
    $form['port'] = array(
      '#type' => 'textfield',
      '#title' => t('Solr port'),
      '#description' => t('The Jetty example server is at port 8983, while Tomcat uses 8080 by default.'),
      '#default_value' => $options['port'],
      '#required' => TRUE,
    );
    $form['path'] = array(
      '#type' => 'textfield',
      '#title' => t('Solr path'),
      '#description' => t('The path that identifies the Solr instance to use on the server.'),
      '#default_value' => $options['path'],
    );

    $form['http'] = array(
      '#type' => 'fieldset',
      '#title' => t('Basic HTTP authentication'),
      '#description' => t('If your Solr server is protected by basic HTTP authentication, enter the login data here.'),
      '#collapsible' => TRUE,
      '#collapsed' => empty($options['http_user']),
    );
    $form['http']['http_user'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#default_value' => $options['http_user'],
    );
    $form['http']['http_pass'] = array(
      '#type' => 'password',
      '#title' => t('Password'),
      '#default_value' => $options['http_pass'],
    );

    return $form;
  }

  public function configurationFormValidate(array $form, array &$values, array &$form_state) {
    if (isset($values['port']) && (!is_numeric($values['port']) || $values['port'] < 0 || $values['port'] > 65535)) {
      form_error($form['port'], t('The port has to be an integer between 0 and 65535.'));
    }
  }

  public function configurationFormSubmit(array $form, array &$values, array &$form_state) {
    $http = $values['http'];
    unset($values['http']);
    $values += $http;
    parent::configurationFormSubmit($form, $values, $form_state);
  }

  public function supportsFeature($feature) {
    $supported = drupal_map_assoc(array(
      'search_api_facets',
      'search_api_multi',
      'search_api_spellcheck',
      'search_api_mlt',
    ));
    return isset($supported[$feature]);
  }

  /**
   * View this server's settings. Output can be HTML or a render array, a <dl>
   * listing all relevant settings is preferred.
   *
   * The default implementation does a crude output as a definition list, with
   * option names taken from the configuration form.
   */
  public function viewSettings() {
    $output = '';
    $options = $this->options;

    $url = 'http://' . $options['host'] . ':' . $options['port'] . $options['path'];
    $output .= "<dl>\n  <dt>";
    $output .= t('Solr server URI');
    $output .= "</dt>\n  <dd>";
    $output .= l($url, $url);
    $output .= '</dd>';
    if ($options['http_user']) {
      $output .= "\n  <dt>";
      $output .= t('Basic HTTP authentication');
      $output .= "</dt>\n  <dd>";
      $output .= t('Username: !user', array('!user' => $options['http_user']));
      $output .= "</dd>\n  <dd>";
      $output .= t('Password: !pass', array('!pass' => str_repeat('*', strlen($options['http_pass']))));
      $output .= '</dd>';
    }
    $output .= "\n</dl>";

    return $output;
  }

  /**
   * Create a connection to the Solr server as configured in $this->options.
   */
  protected function connect() {
    if (!$this->solr) {
      if (!class_exists('Apache_Solr_Service')) {
        throw new Exception(t('SolrPhpClient library not found! Please follow the instructions in search_api_solr/INSTALL.txt for installing the Solr search module.'));
      }
      $this->solr = new SearchApiSolrConnection($this->options);
    }
  }

  public function addIndex(SearchApiIndex $index) {
    if (module_exists('search_api_multi') && module_exists('search_api_views')) {
      views_invalidate_cache();
    }
  }

  public function fieldsUpdated(SearchApiIndex $index) {
    if (module_exists('search_api_multi') && module_exists('search_api_views')) {
      views_invalidate_cache();
    }
    return TRUE;
  }

  public function removeIndex($index) {
    if (module_exists('search_api_multi') && module_exists('search_api_views')) {
      views_invalidate_cache();
    }
    $id = is_object($index) ? $index->machine_name : $index;
    try {
      $this->connect();
      $this->solr->deleteByQuery("index_id:" . $id);
    }
    catch (Exception $e) {
      watchdog('search_api_solr', t("An error occurred while deleting an index' data: !msg.", array('!msg' => $e->getMessage())));
    }
  }

  public function indexItems(SearchApiIndex $index, array $items) {
    $documents = array();
    $ret = array();
    $index_id = $index->machine_name;
    $fields = $this->getFieldNames($index);

    foreach ($items as $id => $item) {
      try {
        $doc = new Apache_Solr_Document();
        $doc->setField('id', $this->createId($index_id, $id));
        $doc->setField('index_id', $index_id);
        $doc->setField('item_id', $id);

        foreach ($item as $key => $field) {
          if (!isset($fields[$key])) {
            throw new SearchApiException(t('Unknown field !field.', array('!field' => $key)));
          }
          $this->addIndexField($doc, $fields[$key], $field['value'], $field['type']);
        }

        $documents[] = $doc;
        $ret[] = $id;
      }
      catch (Exception $e) {
        watchdog('search_api_solr', t('An error occurred while indexing !type with ID !id: !msg.', array('!type' => $index->entity_type, '!id' => $id, '!msg' => $e->getMessage())), NULL, WATCHDOG_WARNING);
      }
    }

    if (!$documents) {
      return array();
    }
    try {
      $this->connect();
      $response = $this->solr->addDocuments($documents);
      if ($response->getHttpStatus() == 200) {
        $this->solr->commit(TRUE, FALSE, FALSE);
        return $ret;
      }
      throw new SearchApiException(t('HTTP status !status: !msg.',
          array('!status' => $response->getHttpStatus(), '!msg' => $response->getHttpStatusMessage())));
    }
    catch (Exception $e) {
      watchdog('search_api_solr', t('An error occurred while indexing: !msg.', array('!msg' => $e->getMessage())), NULL, WATCHDOG_ERROR);
    }
    return array();
  }

  /**
   * Creates an ID used as the unique identifier at the Solr server. This has to
   * consist of both index and item ID.
   */
  protected function createId($index_id, $item_id) {
    return "$index_id-$item_id";
  }

  /**
   * Create a list of all indexed field names mapped to their Solr field names.
   *
   * The special fields "search_api_id", "search_api_relevance", and "id" are
   * also included. Any Solr fields that exist on search results are mapped back
   * to their local field names in the final result set.
   *
   * @see SearchApiSolrService::search()
   */
  protected function getFieldNames(SearchApiIndex $index, $reset = FALSE) {
    if (!isset($this->fieldNames[$index->machine_name]) || $reset) {
      // This array maps "local property name" => "solr doc property name".
      $ret = array(
        'search_api_id' => 'is_search_api_id',
        'search_api_relevance' => 'score',
        'search_api_item_id' => 'item_id',
      );

      // Add the names of any "fields" configured on the index.
      $fields = (isset($index->options['fields']) ? $index->options['fields'] : array());
      foreach ($fields as $key => $field) {
        // Since this determines which fields can be searched and filtered on,
        // don't include fields that aren't indexed.
        if (empty($field['indexed'])) {
          continue;
        }

        // Generate a field name; this corresponds with naming conventions in
        // our schema.xml
        $type = $field['type'];
        $inner_type = search_api_extract_inner_type($type);
        $pref = isset(self::$type_prefixes[$inner_type]) ? self::$type_prefixes[$inner_type] : '';
        if ($pref != 't') {
          $pref .= $type == $inner_type ? 's' : 'm';
        }
        $name = $pref . '_' . $key;

        $ret[$key] = $name;
      }

      // Let modules adjust the field mappings.
      drupal_alter('search_api_solr_field_mapping', $index, $ret);

      $this->fieldNames[$index->machine_name] = $ret;
    }

    return $this->fieldNames[$index->machine_name];
  }

  /**
   * Helper method for indexing.
   * Add $field with field name $key to the document $doc. The format of $field
   * is the same as specified in SearchApiServiceInterface::indexItems().
   */
  protected function addIndexField(Apache_Solr_Document $doc, $key, $value, $type, $multi_valued = FALSE) {
    // Don't index empty values (i.e., when field is missing)
    if (!isset($value)) {
      return;
    }
    if (search_api_is_list_type($type)) {
      $type = substr($type, 5, -1);
      foreach ($value as $v) {
        $this->addIndexField($doc, $key, $v, $type, TRUE);
      }
      return;
    }
    switch ($type) {
      case 'tokens':
        foreach ($value as $v) {
          $doc->addField($key, $v['value']);
        }
        return;
      case 'boolean':
        $value = $value ? 'true' : 'false';
        break;
      case 'date':
        $value = is_numeric($value) ? (int) $value : strtotime($value);
        if ($value === FALSE) {
          return;
        }
        $value = format_date($value, 'custom', self::SOLR_DATE_FORMAT, 'UTC');
        break;
      case 'integer':
        $value = (int) $value;
        break;
      case 'decimal':
        $value = (float) $value;
        break;
    }
    if ($multi_valued) {
      $doc->addField($key, $value);
    }
    else {
      $doc->setField($key, $value);
    }
  }

  /**
   * This method has a custom, Solr-specific extension:
   * If $ids is a string other than "all", it is treated as a Solr query. All
   * items matching that Solr query are then deleted. If $index is additionally
   * specified, then only those items also lying on that index will be deleted.
   * It is up to the caller to ensure $ids is a valid query when the method is
   * called in this fashion.
   */
  public function deleteItems($ids = 'all', SearchApiIndex $index = NULL) {
    $this->connect();
    if ($index) {
      $index_id = $index->machine_name;
      if (is_array($ids)) {
        $solr_ids = array();
        foreach ($ids as $id) {
          $solr_ids[] = $this->createId($index_id, $id);
        }
        $this->solr->deleteByMultipleIds($solr_ids);
      }
      elseif ($ids == 'all') {
        $this->solr->deleteByQuery("index_id:" . $index_id);
      }
      else {
        $this->solr->deleteByQuery("index_id:" . $index_id . ' (' . $ids . ')');
      }
    }
    else {
      $q = $ids == 'all' ? '*:*' : $ids;
      $this->solr->deleteByQuery($q);
    }
  }

  public function search(SearchApiQueryInterface $query) {
    $time_method_called = microtime(TRUE);
    // Reset request handler
    $this->request_handler = NULL;
    // Get field information
    $index = $query->getIndex();
    $fields = $this->getFieldNames($index);

    // Extract keys
    $keys = $query->getKeys();
    if (is_array($keys)) {
      $keys = $this->flattenKeys($keys);
    }

    // Set searched fields
    $options = $query->getOptions();
    $search_fields = $query->getFields();
    $qf = array();
    foreach ($search_fields as $f) {
      $qf[] = $fields[$f];
    }

    // Extract filters
    $filter = $query->getFilter();
    $fq = $this->createFilterQueries($filter, $fields, $index->options['fields']);
    $fq[] = 'index_id:' . $index->machine_name;

    // Extract sort
    $sort = array();
    foreach ($query->getSort() as $f => $order) {
      $f = $fields[$f];
      $order = strtolower($order);
      $sort[] = "$f $order";
    }

    // Get facet fields
    $facets = $query->getOption('search_api_facets') ? $query->getOption('search_api_facets') : array();
    $facet_params = $this->getFacetParams($facets, $fields);

    // Handle More Like This query
    $mlt = $query->getOption('search_api_mlt');
    if ($mlt) {
      $mlt_params['qt'] = 'mlt';
      // The fields to look for similarities in.
      $mlt_fl = array();
      foreach($mlt['fields'] as $f) {
        $mlt_fl[] = $fields[$f];
        // For non-text fields, set minimum word length to 0.
        if (isset($index->options['fields'][$f]['type']) && !search_api_is_text_type($index->options['fields'][$f]['type'])) {
          $mlt_params['f.' . $fields[$f] . '.mlt.minwl'] = 0;
        }
      }
      $mlt_params['mlt.fl'] = implode(',', $mlt_fl);
      $keys = 'id:' . $index->machine_name . '-' . $mlt['id'];
    }

    // Set defaults
    if (!$keys) {
      $keys = NULL;
    }
    $offset = isset($options['offset']) ? $options['offset'] : 0;
    $limit = isset($options['limit']) ? $options['limit'] : 1000000;

    // Collect parameters
    $params = array(
      'qf' => $qf,
    );
    $params['fq'] = $fq;
    if ($sort) {
      $params['sort'] = implode(', ', $sort);
    }
    if (!empty($facet_params['facet.field'])) {
      $params += $facet_params;
    }
    if (!empty($options['search_api_spellcheck'])) {
      $params['spellcheck'] = 'true';
    }
    if (!empty($mlt_params['mlt.fl'])) {
      $params += $mlt_params;
    }
    $call_args = array(
      'query'  => &$keys,
      'offset' => &$offset,
      'limit'  => &$limit,
      'params' => &$params,
    );
    if ($this->request_handler) {
      $this->setRequestHandler($this->request_handler, $call_args);
    }

    try {
      // Send search request
      $time_processing_done = microtime(TRUE);
      $this->connect();
      drupal_alter('search_api_solr_query', $call_args, $query);
      $this->preQuery($call_args, $query);
      $response = $this->solr->search($keys, $offset, $limit, $params);
      $time_query_done = microtime(TRUE);

      if ($response->getHttpStatus() != 200) {
        throw new SearchApiException(t('The Solr server responded with status code !status: !msg.',
            array('!status' => $response->getHttpStatus(), '!msg' => $response->getHttpStatusMessage())));
      }

      // Extract results
      $results = $this->extractResults($query, $response);

      // Extract facets
      if ($facets = $this->extractFacets($query, $response)) {
        $results['search_api_facets'] = $facets;
      }

      drupal_alter('search_api_solr_search_results', $results, $query, $response);
      $this->postQuery($results, $query, $response);

      // Compute performance
      $time_end = microtime(TRUE);
      $results['performance'] = array(
        'complete' => $time_end - $time_method_called,
        'preprocessing' => $time_processing_done - $time_method_called,
        'execution' => $time_query_done - $time_processing_done,
        'postprocessing' => $time_end - $time_query_done,
      );

      return $results;
    }
    catch (Exception $e) {
      throw new SearchApiException($e->getMessage());
    }
  }

  /**
   * Extract results from a Solr response.
   *
   * @param Apache_Solr_Response $response
   *   A response object from SolrPhpClient.
   *
   * @return array
   *   An array with two keys:
   *   - 'result count' contains an integer number of total results
   *   - 'results' is an array of search results; keys are document ids, values
   *      are arrays of property => value pairs
   */
  protected function extractResults(SearchApiQueryInterface $query, Apache_Solr_Response $response) {
    $index = $query->getIndex();
    $fields = $this->getFieldNames($index);

    // Set up the results array.
    $results = array();
    $results['result count'] = $response->response->numFound;
    $results['results'] = array();

    // Add each search result to the results array.
    foreach ($response->response->docs as $doc) {
      // Blank result array.
      $result = array(
        'id' => NULL,
        'score' => NULL,
        'fields' => array(),
      );

      // Extract properties from the Solr document, translating from Solr to
      // Search API property names. This reverses the mapping in
      // SearchApiSolrService::getFieldNames().
      foreach ($fields as $search_api_property => $solr_property) {
        if (isset($doc->{$solr_property})) {
          $result['fields'][$search_api_property] = $doc->{$solr_property};
        }
      }

      // We can find the entity id and score in the special 'search_api_*'
      // properties. Mappings are provided for these properties in
      // SearchApiSolrService::getFieldNames().
      $result['id'] = $result['fields']['search_api_item_id'];
      $result['score'] = $result['fields']['search_api_relevance'];

      // Use the result's id as the array key. By default, 'id' is mapped to
      // 'item_id' in SearchApiSolrService::getFieldNames().
      if ($result['id']) {
        $results['results'][$result['id']] = $result;
      }
    }

    // Check for spellcheck suggestions.
    if (module_exists('search_api_spellcheck') && $query->getOption('search_api_spellcheck')) {
      $results['search_api_spellcheck'] = new SearchApiSpellcheckSolr($response);
    }

    return $results;
  }

  /**
   * Extract facets from a Solr response.
   *
   * @param Apache_Solr_Response $response
   *   A response object from SolrPhpClient.
   *
   * @return array
   *   An array describing facets that apply to the current results.
   */
  protected function extractFacets(SearchApiQueryInterface $query, Apache_Solr_Response $response) {
    if (isset($response->facet_counts->facet_fields)) {
      $index = $query->getIndex();
      $fields = $this->getFieldNames($index);

      $facets = array();
      $facet_fields = $response->facet_counts->facet_fields;

      $extract_facets = $query->getOption('search_api_facets');
      $extract_facets = ($extract_facets ? $extract_facets : array());

      foreach ($extract_facets as $delta => $info) {
        $field = $fields[$info['field']];
        if ($field[0] == 's') {
          $field = 'f_' . $field;
        }
        if (!empty($facet_fields->$field)) {
          $min_count = $info['min_count'];
          $terms = $facet_fields->$field;
          if ($info['missing']) {
            // We have to correctly incorporate the "_empty_" term.
            // This will ensure that the term with the least results is dropped, if the limit would be exceeded.
            $terms = (array) $terms;
            arsort($terms);
            if (count($terms) > $info['limit']) {
              array_pop($terms);
            }
          }
          elseif (isset($terms->_empty_)) {
            $terms = clone $terms;
            unset($terms->_empty_);
          }
          $type = isset($index->options['fields'][$info['field']]['type']) ? $index->options['fields'][$info['field']]['type'] : 'string';
          foreach ($terms as $term => $count) {
            if ($count >= $min_count) {
              if ($type == 'boolean') {
                if ($term == 'true') {
                  $term = 1;
                }
                elseif ($term == 'false') {
                  $term = 0;
                }
              }
              elseif ($type == 'date') {
                $term = isset($term) ? strtotime($term) : NULL;
              }
              $term = $term === '_empty_' ? '!' : '"' . $term . '"';
              $facets[$delta][] = array(
                'filter' => $term,
                'count' => $count,
              );
            }
          }
          if (empty($facets[$delta]) || count($facets[$delta]) <= 1) {
            unset($facets[$delta]);
          }
        }
      }

      return $facets;
    }
  }

  /**
   * Flatten a keys array into a single search string.
   *
   * @param array $keys
   *   The keys array to flatten, formatted as specified by
   *   SearchApiQueryInterface::getKeys().
   *
   * @return string
   *   A Solr query string representing the same keys.
   */
  protected function flattenKeys(array $keys) {
    $k = array();
    $or = $keys['#conjunction'] == 'OR';
    $neg = !empty($keys['#negation']);
    foreach (element_children($keys) as $i) {
      $key = $keys[$i];
      if (!$key) {
        continue;
      }
      if (is_array($key)) {
        $subkeys = $this->flattenKeys($key);
        if ($subkeys) {
          $nested_expressions = TRUE;
          // If this is a negated OR expression, we can't just use nested keys
          // as-is, but have to put them into parantheses.
          if ($or && $neg) {
            if (empty($this->request_handler) || $this->request_handler == 'dismax') {
              $this->request_handler = 'standard';
            }
            $subkeys = "($subkeys)";
          }
          $k[] = $subkeys;
        }
      }
      else {
        $key = trim($key);
        $key = SearchApiSolrConnection::phrase($key);
        $k[] = $key;
      }
    }
    if (!$k) {
      return '';
    }

    // Normally, we use the "dismax" request handler, as it yields the best
    // results for simple queries (only single terms, phrases or simple
    // negation). In more complex cases, we have to switch to the "standard"
    // handler, which offers complex syntax. This is guided by the "#negation"
    // and "#conjunction" settings. Results of the following form will be
    // returned:
    //
    // #conjunction | #negation | handler  | return value
    // ----------------------------------------------------------------
    // AND          | FALSE     | dismax   | A B C
    // AND          | TRUE      | standard | -(A B C)
    // OR           | FALSE     | standard | ((A) OR (B) OR (C))
    // OR           | TRUE      | dismax   | -A -B -C

    // If there was just a single, unnested key, we can ignore all this.
    if (count($k) == 1 && empty($nested_expressions)) {
      $k = reset($k);
      return $neg ? "-$k" : $k;
    }

    if ($or != $neg && (empty($this->request_handler) || $this->request_handler == 'dismax')) {
      $this->request_handler = 'standard';
    }

    if ($or) {
      if ($neg) {
        return '-' . implode(' -', $k);
      }
      return '((' . implode(') OR (', $k) . '))';
    }
    $k = implode(' ', $k);
    return $neg ? "-($k)" : $k;
  }

  /**
   * Transforms a query filter into a flat array of Solr filter queries, using
   * the field names in $fields.
   */
  protected function createFilterQueries(SearchApiQueryFilterInterface $filter, array $solr_fields, array $fields) {
    $or = $filter->getConjunction() == 'OR';
    $fq = array();
    foreach ($filter->getFilters() as $f) {
      if (is_array($f)) {
        if (!isset($fields[$f[0]]) || empty($fields[$f[0]]['indexed'])) {
          throw new SearchApiException(t('Filter term on unknown or unindexed field !field.', array('!field' => $f[0])));
        }
        if ($f[1] !== '') {
          $fq[] = $this->createFilterQuery($solr_fields[$f[0]], $f[1], $f[2], $fields[$f[0]]);
        }
      }
      else {
        $q = $this->createFilterQueries($f, $solr_fields, $fields);
        if ($filter->getConjunction() != $f->getConjunction()) {
          // $or == TRUE means the nested filter has conjunction AND, and vice versa
          $sep = $or ? ' ' : ' OR ';
          $fq[] = '((' . implode(')' . $sep . '(', $q) . '))';
        }
        else {
          $fq = array_merge($fq, $q);
        }
      }
    }
    return $or ? array('((' . implode(') OR (', $fq) . '))') : $fq;
  }

  /**
   * Create a single search query string according to the given field, value
   * and operator.
   */
  protected function createFilterQuery($field, $value, $operator, $field_info) {
    $field = SearchApiSolrConnection::escapeFieldName($field);
    if ($value === NULL) {
      return ($operator == '=' ? '-' : '') . "$field:[* TO *]";
    }
    $value = trim($value);
    $value = $this->formatFilterValue($value, search_api_extract_inner_type($field_info['type']));
    switch ($operator) {
      case '<>':
        return "-($field:$value)";
      case '<':
        return "$field:{* TO $value}";
      case '<=':
        return "$field:[* TO $value]";
      case '>=':
        return "$field:[$value TO *]";
      case '>':
        return "$field:{{$value} TO *}";

      default:
        return "$field:$value";
    }
  }

  /**
   * Format a value for filtering on a field of a specific type.
   */
  protected function formatFilterValue($value, $type) {
    switch ($type) {
      case 'boolean':
        $value = $value ? 'true' : 'false';
        break;
      case 'date':
        $value = is_numeric($value) ? (int) $value : strtotime($value);
        if ($value === FALSE) {
          return 0;
        }
        $value = format_date($value, 'custom', self::SOLR_DATE_FORMAT, 'UTC');
        break;
    }
    return SearchApiSolrConnection::phrase($value);
  }

  /**
   * Helper method for creating the facet field parameters.
   */
  protected function getFacetParams(array $facets, array $fields) {
    if (!$facets) {
      return array();
    }
    $facet_params['facet'] = 'true';
    $facet_params['facet.sort'] = 'count';
    $facet_params['facet.limit'] = 10;
    $facet_params['facet.mincount'] = 1;
    $facet_params['facet.missing'] = 'false';
    foreach ($facets as $info) {
      if (empty($fields[$info['field']])) {
        continue;
      }
      $field = $fields[$info['field']];
      if ($field[0] == 's') {
        $field = 'f_' . $field;
      }
      $facet_params['facet.field'][] = $field;
      if ($info['limit'] != 10) {
        $facet_params["f.$field.facet.limit"] = $info['limit'] ? $info['limit'] : -1;
      }
      if ($info['min_count'] != 1) {
        $facet_params["f.$field.facet.mincount"] = $info['min_count'];
      }
      if ($info['missing']) {
        $facet_params["f.$field.facet.missing"] = 'true';
      }
    }
    return $facet_params;
  }

  /**
   * Helper method for setting the request handler, and making necessary
   * adjustments to the request parameters.
   *
   * @param $handler
   *   Name of the handler to set.
   * @param array $call_args
   *   An associative array containing all four arguments to the
   *   Apache_Solr_Service::search() call ("query", "offset", "limit" and
   *   "params") as references.
   *
   * @return boolean
   *   TRUE iff this method invocation handled the given handler. This allows
   *   subclasses to recognize whether the request handler was already set by
   *   this method.
   */
  protected function setRequestHandler($handler, array &$call_args) {
    if ($handler == 'dismax') {
      return TRUE;
    }
    if ($handler == 'standard') {
      $keys = &$call_args['query'];
      $params = &$call_args['params'];
      $params['qt'] = $handler;
      $k = $keys;
      $fields = $params['qf'];
      unset($params['qf']);
      $keys = implode(":($k) OR ", array_map(array('SearchApiSolrConnection', 'escapeFieldName'), $fields)) . ":($k)";
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Empty method to allow subclassed to apply custom changes before the query
   * is sent to Solr. Works exactly like hook_search_api_solr_query_alter().
   *
   * @param array $call_args
   *   An associative array containing all four arguments to the
   *   Apache_Solr_Service::search() call ("query", "offset", "limit" and
   *   "params") as references.
   * @param SearchApiQueryInterface $query
   *   The SearchApiQueryInterface object representing the executed search query.
   */
  protected function preQuery(array &$call_args, SearchApiQueryInterface $query) {
  }

  /**
   * Empty method to allow subclasses to apply custom changes before search results are returned.
   *
   * Works exactly like hook_search_api_solr_search_results_alter().
   *
   * @param array $results
   *   The results array that will be returned for the search.
   * @param SearchApiQueryInterface $query
   *   The SearchApiQueryInterface object representing the executed search query.
   * @param Apache_Solr_Response $response
   *   The response object returned by Solr.
   */
  protected function postQuery(array &$results, SearchApiQueryInterface $query, Apache_Solr_Response $response) {
  }

  //
  // SearchApiMultiServiceInterface methods
  //

  /**
   * Create a query object for searching on this server.
   *
   * @param $options
   *   Associative array of options configuring this query. See
   *   SearchApiMultiQueryInterface::__construct().
   *
   * @throws SearchApiException
   *   If the server is currently disabled.
   *
   * @return SearchApiMultiQueryInterface
   *   An object for searching this server.
   */
  public function queryMultiple(array $options = array()) {
    return new SearchApiMultiQuery($this->server, $options);
  }

  /**
   * Executes a search on the server represented by this object.
   *
   * @param SearchApiMultiQueryInterface $query
   *   The search query to execute.
   *
   * @throws SearchApiException
   *   If an error prevented the search from completing.
   *
   * @return array
   *   An associative array containing the search results, as required by
   *   SearchApiMultiQueryInterface::execute().
   */
  public function searchMultiple(SearchApiMultiQueryInterface $query) {
    $time_method_called = microtime(TRUE);
    // Get field information
    $solr_fields = array(
      'search_api_id' => 'is_search_api_id',
      'search_api_relevance' => 'score',
      'search_api_multi_index' => 'index_id',
    );
    $fields = array(
      'search_api_multi_index' => array(
        'type' => 'string',
        'indexed' => TRUE,
      ),
    );
    foreach ($query->getIndexes() as $index_id => $index) {
      if (empty($index->options['fields'])) {
        continue;
      }
      $prefix = $index_id . ':';
      foreach ($this->getFieldNames($index) as $field => $key) {
        if (substr($field, 0, 11) !== 'search_api_') {
          $solr_fields[$prefix . $field] = $key;
        }
      }
      foreach ($index->options['fields'] as $field => $info) {
        $fields[$prefix . $field] = $info;
      }
    }

    // Extract keys
    $keys = $query->getKeys();
    if (is_array($keys)) {
      $keys = $this->flattenKeys($keys);
    }

    // Set searched fields
    $search_fields = $query->getFields();
    $qf = array();
    foreach ($search_fields as $f) {
      $qf[] = $solr_fields[$f];
    }

    // Extract filters
    $filter = $query->getFilter();
    $fq = $this->createFilterQueries($filter, $solr_fields, $fields);

    // Extract sort
    $sort = array();
    foreach ($query->getSort() as $f => $order) {
      $f = $solr_fields[$f];
      $order = strtolower($order);
      $sort[] = "$f $order";
    }

    // Get facet fields
    $facets = $query->getOption('search_api_facets') ? $query->getOption('search_api_facets') : array();
    $facet_params = $this->getFacetParams($facets, $solr_fields);

    // Set defaults
    if (!$keys) {
      $keys = NULL;
    }
    $options = $query->getOptions();
    $offset = isset($options['offset']) ? $options['offset'] : 0;
    $limit = isset($options['limit']) ? $options['limit'] : 1000000;

    // Collect parameters
    $params = array(
      'qf' => $qf,
      'fl' => 'item_id,index_id,score',
      'fq' => $fq,
    );
    if ($sort) {
      $params['sort'] = implode(', ', $sort);
    }
    if (!empty($facet_params['facet.field'])) {
      $params += $facet_params;
    }
    try {
      // Send search request
      $time_processing_done = microtime(TRUE);
      $this->connect();
      $call_args = array(
        'query'  => &$keys,
        'offset' => &$offset,
        'limit'  => &$limit,
        'params' => &$params,
      );
      drupal_alter('search_api_solr_multi_query', $call_args, $query);
      $response = $this->solr->search($keys, $offset, $limit, $params);
      $time_query_done = microtime(TRUE);

      if ($response->getHttpStatus() != 200) {
        throw new SearchApiException(t('The Solr server responded with status code !status: !msg.',
            array('!status' => $response->getHttpStatus(), '!msg' => $response->getHttpStatusMessage())));
      }

      // Extract results
      $results = array();
      $results['result count'] = $response->response->numFound;
      $results['results'] = array();
      foreach ($response->response->docs as $doc) {
        $doc->id = $doc->item_id;
        unset($doc->item_id);
        foreach ($doc as $k => $v) {
          $result[$k] = $v;
        }
        $results['results'][$doc->id] = $result;
      }

      // Extract facets
      if (isset($response->facet_counts->facet_fields)) {
        $results['search_api_facets'] = array();
        $facet_fields = $response->facet_counts->facet_fields;
        foreach ($facets as $delta => $info) {
          $field = $solr_fields[$info['field']];
          if ($field[0] == 's') {
            $field = 'f_' . $field;
          }
          if (!empty($facet_fields->$field)) {
            $min_count = $info['min_count'];
            $terms = $facet_fields->$field;
            if ($info['missing']) {
              // We have to correctly incorporate the "_empty_" term.
              // This will ensure that the term with the least results is dropped, if the limit would be exceeded.
              $terms = (array) $terms;
              arsort($terms);
              if (count($terms) > $info['limit']) {
                array_pop($terms);
              }
            }
            foreach ($terms as $term => $count) {
              if ($count >= $min_count) {
                $term = $term == '_empty_' ? '!' : '"' . $term . '"';
                $results['search_api_facets'][$delta][] = array(
                  'filter' => $term,
                  'count' => $count,
                );
              }
            }
            if (empty($results['search_api_facets'][$delta]) || count($results['search_api_facets'][$delta]) <= 1) {
              unset($results['search_api_facets'][$delta]);
            }
          }
        }
      }

      // Compute performance
      $time_end = microtime(TRUE);
      $results['performance'] = array(
        'complete' => $time_end - $time_method_called,
        'preprocessing' => $time_processing_done - $time_method_called,
        'execution' => $time_query_done - $time_processing_done,
        'postprocessing' => $time_end - $time_query_done,
      );

      return $results;
    }
    catch (Exception $e) {
      throw new SearchApiException($e->getMessage());
    }
  }

  //
  // Additional methods that might be used when knowing the service class.
  //

  /**
   * Ping the Solr server to tell whether it can be accessed. Uses the
   * admin/ping request handler.
   */
  public function ping() {
    $this->connect();
    return $this->solr->ping();
  }

  /**
   * Get metadata about fields in the Solr/Lucene index.
   *
   * @param boolean $reset
   *   Reload the cached data?
   */
  public function getFields($reset = FALSE) {
    $cid = 'search_api_solr:fields:' . $this->server->machine_name;

    // If the data hasn't been retrieved before and we aren't refreshing it, try
    // to get data from the cache.
    if (!isset($this->fields) && !$reset) {
      $cache = cache_get($cid);
      if (isset($cache->data) && !$reset) {
        $this->fields = $cache->data;
      }
    }

    // If there was no data in the cache, or if we're refreshing the data,
    // connect to the Solr server, retrieve schema information, and cache it.
    if (!isset($this->fields) || $reset) {
      $this->connect();
      $this->fields = array();
      foreach ($this->solr->getFields() as $name => $info) {
        $this->fields[$name] = new SearchApiSolrField($info);
      }
      cache_set($cid, $this->fields);
    }

    return $this->fields;
  }

}
