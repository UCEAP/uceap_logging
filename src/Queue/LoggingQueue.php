<?php

namespace Drupal\uceap_logging\Queue;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueInterface;

/**
 * Decorates queue objects to log item creation.
 *
 * This decorator wraps any QueueInterface implementation and logs when items
 * are added to the queue. All other queue methods are transparently delegated
 * to the underlying queue implementation using __call(), which preserves
 * default parameter values (such as lease times in claimItem()).
 */
class LoggingQueue implements QueueInterface {

  /**
   * The decorated queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $decoratedQueue;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The queue name.
   *
   * @var string
   */
  protected string $queueName;

  /**
   * Constructs a LoggingQueue object.
   *
   * @param \Drupal\Core\Queue\QueueInterface $decorated_queue
   *   The queue to decorate.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param string $queue_name
   *   The name of the queue.
   */
  public function __construct(
    QueueInterface $decorated_queue,
    LoggerChannelFactoryInterface $logger_factory,
    string $queue_name,
  ) {
    $this->decoratedQueue = $decorated_queue;
    $this->loggerFactory = $logger_factory;
    $this->queueName = $queue_name;
  }

  /**
   * {@inheritdoc}
   */
  public function createItem($data) {
    $this->log('Queue item added to @queue', [
      'queue_data' => json_encode($data),
    ]);

    return $this->decoratedQueue->createItem($data);
  }

  /**
   * {@inheritdoc}
   */
  public function numberOfItems() {
    return $this->decoratedQueue->numberOfItems();
  }

  /**
   * {@inheritdoc}
   */
  public function claimItem($lease_time = NULL) {
    // Use null as sentinel to preserve underlying queue's default lease time.
    if ($lease_time === NULL) {
      $item = $this->decoratedQueue->claimItem();
    }
    else {
      $item = $this->decoratedQueue->claimItem($lease_time);
    }

    // Log successful claims.
    if ($item !== FALSE) {
      $context = ['item_id' => $item->item_id ?? NULL];
      if ($lease_time !== NULL) {
        $context['lease_time'] = $lease_time;
      }
      $this->log('Queue item claimed from @queue', $context);
    }

    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItem($item) {
    $result = $this->decoratedQueue->deleteItem($item);
    $this->log('Queue item deleted from @queue', [
      'item_id' => $item->item_id ?? NULL,
    ]);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function releaseItem($item) {
    $result = $this->decoratedQueue->releaseItem($item);
    $this->log('Queue item released back to @queue', [
      'item_id' => $item->item_id ?? NULL,
    ]);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function createQueue() {
    $this->decoratedQueue->createQueue();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue() {
    $this->decoratedQueue->deleteQueue();
  }

  /**
   * Logs a queue operation.
   *
   * @param string $message
   *   The log message with @queue placeholder.
   * @param array $context
   *   Additional context to include in the log.
   */
  private function log(string $message, array $context = []): void {
    $context['@queue'] = $this->queueName;
    $context['queue_name'] = $this->queueName;
    $this->loggerFactory->get('uceap_queue')->info($message, $context);
  }

}
