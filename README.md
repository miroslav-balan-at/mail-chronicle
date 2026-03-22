# Mail Chronicle

Professional email logging plugin for WordPress with multi-provider support, real-time event tracking, and a React-based admin interface.

[![Tests](https://github.com/miroslav-balan-at/mail-chronicle/actions/workflows/tests.yml/badge.svg)](https://github.com/miroslav-balan-at/mail-chronicle/actions/workflows/tests.yml)
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
- **Translation Ready** — all strings pass through `__()` / `_e()`
- **Developer Hooks** — 12 actions and 4 filters for extension without modifying core files

## Requirements

- WordPress 6.0+
- PHP 8.1+
- Composer
- Node.js & npm (for asset builds only)

## Installation

### From GitHub

```bash
cd wp-content/plugins
git clone https://github.com/miroslav-balan-at/mail-chronicle.git
cd mail-chronicle
composer install --no-dev
npm ci && npm run build
```

Activate the plugin in **Plugins → Installed Plugins**.

### From WordPress.org

Search for **Mail Chronicle** in **Plugins → Add New**.

## Configuration

1. Go to **Mail Chronicle → Settings**
2. Set **Provider** (Mailgun or WordPress)
3. Configure log retention period

### Mailgun Setup

1. Enter your API key and domain in Settings
2. Set the webhook URL in your Mailgun dashboard:
   ```
   https://your-site.com/wp-json/mail-chronicle/v1/webhook/mailgun
   ```
3. Enable the events you want to track (delivered, opened, clicked, bounced, etc.)
4. Optionally configure the background sync interval (default: every 10 minutes)

## REST API

Base path: `/wp-json/mail-chronicle/v1/`
All endpoints require `manage_options` except the webhook endpoint (HMAC-SHA256 verified).

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/emails` | Paginated list with filters |
| `GET` | `/emails/{id}` | Single email |
| `GET` | `/emails/{id}/events` | Event timeline |
| `DELETE` | `/emails/{id}` | Delete one email |
| `DELETE` | `/emails` | Delete all emails |
| `GET` | `/settings` | Current settings |
| `POST` | `/settings` | Save settings |
| `POST` | `/sync` | Trigger provider sync |
| `POST` | `/webhook/mailgun` | Mailgun event receiver |

### Filter Parameters (`GET /emails`)

| Parameter | Type | Description |
|-----------|------|-------------|
| `per_page` | int | Results per page (default: 20) |
| `page` | int | Page number |
| `status` | string | Filter by status |
| `provider` | string | Filter by provider |
| `search` | string | Search recipient/subject |
| `date_from` | string | Start date (Y-m-d) |
| `date_to` | string | End date (Y-m-d) |

## Developer Hooks

### Actions

| Hook | Description |
|------|-------------|
| `mail_chronicle_before_email_logged` | Fires before email record is created |
| `mail_chronicle_after_email_logged` | Fires after email row is inserted |
| `mail_chronicle_email_status_updated` | Fires after status is set to sent/failed |
| `mail_chronicle_before_email_deleted` | Fires before single email is deleted |
| `mail_chronicle_after_email_deleted` | Fires after single email is deleted |
| `mail_chronicle_before_all_emails_deleted` | Fires before all emails are truncated |
| `mail_chronicle_after_all_emails_deleted` | Fires after all emails are truncated |
| `mail_chronicle_after_settings_saved` | Fires after settings are saved |
| `mail_chronicle_before_webhook_processed` | Fires before webhook payload is processed |
| `mail_chronicle_after_webhook_processed` | Fires after webhook is processed |
| `mail_chronicle_before_sync` | Fires before provider sync |
| `mail_chronicle_after_sync` | Fires after provider sync |

### Filters

| Hook | Description |
|------|-------------|
| `mail_chronicle_before_email_logged` | Filter/suppress email data before logging |
| `mail_chronicle_get_emails_args` | Filter query arguments before DB fetch |
| `mail_chronicle_get_emails` | Filter results after DB fetch |
| `mail_chronicle_before_settings_saved` | Filter validated settings before saving |

**Example — suppress logging for a specific address:**

```php
add_filter( 'mail_chronicle_before_email_logged', function( array $data ): array {
    if ( str_contains( $data['to'], 'no-log@example.com' ) ) {
        return [];
    }
    return $data;
} );
```

## Adding a New Email Provider

1. Add a `case` to `src/Common/Entities/Email_Provider.php`
2. Create `src/Features/SyncFrom<Provider>/SyncFrom<Provider>.php` with a `handle(array $args): array` method
3. Add one `case` to `SyncController::dispatch()`
4. Optionally create `src/Features/Process<Provider>Webhook/` for webhook support

The sync endpoint, settings UI, and admin interface adapt automatically.

## Development

```bash
# Install dependencies
composer install
npm ci && npm run build

# Run all tests
composer test

# Static analysis (level 10)
composer phpstan

# Code standards
composer phpcs
composer phpcbf

# Watch assets
npm start
```

## Architecture

Vertical Slice Architecture — each feature is fully self-contained in `src/Features/<FeatureName>/`:

```
src/
├── Common/           # Shared entities, DB schema, constants
├── Features/
│   ├── LogEmail/
│   ├── GetEmails/
│   ├── DeleteEmail/
│   ├── ManageSettings/
│   ├── ProcessMailgunWebhook/
│   ├── SyncFromMailgun/
│   └── PurgeOldLogs/
├── Plugin.php
├── ServiceContainer.php
└── ServiceProvider.php
```

Database tables: `{prefix}mail_chronicle_logs`, `{prefix}mail_chronicle_events`

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GPL v2 or later — see [LICENSE](LICENSE).

## Author

**Miroslav Balan** · [hygienemitsystem.at](https://hygienemitsystem.at) · [miroslav@balan.at](mailto:miroslav@balan.at)
