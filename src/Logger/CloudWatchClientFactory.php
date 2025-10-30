<?php

namespace Drupal\uceap_logging\Logger;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use PhpNexus\Cwh\Handler\CloudWatch;

/**
 * Factory for creating AWS CloudWatch Logs client and handler.
 *
 * This factory retrieves AWS credentials from environment variables or the
 * Drupal Key module (if available).
 *
 * Environment variables:
 * - AWS_ACCESS_KEY_ID: AWS access key
 * - AWS_SECRET_ACCESS_KEY: AWS secret key
 *
 * Key module keys (fallback if environment variables not set):
 * - aws_access_key_id: AWS access key
 * - aws_secret_access_key: AWS secret key
 */
class CloudWatchClientFactory {

  /**
   * Creates and returns an AWS CloudWatch Logs client.
   *
   * Retrieves AWS credentials from environment variables, with fallback to
   * the Drupal Key module if available.
   *
   * @param string|null $access_key_id
   *   Optional AWS access key ID. If not provided, will be retrieved from
   *   environment or Key module.
   * @param string|null $secret_access_key
   *   Optional AWS secret access key. If not provided, will be retrieved from
   *   environment or Key module.
   * @param string $region
   *   AWS region. Defaults to 'us-west-2'.
   *
   * @return \Aws\CloudWatchLogs\CloudWatchLogsClient
   *   The configured CloudWatch Logs client.
   */
  public static function create($access_key_id = NULL, $secret_access_key = NULL, $region = 'us-west-2') {
    $config = [
      'version' => 'latest',
      'region' => $region,
    ];

    // Get AWS credentials if not provided.
    if ($access_key_id === NULL) {
      $access_key_id = self::getAwsCredential('aws_access_key_id');
    }
    if ($secret_access_key === NULL) {
      $secret_access_key = self::getAwsCredential('aws_secret_access_key');
    }

    if (!empty($access_key_id) && !empty($secret_access_key)) {
      $config['credentials'] = [
        'key' => $access_key_id,
        'secret' => $secret_access_key,
      ];
    }

    return new CloudWatchLogsClient($config);
  }

  /**
   * Creates and returns a configured CloudWatch handler.
   *
   * @param string $level
   *   The minimum logging level (DEBUG, INFO, WARNING, ERROR, etc.).
   * @param string|null $log_group
   *   Optional log group name. If not provided, will use environment-based
   *   default.
   * @param string $log_stream
   *   Log stream name. Defaults to 'drupal'.
   *
   * @return \Maxbanton\Cwh\Handler\CloudWatch
   *   The configured CloudWatch handler.
   */
  public static function createHandler($level = 'DEBUG', $log_group = NULL, $log_stream = 'drupal') {
    $client = self::create();

    // Determine log group if not provided.
    if ($log_group === NULL) {
      $log_group = self::getDefaultLogGroup();
    }

    // Log group is managed by Terraform.
    $createGroup = FALSE;
    $createStream = FALSE;
    $retention = NULL;
    // We default to bubbling just like other Monolog handlers.
    $bubble = TRUE;
    // Default is 10000 but we have to pass it explicitly.
    $batchSize = 10000;

    return new CloudWatch(
      $client,
      $log_group,
      $log_stream,
      $retention,
      $batchSize,
      [],
      $level,
      $bubble,
      $createGroup,
      $createStream
    );
  }

  /**
   * Get AWS credential from environment or Key module.
   *
   * @param string $key
   *   The credential key name (lowercase, e.g., 'aws_access_key_id').
   *
   * @return string|null
   *   The credential value, or NULL if not found.
   */
  protected static function getAwsCredential($key) {
    // First check environment variable (uppercase).
    $env_key = strtoupper($key);
    $value = getenv($env_key);

    // Fall back to Key module if environment variable not set.
    if (empty($value) && \Drupal::hasService('key.repository')) {
      try {
        $key_repository = \Drupal::service('key.repository');
        $key_entity = $key_repository->getKey($key);
        if ($key_entity) {
          $value = $key_entity->getKeyValue();
        }
      }
      catch (\Exception $e) {
        // Key module not available or key not found.
        $value = NULL;
      }
    }

    return $value;
  }

  /**
   * Get default log group based on environment.
   *
   * @return string
   *   The log group name.
   */
  protected static function getDefaultLogGroup() {
    // Try to detect environment.
    $environment = 'local';

    // Check if myeap_core.environment service exists.
    if (\Drupal::hasService('myeap_core.environment')) {
      try {
        $env_service = \Drupal::service('myeap_core.environment');
        $env_name = $env_service->getName();
        $environment = match ($env_name) {
          'live' => 'live',
          'test' => 'test',
          'dev' => 'dev',
          default => 'local',
        };
      }
      catch (\Exception $e) {
        // Service not available, stick with default.
      }
    }
    // Fall back to Pantheon environment variable.
    elseif ($pantheon_env = getenv('PANTHEON_ENVIRONMENT')) {
      $environment = match ($pantheon_env) {
        'live' => 'live',
        'test' => 'test',
        'dev' => 'dev',
        default => 'local',
      };
    }

    return '/myeap2/' . $environment;
  }

}
