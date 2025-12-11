<?php

namespace Drupal\uceap_logging\Queue;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;

/**
 * Decorator for QueueFactory to log queue item creation.
 */
class QueueFactoryDecorator extends QueueFactory {

  /**
   * The decorated queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $decoratedFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Sets the decorated factory.
   *
   * @param \Drupal\Core\Queue\QueueFactory $factory
   *   The queue factory to decorate.
   */
  public function setDecoratedFactory(QueueFactory $factory) {
    $this->decoratedFactory = $factory;
  }

  /**
   * Sets the logger factory.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function setLoggerFactory(LoggerChannelFactoryInterface $logger_factory) {
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function get($name, $reliable = FALSE): QueueInterface {
    $queue = $this->decoratedFactory->get($name, $reliable);

    // Wrap the queue with our logger.
    return new QueueLogger($queue, $this->loggerFactory, $name);
  }

}
