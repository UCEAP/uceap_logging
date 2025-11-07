# UCEAP Logging Module

A reusable Drupal module that provides comprehensive logging for HTTP requests and entity CRUD operations with CloudWatch integration.

## Features

### 1. HTTP Request Logging
- Logs every HTTP request to the `uceap_request` channel
- Captures:
  - HTTP method and URI
  - User ID and username
  - Client IP address
  - User agent
  - Referer

### 2. Entity CRUD Logging
- Logs all content entity create, update, and delete operations to the `uceap_entity_crud` channel
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

1. Install dependencies using Composer:
    ```bash
    composer require phpnexus/cwh:^3.0
    composer require 'drupal/monolog:^3.0'
    ```
2. Install this module using Composer (add custom repository first):
    ```bash
    composer require uceap/uceap_logging:@dev
    ```
4. Enable the module:
    ```bash
    drush pm:enable uceap_logging -y
    ```

## Setup

Subclass the `CloudWatchClientFactory` to configure AWS credentials and log group/stream names as needed.

## Usage

Once enabled, the module automatically logs:
- All HTTP requests
- All content entity operations (create, update, delete)

### Viewing Logs

```bash
# View all request logs
drush watchdog:show --type=uceap_request

# View all entity CRUD logs
drush watchdog:show --type=uceap_entity_crud

# View recent logs
drush watchdog:show | grep -E "(uceap_request|uceap_entity_crud)"
```

## CloudWatch Integration

This module includes a `CloudWatchClientFactory` class that simplifies
integration with AWS CloudWatch Logs. You'll need to configure Monolog to use
your subclassed factory to send logs to CloudWatch.

### Example: Monolog Integration

Create or update `web/sites/default/monolog.services.yml`:

```yaml
services:
  monolog.handler.cloudwatch:
    class: PhpNexus\Cwh\Handler\CloudWatch
    factory: ['Drupal\my_module\Logger\CloudWatchClientFactory', 'createHandler']
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
  "channel": "uceap_entity_crud"
}
```

## Configuration

### Sensitive Field Masking

The module provides configurable masking of sensitive field values in entity change logs. When sensitive fields are modified, they appear in logs with masked values (e.g., `***MASKED***`) instead of actual values, providing an audit trail while protecting sensitive data.

#### Default Sensitive Fields

By default, the following field has its value masked:
- `pass` - User passwords

#### Configuring Sensitive Fields

You can customize which fields are masked through the administrative interface:

1. Navigate to **Configuration** > **Development** > **Logging and errors** (`/admin/config/development/logging`)
2. Click on the **UCEAP Logging** tab
3. Enter field machine names (one per line) in the "Sensitive Fields" textarea
4. Click "Save configuration"

#### Automatically Excluded Fields

In addition to user-configured sensitive fields, the following field types are automatically excluded from change tracking entirely:
- Computed fields (derived values)
- Internal fields (system-managed)
- Specific metadata fields: `changed`, `revision_timestamp`, `revision_uid`, `revision_log`

### Logger Channels

- **Request logging**: `uceap_request`
- **Entity logging**: `uceap_entity_crud`

To change these channel names, update the logger calls in:
- `src/EventSubscriber/RequestLoggerSubscriber.php` (line 39)
- `uceap_logging.module` (lines 22, 45, 63)
