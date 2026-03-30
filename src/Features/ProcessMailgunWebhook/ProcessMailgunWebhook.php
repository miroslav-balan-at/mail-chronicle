<?php
/**
 * Feature: Process Mailgun Webhook
 *
 * Primary real-time path for delivery status updates.
 * Delegates all persistence to EmailRepositoryInterface and
 * ProviderEventRepositoryInterface.  No SQL in this class.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\ProcessMailgunWebhook;

use MailChronicle\Common\Entities\Email;
use MailChronicle\Common\Entities\Email_Provider;
use MailChronicle\Common\Entities\Email_Status;
use MailChronicle\Common\Entities\ProviderEvent;
use MailChronicle\Common\Repository\EmailRepositoryInterface;
use MailChronicle\Common\Repository\ProviderEventRepositoryInterface;
use MailChronicle\Features\ManageSettings\ManageSettingsInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Process Mailgun Webhook Handler
 */
final class ProcessMailgunWebhook {

	/**
	 * Seconds within which a webhook timestamp is considered valid (replay-attack guard).
	 */
	const REPLAY_WINDOW = 900;

	private EmailRepositoryInterface $email_repository;

	private ProviderEventRepositoryInterface $event_repository;

	private ManageSettingsInterface $settings;

	/**
	 * Constructor
	 */
	public function __construct(
		EmailRepositoryInterface $email_repository,
		ProviderEventRepositoryInterface $event_repository,
		ManageSettingsInterface $settings
	) {
		$this->email_repository = $email_repository;
		$this->event_repository = $event_repository;
		$this->settings         = $settings;
	}

	public function handle( array $payload ): bool {
		if ( ! $this->verify_signature( $payload ) ) {
			return false;
		}

		$raw_event_data = $payload['event-data'] ?? [];
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Narrows is_array() check return type for PHPStan.
		/** @var array<string, mixed> $event_data */
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
		$this->event_repository->save( ProviderEvent::from_mailgun_event( $log_id, $event_data ) );

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
		$existing_id = $this->email_repository->find_id_by_provider_message_id( $message_id );

		if ( null !== $existing_id ) {
			return $existing_id;
		}

		$event_message = is_array( $event_data['message'] ?? null ) ? $event_data['message'] : [];
		$headers       = is_array( $event_message['headers'] ?? null ) ? $event_message['headers'] : [];
		$recipient     = is_string( $event_data['recipient'] ?? null ) ? $event_data['recipient'] : '';
		$subject       = is_string( $headers['subject'] ?? null ) ? $headers['subject'] : '';
		$raw_timestamp = $event_data['timestamp'] ?? null;
		$now           = current_time( 'mysql', true );
		$sent_at       = is_numeric( $raw_timestamp ) ? gmdate( 'Y-m-d H:i:s', (int) $raw_timestamp ) : $now;

		$email = new Email(
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
			]
		);

		$inserted_id = $this->email_repository->save( $email );

		return false !== $inserted_id ? $inserted_id : null;
	}

	private function maybe_update_status( int $log_id, string $event_type ): void {
		$new_status = Email_Status::from_mailgun_event( $event_type );

		if ( null === $new_status ) {
			return;
		}

		$current_value  = $this->email_repository->get_status( $log_id );
		$current_status = Email_Status::tryFrom( is_string( $current_value ) ? $current_value : '' ) ?? Email_Status::Pending;

		if ( ! Email_Status::is_upgrade( $current_status, $new_status ) ) {
			return;
		}

		$this->email_repository->update_status( $log_id, $new_status->value );
	}

	private function verify_signature( array $payload ): bool {
		$settings = $this->settings->get();
		$api_key  = is_string( $settings['mailgun_api_key'] ?? null ) ? $settings['mailgun_api_key'] : '';

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
}
