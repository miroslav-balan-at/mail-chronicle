# Mail Chronicle — Architecture

> Developer reference. Everything here is derived from the actual source code.

---

## Directory Structure

```
mail-chronicle/
├── mail-chronicle.php           # Plugin header, defines, bootstrap
├── uninstall.php                # Cleanup on uninstall
├── composer.json                # PHP deps; PSR-4 autoload (MailChronicle\ → src/)
├── package.json                 # JS build (@wordpress/scripts)
├── webpack.config.js
│
├── src/
│   ├── Plugin.php               # Singleton bootstrap; wires admin, REST API, assets
│   ├── ServiceContainer.php     # Minimal singleton DI container
│   ├── ServiceProvider.php      # Registers all feature services into the container
│   │
│   ├── Common/
│   │   ├── Constants.php        # Infrastructure keys: TABLE_LOGS, TABLE_EVENTS,
│   │   │                        #   OPTION_SETTINGS, OPTION_DB_VERSION, OPTION_PLUGIN_VER
│   │   ├── Database/
│   │   │   └── Schema.php       # CREATE TABLE via dbDelta; needs_update() / create_tables()
│   │   ├── Entities/
│   │   │   ├── Email_Status.php   # Backed string enum (PHP 8.1)
│   │   │   ├── Email_Provider.php # Backed string enum
│   │   │   ├── Mailgun_Region.php # Backed string enum
│   │   │   ├── Email.php          # DTO — maps a DB log row, typed getters
│   │   │   └── ProviderEvent.php  # DTO — maps a DB event row
│   │   └── WordPress/
│   │       ├── Activator.php    # activate(): run Schema, seed default options, schedule crons
│   │       ├── Deactivator.php  # deactivate(): unschedule all crons
│   │       └── HooksLoader.php  # Collects add_action/add_filter pairs, runs them all via run()
│   │
│   └── Features/
│       ├── LogEmail/
│       │   └── LogEmail.php           # wp_mail filter (PHP_INT_MAX priority) + phpmailer_init
│       ├── GetEmails/
│       │   ├── GetEmails.php          # Query: list (filtered/paginated) + get_by_id + get_events
│       │   ├── EmailLogsPage.php      # Admin page registration
│       │   └── EmailLogsController.php # REST: GET /emails, /emails/{id}, /emails/{id}/events, DELETE
│       ├── DeleteEmail/
│       │   └── DeleteEmail.php        # handle(id): delete one; delete_all(): TRUNCATE both tables
│       ├── ManageSettings/
│       │   ├── ManageSettings.php     # get() + update() with validation; class constants for defaults
│       │   ├── SettingsPage.php       # PHP-rendered admin settings form
│       │   └── SettingsController.php # REST: GET /settings, POST /settings
│       ├── ProcessMailgunWebhook/
│       │   ├── ProcessMailgunWebhook.php # verify_signature → find_or_create_log → maybe_update_status → save_event
│       │   └── WebhookController.php     # REST: POST /webhook/mailgun
│       ├── Sync/
│       │   └── SyncController.php     # REST: POST /sync — reads provider from settings, dispatches
│       ├── SyncFromMailgun/
│       │   ├── SyncFromMailgun.php    # Cursor-based Mailgun Events API sync handler
│       │   └── SyncScheduler.php      # WP-Cron: custom intervals, reschedule(), cron callback
│       └── PurgeOldLogs/
│           ├── PurgeOldLogs.php       # handle(days): DELETE logs + events older than cutoff
│           └── PurgeScheduler.php     # WP-Cron: daily purge callback
│
├── assets/src/admin/email-logs/
│   ├── EmailLogsApp.jsx          # Root React component, fetches emails, handles sync
│   └── components/
│       ├── EmailLogsTable.jsx    # Table, pagination, "Sync Latest" button
│       ├── EmailFilters.jsx      # Status / provider / date / search filters
│       ├── EmailDetailModal.jsx  # Full email body + event timeline
│       └── EmptyState.jsx
│
└── tests/
    ├── bootstrap.php
    ├── wordpress-mocks.php
    ├── TestCase.php
    ├── Unit/Features/            # Unit tests per feature (Mockery for wpdb)
    └── Integration/              # Integration: EmailLogsControllerTest (real wpdb)
```

---

## Bootstrap Flow

```
mail-chronicle.php
  └─ defines: MAIL_CHRONICLE_FILE, _PATH, _URL, _VERSION, _BASENAME
  └─ Plugin::instance()
       ├─ ServiceContainer::instance()
       ├─ ServiceProvider::register()        ← registers all feature factories
       ├─ register_hooks()
       │    ├─ register_activation_hook  → Activator::activate()
       │    ├─ register_deactivation_hook → Deactivator::deactivate()
       │    ├─ LogEmail::register_hooks() → wp_mail filter + phpmailer_init
       │    ├─ PurgeScheduler::register_hooks() → cron callback
       │    └─ SyncScheduler::register_hooks() → cron_schedules filter + cron callback
       └─ init()
            ├─ plugins_loaded → load_textdomain()
            ├─ plugins_loaded → maybe_upgrade_schema()   ← auto-runs dbDelta if needed
            ├─ is_admin → init_admin()
            │    ├─ admin_menu → SettingsPage::add_menu_page()
            │    ├─ admin_menu → EmailLogsPage::add_menu_page()
            │    └─ admin_enqueue_scripts → enqueue_admin_assets()
            └─ rest_api_init → init_rest_api()
                 ├─ EmailLogsController::register_routes()
                 ├─ WebhookController::register_routes()
                 ├─ SyncController::register_routes()
                 └─ SettingsController::register_routes()
```

---

## Database

Two tables (prefix: `{wpdb->prefix}mail_chronicle_`):

### `mail_chronicle_logs`

| Column | Type | Notes |
|--------|------|-------|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | PK |
| `provider_message_id` | `VARCHAR(255)` | Mailgun message-id header (stripped of `<>`) |
| `provider` | `VARCHAR(50)` | `Email_Provider->value` |
| `recipient` | `VARCHAR(255)` | |
| `subject` | `VARCHAR(500)` | |
| `message_html` | `LONGTEXT` | Full HTML body (empty for sync-inserted rows) |
| `message_plain` | `LONGTEXT` | Plain-text body |
| `headers` | `TEXT` | Raw headers string or JSON |
| `attachments` | `TEXT` | JSON-encoded attachment paths |
| `status` | `VARCHAR(50)` | `Email_Status->value` |
| `sent_at` | `DATETIME` | UTC |
| `created_at` | `DATETIME` | UTC |
| `updated_at` | `DATETIME` | UTC |

### `mail_chronicle_events`

| Column | Type | Notes |
|--------|------|-------|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | PK |
| `email_log_id` | `BIGINT UNSIGNED` | FK → logs.id |
| `event_type` | `VARCHAR(100)` | Mailgun event name |
| `event_data` | `LONGTEXT` | Full JSON payload |
| `occurred_at` | `DATETIME` | From webhook/sync timestamp |
| `created_at` | `DATETIME` | UTC |

Schema version is stored in `OPTION_DB_VERSION`. `Plugin::maybe_upgrade_schema()` runs `dbDelta` automatically on every request when the stored version is behind.

---

## Domain Enums (PHP 8.1)

All domain values use **backed string enums**. Raw slugs go in the DB; translated labels are generated on demand at render time.

### `Email_Status`

```php
enum Email_Status: string {
    case Pending   = 'pending';
    case Sent      = 'sent';
    case Delivered = 'delivered';
    case Opened    = 'opened';
    case Clicked   = 'clicked';
    case Failed    = 'failed';
    case Bounced   = 'bounced';
    case Complained = 'complained';
}
```

Key methods:
- `->priority(): int` — numeric rank for upgrade comparison
- `::is_upgrade(self $current, self $new): bool` — returns `true` only when `$new->priority() >= $current->priority()`. Prevents out-of-order webhooks from downgrading a terminal status.
- `->label(): string` — `__('Delivered', 'mail-chronicle')` etc.

**Status upgrade matrix** (higher priority wins):

| Status | Priority |
|--------|----------|
| Pending | 0 |
| Sent | 1 |
| Delivered | 2 |
| Opened | 3 |
| Clicked | 4 |
| Failed | 5 |
| Bounced | 6 |
| Complained | 7 |

### `Email_Provider`

```php
enum Email_Provider: string {
    case WordPress = 'wordpress';
    case Mailgun   = 'mailgun';
    case Sendgrid  = 'sendgrid';
}
```

Methods: `->label(): string`, `->api_base(Mailgun_Region): ?string`

### `Mailgun_Region`

```php
enum Mailgun_Region: string {
    case US = 'US';
    case EU = 'EU';
}
```

Methods: `->api_base(): string` (`https://api.mailgun.net` / `https://api.eu.mailgun.net`), `->label(): string`

---

## Email Logging Flow (`LogEmail`)

```
wp_mail($args)  ← filter at PHP_INT_MAX priority
  │
  ├─ check settings['enabled']
  ├─ detect_provider()  ← checks WPMS_PLUGIN_VER first, then settings
  ├─ sanitize fields, build $email_data
  ├─ apply_filters('mc_before_email_logged', $email_data, $args)
  │     └─ return [] to suppress logging
  ├─ do_action('mc_email_logging', $email_data)
  ├─ wpdb->insert() → $last_email_id
  └─ do_action('mc_after_email_logged', $log_id, $email_data)

phpmailer_init  ← fires before PHPMailer sends
  └─ registers two one-shot actions:
       ├─ wp_mail_succeeded → update_status(id, 'sent', $message_id)
       │    └─ do_action('mc_email_status_updated', $id, 'sent', $message_id)
       └─ wp_mail_failed    → update_status(id, 'failed')
            └─ do_action('mc_email_status_updated', $id, 'failed', null)
```

**Important**: `LogEmail` stores full HTML and plain-text bodies. When `SyncFromMailgun` inserts rows from the Events API, bodies are intentionally left empty (Mailgun's Events API does not return message bodies). Bodies are only available via the `LogEmail` path.

---

## Mailgun Sync Flow (`SyncFromMailgun`)

Uses Mailgun's **cursor-based Events API** with the "trustworthy page" algorithm.

```
handle(args)
  ├─ no 'days' arg → get_cursor_url()   ← stored cursor or 1h look-back (first run)
  ├─ 'days' arg    → build_initial_url() ← forced look-back, resets cursor
  │
  └─ loop (max MAX_PAGES = 10 per cron run):
       ├─ fetch_page(url, auth)
       ├─ empty events → save cursor, stop
       ├─ newest event age < TRUST_AGE_SECONDS (300s = 5 min) → stop, retry same URL next run
       ├─ process_events(events)
       │    ├─ batch fetch existing IDs: 1 SELECT for all message-ids on the page
       │    ├─ existing → update_status() if Email_Status::is_upgrade() allows it
       │    └─ new      → insert_from_event() (no body content)
       └─ update_option(CURSOR_OPTION, next_url)
```

Constants:
- `CURSOR_OPTION = 'mail_chronicle_sync_cursor'` — WP option storing the `paging.next` URL
- `TRUST_AGE_SECONDS = 300` — pages newer than this are skipped (avoids incomplete data)
- `PAGE_LIMIT = 300` — events per page
- `MAX_PAGES = 10` — max pages per cron run (prevents PHP timeouts)
- `HTTP_TIMEOUT = 20` — seconds for `wp_remote_get()`

**Mailgun event → `Email_Status` map:**

| Mailgun event | Status |
|---------------|--------|
| `accepted` | `Pending` |
| `delivered` | `Delivered` |
| `failed` | `Failed` |
| `rejected` | `Failed` |
| `bounced` | `Bounced` |
| `complained` | `Complained` |
| `opened` | `Opened` |
| `clicked` | `Clicked` |

Call `SyncFromMailgun::reset_cursor()` after deleting all logs to avoid the next cron run referencing stale cursors.

---

## Webhook Flow (`ProcessMailgunWebhook`)

```
POST /webhook/mailgun
  │
  WebhookController::handle_request()
  └─ ProcessMailgunWebhook::handle($payload)
       ├─ verify_signature()
       │    ├─ read api_key from settings
       │    ├─ check timestamp within REPLAY_WINDOW (900s = 15 min)
       │    └─ hash_equals(hmac_sha256(timestamp+token, api_key), signature)
       ├─ extract event_type, message_id
       ├─ do_action('mc_before_webhook_processed', $event_type, $message_id, $event_data)
       ├─ find_or_create_log($message_id)
       │    ├─ SELECT by provider_message_id
       │    └─ not found → INSERT minimal placeholder row (status=Pending, no body)
       ├─ maybe_update_status($log_id, $event_type)
       │    └─ only updates if Email_Status::is_upgrade() allows it
       ├─ save_event($log_id, $event_type, $event_data)  ← always saved for audit trail
       └─ do_action('mc_after_webhook_processed', $log_id, $event_type, $event_data)
```

`REPLAY_WINDOW = 900` is a class constant on `ProcessMailgunWebhook`.

---

## Cron Jobs

| WP hook | Handler | Default | Configured by |
|---------|---------|---------|---------------|
| `mail_chronicle_auto_sync` | `SyncScheduler::run()` | every 10 min | Settings → Sync interval |
| `mail_chronicle_purge_old_logs` | `PurgeScheduler::run()` | daily | hardcoded |

**Sync intervals** available: disabled, 1 min, 2 min, 3 min, 4 min, 5 min, 10 min, 20 min, 30 min, hourly, twice daily, daily.
Custom intervals (`mc_every_*`) are registered via the `cron_schedules` filter.

`SyncScheduler::reschedule($interval)` is called every time settings are saved — it clears the old event and schedules a new one.

`PurgeOldLogs::handle($days)` deletes events first (JOIN-based DELETE), then log rows. Setting `log_retention_days = 0` disables purging entirely.

---

## REST API

Base: `/wp-json/mail-chronicle/v1/`

| Method | Path | Auth | Handler |
|--------|------|------|---------|
| `GET` | `/emails` | `manage_options` | `EmailLogsController` |
| `GET` | `/emails/{id}` | `manage_options` | `EmailLogsController` |
| `GET` | `/emails/{id}/events` | `manage_options` | `EmailLogsController` |
| `DELETE` | `/emails/{id}` | `manage_options` | `EmailLogsController` → `DeleteEmail::handle()` |
| `DELETE` | `/emails` | `manage_options` | `EmailLogsController` → `DeleteEmail::delete_all()` |
| `GET` | `/settings` | `manage_options` | `SettingsController` |
| `POST` | `/settings` | `manage_options` | `SettingsController` → `ManageSettings::update()` |
| `POST` | `/sync` | `manage_options` | `SyncController` → `SyncFromMailgun::handle()` |
| `POST` | `/webhook/mailgun` | HMAC-SHA256 | `WebhookController` → `ProcessMailgunWebhook::handle()` |

### `/emails` query parameters

`per_page` (default 20), `page`, `orderby` (default `sent_at`), `order` (default `DESC`), `status`, `provider`, `search` (recipient or subject LIKE), `date_from`, `date_to`.

Results include a computed `open_count` column (LEFT JOIN COUNT on events table).

### `/sync` body parameters

| Parameter | Type | Range | Effect |
|-----------|------|-------|--------|
| *(none)* | — | — | Cursor-based, picks up from stored position |
| `days` | integer | 1–30 | Force full look-back, resets cursor |
| `limit` | integer | 1–300 | Events per page (default 300) |

---

## Frontend (React)

Built with `@wordpress/scripts`. Entry point: `assets/src/index.js` → `EmailLogsApp`.

Data passed from PHP to JS via `wp_localize_script('mail-chronicle-admin', 'mailChronicle', {...})`:

```js
window.mailChronicle = {
  apiUrl:       'https://site.com/wp-json/mail-chronicle/v1',
  nonce:        '...',           // wp_rest nonce
  settings:     { ... },        // current plugin settings
  syncDays:     7,               // default look-back for manual sync (not used by Sync Latest)
  statusLabels: {                // server-translated labels
    pending:    'Ausstehend',
    delivered:  'Zugestellt',
    // ...
  },
  i18n: {
    emailLogs: 'Email Logs',
    settings:  'Einstellungen',
  }
}
```

"**Sync Latest**" button calls `POST /sync` with no body — cursor-based, no forced look-back.

---

## Hooks Reference

### Actions

| Hook | When | Parameters |
|------|------|------------|
| `mc_before_email_logged` | Before DB INSERT in LogEmail (as action; paired filter below) | — |
| `mc_email_logging` | Immediately before DB INSERT | `$email_data` |
| `mc_after_email_logged` | After successful INSERT | `$log_id`, `$email_data` |
| `mc_email_status_updated` | After sent/failed status update | `$id`, `$status`, `$message_id` |
| `mc_before_email_deleted` | Before single delete | `$id` |
| `mc_after_email_deleted` | After single delete | `$id` |
| `mc_before_all_emails_deleted` | Before TRUNCATE | — |
| `mc_after_all_emails_deleted` | After TRUNCATE | — |
| `mc_after_settings_saved` | After `update_option()` succeeds | `$settings` |
| `mc_before_webhook_processed` | After signature verified, before processing | `$event_type`, `$message_id`, `$event_data` |
| `mc_after_webhook_processed` | After webhook fully processed | `$log_id`, `$event_type`, `$event_data` |
| `mc_before_sync` | Before provider dispatch in SyncController | `$provider` (Email_Provider), `$args` |
| `mc_after_sync` | After successful sync | `$result`, `$provider`, `$args` |

### Filters

| Hook | Purpose | Parameters | Return |
|------|---------|------------|--------|
| `mc_before_email_logged` | Modify or suppress log data | `$email_data`, `$args` | `array` (empty = suppress) |
| `mc_get_emails_args` | Modify query args before SELECT | `$args` | `array` |
| `mc_get_emails` | Filter result set after SELECT | `$emails` (Email[]), `$args` | `Email[]` |
| `mc_before_settings_saved` | Modify settings before `update_option()` | `$settings`, `$data` | `array` |

---

## Adding a New Provider

1. Add a `case` to `Email_Provider` enum.
2. Create `src/Features/SyncFrom<Provider>/SyncFrom<Provider>.php` — implement `handle(array $args): array` returning `['success' => bool, 'synced' => int, 'updated' => int, 'skipped' => int, 'total' => int]`.
3. Add one `case` to `SyncController::dispatch()`:
   ```php
   Email_Provider::Sendgrid => ( new SyncFromSendgrid() )->handle( $args ),
   ```
4. (Optional) Create `src/Features/Process<Provider>Webhook/` — add controller to `ServiceProvider` and call `register_routes()` in `Plugin::init_rest_api()`.

The sync endpoint, settings UI (provider `<select>` iterates `Email_Provider::cases()`), and admin interface require no other changes.

---

## Key Design Rules

- **PHP 8.1+ enums** for all domain values. `->value` for DB storage. `->label()` for i18n at render time. Never store translated strings.
- **`define()`** only for infrastructure constants that must exist before autoload (`MAIL_CHRONICLE_*`).
- **Class constants** for per-class magic values (`REPLAY_WINDOW`, `TRUST_AGE_SECONDS`, `PAGE_LIMIT`, etc.).
- **`Constants.php`** for keys shared across ≥2 features (table names, option names).
- **Inline DB access** — no shared repository classes. Each feature queries `$wpdb` directly.
- **Status upgrade guard** — always pass through `Email_Status::is_upgrade()` before updating status. This correctly handles both out-of-order webhooks and the sync path.
- **No body content via sync** — `SyncFromMailgun` and `ProcessMailgunWebhook` both insert/update rows without body text. Only `LogEmail` (the `wp_mail` hook) captures full bodies.
- **Cursor reset** — call `SyncFromMailgun::reset_cursor()` whenever all logs are deleted.
