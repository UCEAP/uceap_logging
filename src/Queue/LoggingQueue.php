<?php

namespace Drupal\uceap_logging\Queue;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\DatabaseQueue;
use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\QueueGarbageCollectionInterface;
use Drupal\Core\Queue\QueueInterface;

/**
 * Decorates queue objects to log queue operations.
 *
 * This decorator wraps any QueueInterface implementation and logs when items
 * are added, claimed, deleted, released, or delayed, and what garbage
 * collection cleans up. Every operation is delegated to the underlying queue.
 * Garbage collection and delaying are passed through only when the underlying
 * queue supports them, so system_cron still releases expired claims.
 */
class LoggingQueue implements QueueInterface, QueueGarbageCollectionInterface, DelayableQueueInterface {

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
   * The database connection, used to log what garbage collection cleans up.
   *
   * @var \Drupal\Core\Database\Connection|null
   */
  protected ?Connection $connection;

  /**
   * Constructs a LoggingQueue object.
   *
   * @param \Drupal\Core\Queue\QueueInterface $decorated_queue
   *   The queue to decorate.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param string $queue_name
   *   The name of the queue.
   * @param \Drupal\Core\Database\Connection|null $connection
   *   The database connection, used to log what garbage collection cleans up.
   */
  public function __construct(
    QueueInterface $decorated_queue,
    LoggerChannelFactoryInterface $logger_factory,
    string $queue_name,
    ?Connection $connection = NULL,
  ) {
    $this->decoratedQueue = $decorated_queue;
    $this->loggerFactory = $logger_factory;
    $this->queueName = $queue_name;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function createItem($data) {
    $this->log('Queue item added to @queue', [
      'queue_data' => $data,
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
   * {@inheritdoc}
   *
   * Without this, system_cron would never reach the decorated queue's garbage
   * collection, so items claimed by an interrupted run would stay claimed.
   */
  public function garbageCollection() {
    if (!$this->decoratedQueue instanceof QueueGarbageCollectionInterface) {
      return;
    }

    // DatabaseQueue::garbageCollection() releases expired claims in every
    // queue and deletes stale batch items, without reporting either. Look
    // them up first so the cleanup can be logged.
    $expired_claims = [];
    $stale_batch_items = 0;
    if ($this->connection && $this->decoratedQueue instanceof DatabaseQueue) {
      $now = \Drupal::time()->getRequestTime();
      $expired_claims = $this->connection->query(
        'SELECT [item_id], [name], [created], [expire] FROM {' . DatabaseQueue::TABLE_NAME . '} WHERE [expire] <> 0 AND [expire] < :now',
        [':now' => $now]
      )->fetchAll();
      $stale_batch_items = (int) $this->connection->query(
        'SELECT COUNT(*) FROM {' . DatabaseQueue::TABLE_NAME . '} WHERE [name] LIKE :batch AND [created] < :cutoff',
        [':batch' => 'drupal_batch:%', ':cutoff' => $now - 864000]
      )->fetchField();
    }

    $this->decoratedQueue->garbageCollection();

    foreach ($expired_claims as $claim) {
      $this->log('Expired claim released in @queue', [
        'item_id' => (int) $claim->item_id,
        'created' => (int) $claim->created,
        'lease_expired' => (int) $claim->expire,
      ], 'warning', $claim->name);
    }
    if ($stale_batch_items) {
      $this->log('Deleted @count stale batch items from the queue table', [
        '@count' => $stale_batch_items,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delayItem($item, int $delay) {
    if (!$this->decoratedQueue instanceof DelayableQueueInterface) {
      return FALSE;
    }

    $result = $this->decoratedQueue->delayItem($item, $delay);
    $this->log('Queue item delayed in @queue', [
      'item_id' => $item->item_id ?? NULL,
      'delay' => $delay,
    ]);
    return $result;
  }

  /**
   * Logs a queue operation.
   *
   * @param string $message
   *   The log message with @queue placeholder.
   * @param array $context
   *   Additional context to include in the log.
   * @param string $level
   *   The log level.
   * @param string|null $queue_name
   *   The queue the operation applies to, if not this queue.
   */
  private function log(string $message, array $context = [], string $level = 'info', ?string $queue_name = NULL): void {
    $queue_name ??= $this->queueName;
    $context['@queue'] = $queue_name;
    $context['queue_name'] = $queue_name;
    $this->loggerFactory->get('uceap_queue')->{$level}($message, $context);
  }

}
