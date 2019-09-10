<?php

namespace Drupal\Tests\search_api_solr;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;

defined('TRAVIS_BUILD_DIR') || define('TRAVIS_BUILD_DIR', getenv('TRAVIS_BUILD_DIR') ?: '.');

class PhpUnitEventListener implements TestListener {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $travisLogger;

  /**
   * @var bool
   */
  protected $errors;

  public function __construct() {
    $this->travisLogger = new Logger('search_api_solr');
    $this->travisLogger->pushHandler(new StreamHandler(TRAVIS_BUILD_DIR . '/solr.query.log', Logger::DEBUG));
  }

  public function addWarning(Test $test, Warning $e, $time) {
    $this->errors = TRUE;
    $this->travisLogger->debug(printf("Error while running test '%s'.\n", $test->getName()));
  }

  public function addError(Test $test, \Exception $e, $time) {
    $this->errors = TRUE;
    $this->travisLogger->debug(printf("Error while running test '%s'.\n", $test->getName()));
  }

  public function addFailure(Test $test, AssertionFailedError $e, $time) {
    $this->errors = TRUE;
    $this->travisLogger->debug(printf("Test '%s' failed.\n", $test->getName()));
  }

  public function addIncompleteTest(Test $test, \Exception $e, $time) {
  }

  public function addRiskyTest(Test $test, \Exception $e, $time) {
  }

  public function addSkippedTest(Test $test, \Exception $e, $time) {
    $this->travisLogger->debug(printf("Test '%s' has been skipped.\n", $test->getName()));
  }

  public function startTest(Test $test) {
    $this->errors = FALSE;
    $this->travisLogger->debug(printf("Test '%s' started.\n", $test->getName()));
  }

  public function endTest(Test $test, $time) {
    if ($this->errors) {
      file_put_contents(TRAVIS_BUILD_DIR . '/solr.error.log', file_get_contents(TRAVIS_BUILD_DIR . '/solr.query.log'), FILE_APPEND);
    }
    file_put_contents(TRAVIS_BUILD_DIR . '/solr.query.log', '');
    $this->travisLogger->debug(printf("Test '%s' ended.\n", $test->getName()));
  }

  public function startTestSuite(TestSuite $suite) {
  }

  public function endTestSuite(TestSuite $suite) {
  }
}
