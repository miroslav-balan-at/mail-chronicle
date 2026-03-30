<?php
/**
 * Feature: Log Email
 *
 * Hooks into wp_mail to capture every outgoing email and persist it to the
 * database.  PHPMailer callbacks then update the status to Sent or Failed.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\LogEmail;

use MailChronicle\Common\Constants;
use MailChronicle\Common\Entities\Email;
use MailChronicle\Common\Entities\Email_Provider;
use MailChronicle\Common\Entities\Email_Status;
use MailChronicle\Common\Repository\EmailRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Log Email Handler
 */
final class LogEmail {

	private ?int $last_email_id = null;

	private EmailRepositoryInterface $email_repository;

	/**
	 * Constructor
	 */
	public function __construct( EmailRepositoryInterface $email_repository ) {
		$this->email_repository = $email_repository;
	}

	/**
	 * Register WordPress hooks
	 */
	public function register_hooks(): void {
		add_filter( 'wp_mail', [ $this, 'handle' ], PHP_INT_MAX );
		add_action( 'phpmailer_init', [ $this, 'capture_provider_id' ] );
	}

	public function handle( array $args ): array {
		$settings = get_option( Constants::OPTION_SETTINGS, [] );
		$settings = is_array( $settings ) ? $settings : [];
		if ( ! isset( $settings['enabled'] ) || true !== $settings['enabled'] ) {
			return $args;
		}

		$raw_to        = $args['to'] ?? '';
		$to_value      = is_array( $raw_to ) ? ( is_string( $raw_to[0] ?? null ) ? $raw_to[0] : '' ) : ( is_string( $raw_to ) ? $raw_to : '' );
		$raw_message   = $args['message'] ?? '';
		$message_html  = is_string( $raw_message ) ? $raw_message : '';
		$message_plain = wp_strip_all_tags( $message_html );

		if ( false === stripos( $message_html, '</' ) ) {
			$message_html = nl2br( esc_html( $message_html ) );
		}

		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- PHPStan type-narrowing annotation for array_filter result.
		/** @var string[] $raw_headers */
		$raw_headers = is_array( $args['headers'] ) ? array_filter( $args['headers'], 'is_string' ) : [];
		$headers     = [] !== $raw_headers ? implode( "\n", $raw_headers ) : ( is_string( $args['headers'] ) ? $args['headers'] : '' );
		$attachments = ( isset( $args['attachments'] ) && [] !== $args['attachments'] && false !== $args['attachments'] ) ? wp_json_encode( $args['attachments'] ) : '';

		$sender = $this->extract_sender( $headers );

		$raw_subject = $args['subject'] ?? '';
		$email_data  = [
			'provider'      => $this->detect_provider(),
			'sender'        => $sender,
			'recipient'     => sanitize_email( $to_value ),
			'subject'       => sanitize_text_field( is_string( $raw_subject ) ? $raw_subject : '' ),
			'message_html'  => $message_html,
			'message_plain' => $message_plain,
			'headers'       => $headers,
			'attachments'   => $attachments,
			'status'        => Email_Status::Pending->value,
			'sent_at'       => current_time( 'mysql' ),
		];

		/**
		 * Filters the email data before it is written to the log.
		 *
		 * Allows third-party code to modify or enrich the log entry — for example
		 * to add custom metadata, change the detected provider, or suppress logging
		 * by returning an empty array.
		 *
		 * @since 1.0.0
		 *
		 * @param array $email_data Sanitized email data about to be persisted.
		 * @param array $args       Original wp_mail() arguments.
		 */
		$email_data = apply_filters( 'mail_chronicle_before_email_logged', $email_data, $args );
		$email_data = is_array( $email_data ) ? $email_data : [];

		if ( [] === $email_data ) {
			return $args;
		}

		/**
		 * Fires immediately before an email log entry is saved to the database.
		 *
		 * @since 1.0.0
		 *
		 * @param array $email_data Email data about to be persisted.
		 */
		do_action( 'mail_chronicle_email_logging', $email_data );

		$inserted_id = $this->email_repository->save( new Email( $email_data ) );
		if ( false !== $inserted_id ) {
			$this->last_email_id = $inserted_id;
		}

		if ( null !== $this->last_email_id ) {
			/**
			 * Fires after an email has been successfully logged to the database.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $email_id   ID of the newly created log entry.
			 * @param array $email_data The data that was persisted.
			 */
			do_action( 'mail_chronicle_after_email_logged', $this->last_email_id, $email_data );
		}

		return $args;
	}

	public function capture_provider_id( \PHPMailer\PHPMailer\PHPMailer $phpmailer ): void {
		if ( null === $this->last_email_id ) {
			return;
		}

		$email_id = $this->last_email_id;

		add_action(
			'wp_mail_succeeded',
			function () use ( $phpmailer, $email_id ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer uses PascalCase property names.
				if ( '' !== $phpmailer->MessageID ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer uses PascalCase property names.
					$message_id = trim( $phpmailer->MessageID, '<>' );
					$this->email_repository->update_status( $email_id, Email_Status::Sent->value, $message_id );
				} else {
					$this->email_repository->update_status( $email_id, Email_Status::Sent->value );
				}

				/**
				 * Fires after an email log's send status has been updated.
				 *
				 * @since 1.0.0
				 *
				 * @param int         $email_id   Log entry ID.
				 * @param string      $status     New status value.
				 * @param string|null $message_id Provider message ID, or null on failure.
				 */
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$resolved_message_id = '' !== $phpmailer->MessageID ? trim( $phpmailer->MessageID, '<>' ) : null;
				do_action( 'mail_chronicle_email_status_updated', $email_id, Email_Status::Sent->value, $resolved_message_id );
			}
		);

		add_action(
			'wp_mail_failed',
			function () use ( $email_id ) {
				$this->email_repository->update_status( $email_id, Email_Status::Failed->value );

				/**
				 * Fires after an email log's send status has been updated.
				 *
				 * @since 1.0.0
				 *
				 * @param int         $email_id   Log entry ID.
				 * @param string      $status     New status value.
				 * @param string|null $message_id Provider message ID, or null on failure.
				 */
				do_action( 'mail_chronicle_email_status_updated', $email_id, Email_Status::Failed->value, null );
			}
		);
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Extract the sender address from the headers string.
	 * Falls back to the site admin email.
	 */
	private function extract_sender( string $headers ): string {
		if ( '' !== $headers && 1 === preg_match( '/^From:\s*(.+)$/im', $headers, $matches ) ) {
			$from = trim( $matches[1] );
			// Extract bare address from "Name <email>" format.
			if ( 1 === preg_match( '/<([^>]+)>/', $from, $addr ) ) {
				return sanitize_email( $addr[1] );
			}
			return sanitize_email( $from );
		}

		return sanitize_email( (string) get_bloginfo( 'admin_email' ) );
	}

	/**
	 * Detect which email provider is active.
	 */
	private function detect_provider(): string {
		if ( defined( 'WPMS_PLUGIN_VER' ) ) {
			$wp_mail_smtp_options = get_option( 'wp_mail_smtp', [] );
			$wp_mail_smtp_options = is_array( $wp_mail_smtp_options ) ? $wp_mail_smtp_options : [];
			$mail_options         = isset( $wp_mail_smtp_options['mail'] ) && is_array( $wp_mail_smtp_options['mail'] )
				? $wp_mail_smtp_options['mail']
				: [];
			if ( isset( $mail_options['mailer'] ) && is_string( $mail_options['mailer'] ) && '' !== $mail_options['mailer'] ) {
				return $mail_options['mailer'];
			}
		}

		$settings = get_option( Constants::OPTION_SETTINGS, [] );
		$settings = is_array( $settings ) ? $settings : [];

		return isset( $settings['provider'] ) && is_string( $settings['provider'] )
			? $settings['provider']
			: Email_Provider::WordPress->value;
	}
}
