<?php
/**
 * Database Schema
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Common\Database;

use MailChronicle\Common\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Database Schema Class
 */
final class Schema {

	/**
	 * Internal schema version. Bump whenever the table structure changes.
	 */
	const DB_VERSION = '1.3.0';

	private \wpdb $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Create or update database tables via dbDelta.
	 */
	public function create_tables(): void {
		$wpdb = $this->wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_logs      = $wpdb->prefix . Constants::TABLE_LOGS;
		$table_events    = $wpdb->prefix . Constants::TABLE_EVENTS;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Email logs table.
		$sql_logs = "CREATE TABLE {$table_logs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			provider_message_id VARCHAR(255) DEFAULT NULL,
			provider VARCHAR(50) DEFAULT 'WordPress',
			sender VARCHAR(255) NOT NULL DEFAULT '',
			recipient VARCHAR(255) NOT NULL,
			subject VARCHAR(500) NOT NULL,
			message_html LONGTEXT,
			message_plain LONGTEXT,
			headers TEXT,
			attachments TEXT,
			status VARCHAR(50) DEFAULT 'pending',
			body_pending TINYINT(1) NOT NULL DEFAULT 0,
			sent_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_message_recipient (provider_message_id, recipient(191)),
			KEY provider (provider),
			KEY recipient (recipient(191)),
			KEY status (status),
			KEY sent_at (sent_at),
			KEY created_at (created_at),
			KEY status_sent_at (status, sent_at),
			KEY provider_sent_at (provider, sent_at),
			KEY body_pending (body_pending)
		) {$charset_collate};";

		dbDelta( $sql_logs );

		// Provider events table.
		$sql_events = "CREATE TABLE {$table_events} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email_log_id BIGINT UNSIGNED NOT NULL,
			event_type VARCHAR(50) NOT NULL,
			event_data TEXT,
			occurred_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY email_log_id (email_log_id),
			KEY event_type (event_type),
			KEY occurred_at (occurred_at),
			KEY email_log_id_event_type (email_log_id, event_type)
		) {$charset_collate};";

		dbDelta( $sql_events );

		update_option( Constants::OPTION_DB_VERSION, self::DB_VERSION );
	}

	/**
	 * Drop database tables (used on uninstall).
	 */
	public function drop_tables(): void {
		$wpdb = $this->wpdb;

		$table_logs   = $wpdb->prefix . Constants::TABLE_LOGS;
		$table_events = $wpdb->prefix . Constants::TABLE_EVENTS;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix, not user input.
		$wpdb->query( "DROP TABLE IF EXISTS {$table_events}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix, not user input.
		$wpdb->query( "DROP TABLE IF EXISTS {$table_logs}" );

		delete_option( Constants::OPTION_DB_VERSION );
	}

	/**
	 * Whether the stored schema version is behind the current one.
	 */
	public function needs_update(): bool {
		$raw_version     = get_option( Constants::OPTION_DB_VERSION, '0.0.0' );
		$current_version = is_string( $raw_version ) ? $raw_version : '0.0.0';
		return version_compare( $current_version, self::DB_VERSION, '<' );
	}
}
