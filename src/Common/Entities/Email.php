<?php
/**
 * Email Entity
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Common\Entities;

/**
 * Email Entity Class
 */
final class Email {

	private ?int $id;

	private ?string $provider_message_id;

	private string $provider;

	private string $sender;

	private string $recipient;

	private string $subject;

	private string $message_html;

	private string $message_plain;

	private string $headers;

	private string $attachments;

	private string $status;

	private string $sent_at;

	private string $created_at;

	private string $updated_at;

	private bool $body_pending;

	private int $open_count;

	public function __construct( array $data = [] ) {
		$this->id                  = is_numeric( $data['id'] ?? null ) ? (int) $data['id'] : null;
		$this->provider_message_id = is_string( $data['provider_message_id'] ?? null ) ? $data['provider_message_id'] : null;
		$this->provider            = is_string( $data['provider'] ?? null ) ? $data['provider'] : Email_Provider::WordPress->value;
		$this->sender              = is_string( $data['sender'] ?? null ) ? $data['sender'] : '';
		$this->recipient           = is_string( $data['recipient'] ?? null ) ? $data['recipient'] : '';
		$this->subject             = is_string( $data['subject'] ?? null ) ? $data['subject'] : '';
		$this->message_html        = is_string( $data['message_html'] ?? null ) ? $data['message_html'] : '';
		$this->message_plain       = is_string( $data['message_plain'] ?? null ) ? $data['message_plain'] : '';
		$this->headers             = is_string( $data['headers'] ?? null ) ? $data['headers'] : '';
		$this->attachments         = is_string( $data['attachments'] ?? null ) ? $data['attachments'] : '';
		$this->status              = is_string( $data['status'] ?? null ) ? $data['status'] : Email_Status::Pending->value;
		$this->sent_at             = is_string( $data['sent_at'] ?? null ) ? $data['sent_at'] : current_time( 'mysql' );
		$this->created_at          = is_string( $data['created_at'] ?? null ) ? $data['created_at'] : current_time( 'mysql' );
		$this->updated_at          = is_string( $data['updated_at'] ?? null ) ? $data['updated_at'] : current_time( 'mysql' );
		$this->body_pending        = isset( $data['body_pending'] ) && ( true === $data['body_pending'] || ( is_numeric( $data['body_pending'] ) && 1 === (int) $data['body_pending'] ) );
		$this->open_count          = is_numeric( $data['open_count'] ?? null ) ? (int) $data['open_count'] : 0;
	}

	/**
	 * Get ID
	 */
	public function get_id(): ?int {
		return $this->id;
	}

	public function set_id( int $id ): void {
		$this->id = $id;
	}

	/**
	 * Get provider message ID
	 */
	public function get_provider_message_id(): ?string {
		return $this->provider_message_id;
	}

	public function set_provider_message_id( string $provider_message_id ): void {
		$this->provider_message_id = $provider_message_id;
	}

	/**
	 * Get provider
	 */
	public function get_provider(): string {
		return $this->provider;
	}

	public function set_provider( string $provider ): void {
		$this->provider = $provider;
	}

	/**
	 * Get sender
	 */
	public function get_sender(): string {
		return $this->sender;
	}

	/**
	 * Get recipient
	 */
	public function get_recipient(): string {
		return $this->recipient;
	}

	/**
	 * Get subject
	 */
	public function get_subject(): string {
		return $this->subject;
	}

	/**
	 * Get HTML message
	 */
	public function get_message_html(): string {
		return $this->message_html;
	}

	/**
	 * Get plain text message
	 */
	public function get_message_plain(): string {
		return $this->message_plain;
	}

	public function set_message_html( string $html ): void {
		$this->message_html = $html;
	}

	public function set_message_plain( string $plain ): void {
		$this->message_plain = $plain;
	}

	/**
	 * Get headers
	 */
	public function get_headers(): string {
		return $this->headers;
	}

	/**
	 * Get attachments
	 */
	public function get_attachments(): string {
		return $this->attachments;
	}

	/**
	 * Get status
	 */
	public function get_status(): string {
		return $this->status;
	}

	public function set_status( string $status ): void {
		$this->status     = $status;
		$this->updated_at = current_time( 'mysql' );
	}

	/**
	 * Upgrade the status only when the incoming value is higher-priority.
	 * Returns true when the status was actually changed.
	 */
	public function upgrade_status( Email_Status $incoming ): bool {
		$current = Email_Status::tryFrom( $this->status ) ?? Email_Status::Pending;
		if ( ! Email_Status::is_upgrade( $current, $incoming ) ) {
			return false;
		}
		$this->status     = $incoming->value;
		$this->updated_at = current_time( 'mysql' );
		return true;
	}

	/**
	 * Mark the email as sent, optionally recording the provider message ID.
	 */
	public function mark_sent( ?string $provider_message_id = null ): void {
		if ( null !== $provider_message_id && '' !== $provider_message_id ) {
			$this->provider_message_id = $provider_message_id;
		}
		$this->status     = Email_Status::Sent->value;
		$this->updated_at = current_time( 'mysql' );
	}

	/**
	 * Mark the email as failed.
	 */
	public function mark_failed(): void {
		$this->status     = Email_Status::Failed->value;
		$this->updated_at = current_time( 'mysql' );
	}

	/**
	 * Assign the provider-issued message ID (e.g. from PHPMailer or API).
	 */
	public function assign_provider_message_id( string $provider_message_id ): void {
		$this->provider_message_id = $provider_message_id;
	}

	/**
	 * Whether the email body still needs to be fetched from provider storage.
	 */
	public function is_body_pending(): bool {
		return $this->body_pending;
	}

	/**
	 * Mark the body as fetched (or failed — either way, no longer pending).
	 */
	public function resolve_body(): void {
		$this->body_pending = false;
	}

	/**
	 * Mark the body as needing a fetch from provider storage.
	 */
	public function mark_body_pending(): void {
		$this->body_pending = true;
	}

	/**
	 * Get sent at
	 */
	public function get_sent_at(): string {
		return $this->sent_at;
	}

	/**
	 * Get created at
	 */
	public function get_created_at(): string {
		return $this->created_at;
	}

	/**
	 * Get updated at
	 */
	public function get_updated_at(): string {
		return $this->updated_at;
	}

	/**
	 * Convert to array
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'id'                  => $this->id,
			'provider_message_id' => $this->provider_message_id,
			'provider'            => $this->provider,
			'sender'              => $this->sender,
			'recipient'           => $this->recipient,
			'subject'             => $this->subject,
			'message_html'        => $this->message_html,
			'message_plain'       => $this->message_plain,
			'headers'             => $this->headers,
			'attachments'         => $this->attachments,
			'status'              => $this->status,
			'body_pending'        => $this->body_pending,
			'open_count'          => $this->open_count,
			'sent_at'             => $this->sent_at,
			'created_at'          => $this->created_at,
			'updated_at'          => $this->updated_at,
		];
	}
}
