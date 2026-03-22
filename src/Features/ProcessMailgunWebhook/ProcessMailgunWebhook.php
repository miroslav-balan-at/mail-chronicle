<?php
/**
 * Feature: Process Mailgun Webhook
 *
 * Primary real-time path for delivery status updates.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\ProcessMailgunWebhook;

use MailChronicle\Common\Constants;
use MailChronicle\Common\Entities\Email_Provider;
use MailChronicle\Common\Entities\Email_Status;

defined( 'ABSPATH' ) || exit;

/**
 * Process Mailgun Webhook Handler
 */
final class ProcessMailgunWebhook {

	/**
	 * Seconds within which a webhook timestamp is considered valid (replay-attack guard).
	 */
	const REPLAY_WINDOW = 900;

	private \wpdb $wpdb;

	// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Shaped array type for PHPStan.
	/** @var array{logs: string, events: string} */
	private array $tables;

	/**
	 * Constructor
	 */
	public function __construct() {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Declares type of WordPress $wpdb global.
		/** @var \wpdb $wpdb WordPress database instance. */
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->tables = [
			'logs'   => $wpdb->prefix . Constants::TABLE_LOGS,
			'events' => $wpdb->prefix . Constants::TABLE_EVENTS,
		];
	}

	public function handle( array $payload ): bool {
		if ( ! $this->verify_signature( $payload ) ) {
			return false;
		}

		$raw_event_data = $payload['event-data'] ?? [];
		$event_data     = is_array( $raw_event_data ) ? $raw_event_data : [];
		$event_type     = is_string( $event_data['event'] ?? null ) ? $event_data['event'] : '';
		$raw_message    = is_array( $event_data['message'] ?? null ) ? $event_data['message'] : [];
		$raw_headers    = is_array( $raw_message['headers'] ?? null ) ? $raw_message['headers'] : [];
		$raw_message_id = is_string( $raw_headers['message-id'] ?? null ) ? $raw_headers['message-id'] : '';
		$message_id     = trim( $raw_message_id, '<>' );

		if ( '' === $event_type || '' === $message_id ) {
			return false;
		}

		/**
		 * Fires before a Mailgun webhook payload is processed.
		 *
		 * @since 1.0.0
		 *
		 * @param string $event_type Mailgun event name (e.g. 'delivered', 'bounced').
		 * @param string $message_id Provider message ID from the webhook headers.
		 * @param array  $event_data Full event-data sub-array from the payload.
		 */
		do_action( 'mail_chronicle_before_webhook_processed', $event_type, $message_id, $event_data );

		$log_id = $this->find_or_create_log( $message_id, $event_data );

		if ( null === $log_id ) {
			return false;
		}

		$this->maybe_update_status( $log_id, $event_type );
		$this->save_event( $log_id, $event_type, $event_data );

		/**
		 * Fires after a Mailgun webhook payload has been processed successfully.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $log_id     The log entry ID that was created or updated.
		 * @param string $event_type Mailgun event name.
		 * @param array  $event_data Full event-data sub-array from the payload.
		 */
		do_action( 'mail_chronicle_after_webhook_processed', $log_id, $event_type, $event_data );

		return true;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private function find_or_create_log( string $message_id, array $event_data ): ?int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query is wrapped in $wpdb->prepare(); table name comes from $wpdb->prefix, not user input.
		// @phpstan-ignore-next-line
		$find_sql = $this->wpdb->prepare( "SELECT id FROM {$this->tables['logs']} WHERE provider_message_id = %s", $message_id );
		$id       = $this->wpdb->get_var( $find_sql );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( null !== $id && '' !== $id ) {
			return (int) $id;
		}

		$event_message = is_array( $event_data['message'] ?? null ) ? $event_data['message'] : [];
		$headers       = is_array( $event_message['headers'] ?? null ) ? $event_message['headers'] : [];
		$recipient     = is_string( $event_data['recipient'] ?? null ) ? $event_data['recipient'] : '';
		$subject       = is_string( $headers['subject'] ?? null ) ? $headers['subject'] : '';
		$raw_timestamp = $event_data['timestamp'] ?? null;
		$now           = current_time( 'mysql', true );
		$sent_at       = is_numeric( $raw_timestamp ) ? gmdate( 'Y-m-d H:i:s', (int) $raw_timestamp ) : $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $this->wpdb->insert(
			$this->tables['logs'],
			[
				'provider_message_id' => $message_id,
				'provider'            => Email_Provider::Mailgun->value,
				'recipient'           => $recipient,
				'subject'             => $subject,
				'message_html'        => '',
				'message_plain'       => '',
				'headers'             => wp_json_encode( $headers ),
				'status'              => Email_Status::Pending->value,
				'sent_at'             => $sent_at,
				'created_at'          => $now,
				'updated_at'          => $now,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return ( false !== $inserted ) ? (int) $this->wpdb->insert_id : null;
	}

	private function maybe_update_status( int $log_id, string $event_type ): void {
		$new_status = Email_Status::tryFrom( $this->map_event_to_status( $event_type ) );

		if ( null === $new_status ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query is wrapped in $wpdb->prepare(); table name comes from $wpdb->prefix, not user input.
		// @phpstan-ignore-next-line
		$status_sql    = $this->wpdb->prepare( "SELECT status FROM {$this->tables['logs']} WHERE id = %d", $log_id );
		$raw_value     = $this->wpdb->get_var( $status_sql );
		$current_value = is_string( $raw_value ) ? $raw_value : '';
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current_status = Email_Status::tryFrom( $current_value ) ?? Email_Status::Pending;

		if ( ! Email_Status::is_upgrade( $current_status, $new_status ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update(
			$this->tables['logs'],
			[
				'status'     => $new_status->value,
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $log_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	private function save_event( int $log_id, string $event_type, array $event_data ): void {
		$raw_occurred_ts = $event_data['timestamp'] ?? null;
		$occurred_at     = is_numeric( $raw_occurred_ts )
			? gmdate( 'Y-m-d H:i:s', (int) $raw_occurred_ts )
			: current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->insert(
			$this->tables['events'],
			[
				'email_log_id' => $log_id,
				'event_type'   => $event_type,
				'event_data'   => wp_json_encode( $event_data ),
				'occurred_at'  => $occurred_at,
				'created_at'   => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);
	}

	private function verify_signature( array $payload ): bool {
		$raw_settings = get_option( Constants::OPTION_SETTINGS, [] );
		$settings     = is_array( $raw_settings ) ? $raw_settings : [];
		$api_key      = isset( $settings['mailgun_api_key'] ) && is_string( $settings['mailgun_api_key'] ) ? $settings['mailgun_api_key'] : '';

		if ( '' === $api_key ) {
			return false;
		}

		$raw_signature = $payload['signature'] ?? [];
		$signature     = is_array( $raw_signature ) ? $raw_signature : [];
		$token         = isset( $signature['token'] ) && is_string( $signature['token'] ) ? $signature['token'] : '';
		$timestamp     = isset( $signature['timestamp'] ) && is_string( $signature['timestamp'] ) ? $signature['timestamp'] : '';
		$sig           = isset( $signature['signature'] ) && is_string( $signature['signature'] ) ? $signature['signature'] : '';

		if ( '' === $token || '' === $timestamp || '' === $sig ) {
			return false;
		}

		if ( abs( time() - (int) $timestamp ) > self::REPLAY_WINDOW ) {
			return false;
		}

		return hash_equals(
			hash_hmac( 'sha256', $timestamp . $token, $api_key ),
			$sig
		);
	}

	private function map_event_to_status( string $event ): string {
		$map = [
			'accepted'   => Email_Status::Pending->value,
			'delivered'  => Email_Status::Delivered->value,
			'opened'     => Email_Status::Opened->value,
			'clicked'    => Email_Status::Clicked->value,
			'failed'     => Email_Status::Failed->value,
			'bounced'    => Email_Status::Bounced->value,
			'complained' => Email_Status::Complained->value,
		];

		return $map[ $event ] ?? '';
	}
}
