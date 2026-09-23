<?php

namespace Drupal\Tests\uceap_logging\Unit\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\uceap_logging\EventSubscriber\RequestLoggerSubscriber;
use Drupal\uceap_logging\Logger\UriMasker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests for the RequestLoggerSubscriber.
 *
 * @group uceap_logging
 * @coversDefaultClass \Drupal\uceap_logging\EventSubscriber\RequestLoggerSubscriber
 */
class RequestLoggerSubscriberTest extends UnitTestCase {

  /**
   * Tests that sensitive query parameters are masked in the logged URI.
   *
   * @covers ::onKernelRequest
   */
  public function testLogsMaskedUri(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->with('uceap_request')->willReturn($logger);

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(18088);
    $account->method('getAccountName')->willReturn('cashnet');

    $masker = new UriMasker($this->getConfigFactoryStub([
      'uceap_logging.settings' => [
        'sensitive_query_parameters' => ['password'],
      ],
    ]));

    $logger->expects($this->once())
      ->method('info')
      ->with(
        $this->anything(),
        $this->callback(function (array $context) {
          return $context['@uri'] === '/finance/transaction/payment?operator=cashnet&password=****MASKED****&command=post';
        })
      );

    $subscriber = new RequestLoggerSubscriber($logger_factory, $account, $masker);
    $request = Request::create('/finance/transaction/payment?operator=cashnet&password=s3cret&command=post');
    $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

    $subscriber->onKernelRequest($event);
  }

}
