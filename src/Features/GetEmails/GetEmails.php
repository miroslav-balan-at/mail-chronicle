<?php
/**
 * Feature: Get Emails
 *
 * This feature handles querying and displaying email logs.
 * Everything needed for this feature is in this folder.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\GetEmails;

use MailChronicle\Common\Constants;
use MailChronicle\Common\Entities\Email;
use MailChronicle\Common\Entities\Email_Status;

/**
 * Get Emails Handler
 *
 * Handles querying emails with filters, pagination, and sorting.
 */
final class GetEmails implements GetEmailsInterface {

	private string $table;

	private \wpdb $wpdb;

	private string $events_table;

	/**
	 * Constructor
	 */
	public function __construct() {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Declares type of WordPress $wpdb global.
		/** @var \wpdb $wpdb WordPress database instance. */
		global $wpdb;
		$this->wpdb         = $wpdb;
		$this->table        = $wpdb->prefix . Constants::TABLE_LOGS;
		$this->events_table = $wpdb->prefix . Constants::TABLE_EVENTS;
	}

	/**
	 * Handle query
	 *
	 * @param array $args Query arguments.
	 * @return array{emails: Email[], total: int} Array with 'emails' and 'total'.
	 */
	public function handle( array $args = [] ): array {
		$defaults = [
			'per_page'  => 20,
			'page'      => 1,
			'orderby'   => 'sent_at',
			'order'     => 'DESC',
			'status'    => '',
			'provider'  => '',
			'search'    => '',
			'date_from' => '',
			'date_to'   => '',
		];

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filters the query arguments before emails are fetched from the database.
		 *
		 * Use this to add custom WHERE conditions, change sort order, or override
		 * pagination without modifying core plugin code.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Merged query arguments (includes defaults).
		 */
		$args = apply_filters( 'mail_chronicle_get_emails_args', $args );
		$args = is_array( $args ) ? $args : [];

		// Build WHERE clause (all columns prefixed with l. to avoid ambiguity after the open_count join).
		$where  = [ '1=1' ];
		$values = [];

		$status    = is_string( $args['status'] ?? null ) ? $args['status'] : '';
		$provider  = is_string( $args['provider'] ?? null ) ? $args['provider'] : '';
		$search    = is_string( $args['search'] ?? null ) ? $args['search'] : '';
		$date_from = is_string( $args['date_from'] ?? null ) ? $args['date_from'] : '';
		$date_to   = is_string( $args['date_to'] ?? null ) ? $args['date_to'] : '';
		$orderby   = is_string( $args['orderby'] ?? null ) ? $args['orderby'] : 'sent_at';
		$order     = is_string( $args['order'] ?? null ) ? $args['order'] : 'DESC';
		$per_page  = is_int( $args['per_page'] ?? null ) ? $args['per_page'] : 20;
		$page      = is_int( $args['page'] ?? null ) ? $args['page'] : 1;

		if ( '' !== $status ) {
			$where[]  = 'l.status = %s';
			$values[] = $status;
		}

		if ( '' !== $provider ) {
			$where[]  = 'l.provider = %s';
			$values[] = $provider;
		}

		if ( '' !== $search ) {
			$where[]    = '(l.recipient LIKE %s OR l.subject LIKE %s)';
			$like_value = '%' . $this->wpdb->esc_like( $search ) . '%';
			$values[]   = $like_value;
			$values[]   = $like_value;
		}

		if ( '' !== $date_from ) {
			$where[]  = 'l.sent_at >= %s';
			$values[] = $date_from;
		}

		if ( '' !== $date_to ) {
			$where[]  = 'l.sent_at <= %s';
			$values[] = $date_to;
		}

		$where_clause = implode( ' AND ', $where );

		// Get total count.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query is built from trusted column whitelist; table name and WHERE clause use $wpdb->prefix and $wpdb->prepare() respectively.
		$count_query = "SELECT COUNT(*) FROM {$this->table} l WHERE {$where_clause}";
		if ( count( $values ) > 0 ) {
			// @phpstan-ignore-next-line
			$total = (int) $this->wpdb->get_var( $this->wpdb->prepare( $count_query, $values ) );
		} else {
			$total = (int) $this->wpdb->get_var( $count_query );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Get emails with open_count joined from the events table.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query is built from trusted column whitelist; table names from $wpdb->prefix and wrapped in $wpdb->prepare().
		$offset       = ( $page - 1 ) * $per_page;
		$opened_event = Email_Status::Opened->value;
		$query        = "SELECT l.*, COALESCE(oc.open_count, 0) AS open_count
				   FROM {$this->table} l
				   LEFT JOIN (
				       SELECT email_log_id, COUNT(*) AS open_count
				       FROM {$this->events_table}
				       WHERE event_type = '{$opened_event}'
				       GROUP BY email_log_id
				   ) oc ON oc.email_log_id = l.id
				   WHERE {$where_clause}
				   ORDER BY l.{$orderby} {$order}
				   LIMIT %d OFFSET %d";

		$query_values   = $values;
		$query_values[] = $per_page;
		$query_values[] = $offset;

		// @phpstan-ignore-next-line
		$prepared_query = $this->wpdb->prepare( $query, $query_values );
		$results        = $this->wpdb->get_results( $prepared_query, ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$rows   = is_array( $results ) ? $results : [];
		$emails = array_map(
			function ( array $row ): Email {
				return new Email( $row );
			},
			$rows
		);

		/**
		 * Filters the email log results before they are returned.
		 *
		 * @since 1.0.0
		 *
		 * @param Email[] $emails Array of Email entity objects.
		 * @param array   $args   The query arguments that produced these results.
		 */
		$emails = apply_filters( 'mail_chronicle_get_emails', $emails, $args );

		return [
			'emails' => is_array( $emails ) ? $emails : [],
			'total'  => $total,
		];
	}

	/**
	 * Get single email by ID
	 *
	 * @param int $id Email ID.
	 * @return Email|null
	 */
	public function get_by_id( int $id ): ?Email {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query is wrapped in $wpdb->prepare(); table name comes from $wpdb->prefix, not user input.
		// @phpstan-ignore-next-line
		$prepared_row = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id );
		$row          = $this->wpdb->get_row( $prepared_row, ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? new Email( $row ) : null;
	}

	/**
	 * Get events for email
	 *
	 * @param int $email_id Email ID.
	 * @return list<array<mixed>>
	 */
	public function get_events( int $email_id ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query is wrapped in $wpdb->prepare(); table name comes from $wpdb->prefix, not user input.
		// @phpstan-ignore-next-line
		$prepared_events = $this->wpdb->prepare( "SELECT * FROM {$this->events_table} WHERE email_log_id = %d ORDER BY occurred_at DESC", $email_id );
		$results         = $this->wpdb->get_results( $prepared_events, ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $results ) ? $results : [];
	}
}
