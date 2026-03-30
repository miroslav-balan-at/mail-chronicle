<?php
/**
 * Feature: Sync From Mailgun — Scheduler
 *
 * Registers custom WP-Cron intervals and manages the scheduled sync event.
 * Kept separate from SyncFromMailgun so the domain handler has no coupling
 * to WP-Cron internals.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\SyncFromMailgun;

use MailChronicle\Common\Entities\Email_Provider;
use MailChronicle\Features\ManageSettings\ManageSettingsInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Sync Scheduler
 */
final class SyncScheduler {

	/**
	 * WordPress cron hook name.
	 */
	const CRON_HOOK = 'mail_chronicle_auto_sync';

	private SyncFromMailgun $handler;

	private ManageSettingsInterface $settings;

	/**
	 * Default sync interval key.
	 */
	const DEFAULT_INTERVAL = 'mc_every_10_minutes';

	/**
	 * Returns all supported intervals (key => translated label).
	 *
	 * @return array<string, string>
	 */
	public static function intervals(): array {
		return [
			'disabled'            => __( 'Disabled', 'mail-chronicle' ),
			'mc_every_1_minute'   => __( 'Every minute', 'mail-chronicle' ),
			'mc_every_2_minutes'  => __( 'Every 2 minutes', 'mail-chronicle' ),
			'mc_every_3_minutes'  => __( 'Every 3 minutes', 'mail-chronicle' ),
			'mc_every_4_minutes'  => __( 'Every 4 minutes', 'mail-chronicle' ),
			'mc_every_5_minutes'  => __( 'Every 5 minutes', 'mail-chronicle' ),
			'mc_every_10_minutes' => __( 'Every 10 minutes', 'mail-chronicle' ),
			'mc_every_20_minutes' => __( 'Every 20 minutes', 'mail-chronicle' ),
			'mc_every_30_minutes' => __( 'Every 30 minutes', 'mail-chronicle' ),
			'hourly'              => __( 'Every hour', 'mail-chronicle' ),
			'twicedaily'          => __( 'Twice a day', 'mail-chronicle' ),
			'daily'               => __( 'Once a day', 'mail-chronicle' ),
		];
	}

	/**
	 * Custom interval definitions for the cron_schedules filter.
	 *
	 * @return array<string, array{interval: int, display: string}>
	 */
	public static function custom_schedules(): array {
		return [
			'mc_every_1_minute'   => [
				'interval' => 60,
				'display'  => __( 'Every minute', 'mail-chronicle' ),
			],
			'mc_every_2_minutes'  => [
				'interval' => 120,
				'display'  => __( 'Every 2 minutes', 'mail-chronicle' ),
			],
			'mc_every_3_minutes'  => [
				'interval' => 180,
				'display'  => __( 'Every 3 minutes', 'mail-chronicle' ),
			],
			'mc_every_4_minutes'  => [
				'interval' => 240,
				'display'  => __( 'Every 4 minutes', 'mail-chronicle' ),
			],
			'mc_every_5_minutes'  => [
				'interval' => 300,
				'display'  => __( 'Every 5 minutes', 'mail-chronicle' ),
			],
			'mc_every_10_minutes' => [
				'interval' => 600,
				'display'  => __( 'Every 10 minutes', 'mail-chronicle' ),
			],
			'mc_every_20_minutes' => [
				'interval' => 1200,
				'display'  => __( 'Every 20 minutes', 'mail-chronicle' ),
			],
			'mc_every_30_minutes' => [
				'interval' => 1800,
				'display'  => __( 'Every 30 minutes', 'mail-chronicle' ),
			],
		];
	}

	public function __construct( SyncFromMailgun $handler, ManageSettingsInterface $settings ) {
		$this->handler  = $handler;
		$this->settings = $settings;
	}

	/**
	 * Register hooks (called once at plugin init, before any cron event fires).
	 */
	public function register_hooks(): void {
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
		add_action( 'mail_chronicle_after_settings_saved', [ $this, 'on_settings_saved' ] );
	}

	/**
	 * React to settings changes — reschedule the cron event.
	 *
	 * @param array<string, mixed> $settings The saved settings.
	 */
	public function on_settings_saved( array $settings ): void {
		$interval = is_string( $settings['sync_interval'] ?? null ) ? $settings['sync_interval'] : 'disabled';
		self::reschedule( $interval );
	}

	/**
	 * Merge our custom intervals into the global WP cron schedule list.
	 *
	 * @return array<string, array{interval: int, display: string}>
	 */
	public function add_cron_schedules( array $schedules ): array {
		$merged = array_merge( $schedules, self::custom_schedules() );
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Narrows array_merge() return type for PHPStan.
		/** @var array<string, array{interval: int, display: string}> $merged */
		return $merged;
	}

	public static function reschedule( string $interval ): void {
		self::unschedule();

		if ( 'disabled' === $interval ) {
			return;
		}

		wp_schedule_event( time(), $interval, self::CRON_HOOK );
	}

	/**
	 * Remove the scheduled sync event.
	 * Call this on plugin deactivation.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Cron callback — reads current settings and runs the sync.
	 */
	public function run(): void {
		$settings = $this->settings->get();

		if ( Email_Provider::Mailgun->value !== ( $settings['provider'] ?? '' ) ) {
			return;
		}

		if ( ! isset( $settings['mailgun_api_key'] ) || '' === $settings['mailgun_api_key']
			|| ! isset( $settings['mailgun_domain'] ) || '' === $settings['mailgun_domain'] ) {
			return;
		}

		$this->handler->handle();
	}
}
