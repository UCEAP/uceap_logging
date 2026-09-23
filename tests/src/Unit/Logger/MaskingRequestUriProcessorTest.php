<?php

namespace Drupal\Tests\uceap_logging\Unit\Logger;

use Drupal\Tests\UnitTestCase;
use Drupal\uceap_logging\Logger\MaskingRequestUriProcessor;
use Drupal\uceap_logging\Logger\UriMasker;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Tests for the MaskingRequestUriProcessor decorator.
 *
 * @group uceap_logging
 * @coversDefaultClass \Drupal\uceap_logging\Logger\MaskingRequestUriProcessor
 */
class MaskingRequestUriProcessorTest extends UnitTestCase {

  /**
   * Builds the processor around an inner processor that sets extra fields.
   *
   * @param array $extra
   *   The extra fields the inner processor adds to the record.
   *
   * @return \Drupal\uceap_logging\Logger\MaskingRequestUriProcessor
   *   The processor.
   */
  protected function createProcessor(array $extra): MaskingRequestUriProcessor {
    $inner = function (LogRecord $record) use ($extra): LogRecord {
      $record->extra = array_merge($record->extra, $extra);
      return $record;
    };
    $masker = new UriMasker($this->getConfigFactoryStub([
      'uceap_logging.settings' => [
        'sensitive_query_parameters' => ['password'],
      ],
    ]));
    return new MaskingRequestUriProcessor($inner, $masker);
  }

  /**
   * Builds an empty log record.
   */
  protected function createRecord(): LogRecord {
    return new LogRecord(new \DateTimeImmutable(), 'uceap_entity_crud', Level::Info, 'Created transaction');
  }

  /**
   * Tests that the request URI added by the inner processor is masked.
   *
   * @covers ::__invoke
   */
  public function testMasksRequestUri(): void {
    $processor = $this->createProcessor([
      'request_uri' => 'https://example.com/finance/transaction/payment?operator=cashnet&password=s3cret&command=post',
    ]);

    $record = $processor($this->createRecord());

    $this->assertSame(
      'https://example.com/finance/transaction/payment?operator=cashnet&password=****MASKED****&command=post',
      $record->extra['request_uri']
    );
  }

  /**
   * Tests that records without a request URI pass through unchanged.
   *
   * @covers ::__invoke
   */
  public function testPassesThroughWithoutRequestUri(): void {
    $processor = $this->createProcessor(['other' => 'value']);

    $record = $processor($this->createRecord());

    $this->assertSame(['other' => 'value'], $record->extra);
  }

}
