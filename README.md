# Mail Chronicle

Professional email logging plugin for WordPress with multi-provider support, real-time event tracking, and a React-based admin interface.

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-blue.svg)](LICENSE)

## Features

- **Automatic Email Logging** — captures all outgoing WordPress emails via `wp_mail`
- **Multi-Provider Support** — Mailgun, WordPress default; SendGrid-ready via enum + one `case`
- **Event Tracking** — delivery, opens, clicks, bounces via Mailgun webhooks or background sync
- **React Admin UI** — filterable table, detail modal with event timeline, Sync Latest button
- **REST API** — full programmatic access with `manage_options` auth
- **Webhook Support** — HMAC-SHA256 verified real-time status updates from Mailgun
- **Background Sync** — cursor-based WP-Cron sync picks up where it left off
- **Translation Ready** — all strings pass through `__()` / `_e()`; status labels resolved at render time
- **Developer Hooks** — 12 actions and 4 filters for extension without modifying core files
- **Vertical Slice Architecture** — each feature is fully self-contained

## Requirements

- WordPress 6.0+
- PHP 8.1+
- Composer
- Node.js & npm (for asset builds only)

## Quick Start

```bash
composer install
npm install && npm run build
```

Then activate the plugin in WordPress admin.

## Configuration

### Basic Setup

1. **Mail Chronicle → Settings**
2. Set **Provider** to Mailgun (or leave as WordPress for logging only)
3. Configure log retention period

### Mailgun Integration

1. Enter your Mailgun API key and domain in Settings
2. Set the Mailgun webhook URL to:
   ```
   https://your-site.com/wp-json/mail-chronicle/v1/webhook/mailgun
   ```
3. Enable events you want to track in Mailgun (delivered, opened, clicked, bounced, etc.)
4. Optionally configure the background sync interval (default: every 10 minutes)

## REST API

Base path: `/wp-json/mail-chronicle/v1/`
All endpoints require the `manage_options` capability except the webhook endpoint (which uses HMAC-SHA256 verification).

### Email Logs

```http
GET /emails
GET /emails?per_page=20&page=1&status=delivered&provider=mailgun&search=example.com&date_from=2024-01-01&date_to=2024-12-31
```

```http
GET /emails/{id}
```

```http
GET /emails/{id}/events
```

```http
DELETE /emails/{id}
```

```http
DELETE /emails
```

### Settings

```http
GET /settings
```

```http
POST /settings
Content-Type: application/json

{
  "enabled": true,
  "provider": "mailgun",
  "mailgun_api_key": "key-...",
  "mailgun_domain": "mg.example.com",
  "mailgun_region": "EU",
  "log_retention_days": 120,
  "sync_interval": "mc_every_10_minutes",
  "sync_days": 7
}
```

### Sync

Provider-agnostic endpoint — reads the configured provider from settings.

```http
POST /sync
Content-Type: application/json

{}
```
Cursor-based sync: picks up from where the last sync ended.

```http
POST /sync
Content-Type: application/json

{ "days": 7 }
```
Force a full look-back of 7 days (resets cursor). Range: 1–30.

```http
POST /sync
Content-Type: application/json

{ "limit": 100 }
```
Override max events per page (default 300, range 1–300).

**Response:**
```json
{
  "success": true,
  "data": {
    "synced": 14,
    "updated": 3,
    "skipped": 0,
    "total": 17,
    "provider": "mailgun",
    "message": "Synced 14 new emails and updated 3 existing emails."
  }
}
```

### Mailgun Webhook

```http
POST /webhook/mailgun
```
Payload must be signed with your Mailgun Webhook Signing Key. Signature verification uses HMAC-SHA256 with a 15-minute replay-attack window.

## Hooks Reference

Mail Chronicle provides actions and filters so you can extend the plugin without modifying plugin files. All hooks use the `mc_` prefix.

### Actions

#### `mc_before_email_logged`

Fires just before a new email record is created.

```php
do_action( 'mc_before_email_logged' );
```

---

#### `mc_email_logging`

Fires immediately before the database `INSERT` in `LogEmail`.

```php
/**
 * @param array $data Row data about to be inserted.
 */
do_action( 'mc_email_logging', $data );
```

---

#### `mc_after_email_logged`

Fires after a new email log row has been inserted.

```php
/**
 * @param int   $log_id ID of the newly created log row.
 * @param array $data   Data that was inserted.
 */
do_action( 'mc_after_email_logged', $log_id, $data );
```

Example — send a Slack notification when an email is logged:
```php
add_action( 'mc_after_email_logged', function( int $log_id, array $data ) {
    // notify your monitoring system
}, 10, 2 );
```

---

#### `mc_email_status_updated`

Fires after the email status is set to `sent` or `failed` following the actual send attempt.

```php
/**
 * @param int    $log_id Log row ID.
 * @param string $status New status value ('sent' or 'failed').
 */
do_action( 'mc_email_status_updated', $log_id, $status );
```

---

#### `mc_before_email_deleted`

Fires before a single email log and its associated events are deleted.

```php
/**
 * @param int $id Log row ID about to be deleted.
 */
do_action( 'mc_before_email_deleted', $id );
```

---

#### `mc_after_email_deleted`

Fires after a single email log has been deleted.

```php
/**
 * @param int $id Log row ID that was deleted.
 */
do_action( 'mc_after_email_deleted', $id );
```

---

#### `mc_before_all_emails_deleted`

Fires before all email logs are truncated.

```php
do_action( 'mc_before_all_emails_deleted' );
```

---

#### `mc_after_all_emails_deleted`

Fires after all email logs have been truncated.

```php
do_action( 'mc_after_all_emails_deleted' );
```

---

#### `mc_after_settings_saved`

Fires after plugin settings have been saved successfully.

```php
/**
 * @param array $settings The settings that were saved.
 */
do_action( 'mc_after_settings_saved', $settings );
```

---

#### `mc_before_webhook_processed`

Fires before a Mailgun webhook payload is processed (after signature verification).

```php
/**
 * @param string $event_type Mailgun event name (e.g. 'delivered', 'bounced').
 * @param string $message_id Provider message ID from the webhook headers.
 * @param array  $event_data Full event-data sub-array from the payload.
 */
do_action( 'mc_before_webhook_processed', $event_type, $message_id, $event_data );
```

---

#### `mc_after_webhook_processed`

Fires after a Mailgun webhook payload has been processed successfully.

```php
/**
 * @param int    $log_id     The log entry ID that was created or updated.
 * @param string $event_type Mailgun event name.
 * @param array  $event_data Full event-data sub-array from the payload.
 */
do_action( 'mc_after_webhook_processed', $log_id, $event_type, $event_data );
```

---

#### `mc_before_sync`

Fires before a provider sync is triggered via the REST API.

```php
/**
 * @param Email_Provider $provider The provider that will be synced.
 * @param array          $args     Sync arguments (may include 'days', 'limit').
 */
do_action( 'mc_before_sync', $provider, $args );
```

---

#### `mc_after_sync`

Fires after a provider sync has completed successfully.

```php
/**
 * @param array          $result   Sync result: synced, updated, skipped, total counts.
 * @param Email_Provider $provider The provider that was synced.
 * @param array          $args     The sync arguments that were used.
 */
do_action( 'mc_after_sync', $result, $provider, $args );
```

---

### Filters

#### `mc_before_email_logged`

Filter the email data array before it is logged. Return an empty array to suppress logging for this email.

```php
/**
 * @param array $data Email data: to, subject, message, headers, attachments.
 * @return array Filtered data, or empty array to suppress logging.
 */
apply_filters( 'mc_before_email_logged', $data );
```

Examples:

```php
// Suppress logging for a specific address
add_filter( 'mc_before_email_logged', function( array $data ): array {
    if ( str_contains( $data['to'], 'no-log@example.com' ) ) {
        return [];
    }
    return $data;
} );

// Add custom metadata to the log row
add_filter( 'mc_before_email_logged', function( array $data ): array {
    $data['headers'][] = 'X-Site-ID: ' . get_current_blog_id();
    return $data;
} );
```

---

#### `mc_get_emails_args`

Filter the query arguments before emails are fetched from the database.

```php
/**
 * @param array $args Merged query arguments.
 *   Keys: per_page, page, orderby, order, status, provider, search, date_from, date_to
 * @return array Filtered arguments.
 */
apply_filters( 'mc_get_emails_args', $args );
```

Example:

```php
// Force a specific provider on a custom admin page
add_filter( 'mc_get_emails_args', function( array $args ): array {
    $args['provider'] = 'mailgun';
    return $args;
} );
```

---

#### `mc_get_emails`

Filter the email log results after they are fetched.

```php
/**
 * @param Email[] $emails Array of Email entity objects.
 * @param array   $args   The query arguments that produced these results.
 * @return Email[]
 */
apply_filters( 'mc_get_emails', $emails, $args );
```

---

#### `mc_before_settings_saved`

Filter the validated settings array before it is persisted to the options table.

```php
/**
 * @param array $settings Validated settings about to be saved.
 * @param array $data     Raw input data passed to update().
 * @return array Filtered settings.
 */
apply_filters( 'mc_before_settings_saved', $settings, $data );
```

Example:

```php
// Enforce a minimum log retention period
add_filter( 'mc_before_settings_saved', function( array $settings, array $data ): array {
    $settings['log_retention_days'] = max( 30, $settings['log_retention_days'] );
    return $settings;
}, 10, 2 );
```

---

## Adding a New Email Provider

1. Add a `case` to `src/Common/Entities/Email_Provider.php`.
2. Create `src/Features/SyncFrom<Provider>/SyncFrom<Provider>.php` with a `handle(array $args): array` method.
3. Add one `case` to `SyncController::dispatch()`:
   ```php
   Email_Provider::Sendgrid => ( new SyncFromSendgrid() )->handle( $args ),
   ```
4. (Optional) Create `src/Features/Process<Provider>Webhook/` if the provider supports webhooks.
5. Register the webhook controller in `ServiceProvider.php`.

The sync endpoint, settings UI, and admin interface adapt automatically — no other files need to change.

## Development

```bash
# Run tests
composer test

# Watch mode for assets
npm start

# Production build
npm run build

# Code standards check
composer phpcs

# Code standards fix
composer phpcbf
```

## Documentation

- [Installation Guide](INSTALLATION.md)
- [Architecture Overview](ARCHITECTURE.md)
- [Testing Guide](TESTING.md)
- [Changelog](CHANGELOG.md)

## License

GPL v2 or later — see [LICENSE](LICENSE)

## Author

**Miroslav Balan**
Website: [hygienemitsystem.at](https://hygienemitsystem.at)
