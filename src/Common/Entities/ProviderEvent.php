<?php
/**
 * Provider Event Entity (e.g., Mailgun events)
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Common\Entities;

/**
 * Provider Event Entity Class
 */
final class ProviderEvent {

	private ?int $id;

	private int $email_log_id;

	private string $event_type;

	private string $event_data;

	private string $occurred_at;

	private string $created_at;

	/**
	 * Named constructor — build a ProviderEvent from a Mailgun event-data array.
	 *
	 * @param int                  $email_log_id Log entry this event belongs to.
	 * @param array<string, mixed> $event_data   Raw Mailgun event-data sub-array.
	 */
	public static function from_mailgun_event( int $email_log_id, array $event_data ): self {
		$event_type      = is_string( $event_data['event'] ?? null ) ? $event_data['event'] : '';
		$raw_occurred_ts = $event_data['timestamp'] ?? null;
		$occurred_at     = is_numeric( $raw_occurred_ts )
			? gmdate( 'Y-m-d H:i:s', (int) $raw_occurred_ts )
			: current_time( 'mysql', true );

		return new self(
			[
				'email_log_id' => $email_log_id,
				'event_type'   => $event_type,
				'event_data'   => (string) wp_json_encode( $event_data ),
				'occurred_at'  => $occurred_at,
			]
		);
	}

	public function __construct( array $data = [] ) {
		$this->id           = is_numeric( $data['id'] ?? null ) ? (int) $data['id'] : null;
		$this->email_log_id = is_numeric( $data['email_log_id'] ?? null ) ? (int) $data['email_log_id'] : 0;
		$this->event_type   = is_string( $data['event_type'] ?? null ) ? $data['event_type'] : '';
		$this->event_data   = is_string( $data['event_data'] ?? null ) ? $data['event_data'] : '';
		$this->occurred_at  = is_string( $data['occurred_at'] ?? null ) ? $data['occurred_at'] : current_time( 'mysql' );
		$this->created_at   = is_string( $data['created_at'] ?? null ) ? $data['created_at'] : current_time( 'mysql' );
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
	 * Get email log ID
	 */
	public function get_email_log_id(): int {
		return $this->email_log_id;
	}

	/**
	 * Get event type
	 */
	public function get_event_type(): string {
		return $this->event_type;
	}

	/**
	 * Get event data
	 */
	public function get_event_data(): string {
		return $this->event_data;
	}

	/**
	 * Get occurred at
	 */
	public function get_occurred_at(): string {
		return $this->occurred_at;
	}

	/**
	 * Get created at
	 */
	public function get_created_at(): string {
		return $this->created_at;
	}

	/**
	 * Convert to array
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'id'           => $this->id,
			'email_log_id' => $this->email_log_id,
			'event_type'   => $this->event_type,
			'event_data'   => $this->event_data,
			'occurred_at'  => $this->occurred_at,
			'created_at'   => $this->created_at,
		];
	}
}
