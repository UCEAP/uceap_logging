<?php

namespace Drupal\uceap_logging\Queue;

use Drupal\Core\Database\Connection;
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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection|null
   */
  protected ?Connection $connection;

  /**
   * Constructs a LoggingQueueFactory.
   *
   * @param \Drupal\Core\Queue\QueueFactory $decorated_factory
   *   The queue factory to decorate.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Database\Connection|null $connection
   *   The database connection, used to log what garbage collection cleans up.
   */
  public function __construct(
    QueueFactory $decorated_factory,
    LoggerChannelFactoryInterface $logger_factory,
    ?Connection $connection = NULL,
  ) {
    $this->decoratedFactory = $decorated_factory;
    $this->loggerFactory = $logger_factory;
    $this->connection = $connection;
    // Don't call parent constructor as we're decorating, not extending.
  }

  /**
   * {@inheritdoc}
   */
  public function get($name, $reliable = FALSE) {
    // Get the queue from the decorated factory.
    $queue = $this->decoratedFactory->get($name, $reliable);

    // Wrap it with logging functionality.
    return new LoggingQueue($queue, $this->loggerFactory, $name, $this->connection);
  }

}
