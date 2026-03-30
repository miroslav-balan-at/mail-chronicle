<?php
/**
 * Uninstall script
 *
 * @package MailChronicle
 */

namespace MailChronicle;

use MailChronicle\Common\Constants;
use MailChronicle\Features\SyncFromMailgun\SyncFromMailgun;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

global $wpdb;

// Delete plugin options.
delete_option( Constants::OPTION_PLUGIN_VER );
delete_option( Constants::OPTION_SETTINGS );
delete_option( Constants::OPTION_DB_VERSION );
delete_option( SyncFromMailgun::CURSOR_OPTION );

// Delete tables.
$mail_chronicle_table_logs   = $wpdb->prefix . Constants::TABLE_LOGS;
$mail_chronicle_table_events = $wpdb->prefix . Constants::TABLE_EVENTS;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix, not user input.
$wpdb->query( "DROP TABLE IF EXISTS {$mail_chronicle_table_events}" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix, not user input.
$wpdb->query( "DROP TABLE IF EXISTS {$mail_chronicle_table_logs}" );

// Clear any cached data.
wp_cache_flush();
