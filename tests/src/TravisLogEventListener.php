<?php

namespace Drupal\Tests\search_api_solr;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;

defined('TRAVIS_BUILD_DIR') || define('TRAVIS_BUILD_DIR', getenv('TRAVIS_BUILD_DIR') ?: '.');
/**
 *
 */
class TravisLogEventListener implements TestListener {

  /**
   * @var bool
   */
  protected $errors;

  /**
   *
   */
  public function addWarning(Test $test, Warning $e, float $time): void {
    $this->errors = TRUE;
    file_put_contents(TRAVIS_BUILD_DIR . '/solr.error.log', printf("Warning while running test '%s'.\n", $test->getName()), FILE_APPEND | LOCK_EX);
  }

  /**
   *
   */
  public function addError(Test $test, \Throwable $e, float $time): void {
    $this->errors = TRUE;
    file_put_contents(TRAVIS_BUILD_DIR . '/solr.error.log', printf("Error while running test '%s'.\n", $test->getName()), FILE_APPEND | LOCK_EX);
  }

  /**
   *
   */
  public function addFailure(Test $test, AssertionFailedError $e, float $time): void {
    $this->errors = TRUE;
    file_put_contents(TRAVIS_BUILD_DIR . '/solr.error.log', printf("Test '%s' failed.\n", $test->getName()), FILE_APPEND | LOCK_EX);
  }

  /**
   *
   */
  public function addIncompleteTest(Test $test, \Throwable $e, float $time): void {
  }

  /**
   *
   */
  public function addRiskyTest(Test $test, \Throwable $e, float $time): void {
  }

  /**
   *
   */
  public function addSkippedTest(Test $test, \Throwable $e, float $time): void {
  }

  /**
   *
   */
  public function startTest(Test $test): void {
    // In case of a runtime error in the previous test, keep the log.
    if (file_exists(TRAVIS_BUILD_DIR . '/solr.query.log')) {
      file_put_contents(TRAVIS_BUILD_DIR . '/solr.error.log', file_get_contents(TRAVIS_BUILD_DIR . '/solr.query.log'), FILE_APPEND | LOCK_EX);
    }
    $this->errors = FALSE;
  }

  /**
   *
   */
  public function endTest(Test $test, float $time): void {
    if (file_exists(TRAVIS_BUILD_DIR . '/solr.query.log')) {
      if ($this->errors) {
        file_put_contents(TRAVIS_BUILD_DIR . '/solr.error.log', file_get_contents(TRAVIS_BUILD_DIR . '/solr.query.log'), FILE_APPEND | LOCK_EX);
      }
      unlink(TRAVIS_BUILD_DIR . '/solr.query.log');
    }
  }

  /**
   *
   */
  public function startTestSuite(TestSuite $suite): void {
  }

  /**
   *
   */
  public function endTestSuite(TestSuite $suite): void {
  }

}
