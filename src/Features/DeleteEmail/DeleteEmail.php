<?php
/**
 * Feature: Delete Email
 *
 * This feature handles deleting email logs.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\DeleteEmail;

use MailChronicle\Common\Constants;

/**
 * Delete Email Handler
 */
final class DeleteEmail {

	// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Shaped array type for PHPStan.
	/** @var array{logs: string, events: string} */
	private array $tables;

	private \wpdb $wpdb;

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

	public function handle( int $id ): bool {
		/**
		 * Fires before an email log entry and its events are deleted.
		 *
		 * @since 1.0.0
		 *
		 * @param int $id Log entry ID about to be deleted.
		 */
		do_action( 'mail_chronicle_before_email_deleted', $id );

		// Delete associated events first to avoid orphaned records.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete(
			$this->tables['events'],
			[ 'email_log_id' => $id ],
			[ '%d' ]
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete(
			$this->tables['logs'],
			[ 'id' => $id ],
			[ '%d' ]
		);

		$deleted = false !== $result && $result > 0;

		if ( $deleted ) {
			/**
			 * Fires after an email log entry has been deleted.
			 *
			 * @since 1.0.0
			 *
			 * @param int $id Log entry ID that was deleted.
			 */
			do_action( 'mail_chronicle_after_email_deleted', $id );
		}

		return $deleted;
	}

	/**
	 * Delete all email logs and their events.
	 */
	public function delete_all(): int {
		/**
		 * Fires before all email logs and events are truncated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'mail_chronicle_before_all_emails_deleted' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix, not user input.
		$this->wpdb->query( "TRUNCATE TABLE {$this->tables['events']}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix, not user input.
		$deleted = $this->wpdb->query( "TRUNCATE TABLE {$this->tables['logs']}" );

		/**
		 * Fires after all email logs and events have been truncated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'mail_chronicle_after_all_emails_deleted' );

		return false === $deleted ? -1 : 0;
	}
}
