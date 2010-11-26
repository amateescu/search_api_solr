<?php
// $Id$

/**
 * A few custom rewrites to the Apache_Solr_Service class, to allow providing
 * HTTP authentication.
 */
class SearchApiSolrConnection extends Apache_Solr_Service {

  /**
   * Authentication string (username + password) for HTTP authentication.
   */
  protected $http_auth;

  /**
   * Constructs a Solr connection with an optional HTTP user and password.
   *
   * @param array $options
   *   An array containing construction arguments.
   */
  public function __construct(array $options) {
    $options += array(
      'host' => 'localhost',
      'port' => 8983,
      'path' => '',
      'http_user' => NULL,
      'http_pass' => NULL,
    );
    parent::__construct($options['host'], $options['port'], $options['path']);
    if ($options['http_user'] && $options['http_pass']) {
      $this->http_auth = 'Authorization: Basic ' . base64_encode($options['http_user'] . ':' . $options['http_pass']);
    }
    // Since /ping otherwise complains about missing default field.
    $this->_pingUrl .= '?q=id:1';
  }

  protected function _sendRawGet($url, $timeout = FALSE) {
    // Little "hack" to allow filter-only queries
    // Since "*:*" doesn't work with the dismax query handler, we mustn't set
    // "q", to let "q.alt" kick in. However, Apache_Solr_Service::search() will
    // always add "q", even if it is empty. Therefore, we delete empty "q"
    // parameters here.
    $url = preg_replace('/([?&])q=(&|$)/', '$1$2', $url);
    if ($this->http_auth) {
      stream_context_set_option($this->_getContext, 'http', 'header', $this->http_auth);
    }
    return parent::_sendRawGet($url, $timeout);
  }

  protected function _sendRawPost($url, $rawPost, $timeout = FALSE, $contentType = 'text/xml; charset=UTF-8') {
    if ($this->http_auth) {
      // Dirty hack, but probably still better than copying the whole method.
      $contentType .= "\r\n" . $this->http_auth;
    }
    return parent::_sendRawPost($url, $rawPost, $timeout, $contentType);
  }

  public function ping($timeout = 2) {
    // When using timeout in context and file_get_contents it seems to take twice the timout value.
    $timeout = $timeout > 0 ? (float) $timeout / 2 : -1;

    $options = array(
      'http' => array(
        'method' => 'HEAD',
        'timeout' => $timeout
      )
    );
    if ($this->http_auth) {
      $options['http']['header'] = $this->http_auth;
    }
    $context = stream_context_create($options);

    // Attempt a HEAD request to the solr ping page.
    $start = microtime(TRUE);
    $ping = @file_get_contents($this->_pingUrl, FALSE, $context);

    // Result is false if there was a timeout or if the HTTP status was not 200.
    if ($ping !== FALSE) {
      return microtime(TRUE) - $start;
    }
    else {
      if (isset($php_errormsg)) {
        watchdog('search_api_solr', t('Error while trying to ping Solr server: !msg.', array('!msg' => $php_errormsg)), NULL, WATCHDOG_WARNING);
      }
      return FALSE;
    }
  }

}
