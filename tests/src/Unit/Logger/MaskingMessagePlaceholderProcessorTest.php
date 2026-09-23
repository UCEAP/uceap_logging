<?php

namespace Drupal\Tests\uceap_logging\Unit\Logger;

use Drupal\Tests\UnitTestCase;
use Drupal\uceap_logging\Logger\MaskingMessagePlaceholderProcessor;
use Drupal\uceap_logging\Logger\UriMasker;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Tests for the MaskingMessagePlaceholderProcessor decorator.
 *
 * @group uceap_logging
 * @coversDefaultClass \Drupal\uceap_logging\Logger\MaskingMessagePlaceholderProcessor
 */
class MaskingMessagePlaceholderProcessorTest extends UnitTestCase {

  /**
   * Builds the processor around an inner processor that records its input.
   *
   * @param \Monolog\LogRecord|null $received
   *   Set to the record the inner processor receives.
   *
   * @return \Drupal\uceap_logging\Logger\MaskingMessagePlaceholderProcessor
   *   The processor.
   */
  protected function createProcessor(?LogRecord &$received): MaskingMessagePlaceholderProcessor {
    $inner = function (LogRecord $record) use (&$received): LogRecord {
      $received = $record;
      return $record;
    };
    $masker = new UriMasker($this->getConfigFactoryStub([
      'uceap_logging.settings' => [
        'sensitive_query_parameters' => ['password'],
      ],
    ]));
    return new MaskingMessagePlaceholderProcessor($inner, $masker);
  }

  /**
   * Tests that an @uri placeholder is masked before substitution.
   *
   * This is the placeholder core's ExceptionLoggingSubscriber uses for its
   * "access denied" and "page not found" messages.
   *
   * @covers ::__invoke
   */
  public function testMasksUriPlaceholder(): void {
    $processor = $this->createProcessor($received);
    $record = new LogRecord(new \DateTimeImmutable(), 'access denied', Level::Warning, 'Path: @uri. %type', [
      '@uri' => '/finance/transaction/payment?operator=cashnet&password=s3cret&command=post',
      '%type' => 'AccessDeniedHttpException',
    ]);

    $processor($record);

    $this->assertSame('/finance/transaction/payment?operator=cashnet&password=****MASKED****&command=post', $received->context['@uri']);
    $this->assertSame('AccessDeniedHttpException', $received->context['%type']);
  }

  /**
   * Tests that records without an @uri placeholder pass through unchanged.
   *
   * @covers ::__invoke
   */
  public function testPassesThroughWithoutUriPlaceholder(): void {
    $processor = $this->createProcessor($received);
    $record = new LogRecord(new \DateTimeImmutable(), 'php', Level::Info, 'Hello @name', ['@name' => 'password=s3cret']);

    $processor($record);

    $this->assertSame($record, $received);
  }

}
