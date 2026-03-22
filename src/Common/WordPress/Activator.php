<?php
/**
 * Plugin Activator
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Common\WordPress;

use MailChronicle\Common\Constants;
use MailChronicle\Common\Database\Schema;
use MailChronicle\Common\Entities\Email_Provider;
use MailChronicle\Common\Entities\Mailgun_Region;
use MailChronicle\Features\ManageSettings\ManageSettings;
use MailChronicle\Features\PurgeOldLogs\PurgeScheduler;
use MailChronicle\Features\SyncFromMailgun\SyncScheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Activator Class
 */
final class Activator {

	/**
	 * Activate plugin
	 */
	public static function activate(): void {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Declares type of WordPress $wpdb global.
		/** @var \wpdb $wpdb WordPress database instance. */
		global $wpdb;

		// Create / update database tables.
		( new Schema( $wpdb ) )->create_tables();

		// Set default options (only when not already set).
		if ( false === get_option( Constants::OPTION_SETTINGS ) ) {
			add_option(
				Constants::OPTION_SETTINGS,
				[
					'enabled'            => true,
					'provider'           => Email_Provider::Mailgun->value,
					'mailgun_api_key'    => '',
					'mailgun_domain'     => '',
					'mailgun_region'     => Mailgun_Region::US->value,
					'log_retention_days' => ManageSettings::DEFAULT_RETENTION_DAYS,
					'sync_interval'      => SyncScheduler::DEFAULT_INTERVAL,
					'sync_days'          => ManageSettings::DEFAULT_SYNC_DAYS,
				]
			);
		}

		// Record plugin version.
		update_option( Constants::OPTION_PLUGIN_VER, MAIL_CHRONICLE_VERSION );

		// Schedule daily purge cron.
		PurgeScheduler::schedule();

		// Schedule auto-sync cron (reads saved interval; disabled by default on first run).
		$settings      = get_option( Constants::OPTION_SETTINGS, [] );
		$settings      = is_array( $settings ) ? $settings : [];
		$sync_interval = isset( $settings['sync_interval'] ) && is_string( $settings['sync_interval'] )
			? $settings['sync_interval']
			: 'disabled';
		SyncScheduler::reschedule( $sync_interval );

		// Flush rewrite rules for REST webhook endpoint.
		flush_rewrite_rules();
	}
}
