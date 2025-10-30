<?php

namespace Drupal\uceap_logging\Logger;

use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Monolog processor that adds session ID to all log records.
 */
class SessionProcessor {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a SessionProcessor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * Adds session ID to the log record.
   *
   * @param \Monolog\LogRecord $record
   *   The log record.
   *
   * @return \Monolog\LogRecord
   *   The modified log record.
   */
  public function __invoke(LogRecord $record): LogRecord {
    $session_id = NULL;

    $request = $this->requestStack->getCurrentRequest();
    if ($request && $request->hasSession()) {
      $session = $request->getSession();
      if ($session->isStarted()) {
        $session_id = $session->getId();
      }
    }

    if ($session_id) {
      $record['extra']['session_id'] = $session_id;
    }

    return $record;
  }

}
