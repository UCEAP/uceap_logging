<?php

namespace Drupal\uceap_logging\Logger;

use Monolog\LogRecord;

/**
 * Decorates Monolog's message_placeholder processor to mask @uri values.
 *
 * Core's ExceptionLoggingSubscriber logs the raw request URI through an @uri
 * placeholder on the "access denied" and "page not found" channels, so a
 * rejected request carrying credentials in its query string would otherwise
 * write them into the message text.
 */
class MaskingMessagePlaceholderProcessor {

  /**
   * The decorated message_placeholder processor.
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
   * Constructs a MaskingMessagePlaceholderProcessor.
   *
   * @param callable $inner
   *   The decorated message_placeholder processor.
   * @param \Drupal\uceap_logging\Logger\UriMasker $uri_masker
   *   The URI masker.
   */
  public function __construct(callable $inner, UriMasker $uri_masker) {
    $this->inner = $inner;
    $this->uriMasker = $uri_masker;
  }

  /**
   * Masks the @uri placeholder, then substitutes placeholders as usual.
   *
   * @param \Monolog\LogRecord $record
   *   The log record.
   *
   * @return \Monolog\LogRecord
   *   The modified log record.
   */
  public function __invoke(LogRecord $record): LogRecord {
    if (isset($record->context['@uri']) && is_string($record->context['@uri'])) {
      $context = $record->context;
      $context['@uri'] = $this->uriMasker->mask($context['@uri']);
      $record = $record->with(context: $context);
    }

    return ($this->inner)($record);
  }

}
