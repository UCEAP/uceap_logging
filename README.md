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
- Masks the values of configured query parameters (see [Sensitive Query Parameter Masking](#sensitive-query-parameter-masking)) in the logged URI, in the `extra.request_uri` that Monolog adds to every log record, and in any message's `@uri` placeholder

### 2. Entity CRUD Logging
- Logs all content entity create, update, and delete operations to the `uceap_entity_crud` channel
- For **create** operations:
  - Entity type, bundle, label, and ID
- For **update** operations:
  - Field-level change tracking with old and new values
  - Detects when entities are saved without changes
- For **delete** operations:
  - Entity type, bundle, label, and ID

### 3. Queue Item Logging
- Logs all queue operations to the `uceap_queue` channel:
  - **Item added**: Queue name, item data (serialized)
  - **Item claimed**: Queue name, item ID, lease time (if explicitly provided)
  - **Item released**: Queue name, item ID
  - **Item deleted**: Queue name, item ID
- Provides complete visibility into queue lifecycle
- Uses a transparent service decorator pattern that preserves all queue implementation behaviors (including different default lease times)

### 4. Structured Context for CloudWatch
All logs include queryable JSON metadata:
- `entity_type` - The entity type (user, node, etc.)
- `bundle` - The bundle/content type
- `entity_id` - The entity ID
- `operation` - One of: `create`, `update`, or `delete`
- `field_changes` - Map of changed fields with old/new values (updates only)
- `queue_name` - The queue name (queue logs only)
- `queue_data` - The serialized queue item data (for queue item additions)
- `item_id` - The queue item ID (for claim/release/delete operations)
- `lease_time` - The lease time in seconds (for claim operations, when explicitly provided)

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
- All queue item additions

### Viewing Logs

```bash
# View all request logs
drush watchdog:show --type=uceap_request

# View all entity CRUD logs
drush watchdog:show --type=uceap_entity_crud

# View all queue logs
drush watchdog:show --type=uceap_queue

# View recent logs
drush watchdog:show | grep -E "(uceap_request|uceap_entity_crud|uceap_queue)"
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

### Sensitive Query Parameter Masking

Some clients authenticate by passing credentials in the query string (for example, a payment postback sending `?operator=…&password=…`). The module masks the values of configured query parameters wherever the request URI is logged:

- the `@uri` placeholder in the `uceap_request` access log line, and
- the `extra.request_uri` field that Monolog's `request_uri` processor adds to every record written during the request (the module decorates `monolog.processor.request_uri`), and
- any message's `@uri` placeholder, such as core's "access denied" and "page not found" lines for a rejected request (the module decorates `monolog.processor.message_placeholder`).

Parameter names are matched case-insensitively. Only the value is replaced, so the rest of the URI stays useful for debugging:

```
GET /finance/transaction/payment?operator=cashnet&password=****MASKED****&command=post
```

#### Default Sensitive Query Parameters

By default, the following parameter has its value masked:
- `password`

#### Configuring Sensitive Query Parameters

1. Navigate to **Configuration** > **Development** > **Logging and errors** (`/admin/config/development/logging`)
2. Click on the **UCEAP Logging** tab
3. Enter query parameter names (one per line) in the "Sensitive Query Parameters" textarea
4. Click "Save configuration"

Or set `sensitive_query_parameters` in `uceap_logging.settings` configuration.

### Logger Channels

- **Request logging**: `uceap_request`
- **Entity logging**: `uceap_entity_crud`
- **Queue logging**: `uceap_queue`

To change these channel names, update the logger calls in:
- `src/EventSubscriber/RequestLoggerSubscriber.php` (line 39)
- `uceap_logging.module` (lines 22, 45, 63)
- `src/Queue/LoggingQueue.php` (createItem method)
