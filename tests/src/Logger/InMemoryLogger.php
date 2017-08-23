<?php

namespace Drupal\Tests\search_api_solr\Logger;

use Psr\Log\AbstractLogger;


/**
 * A simple in memory logger.
 */
class InMemoryLogger extends AbstractLogger {

  private $messages = [];

  /**
   *  {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    $this->messages[] = [
      'level' => $level,
      'message' => $message,
      'context' => $context,
    ];
  }

  public function getLastMessage() {
    return end($this->messages);
  }
}
