<?php
/**
 * Feature: Purge Old Logs — Scheduler
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\PurgeOldLogs;

use MailChronicle\Features\ManageSettings\ManageSettings;
use MailChronicle\Features\ManageSettings\ManageSettingsInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Purge Scheduler
 */
final class PurgeScheduler {

	/**
	 * WordPress cron hook name.
	 */
	const CRON_HOOK = 'mail_chronicle_purge_old_logs';

	private PurgeOldLogs $handler;

	private ManageSettingsInterface $settings;

	public function __construct( PurgeOldLogs $handler, ManageSettingsInterface $settings ) {
		$this->handler  = $handler;
		$this->settings = $settings;
	}

	/**
	 * Register hooks (called once at plugin init).
	 */
	public function register_hooks(): void {
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
	}

	/**
	 * Schedule the daily cron event if not already scheduled.
	 * Call this on plugin activation.
	 */
	public static function schedule(): void {
		if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Remove the cron event.
	 * Call this on plugin deactivation.
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback — reads current retention setting and runs the purge.
	 */
	public function run(): void {
		$settings = $this->settings->get();
		$days     = is_numeric( $settings['log_retention_days'] ?? null ) ? (int) $settings['log_retention_days'] : ManageSettings::DEFAULT_RETENTION_DAYS;

		$this->handler->handle( $days );
	}
}
