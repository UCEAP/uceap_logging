<?php

namespace Drupal\uceap_logging\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Masks the values of sensitive query parameters in URIs before logging.
 */
class UriMasker {

  /**
   * The replacement written in place of a sensitive value.
   */
  const MASK = '****MASKED****';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a UriMasker.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Replaces the values of configured sensitive query parameters.
   *
   * @param string $uri
   *   A request URI, either a path with query string or an absolute URL.
   *
   * @return string
   *   The URI with each sensitive parameter's value replaced by the mask.
   */
  public function mask(string $uri): string {
    if (!str_contains($uri, '?')) {
      return $uri;
    }

    $parameters = $this->configFactory->get('uceap_logging.settings')->get('sensitive_query_parameters') ?? [];
    if (empty($parameters)) {
      return $uri;
    }

    [$base, $query] = explode('?', $uri, 2);
    $names = implode('|', array_map(fn ($name) => preg_quote($name, '/'), $parameters));
    $query = preg_replace('/(^|&)(' . $names . ')=[^&#]*/i', '$1$2=' . self::MASK, $query);

    return $base . '?' . $query;
  }

}
