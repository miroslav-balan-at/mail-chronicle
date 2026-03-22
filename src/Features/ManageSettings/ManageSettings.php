<?php
/**
 * Feature: Manage Settings
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\ManageSettings;

use MailChronicle\Common\Constants;
use MailChronicle\Common\Entities\Email_Provider;
use MailChronicle\Common\Entities\Mailgun_Region;
use MailChronicle\Features\SyncFromMailgun\SyncScheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Manage Settings Handler
 */
final class ManageSettings {

	// ── Defaults ──────────────────────────────────────────────────────────

	/** Default log retention in days (4 months). */
	const DEFAULT_RETENTION_DAYS = 120;

	/** Default look-back window for the manual Sync button. */
	const DEFAULT_SYNC_DAYS = 7;

	// ── Public API ────────────────────────────────────────────────────────

	/**
	 * Get current settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$defaults = [
			'enabled'            => true,
			'provider'           => Email_Provider::Mailgun->value,
			'mailgun_api_key'    => '',
			'mailgun_domain'     => '',
			'mailgun_region'     => Mailgun_Region::US->value,
			'log_retention_days' => self::DEFAULT_RETENTION_DAYS,
			'sync_interval'      => SyncScheduler::DEFAULT_INTERVAL,
			'sync_days'          => self::DEFAULT_SYNC_DAYS,
		];

		$saved  = get_option( Constants::OPTION_SETTINGS, [] );
		$saved  = is_array( $saved ) ? $saved : [];
		$merged = wp_parse_args( $saved, $defaults );
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Narrows wp_parse_args() return type for PHPStan.
		/** @var array<string, mixed> $merged */
		return $merged;
	}

	/**
	 * Validate and persist settings, then reschedule cron.
	 *
	 * @return bool True when the option was updated.
	 */
	public function update( array $data ): bool {
		$valid_intervals = array_keys( SyncScheduler::intervals() );
		$sync_interval   = sanitize_text_field( is_string( $data['sync_interval'] ?? null ) ? $data['sync_interval'] : 'disabled' );

		$settings = [
			'enabled'            => isset( $data['enabled'] ) ? (bool) $data['enabled'] : false,
			'provider'           => sanitize_text_field( is_string( $data['provider'] ?? null ) ? $data['provider'] : Email_Provider::WordPress->value ),
			'mailgun_api_key'    => sanitize_text_field( is_string( $data['mailgun_api_key'] ?? null ) ? $data['mailgun_api_key'] : '' ),
			'mailgun_domain'     => sanitize_text_field( is_string( $data['mailgun_domain'] ?? null ) ? $data['mailgun_domain'] : '' ),
			'mailgun_region'     => sanitize_text_field( is_string( $data['mailgun_region'] ?? null ) ? $data['mailgun_region'] : Mailgun_Region::US->value ),
			'log_retention_days' => absint( is_numeric( $data['log_retention_days'] ?? null ) ? $data['log_retention_days'] : self::DEFAULT_RETENTION_DAYS ),
			'sync_interval'      => in_array( $sync_interval, $valid_intervals, true ) ? $sync_interval : 'disabled',
			'sync_days'          => absint( is_numeric( $data['sync_days'] ?? null ) ? $data['sync_days'] : self::DEFAULT_SYNC_DAYS ),
		];

		/**
		 * Filters the validated settings array before it is persisted.
		 *
		 * Allows third-party code to add, remove, or transform settings values
		 * before they are written to the WordPress options table.
		 *
		 * @since 1.0.0
		 *
		 * @param array $settings Validated settings about to be saved.
		 * @param array $data     Raw input data passed to update().
		 */
		$filtered_settings = apply_filters( 'mail_chronicle_before_settings_saved', $settings, $data );
		$settings          = is_array( $filtered_settings ) ? $filtered_settings : $settings;

		$result = update_option( Constants::OPTION_SETTINGS, $settings );

		if ( $result ) {
			/**
			 * Fires after plugin settings have been saved successfully.
			 *
			 * @since 1.0.0
			 *
			 * @param array $settings The settings that were saved.
			 */
			do_action( 'mail_chronicle_after_settings_saved', $settings );
		}

		$reschedule_interval = is_string( $settings['sync_interval'] ?? null ) ? $settings['sync_interval'] : 'disabled';
		SyncScheduler::reschedule( $reschedule_interval );

		return $result;
	}
}
