<?php

namespace Drupal\Tests\uceap_logging\Unit\Queue;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\uceap_logging\Queue\LoggingQueue;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for the LoggingQueue decorator.
 *
 * @group uceap_logging
 * @coversDefaultClass \Drupal\uceap_logging\Queue\LoggingQueue
 */
class LoggingQueueTest extends UnitTestCase {

  /**
   * The decorated queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected QueueInterface | MockObject $decoratedQueue;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerChannelFactoryInterface | MockObject $loggerFactory;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerChannelInterface | MockObject $logger;

  /**
   * The logging queue being tested.
   *
   * @var \Drupal\uceap_logging\Queue\LoggingQueue
   */
  protected LoggingQueue $loggingQueue;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->decoratedQueue = $this->createMock(QueueInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->logger = $this->createMock(LoggerChannelInterface::class);

    $this->loggerFactory
      ->method('get')
      ->with('uceap_queue')
      ->willReturn($this->logger);

    $this->loggingQueue = new LoggingQueue(
      $this->decoratedQueue,
      $this->loggerFactory,
      'test_queue'
    );
  }

  /**
   * Tests that createItem logs and delegates to decorated queue.
   *
   * @covers ::createItem
   */
  public function testCreateItemLogsAndDelegates(): void {
    $data = ['foo' => 'bar', 'baz' => 123];
    $expectedItemId = 42;

    // Expect logger to be called with queue name and data.
    $this->logger
      ->expects($this->once())
      ->method('info')
      ->with(
        'Queue item added to @queue',
        $this->callback(function ($context) use ($data) {
          return $context['@queue'] === 'test_queue'
            && $context['queue_name'] === 'test_queue'
            && json_decode($context['queue_data'], TRUE) === $data;
        })
      );

    // Expect decorated queue to receive the createItem call.
    $this->decoratedQueue
      ->expects($this->once())
      ->method('createItem')
      ->with($data)
      ->willReturn($expectedItemId);

    $result = $this->loggingQueue->createItem($data);

    $this->assertEquals($expectedItemId, $result);
  }

  /**
   * Tests that other methods are delegated to decorated queue.
   *
   * @dataProvider delegatedMethodsProvider
   */
  public function testDelegatedMethods(string $method, array $args, $expectedReturn): void {
    // Set up expectation on decorated queue.
    $mock = $this->decoratedQueue
      ->expects($this->once())
      ->method($method)
      ->willReturn($expectedReturn);

    // Add argument expectations if provided.
    if (!empty($args)) {
      $mock->with(...$args);
    }

    $result = call_user_func_array([$this->loggingQueue, $method], $args);

    $this->assertEquals($expectedReturn, $result);
  }

  /**
   * Data provider for testDelegatedMethods.
   *
   * @return array
   *   Test cases with method name, arguments, and expected return value.
   */
  public function delegatedMethodsProvider(): array {
    return [
      'numberOfItems' => ['numberOfItems', [], 5],
      'createQueue' => ['createQueue', [], NULL],
      'deleteQueue' => ['deleteQueue', [], NULL],
    ];
  }

  /**
   * Tests that claimItem preserves default lease time behavior.
   *
   * This test verifies that when claimItem() is called without arguments,
   * the underlying queue's default lease time is preserved (not overridden
   * with a different default from the decorator).
   *
   * @covers ::claimItem
   */
  public function testClaimItemPreservesDefaultLeaseTime(): void {
    // Create a custom queue class with a specific default lease time.
    $customQueue = new class() implements QueueInterface {

      /**
       * Flag indicating if claimItem was called.
       *
       * @var bool
       */
      public $claimItemCalled = FALSE;

      /**
       * Arguments received by claimItem.
       *
       * @var array
       */
      public $receivedArgs = [];

      /**
       * {@inheritdoc}
       */
      public function createItem($data) {
        return TRUE;
      }

      /**
       * {@inheritdoc}
       */
      public function numberOfItems() {
        return 0;
      }

      /**
       * {@inheritdoc}
       */
      public function claimItem($lease_time = 42) {
        $this->claimItemCalled = TRUE;
        $this->receivedArgs = func_get_args();
        return FALSE;
      }

      /**
       * {@inheritdoc}
       */
      public function deleteItem($item) {
      }

      /**
       * {@inheritdoc}
       */
      public function releaseItem($item) {
        return TRUE;
      }

      /**
       * {@inheritdoc}
       */
      public function createQueue() {
      }

      /**
       * {@inheritdoc}
       */
      public function deleteQueue() {
      }

    };

    $loggingQueue = new LoggingQueue(
      $customQueue,
      $this->loggerFactory,
      'test_queue'
    );

    // Call claimItem without arguments.
    $loggingQueue->claimItem();

    // Verify the underlying queue received no arguments.
    // (so its default applies).
    $this->assertTrue($customQueue->claimItemCalled);
    $this->assertEmpty(
      $customQueue->receivedArgs,
      'claimItem should be called with no arguments'
    );
  }

  /**
   * Tests that claimItem passes explicit lease time through.
   *
   * @covers ::claimItem
   */
  public function testClaimItemPassesExplicitLeaseTime(): void {
    $expectedLeaseTime = 120;
    $mockItem = (object) ['item_id' => 42, 'data' => 'test'];

    $this->decoratedQueue
      ->expects($this->once())
      ->method('claimItem')
      ->with($expectedLeaseTime)
      ->willReturn($mockItem);

    $this->loggingQueue->claimItem($expectedLeaseTime);
  }

  /**
   * Tests that claimItem logs when an item is successfully claimed.
   *
   * @covers ::claimItem
   */
  public function testClaimItemLogsSuccessfulClaim(): void {
    $mockItem = (object) ['item_id' => 123, 'data' => 'test data'];

    $this->decoratedQueue
      ->method('claimItem')
      ->willReturn($mockItem);

    $this->logger
      ->expects($this->once())
      ->method('info')
      ->with(
        'Queue item claimed from @queue',
        $this->callback(function ($context) {
          return $context['@queue'] === 'test_queue'
            && $context['queue_name'] === 'test_queue'
            && $context['item_id'] === 123;
        })
      );

    $result = $this->loggingQueue->claimItem();
    $this->assertEquals($mockItem, $result);
  }

  /**
   * Tests that claimItem does not log when no item is available.
   *
   * @covers ::claimItem
   */
  public function testClaimItemDoesNotLogWhenNoItemAvailable(): void {
    $this->decoratedQueue
      ->method('claimItem')
      ->willReturn(FALSE);

    $this->logger
      ->expects($this->never())
      ->method('info');

    $result = $this->loggingQueue->claimItem();
    $this->assertFalse($result);
  }

  /**
   * Tests that claimItem logs lease time when explicitly provided.
   *
   * @covers ::claimItem
   */
  public function testClaimItemLogsLeaseTimeWhenProvided(): void {
    $mockItem = (object) ['item_id' => 456, 'data' => 'test'];
    $leaseTime = 60;

    $this->decoratedQueue
      ->method('claimItem')
      ->willReturn($mockItem);

    $this->logger
      ->expects($this->once())
      ->method('info')
      ->with(
        'Queue item claimed from @queue',
        $this->callback(function ($context) use ($leaseTime) {
          return $context['@queue'] === 'test_queue'
            && $context['queue_name'] === 'test_queue'
            && $context['item_id'] === 456
            && $context['lease_time'] === $leaseTime;
        })
      );

    $this->loggingQueue->claimItem($leaseTime);
  }

  /**
   * Tests that deleteItem logs the deletion.
   *
   * @covers ::deleteItem
   */
  public function testDeleteItemLogs(): void {
    $mockItem = (object) ['item_id' => 789, 'data' => 'test'];

    $this->decoratedQueue
      ->expects($this->once())
      ->method('deleteItem')
      ->with($mockItem);

    $this->logger
      ->expects($this->once())
      ->method('info')
      ->with(
        'Queue item deleted from @queue',
        $this->callback(function ($context) {
          return $context['@queue'] === 'test_queue'
            && $context['queue_name'] === 'test_queue'
            && $context['item_id'] === 789;
        })
      );

    $this->loggingQueue->deleteItem($mockItem);
  }

  /**
   * Tests that releaseItem logs the release.
   *
   * @covers ::releaseItem
   */
  public function testReleaseItemLogs(): void {
    $mockItem = (object) ['item_id' => 321, 'data' => 'test'];

    $this->decoratedQueue
      ->expects($this->once())
      ->method('releaseItem')
      ->with($mockItem)
      ->willReturn(TRUE);

    $this->logger
      ->expects($this->once())
      ->method('info')
      ->with(
        'Queue item released back to @queue',
        $this->callback(function ($context) {
          return $context['@queue'] === 'test_queue'
            && $context['queue_name'] === 'test_queue'
            && $context['item_id'] === 321;
        })
      );

    $result = $this->loggingQueue->releaseItem($mockItem);
    $this->assertTrue($result);
  }

}
