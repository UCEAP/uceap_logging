<?php

namespace Drupal\Tests\uceap_logging\Unit\Queue;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\uceap_logging\Queue\LoggingQueue;
use Drupal\uceap_logging\Queue\LoggingQueueFactory;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for the LoggingQueueFactory decorator.
 *
 * @group uceap_logging
 * @coversDefaultClass \Drupal\uceap_logging\Queue\LoggingQueueFactory
 */
class LoggingQueueFactoryTest extends UnitTestCase {

  /**
   * The decorated queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory|\PHPUnit\Framework\MockObject\MockObject
   */
  protected QueueFactory | MockObject $decoratedFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerChannelFactoryInterface | MockObject $loggerFactory;

  /**
   * The logging queue factory being tested.
   *
   * @var \Drupal\uceap_logging\Queue\LoggingQueueFactory
   */
  protected LoggingQueueFactory $loggingQueueFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->decoratedFactory = $this->createMock(QueueFactory::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);

    $this->loggingQueueFactory = new LoggingQueueFactory(
      $this->decoratedFactory,
      $this->loggerFactory
    );
  }

  /**
   * Tests that get() wraps queues with LoggingQueue.
   *
   * @covers ::get
   */
  public function testGetWrapsQueueWithLogging(): void {
    $queueName = 'test_queue';
    $mockQueue = $this->createMock(QueueInterface::class);

    $this->decoratedFactory
      ->expects($this->once())
      ->method('get')
      ->with($queueName)
      ->willReturn($mockQueue);

    $result = $this->loggingQueueFactory->get($queueName);

    $this->assertInstanceOf(LoggingQueue::class, $result);
  }

  /**
   * Tests that get() preserves queue name.
   *
   * @covers ::get
   */
  public function testGetPreservesQueueName(): void {
    $queueName = 'my_custom_queue';
    $mockQueue = $this->createMock(QueueInterface::class);

    $this->decoratedFactory
      ->method('get')
      ->with($queueName)
      ->willReturn($mockQueue);

    $result = $this->loggingQueueFactory->get($queueName);

    // Verify we can call methods on the wrapped queue.
    // The queue name will be used in logging.
    $this->assertInstanceOf(LoggingQueue::class, $result);
  }

  /**
   * Tests that get() with reliable option works correctly.
   *
   * @covers ::get
   */
  public function testGetWithReliableOption(): void {
    $queueName = 'test_queue';
    $reliable = TRUE;
    $mockQueue = $this->createMock(QueueInterface::class);

    $this->decoratedFactory
      ->expects($this->once())
      ->method('get')
      ->with($queueName, $reliable)
      ->willReturn($mockQueue);

    $result = $this->loggingQueueFactory->get($queueName, $reliable);

    $this->assertInstanceOf(LoggingQueue::class, $result);
  }

}
