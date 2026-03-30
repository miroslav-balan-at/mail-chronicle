<?php
/**
 * Wpdb Email Repository
 *
 * Concrete implementation of EmailRepositoryInterface backed by $wpdb.
 * All SQL lives here; feature handlers never touch $wpdb directly.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Common\Infrastructure;

use MailChronicle\Common\Constants;
use MailChronicle\Common\Entities\Email;
use MailChronicle\Common\Entities\Email_Status;
use MailChronicle\Common\Query\EmailQuery;
use MailChronicle\Common\Repository\EmailRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Wpdb-backed repository for the mail_chronicle_logs table.
 */
final class WpdbEmailRepository implements EmailRepositoryInterface {

	private \wpdb $wpdb;

	private string $table;

	private string $events_table;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb         = $wpdb;
		$this->table        = $wpdb->prefix . Constants::TABLE_LOGS;
		$this->events_table = $wpdb->prefix . Constants::TABLE_EVENTS;
	}

	public function save( Email $email ): int|false {
		$db_data = [
			'provider_message_id' => $email->get_provider_message_id(),
			'provider'            => $email->get_provider(),
			'sender'              => $email->get_sender(),
			'recipient'           => $email->get_recipient(),
			'subject'             => $email->get_subject(),
			'message_html'        => $email->get_message_html(),
			'message_plain'       => $email->get_message_plain(),
			'headers'             => $email->get_headers(),
			'attachments'         => $email->get_attachments(),
			'status'              => $email->get_status(),
			'sent_at'             => $email->get_sent_at(),
			'created_at'          => $email->get_created_at(),
			'updated_at'          => $email->get_updated_at(),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->insert( $this->table, $db_data );

		return false !== $result ? (int) $this->wpdb->insert_id : false;
	}

	public function update_status( int $id, string $status, ?string $message_id = null ): void {
		$data = [
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		];

		if ( null !== $message_id && '' !== $message_id ) {
			$data['provider_message_id'] = $message_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update( $this->table, $data, [ 'id' => $id ] );
	}

	public function find_by_id( int $id ): ?Email {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// @phpstan-ignore-next-line
		$prepared_row = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id );
		$row          = $this->wpdb->get_row( $prepared_row, ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? new Email( $row ) : null;
	}

	public function find_id_by_provider_message_id( string $provider_message_id ): ?int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// @phpstan-ignore-next-line
		$find_sql = $this->wpdb->prepare( "SELECT id FROM {$this->table} WHERE provider_message_id = %s", $provider_message_id );
		$id       = $this->wpdb->get_var( $find_sql );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return ( null !== $id && '' !== $id ) ? (int) $id : null;
	}

	public function get_status( int $id ): ?string {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// @phpstan-ignore-next-line
		$status_sql = $this->wpdb->prepare( "SELECT status FROM {$this->table} WHERE id = %d", $id );
		$raw_value  = $this->wpdb->get_var( $status_sql );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_string( $raw_value ) ? $raw_value : null;
	}

	public function query( EmailQuery $email_query ): array {
		$where  = [ '1=1' ];
		$values = [];

		if ( '' !== $email_query->status ) {
			$where[]  = 'l.status = %s';
			$values[] = $email_query->status;
		}

		if ( '' !== $email_query->provider ) {
			$where[]  = 'l.provider = %s';
			$values[] = $email_query->provider;
		}

		if ( '' !== $email_query->search ) {
			$where[]    = '(l.recipient LIKE %s OR l.subject LIKE %s)';
			$like_value = '%' . $this->wpdb->esc_like( $email_query->search ) . '%';
			$values[]   = $like_value;
			$values[]   = $like_value;
		}

		if ( '' !== $email_query->date_from ) {
			$where[]  = 'l.sent_at >= %s';
			$values[] = $email_query->date_from;
		}

		if ( '' !== $email_query->date_to ) {
			$where[]  = 'l.sent_at <= %s';
			$values[] = $email_query->date_to;
		}

		if ( '' !== $email_query->domain ) {
			$like_addr    = '%@' . $this->wpdb->esc_like( $email_query->domain );
			$like_headers = '%' . $this->wpdb->esc_like( $email_query->domain ) . '%';
			$where[]      = '(l.recipient LIKE %s OR l.sender LIKE %s OR l.headers LIKE %s)';
			$values[]     = $like_addr;
			$values[]     = $like_addr;
			$values[]     = $like_headers;
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_query = "SELECT COUNT(*) FROM {$this->table} l WHERE {$where_clause}";
		if ( count( $values ) > 0 ) {
			// @phpstan-ignore-next-line
			$total = (int) $this->wpdb->get_var( $this->wpdb->prepare( $count_query, $values ) );
		} else {
			$total = (int) $this->wpdb->get_var( $count_query );
		}

		$opened_event = Email_Status::Opened->value;
		$orderby      = $email_query->orderby;
		$order        = $email_query->order;
		$per_page     = $email_query->per_page;
		$offset       = $email_query->offset();
		$data_query   = "SELECT l.*, COALESCE(oc.open_count, 0) AS open_count
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
		$prepared_query = $this->wpdb->prepare( $data_query, $query_values );
		$results        = $this->wpdb->get_results( $prepared_query, ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$rows   = is_array( $results ) ? $results : [];
		$emails = array_map(
			function ( array $row ): Email {
				return new Email( $row );
			},
			$rows
		);

		return [
			'emails' => $emails,
			'total'  => $total,
		];
	}

	public function find_existing_ids_by_provider_message_ids( array $provider_message_ids ): array {
		if ( [] === $provider_message_ids ) {
			return [];
		}

		$placeholders = implode( ', ', array_fill( 0, count( $provider_message_ids ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$existing_sql = "SELECT id, provider_message_id FROM {$this->table} WHERE provider_message_id IN ({$placeholders})";
		// @phpstan-ignore-next-line
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $existing_sql, ...$provider_message_ids ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$map = [];
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			if ( ! is_object( $row ) || ! isset( $row->provider_message_id, $row->id ) ) {
				continue;
			}
			if ( ! is_string( $row->provider_message_id ) || ! is_numeric( $row->id ) ) {
				continue;
			}
			$map[ $row->provider_message_id ] = (int) $row->id;
		}

		return $map;
	}

	public function update_content( int $id, string $html, string $plain ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update(
			$this->table,
			[
				'message_html'  => $html,
				'message_plain' => $plain,
				'updated_at'    => current_time( 'mysql' ),
			],
			[ 'id' => $id ]
		);
	}

	public function update_headers( int $id, string $headers ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update(
			$this->table,
			[
				'headers'    => $headers,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $id ]
		);
	}

	public function count_pending_bodies(): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table} WHERE message_html = '' AND headers LIKE '%mc_storage_url%'"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_numeric( $count ) ? (int) $count : 0;
	}

	public function find_next_pending_body(): ?Email {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $this->wpdb->get_row(
			"SELECT * FROM {$this->table} WHERE message_html = '' AND headers LIKE '%mc_storage_url%' ORDER BY id ASC LIMIT 1",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? new Email( $row ) : null;
	}

	public function delete( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete(
			$this->events_table,
			[ 'email_log_id' => $id ],
			[ '%d' ]
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete(
			$this->table,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result && $result > 0;
	}

	public function delete_all(): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->wpdb->query( "TRUNCATE TABLE {$this->events_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $this->wpdb->query( "TRUNCATE TABLE {$this->table}" );

		return false === $deleted ? -1 : 0;
	}

	public function delete_older_than( string $cutoff ): int {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$events_template = "DELETE e FROM {$this->events_table} e INNER JOIN {$this->table} l ON l.id = e.email_log_id WHERE l.sent_at < %s";
		$events_sql      = $this->wpdb->prepare( $events_template, $cutoff ); // @phpstan-ignore-line
		if ( is_string( $events_sql ) ) {
			$this->wpdb->query( $events_sql );
		}

		$logs_template = "DELETE FROM {$this->table} WHERE sent_at < %s";
		$logs_sql      = $this->wpdb->prepare( $logs_template, $cutoff ); // @phpstan-ignore-line
		$deleted       = is_string( $logs_sql ) ? $this->wpdb->query( $logs_sql ) : false;
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return false === $deleted ? -1 : (int) $deleted;
	}
}
