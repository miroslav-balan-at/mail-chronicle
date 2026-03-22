<?php
/**
 * Wpdb Provider Event Repository
 *
 * Concrete implementation of ProviderEventRepositoryInterface backed by $wpdb.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Common\Infrastructure;

use MailChronicle\Common\Constants;
use MailChronicle\Common\Entities\ProviderEvent;
use MailChronicle\Common\Repository\ProviderEventRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Wpdb-backed repository for the mail_chronicle_events table.
 */
final class WpdbProviderEventRepository implements ProviderEventRepositoryInterface {

	private \wpdb $wpdb;

	private string $table;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . Constants::TABLE_EVENTS;
	}

	public function save( ProviderEvent $event ): int|false {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->insert(
			$this->table,
			[
				'email_log_id' => $event->get_email_log_id(),
				'event_type'   => $event->get_event_type(),
				'event_data'   => $event->get_event_data(),
				'occurred_at'  => $event->get_occurred_at(),
				'created_at'   => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);

		return false !== $result ? (int) $this->wpdb->insert_id : false;
	}

	public function find_by_email_log_id( int $email_log_id ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// @phpstan-ignore-next-line
		$prepared_events = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE email_log_id = %d ORDER BY occurred_at DESC", $email_log_id );
		$results         = $this->wpdb->get_results( $prepared_events, ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $results ) ? $results : [];
	}
}
