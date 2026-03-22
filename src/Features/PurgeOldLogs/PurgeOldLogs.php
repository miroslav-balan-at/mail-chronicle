<?php
/**
 * Feature: Purge Old Logs
 *
 * Domain handler — deletes email logs (and their events) older than the
 * configured retention window.  Zero retention means "keep forever".
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\PurgeOldLogs;

use MailChronicle\Common\Constants;

/**
 * Purge Old Logs Handler
 */
final class PurgeOldLogs {

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

	/**
	 * Delete logs older than $days days.
	 *
	 * When $days is 0 nothing is deleted (retain forever).
	 */
	public function handle( int $days ): int {
		if ( $days <= 0 ) {
			return 0;
		}

		$timestamp = strtotime( "-{$days} days" );
		$cutoff    = gmdate( 'Y-m-d H:i:s', false !== $timestamp ? $timestamp : 0 );

		// Delete child events first to avoid orphaned rows.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query is wrapped in $wpdb->prepare(); table names come from $wpdb->prefix, not user input.
		$events_template = "DELETE e FROM {$this->tables['events']} e INNER JOIN {$this->tables['logs']} l ON l.id = e.email_log_id WHERE l.sent_at < %s";
		$events_sql      = $this->wpdb->prepare( $events_template, $cutoff ); // @phpstan-ignore-line
		if ( is_string( $events_sql ) ) {
			$this->wpdb->query( $events_sql );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Delete the log rows themselves.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query is wrapped in $wpdb->prepare(); table name comes from $wpdb->prefix, not user input.
		$logs_template = "DELETE FROM {$this->tables['logs']} WHERE sent_at < %s";
		$logs_sql      = $this->wpdb->prepare( $logs_template, $cutoff ); // @phpstan-ignore-line
		$deleted       = is_string( $logs_sql ) ? $this->wpdb->query( $logs_sql ) : false;
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return false === $deleted ? -1 : (int) $deleted;
	}
}
