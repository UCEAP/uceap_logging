<?php

namespace Drupal\uceap_logging\Queue;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueInterface;

/**
 * Decorator for queue objects to log createItem operations.
 */
class QueueLogger implements QueueInterface {

  /**
   * The decorated queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The queue name.
   *
   * @var string
   */
  protected $queueName;

  /**
   * Constructs a QueueLogger object.
   *
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   The queue object to decorate.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param string $queue_name
   *   The queue name.
   */
  public function __construct(QueueInterface $queue, LoggerChannelFactoryInterface $logger_factory, string $queue_name) {
    $this->queue = $queue;
    $this->logger = $logger_factory->get('uceap_queue');
    $this->queueName = $queue_name;
  }

  /**
   * {@inheritdoc}
   */
  public function createItem($data) {
    $result = $this->queue->createItem($data);

    if ($result) {
      // Log the queue item creation.
      $context = [
        '@queue_name' => $this->queueName,
        '@data' => $this->formatData($data),
        'queue_name' => $this->queueName,
        'operation' => 'create_item',
      ];

      $this->logger->info('Queue item created in @queue_name: @data', $context);
    }

    return $result;
  }

  /**
   * Format data for logging.
   *
   * @param mixed $data
   *   The queue item data.
   *
   * @return string
   *   Formatted data string.
   */
  protected function formatData($data) {
    if (is_scalar($data)) {
      $str = (string) $data;
      return strlen($str) > 200 ? substr($str, 0, 200) . '...' : $str;
    }
    elseif (is_array($data) || is_object($data)) {
      $json = json_encode($data);
      if ($json === FALSE) {
        // Handle encoding failure (circular references, invalid UTF-8, etc.).
        return '[JSON encoding failed for ' . gettype($data) . ': ' . json_last_error_msg() . ']';
      }
      return strlen($json) > 200 ? substr($json, 0, 200) . '...' : $json;
    }
    else {
      return gettype($data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function numberOfItems() {
    return $this->queue->numberOfItems();
  }

  /**
   * {@inheritdoc}
   */
  public function claimItem($lease_time = 3600) {
    return $this->queue->claimItem($lease_time);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItem($item) {
    return $this->queue->deleteItem($item);
  }

  /**
   * {@inheritdoc}
   */
  public function releaseItem($item) {
    return $this->queue->releaseItem($item);
  }

  /**
   * {@inheritdoc}
   */
  public function createQueue() {
    return $this->queue->createQueue();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue() {
    return $this->queue->deleteQueue();
  }

}
