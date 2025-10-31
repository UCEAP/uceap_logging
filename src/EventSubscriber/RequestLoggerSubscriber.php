<?php

namespace Drupal\uceap_logging\EventSubscriber;

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
   * Constructs a new RequestLoggerSubscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, AccountInterface $current_user) {
    $this->logger = $logger_factory->get('uceap_request');
    $this->currentUser = $current_user;
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

    $this->logger->info('@method @uri | User: @user_id (@username) | IP: @ip | Referer: @referer | UA: @user_agent', $context);
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
