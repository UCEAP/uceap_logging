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
abstract class CloudWatchClientFactory {

  /**
   * Creates and returns an AWS CloudWatch Logs client.
   *
   * Retrieves AWS credentials from helper methods implemented in a subclass.
   *
   * @return \Aws\CloudWatchLogs\CloudWatchLogsClient
   *   The configured CloudWatch Logs client.
   */
  public static function create() {
    $config = [
      'version' => 'latest',
      'region' => 'us-west-2',
      'credentials' => [
        'key' => static::getAwsAccessKeyId(),
        'secret' => static::getAwsSecretAccessKey(),
      ],
    ];

    return new CloudWatchLogsClient($config);
  }

  /**
   * Creates and returns a configured CloudWatch handler.
   *
   * @param string|null $level
   *   (Optional) minimum logging level (DEBUG, INFO, WARNING, ERROR, etc.).
   *   Defaults to 'INFO'.
   *
   * @return \Maxbanton\Cwh\Handler\CloudWatch
   *   The configured CloudWatch handler.
   */
  public static function createHandler($level = 'INFO') {
    $client = static::create();

    // Log group is managed by Terraform.
    $createGroup = FALSE;
    $createStream = FALSE;
    $retention = NULL;
    // QUESTION should these be customizable?
    $log_group = static::getLogGroup();
    $log_stream = 'drupal';
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
   * Get AWS Access Key ID.
   *
   * @return string|null
   *   The AWS Access Key ID value, or NULL if not found.
   */
  abstract public static function getAwsAccessKeyId();

  /**
   * Get AWS Secret Access Key.
   *
   * @return string|null
   *   The AWS Secret Access Key value, or NULL if not found.
   */
  abstract public static function getAwsSecretAccessKey();

  /**
   * Get log group.
   *
   * @return string
   *   The log group name.
   */
  abstract public static function getLogGroup();

}
