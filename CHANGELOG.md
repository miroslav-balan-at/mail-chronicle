# Changelog

All notable changes to Mail Chronicle will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Planned for 1.1.0 — SendGrid Integration

- **SendGrid webhook support** — verify SendGrid Event Webhook signatures (ECDSA or HTTP basic) and map events (`delivered`, `open`, `click`, `bounce`, `spamreport`, `unsubscribe`) to `Email_Status`
- **SendGrid Events API sync** — cursor-based background sync using the SendGrid Activity Feed API; same `POST /sync` endpoint, one new `case` in `SyncController::dispatch()`
- **Dedicated sync scheduler** — `SyncFromSendgrid` handler + optional separate cron interval per provider
- **Provider auto-detection** — detect WP Mail SMTP / FluentSMTP SendGrid mailer the same way Mailgun is currently detected
- **Settings UI** — SendGrid API key field, webhook signing key field, region not required

---

## [1.0.0] - 2026-03-19

### Added

**Core logging**
- Automatic capture of all outgoing WordPress emails via `wp_mail` filter (`PHP_INT_MAX` priority — runs last so the email is already composed)
- Provider auto-detection: checks WP Mail SMTP settings first, falls back to plugin setting
- Full HTML and plain-text body storage
- Attachment list stored as JSON

**Mailgun integration**
- Real-time status updates via Mailgun webhooks (`POST /webhook/mailgun`)
  - HMAC-SHA256 signature verification
  - 15-minute replay-attack window
  - Events: `delivered`, `opened`, `clicked`, `failed`, `bounced`, `complained`
- Background sync via Mailgun Events API (`SyncFromMailgun`)
  - Cursor-based pagination — picks up from stored position each cron run
  - "Trustworthy page" algorithm: skips pages younger than 5 minutes
  - Up to 10 pages × 300 events per cron run
  - Sync interval configurable: 1 min → daily, or disabled
- "Sync Latest" button in admin UI — triggers cursor-based sync on demand

**Status tracking**
- Eight statuses: `pending`, `sent`, `delivered`, `opened`, `clicked`, `failed`, `bounced`, `complained`
- Upgrade-only guard: `Email_Status::is_upgrade()` prevents out-of-order webhooks from downgrading terminal statuses
- Status priority order: Pending < Sent < Delivered < Opened < Clicked < Failed < Bounced < Complained

**Admin interface (React)**
- Filterable, sortable, paginated email logs table
- Filters: status, provider, date range, full-text search (recipient + subject)
- Email detail modal: HTML preview, plain text, headers, event timeline
- Settings page: provider, API key, domain, region, retention period, sync interval

**REST API** (`/wp-json/mail-chronicle/v1/`)
- `GET /emails` — paginated list with open_count aggregation
- `GET /emails/{id}` — single email
- `GET /emails/{id}/events` — event timeline
- `DELETE /emails/{id}` — delete one email and its events
- `DELETE /emails` — delete all emails (TRUNCATE)
- `GET /settings` — current settings
- `POST /settings` — save settings, reschedules cron automatically
- `POST /sync` — provider-agnostic sync trigger (reads configured provider from settings)
- `POST /webhook/mailgun` — Mailgun event receiver

**Developer hooks**
- 12 actions: `mail_chronicle_email_logging`, `mail_chronicle_after_email_logged`, `mail_chronicle_email_status_updated`, `mail_chronicle_before_email_deleted`, `mail_chronicle_after_email_deleted`, `mail_chronicle_before_all_emails_deleted`, `mail_chronicle_after_all_emails_deleted`, `mail_chronicle_after_settings_saved`, `mail_chronicle_before_webhook_processed`, `mail_chronicle_after_webhook_processed`, `mail_chronicle_before_sync`, `mail_chronicle_after_sync`
- 4 filters: `mail_chronicle_before_email_logged` (supports suppression by returning `[]`), `mail_chronicle_get_emails_args`, `mail_chronicle_get_emails`, `mail_chronicle_before_settings_saved`

**Database**
- Two tables: `mail_chronicle_logs`, `mail_chronicle_events`
- Auto-upgrade via `dbDelta` on every request when schema version changes
- Configurable log retention with daily cron purge (set to 0 to retain forever)

**Architecture**
- PHP 8.1 backed string enums for all domain values (`Email_Status`, `Email_Provider`, `Mailgun_Region`)
- Vertical Slice Architecture — each feature fully self-contained
- Repository pattern: `EmailRepositoryInterface` + `ProviderEventRepositoryInterface`; wpdb implementations isolated in `Common/Infrastructure/`
- `EmailQuery` value object encapsulates all list-query parameters and prevents SQL injection via orderby whitelist
- Constructor injection throughout; `ServiceProvider` is the single composition root
- Provider-agnostic sync endpoint: adding a new provider requires one new `case` in `SyncController`
- PSR-4 autoloading, Composer-managed dependencies
- PHPStan level 10, WP Coding Standards compliant (phpcs.xml)

**i18n**
- All strings pass through `__()` / `_e()` — no translated strings stored in DB
- Status labels resolved at render time via `Email_Status::label()`
- Server-translated labels passed to JS via `wp_localize_script`

### Technical

- Minimum WordPress: 6.0
- Minimum PHP: 8.1
- Database tables: `{prefix}mail_chronicle_logs`, `{prefix}mail_chronicle_events`
- REST API namespace: `mail-chronicle/v1`
- Text domain: `mail-chronicle`
- WP-Cron hooks: `mail_chronicle_auto_sync`, `mail_chronicle_purge_old_logs`

### Known Limitations

- Email body content is only available for emails sent via the WordPress `wp_mail` path. Rows created by webhook or sync (Mailgun Events API) have empty body fields — the Mailgun Events API does not return message content.
- Export not yet implemented.
- Email resend not yet implemented.
- Full event tracking (opened, clicked, etc.) requires Mailgun. WordPress-provider emails only log `sent` / `failed`.

---

## Support

- GitHub Issues: https://github.com/miroslav-balan-at/mail-chronicle/issues
- Email: miroslav@balan.at
