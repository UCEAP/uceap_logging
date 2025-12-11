<?php

namespace Drupal\uceap_logging\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to log all HTTP requests.
 */
class RequestLoggerSubscriber implements EventSubscriberInterface {

  /**
   * Maximum length for POST field values before truncation.
   */
  const POST_FIELD_MAX_LENGTH = 100;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * List of sensitive field names that should be masked.
   *
   * @var array
   */
  protected $sensitiveFields;

  /**
   * Constructs a new RequestLoggerSubscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, AccountInterface $current_user, ConfigFactoryInterface $config_factory) {
    $this->logger = $logger_factory->get('uceap_request');
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    
    // Load sensitive fields configuration once.
    $config = $this->configFactory->get('uceap_logging.settings');
    $this->sensitiveFields = $config->get('sensitive_fields') ?? [];
  }

  /**
   * Logs each request.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onKernelRequest(RequestEvent $event) {
    // Only log the main request, not subrequests.
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    // Gather request information.
    $context = [
      '@method' => $request->getMethod(),
      '@uri' => $request->getRequestUri(),
      '@ip' => $request->getClientIp(),
      '@user_id' => $this->currentUser->id(),
      '@username' => $this->currentUser->getAccountName(),
      '@user_agent' => $request->headers->get('User-Agent'),
      '@referer' => $request->headers->get('referer', 'none'),
    ];

    // Add POST data if this is a POST request.
    $log_message = '@method @uri | User: @user_id (@username) | IP: @ip | Referer: @referer | UA: @user_agent';
    if ($request->isMethod('POST')) {
      $post_data = $request->request->all();
      $truncated_data = $this->truncatePostData($post_data, $this->sensitiveFields);
      // Serialize POST data for safe logging.
      $context['post_data'] = json_encode($truncated_data);
      $log_message .= ' | POST: @post_data';
    }

    $this->logger->info($log_message, $context);
  }

  /**
   * Truncates POST data field values to maximum length and masks sensitive fields.
   *
   * @param array $data
   *   The POST data array.
   * @param array $sensitive_fields
   *   List of field names to mask.
   *
   * @return array
   *   The POST data with truncated and masked values.
   */
  protected function truncatePostData(array $data, array $sensitive_fields) {
    $truncated = [];
    foreach ($data as $key => $value) {
      // Mask sensitive fields.
      if (in_array($key, $sensitive_fields)) {
        $truncated[$key] = '***MASKED***';
      }
      elseif (is_array($value)) {
        $truncated[$key] = $this->truncatePostData($value, $sensitive_fields);
      }
      elseif (is_string($value) && strlen($value) > self::POST_FIELD_MAX_LENGTH) {
        $truncated[$key] = substr($value, 0, self::POST_FIELD_MAX_LENGTH) . '...';
      }
      else {
        $truncated[$key] = $value;
      }
    }
    return $truncated;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Set priority to 50 so it runs early but after most routing logic.
    $events[KernelEvents::REQUEST][] = ['onKernelRequest', 50];
    return $events;
  }

}
