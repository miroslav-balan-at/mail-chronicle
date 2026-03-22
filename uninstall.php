<?php
/**
 * Uninstall script
 *
 * @package MailChronicle
 */

namespace MailChronicle;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Delete plugin options.
delete_option( 'mail_chronicle_version' );
delete_option( 'mail_chronicle_settings' );
delete_option( 'mail_chronicle_db_version' );

// Delete tables.
$mail_chronicle_table_logs   = $wpdb->prefix . 'mail_chronicle_logs';
$mail_chronicle_table_events = $wpdb->prefix . 'mail_chronicle_events';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix, not user input.
$wpdb->query( "DROP TABLE IF EXISTS {$mail_chronicle_table_events}" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix, not user input.
$wpdb->query( "DROP TABLE IF EXISTS {$mail_chronicle_table_logs}" );

// Clear any cached data.
wp_cache_flush();
