<?php

namespace Drupal\uceap_logging\Queue;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Decorates the QueueFactory to return logging-enabled queues.
 *
 * This factory decorator wraps Drupal's default QueueFactory and returns
 * LoggingQueue instances that log all queue item additions while
 * transparently delegating all other operations to the underlying queue.
 */
class LoggingQueueFactory extends QueueFactory {

  /**
   * The decorated queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $decoratedFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructs a LoggingQueueFactory.
   *
   * @param \Drupal\Core\Queue\QueueFactory $decorated_factory
   *   The queue factory to decorate.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    QueueFactory $decorated_factory,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->decoratedFactory = $decorated_factory;
    $this->loggerFactory = $logger_factory;
    // Don't call parent constructor as we're decorating, not extending.
  }

  /**
   * {@inheritdoc}
   */
  public function get($name, $reliable = FALSE) {
    // Get the queue from the decorated factory.
    $queue = $this->decoratedFactory->get($name, $reliable);

    // Wrap it with logging functionality.
    return new LoggingQueue($queue, $this->loggerFactory, $name);
  }

}
