<?php

namespace Drupal\Tests\uceap_logging\Unit\Logger;

use Drupal\Tests\UnitTestCase;
use Drupal\uceap_logging\Logger\UriMasker;

/**
 * Tests for the UriMasker service.
 *
 * @group uceap_logging
 * @coversDefaultClass \Drupal\uceap_logging\Logger\UriMasker
 */
class UriMaskerTest extends UnitTestCase {

  /**
   * Builds a masker configured with the given sensitive query parameters.
   *
   * @param array|null $parameters
   *   The sensitive query parameter names, or NULL for an unset config value.
   *
   * @return \Drupal\uceap_logging\Logger\UriMasker
   *   The masker.
   */
  protected function createMasker(?array $parameters): UriMasker {
    $config_factory = $this->getConfigFactoryStub([
      'uceap_logging.settings' => [
        'sensitive_query_parameters' => $parameters,
      ],
    ]);
    return new UriMasker($config_factory);
  }

  /**
   * Tests masking of configured query parameter values.
   *
   * @covers ::mask
   * @dataProvider maskProvider
   */
  public function testMask(array $parameters, string $uri, string $expected): void {
    $this->assertSame($expected, $this->createMasker($parameters)->mask($uri));
  }

  /**
   * Data provider for testMask().
   */
  public static function maskProvider(): array {
    return [
      'cashnet postback' => [
        ['password'],
        '/finance/transaction/payment?operator=cashnet&password=s3cret&custcode=123&command=post&result=0',
        '/finance/transaction/payment?operator=cashnet&password=****MASKED****&custcode=123&command=post&result=0',
      ],
      'first parameter' => [
        ['password'],
        '/path?password=s3cret&a=1',
        '/path?password=****MASKED****&a=1',
      ],
      'last parameter' => [
        ['password'],
        '/path?a=1&password=s3cret',
        '/path?a=1&password=****MASKED****',
      ],
      'empty value' => [
        ['password'],
        '/path?password=&a=1',
        '/path?password=****MASKED****&a=1',
      ],
      'case insensitive name' => [
        ['password'],
        '/path?PassWord=s3cret',
        '/path?PassWord=****MASKED****',
      ],
      'fragment preserved' => [
        ['password'],
        '/path?password=s3cret#top',
        '/path?password=****MASKED****#top',
      ],
      'repeated parameter' => [
        ['password'],
        '/path?password=one&password=two',
        '/path?password=****MASKED****&password=****MASKED****',
      ],
      'multiple configured parameters' => [
        ['password', 'token'],
        '/path?token=abc&password=s3cret&keep=1',
        '/path?token=****MASKED****&password=****MASKED****&keep=1',
      ],
      'absolute url' => [
        ['password'],
        'https://example.com/finance/account/e_refund?operator=cashnet&password=s3cret',
        'https://example.com/finance/account/e_refund?operator=cashnet&password=****MASKED****',
      ],
      'name prefix not matched' => [
        ['password'],
        '/path?xpassword=keep&password_hint=keep',
        '/path?xpassword=keep&password_hint=keep',
      ],
      'password in path not matched' => [
        ['password'],
        '/user/password=reset?a=1',
        '/user/password=reset?a=1',
      ],
      'no query string' => [
        ['password'],
        '/finance/transaction/payment',
        '/finance/transaction/payment',
      ],
      'no configured parameters' => [
        [],
        '/path?password=s3cret',
        '/path?password=s3cret',
      ],
    ];
  }

  /**
   * Tests that an unset config value leaves the URI untouched.
   *
   * @covers ::mask
   */
  public function testMaskWithUnsetConfig(): void {
    $this->assertSame('/path?password=s3cret', $this->createMasker(NULL)->mask('/path?password=s3cret'));
  }

}
