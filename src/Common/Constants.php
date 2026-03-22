<?php
/**
 * Common constants — infrastructure keys shared across features.
 *
 * Domain values (statuses, providers, regions) are enums:
 *
 *   @see \MailChronicle\Common\Entities\Email_Status
 *   @see \MailChronicle\Common\Entities\Email_Provider
 *   @see \MailChronicle\Common\Entities\Mailgun_Region
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Common;

defined( 'ABSPATH' ) || exit;

/**
 * Shared infrastructure constants.
 * Each class that owns a setting/table/cron key defines its own class constant.
 * This class holds only the handful of keys used by more than one feature.
 */
final class Constants {

	// ── WordPress option keys ──────────────────────────────────────────────

	/** Plugin settings option name. */
	const OPTION_SETTINGS = 'mail_chronicle_settings';

	/** Database schema version option name. */
	const OPTION_DB_VERSION = 'mail_chronicle_db_version';

	/** Plugin version option name (for upgrade tracking). */
	const OPTION_PLUGIN_VER = 'mail_chronicle_version';

	// ── Database table name suffixes (without $wpdb->prefix) ──────────────

	/** Email logs table suffix. */
	const TABLE_LOGS = 'mail_chronicle_logs';

	/** Provider events table suffix. */
	const TABLE_EVENTS = 'mail_chronicle_events';
}
