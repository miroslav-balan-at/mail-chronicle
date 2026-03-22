<?php
/**
 * Feature: Sync From Mailgun
 *
 * Uses Mailgun's cursor-based Events API with the "trustworthy page" algorithm
 * recommended in the official Mailgun documentation:
 *
 *   - Poll ascending from a stored cursor (`next` URL)
 *   - Skip pages whose most-recent event is newer than TRUST_AGE_SECONDS
 *     (those pages may still be incomplete due to out-of-order delivery)
 *   - Store the `next` cursor after each trustworthy page so the next cron
 *     run continues exactly where we left off — no duplicates, no gaps
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\SyncFromMailgun;

use MailChronicle\Common\Constants;
use MailChronicle\Common\Entities\Email_Provider;
use MailChronicle\Common\Entities\Email_Status;
use MailChronicle\Common\Entities\Mailgun_Region;

defined( 'ABSPATH' ) || exit;

/**
 * Sync From Mailgun Handler
 */
final class SyncFromMailgun {

	/**
	 * WP option key that stores the Mailgun `next` cursor URL.
	 */
	const CURSOR_OPTION = 'mail_chronicle_sync_cursor';

	/**
	 * Pages whose newest event is younger than this are considered untrustworthy.
	 * Mailgun's docs recommend 30 min; 5 min is a reasonable near-real-time trade-off.
	 */
	const TRUST_AGE_SECONDS = 300;

	/**
	 * Maximum events per page fetched from Mailgun.
	 */
	const PAGE_LIMIT = 300;

	/**
	 * Maximum pages processed per cron run (avoids timeouts on busy sites).
	 */
	const MAX_PAGES = 10;

	/**
	 * HTTP timeout in seconds for Mailgun API calls.
	 */
	const HTTP_TIMEOUT = 20;

	/**
	 * Map of Mailgun event names → Email_Status cases.
	 *
	 * @var array<string, Email_Status>
	 */
	private const EVENT_STATUS_MAP = [
		'accepted'   => Email_Status::Pending,
		'delivered'  => Email_Status::Delivered,
		'failed'     => Email_Status::Failed,
		'rejected'   => Email_Status::Failed,
		'bounced'    => Email_Status::Bounced,
		'complained' => Email_Status::Complained,
		'opened'     => Email_Status::Opened,
		'clicked'    => Email_Status::Clicked,
	];

	private \wpdb $wpdb;

	private string $table;

	public function __construct( ?\wpdb $wpdb = null ) {
		if ( null === $wpdb ) {
			// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Declares type of WordPress $wpdb global.
			/** @var \wpdb $wpdb WordPress database instance. */
			global $wpdb;
		}
		$this->wpdb  = $wpdb;
		$this->table = $this->wpdb->prefix . Constants::TABLE_LOGS;
	}

	/**
	 * Sync emails from Mailgun.
	 *
	 * For cron calls pass no $args — the cursor picks up where we left off.
	 * For a manual/REST call pass ['days' => N] to force a fresh lookback window.
	 *
	 * @return array{success: bool, synced: int, updated: int, skipped: int, total: int, message?: string}
	 */
	public function handle( array $args = [] ): array {
		$raw_settings = get_option( Constants::OPTION_SETTINGS, [] );
		$settings     = is_array( $raw_settings ) ? $raw_settings : [];
		$api_key      = isset( $settings['mailgun_api_key'] ) && is_string( $settings['mailgun_api_key'] ) ? $settings['mailgun_api_key'] : '';
		$domain       = isset( $settings['mailgun_domain'] ) && is_string( $settings['mailgun_domain'] ) ? $settings['mailgun_domain'] : '';
		$region_str   = isset( $settings['mailgun_region'] ) && is_string( $settings['mailgun_region'] ) ? $settings['mailgun_region'] : '';
		$region       = Mailgun_Region::tryFrom( $region_str ) ?? Mailgun_Region::US;

		if ( '' === $api_key || '' === $domain ) {
			return [
				'success' => false,
				'synced'  => 0,
				'updated' => 0,
				'skipped' => 0,
				'total'   => 0,
				'message' => __( 'Mailgun API key or domain not configured', 'mail-chronicle' ),
			];
		}

		$limit = max( 1, min( self::PAGE_LIMIT, is_numeric( $args['limit'] ?? null ) ? (int) $args['limit'] : self::PAGE_LIMIT ) );

		$force_reset = isset( $args['days'] );
		$url         = $force_reset
			? $this->build_initial_url( $domain, $region, is_numeric( $args['days'] ?? null ) ? (int) $args['days'] : 1, $limit )
			: $this->get_cursor_url( $domain, $region, $limit );

		$auth    = 'Basic ' . base64_encode( 'api:' . $api_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$synced  = 0;
		$updated = 0;
		$skipped = 0;

		for ( $page = 0; $page < self::MAX_PAGES; $page++ ) {
			$result = $this->fetch_page( $url, $auth );

			if ( ! $result['success'] ) {
				return [
					'success' => false,
					'synced'  => 0,
					'updated' => 0,
					'skipped' => 0,
					'total'   => 0,
					'message' => $result['message'] ?? __( 'Unknown error', 'mail-chronicle' ),
				];
			}

			$events   = $result['events'];
			$next_url = $result['next_url'];

			// Empty page — we have caught up. Store cursor and stop.
			if ( [] === $events ) {
				if ( null !== $next_url && '' !== $next_url ) {
					update_option( self::CURSOR_OPTION, $next_url, false );
				}
				break;
			}

			// Trustworthy-page check: ascending order so last item is the newest.
			$last_event = end( $events );
			$newest_ts  = ( is_array( $last_event ) && is_numeric( $last_event['timestamp'] ?? null ) ) ? (int) $last_event['timestamp'] : 0;
			$age        = time() - $newest_ts;

			if ( $age < self::TRUST_AGE_SECONDS ) {
				// Too fresh — stop here; next run retries from the same $url.
				break;
			}

			$counts   = $this->process_events( $events );
			$synced  += $counts['synced'];
			$updated += $counts['updated'];
			$skipped += $counts['skipped'];

			if ( null !== $next_url && '' !== $next_url ) {
				update_option( self::CURSOR_OPTION, $next_url, false );
				$url = $next_url;
			} else {
				break;
			}
		}

		return [
			'success' => true,
			'synced'  => $synced,
			'updated' => $updated,
			'skipped' => $skipped,
			'total'   => $synced + $updated + $skipped,
		];
	}

	/**
	 * Reset the stored cursor (call after "Delete All Logs").
	 */
	public static function reset_cursor(): void {
		delete_option( self::CURSOR_OPTION );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private function build_initial_url( string $domain, Mailgun_Region $region, int $days, int $limit ): string {
		return add_query_arg(
			[
				'begin'     => strtotime( "-{$days} days" ),
				'ascending' => 'yes',
				'limit'     => $limit,
			],
			sprintf( '%s/v3/%s/events', $region->api_base(), $domain )
		);
	}

	private function get_cursor_url( string $domain, Mailgun_Region $region, int $limit ): string {
		$raw_cursor = get_option( self::CURSOR_OPTION, '' );
		$cursor     = is_string( $raw_cursor ) ? $raw_cursor : '';

		if ( '' !== $cursor ) {
			return $cursor;
		}

		// First run ever — look back 1 hour to catch recently sent emails.
		return $this->build_initial_url( $domain, $region, 1, $limit );
	}

	/**
	 * Fetch a single events page from Mailgun.
	 *
	 * @return array{success: bool, events: array<int, array<string, mixed>>, next_url: string|null, message?: string}
	 */
	private function fetch_page( string $url, string $auth ): array {
		$response = wp_remote_get(
			$url,
			[
				'headers' => [ 'Authorization' => $auth ],
				'timeout' => self::HTTP_TIMEOUT,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success'  => false,
				'events'   => [],
				'next_url' => null,
				'message'  => $response->get_error_message(),
			];
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
			$message = ( is_array( $decoded ) && isset( $decoded['message'] ) && is_string( $decoded['message'] ) )
				? $decoded['message']
				/* translators: %d: HTTP status code returned by Mailgun */
				: sprintf( __( 'Mailgun API returned HTTP %d', 'mail-chronicle' ), is_int( $code ) ? $code : 0 );
			return [
				'success'  => false,
				'events'   => [],
				'next_url' => null,
				'message'  => $message,
			];
		}

		$data      = json_decode( wp_remote_retrieve_body( $response ), true );
		$data      = is_array( $data ) ? $data : [];
		$raw_items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : [];
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Declares narrowed type after array_filter.
		/** @var array<int, array<string, mixed>> $events */
		$events   = array_values( array_filter( $raw_items, 'is_array' ) );
		$paging   = isset( $data['paging'] ) && is_array( $data['paging'] ) ? $data['paging'] : [];
		$next_url = isset( $paging['next'] ) && is_string( $paging['next'] ) ? $paging['next'] : null;

		return [
			'success'  => true,
			'events'   => $events,
			'next_url' => $next_url,
		];
	}

	private function extract_message_id( array $event ): ?string {
		$message = isset( $event['message'] ) && is_array( $event['message'] ) ? $event['message'] : [];
		$headers = isset( $message['headers'] ) && is_array( $message['headers'] ) ? $message['headers'] : [];
		$mid     = isset( $headers['message-id'] ) && is_string( $headers['message-id'] ) ? $headers['message-id'] : null;
		return ( null !== $mid && '' !== $mid ) ? $mid : null;
	}

	/**
	 * Batch-process a page of events: one SELECT for existence, then inserts/updates.
	 *
	 * @param array<int, array<string, mixed>> $events
	 * @return array{synced: int, updated: int, skipped: int}
	 */
	private function process_events( array $events ): array {
		$message_ids = [];
		foreach ( $events as $event ) {
			$mid = $this->extract_message_id( $event );
			if ( null !== $mid ) {
				$message_ids[] = $mid;
			}
		}

		$existing = $this->fetch_existing_ids( $message_ids );

		$synced  = 0;
		$updated = 0;
		$skipped = 0;

		foreach ( $events as $event ) {
			$mid = $this->extract_message_id( $event );

			if ( null === $mid ) {
				++$skipped;
				continue;
			}

			if ( isset( $existing[ $mid ] ) ) {
				$this->update_status( $existing[ $mid ], $event );
				++$updated;
			} else {
				$this->insert_from_event( $event );
				++$synced;
			}
		}

		return compact( 'synced', 'updated', 'skipped' );
	}

	/**
	 * Single query to find which message-ids already exist in the DB.
	 *
	 * @return array<string, int>
	 */
	private function fetch_existing_ids( array $message_ids ): array {
		if ( [] === $message_ids ) {
			return [];
		}

		$placeholders = implode( ', ', array_fill( 0, count( $message_ids ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Query is wrapped in $wpdb->prepare() with dynamic %s placeholders built from array_fill(); table name comes from $wpdb->prefix, not user input.
		$existing_sql = "SELECT id, provider_message_id FROM {$this->table} WHERE provider_message_id IN ({$placeholders})";
		// @phpstan-ignore-next-line
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $existing_sql, ...$message_ids ) );
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

	/**
	 * Insert a new log row from a Mailgun event.
	 * Body content is intentionally omitted — it's populated by webhooks instead.
	 */
	private function insert_from_event( array $event ): void {
		$message = isset( $event['message'] ) && is_array( $event['message'] ) ? $event['message'] : [];
		$headers = isset( $message['headers'] ) && is_array( $message['headers'] ) ? $message['headers'] : [];
		$status  = self::map_event_status( is_string( $event['event'] ?? null ) ? $event['event'] : '' );

		$timestamp = is_numeric( $event['timestamp'] ?? null ) ? (int) $event['timestamp'] : time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->insert(
			$this->table,
			[
				'provider_message_id' => is_string( $headers['message-id'] ?? null ) ? $headers['message-id'] : '',
				'provider'            => Email_Provider::Mailgun->value,
				'recipient'           => is_string( $event['recipient'] ?? null ) ? $event['recipient'] : '',
				'subject'             => is_string( $headers['subject'] ?? null ) ? $headers['subject'] : '',
				'message_html'        => '',
				'message_plain'       => '',
				'headers'             => wp_json_encode( $headers ),
				'status'              => $status->value,
				'sent_at'             => gmdate( 'Y-m-d H:i:s', $timestamp ),
				'created_at'          => current_time( 'mysql' ),
				'updated_at'          => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	private function update_status( int $id, array $event ): void {
		$new_status = self::map_event_status( is_string( $event['event'] ?? null ) ? $event['event'] : '' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query is wrapped in $wpdb->prepare(); table name comes from $wpdb->prefix, not user input.
		$status_template = "SELECT status FROM {$this->table} WHERE id = %d";
		// @phpstan-ignore-next-line
		$current_value = $this->wpdb->get_var( $this->wpdb->prepare( $status_template, $id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$current_status = Email_Status::tryFrom( is_string( $current_value ) ? $current_value : '' ) ?? Email_Status::Pending;

		if ( ! Email_Status::is_upgrade( $current_status, $new_status ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update(
			$this->table,
			[
				'status'     => $new_status->value,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	private static function map_event_status( string $event ): Email_Status {
		return self::EVENT_STATUS_MAP[ $event ] ?? Email_Status::Pending;
	}
}
