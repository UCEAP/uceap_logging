# UCEAP Logging Module

A reusable Drupal module that provides comprehensive logging for HTTP requests and entity CRUD operations with CloudWatch integration.

## Features

### 1. HTTP Request Logging
- Logs every HTTP request to the `myeap_request` channel
- Captures:
  - HTTP method and URI
  - User ID and username
  - Client IP address
  - User agent
  - Referer

### 2. Entity CRUD Logging
- Logs all content entity create, update, and delete operations to the `myeap_entity_crud` channel
- For **create** operations:
  - Entity type, bundle, label, and ID
- For **update** operations:
  - Field-level change tracking with old and new values
  - Detects when entities are saved without changes
- For **delete** operations:
  - Entity type, bundle, label, and ID

### 3. Structured Context for CloudWatch
All logs include queryable JSON metadata:
- `entity_type` - The entity type (user, node, etc.)
- `bundle` - The bundle/content type
- `entity_id` - The entity ID
- `operation` - One of: `create`, `update`, or `delete`
- `field_changes` - Map of changed fields with old/new values (updates only)

## Prerequisites

This module requires the [AWS CloudWatch Logs Handler for Monolog](https://github.com/phpnexus/cwh).

## Installation

1. Copy this module to your Drupal installation's `modules/custom` directory
2. Install dependencies using Composer:
    ```bash
    composer require phpnexus/cwh:^3.0
    ```
2. Enable the module:
    ```bash
    drush pm:enable uceap_logging -y
    ```

## Usage

Once enabled, the module automatically logs:
- All HTTP requests
- All content entity operations (create, update, delete)

### Viewing Logs

```bash
# View all request logs
drush watchdog:show --type=myeap_request

# View all entity CRUD logs
drush watchdog:show --type=myeap_entity_crud

# View recent logs
drush watchdog:show | grep -E "(myeap_request|myeap_entity_crud)"
```

## CloudWatch Integration

This module includes a `CloudWatchClientFactory` class that simplifies integration with AWS CloudWatch Logs.

### Example: Monolog Integration

Create or update `web/sites/default/monolog.services.yml`:

```yaml
services:
  monolog.handler.cloudwatch:
    class: PhpNexus\Cwh\Handler\CloudWatch
    factory: ['Drupal\uceap_logging\Logger\CloudWatchClientFactory', 'createHandler']
    arguments: ['DEBUG']  # Log level

parameters:
  monolog.channel_handlers:
    default:
      handlers:
        - name: 'cloudwatch'
          formatter: 'json'
```

### CloudWatch Log Format

When integrated with Monolog and CloudWatch, logs are automatically sent to CloudWatch in structured JSON format:

```json
{
  "message": "Updated user (user): john_doe (ID: 123) | 3 field(s) changed",
  "context": {
    "entity_type": "user",
    "bundle": "user",
    "entity_id": 123,
    "operation": "update",
    "field_changes": {
      "name": {
        "old": "old_username",
        "new": "john_doe"
      },
      "mail": {
        "old": "old@example.com",
        "new": "john@example.com"
      },
      "pass": {
        "old": "***MASKED***",
        "new": "***MASKED***"
      }
    }
  },
  "level": "INFO",
  "channel": "myeap_entity_crud"
}
```

## Configuration

### Sensitive Field Masking

By default, the following sensitive fields have their values masked in logs:
- `pass` - User passwords
- `uuid` - Entity UUIDs
- `revision_timestamp` - Revision timestamps
- `revision_uid` - Revision authors
- `revision_log` - Revision log messages
- `changed` - Changed timestamps

When these fields are modified, they appear in the logs with masked values (e.g., `***MASKED***`) instead of actual values, providing an audit trail while protecting sensitive data.

Additionally, computed and internal fields are automatically excluded from logging as they are derived values.

To customize which fields are masked, modify the `$sensitive_fields` array in `_uceap_logging_get_entity_field_changes()`.

### Logger Channels

- **Request logging**: `myeap_request`
- **Entity logging**: `myeap_entity_crud`

To change these channel names, update the logger calls in:
- `src/EventSubscriber/RequestLoggerSubscriber.php` (line 39)
- `uceap_logging.module` (lines 22, 45, 63)
