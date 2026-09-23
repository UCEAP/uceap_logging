<?php

namespace Drupal\uceap_logging\Logger;

use Monolog\LogRecord;

/**
 * Decorates Monolog's request_uri processor to mask sensitive query values.
 *
 * The request_uri processor stamps the full request URI onto every record
 * written during a request, so credentials passed in the query string would
 * otherwise reach every log line, not just the request log.
 */
class MaskingRequestUriProcessor {

  /**
   * The decorated request_uri processor.
   *
   * @var callable
   */
  protected $inner;

  /**
   * The URI masker.
   *
   * @var \Drupal\uceap_logging\Logger\UriMasker
   */
  protected $uriMasker;

  /**
   * Constructs a MaskingRequestUriProcessor.
   *
   * @param callable $inner
   *   The decorated request_uri processor.
   * @param \Drupal\uceap_logging\Logger\UriMasker $uri_masker
   *   The URI masker.
   */
  public function __construct(callable $inner, UriMasker $uri_masker) {
    $this->inner = $inner;
    $this->uriMasker = $uri_masker;
  }

  /**
   * Adds the request URI to the record with sensitive values masked.
   *
   * @param \Monolog\LogRecord $record
   *   The log record.
   *
   * @return \Monolog\LogRecord
   *   The modified log record.
   */
  public function __invoke(LogRecord $record): LogRecord {
    $record = ($this->inner)($record);

    if (isset($record->extra['request_uri']) && is_string($record->extra['request_uri'])) {
      $record->extra = array_merge($record->extra, [
        'request_uri' => $this->uriMasker->mask($record->extra['request_uri']),
      ]);
    }

    return $record;
  }

}
