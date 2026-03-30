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
 * Delegates all persistence to EmailRepositoryInterface.  No SQL in this class.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\SyncFromMailgun;

use MailChronicle\Common\Entities\Email;
use MailChronicle\Common\Entities\Email_Provider;
use MailChronicle\Common\Entities\Email_Status;
use MailChronicle\Common\Entities\Mailgun_Region;
use MailChronicle\Common\Repository\EmailRepositoryInterface;
use MailChronicle\Features\ManageSettings\ManageSettingsInterface;

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

	private EmailRepositoryInterface $email_repository;

	private ManageSettingsInterface $settings;

	public function __construct( EmailRepositoryInterface $email_repository, ManageSettingsInterface $settings ) {
		$this->email_repository = $email_repository;
		$this->settings         = $settings;
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
		$settings   = $this->settings->get();
		$api_key    = is_string( $settings['mailgun_api_key'] ?? null ) ? $settings['mailgun_api_key'] : '';
		$domain     = is_string( $settings['mailgun_domain'] ?? null ) ? $settings['mailgun_domain'] : '';
		$region_str = is_string( $settings['mailgun_region'] ?? null ) ? $settings['mailgun_region'] : '';
		$region     = Mailgun_Region::tryFrom( $region_str ) ?? Mailgun_Region::US;

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

			$counts   = $this->process_events( $events, $auth );
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
	public function reset_cursor(): void {
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
	private function process_events( array $events, string $auth ): array {
		$message_ids = [];
		foreach ( $events as $event ) {
			$mid = $this->extract_message_id( $event );
			if ( null !== $mid ) {
				$message_ids[] = $mid;
			}
		}

		$existing = $this->email_repository->find_existing_ids_by_provider_message_ids( $message_ids );

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
				$this->update_existing( $existing[ $mid ], $event, $auth );
				++$updated;
			} else {
				$this->insert_from_event( $event, $auth );
				++$synced;
			}
		}

		return compact( 'synced', 'updated', 'skipped' );
	}

	/**
	 * Insert a new log row from a Mailgun event.
	 * If the event carries a storage URL (message storage enabled in Mailgun),
	 * the URL is stored in the headers JSON under `mc_storage_url` for
	 * on-demand retrieval — no HTTP call is made during sync.
	 */
	private function insert_from_event( array $event, string $auth ): void {
		$message   = isset( $event['message'] ) && is_array( $event['message'] ) ? $event['message'] : [];
		$headers   = isset( $message['headers'] ) && is_array( $message['headers'] ) ? $message['headers'] : [];
		$status    = Email_Status::from_mailgun_event( is_string( $event['event'] ?? null ) ? $event['event'] : '' ) ?? Email_Status::Pending;
		$timestamp = is_numeric( $event['timestamp'] ?? null ) ? (int) $event['timestamp'] : time();

		$storage_url = self::extract_storage_url( $event );
		if ( '' !== $storage_url ) {
			$headers['mc_storage_url'] = $storage_url;
		}

		$body = '' !== $storage_url ? $this->fetch_body_from_storage( $storage_url, $auth ) : [
			'html'  => '',
			'plain' => '',
		];

		$sender = is_string( $event['sender'] ?? null ) ? $event['sender'] : ( is_string( $headers['from'] ?? null ) ? $headers['from'] : '' );

		$email = new Email(
			[
				'provider_message_id' => is_string( $headers['message-id'] ?? null ) ? $headers['message-id'] : '',
				'provider'            => Email_Provider::Mailgun->value,
				'sender'              => $sender,
				'recipient'           => is_string( $event['recipient'] ?? null ) ? $event['recipient'] : '',
				'subject'             => is_string( $headers['subject'] ?? null ) ? $headers['subject'] : '',
				'message_html'        => $body['html'],
				'message_plain'       => $body['plain'],
				'headers'             => (string) wp_json_encode( $headers ),
				'status'              => $status->value,
				'sent_at'             => gmdate( 'Y-m-d H:i:s', $timestamp ),
			]
		);

		$this->email_repository->save( $email );
	}

	private function update_existing( int $id, array $event, string $auth ): void {
		$new_status     = Email_Status::from_mailgun_event( is_string( $event['event'] ?? null ) ? $event['event'] : '' ) ?? Email_Status::Pending;
		$current_value  = $this->email_repository->get_status( $id );
		$current_status = Email_Status::tryFrom( is_string( $current_value ) ? $current_value : '' ) ?? Email_Status::Pending;

		if ( Email_Status::is_upgrade( $current_status, $new_status ) ) {
			$this->email_repository->update_status( $id, $new_status->value );
		}

		// Backfill mc_storage_url if missing — allows on-demand content fetch for
		// emails that were synced before the FetchStoredContent feature was added.
		$storage_url = self::extract_storage_url( $event );

		if ( '' !== $storage_url ) {
			$existing = $this->email_repository->find_by_id( $id );
			if ( null !== $existing ) {
				$headers_data = json_decode( $existing->get_headers(), true );
				$headers_data = is_array( $headers_data ) ? $headers_data : [];

				if ( ! isset( $headers_data['mc_storage_url'] ) ) {
					$headers_data['mc_storage_url'] = $storage_url;
					$this->email_repository->update_headers( $id, (string) wp_json_encode( $headers_data ) );
				}

				if ( '' === $existing->get_message_html() ) {
					$body = $this->fetch_body_from_storage( $storage_url, $auth );
					if ( '' !== $body['html'] || '' !== $body['plain'] ) {
						$this->email_repository->update_content( $id, $body['html'], $body['plain'] );
					}
				}
			}
		}
	}

	/**
	 * Fetch body-html and body-plain from a Mailgun stored-message URL.
	 *
	 * @return array{html: string, plain: string}
	 */
	private function fetch_body_from_storage( string $storage_url, string $auth ): array {
		$response = wp_remote_get(
			$storage_url,
			[
				'headers' => [ 'Authorization' => $auth ],
				'timeout' => self::HTTP_TIMEOUT,
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return [
				'html'  => '',
				'plain' => '',
			];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : [];

		return [
			'html'  => isset( $data['body-html'] ) && is_string( $data['body-html'] ) ? $data['body-html'] : '',
			'plain' => isset( $data['body-plain'] ) && is_string( $data['body-plain'] ) ? $data['body-plain'] : '',
		];
	}

	/**
	 * Extract the storage URL from a Mailgun event.
	 * The `storage.url` field can be either a plain string or a single-element
	 * array depending on the Mailgun region / API version.
	 */
	private static function extract_storage_url( array $event ): string {
		$storage = isset( $event['storage'] ) && is_array( $event['storage'] ) ? $event['storage'] : [];
		$raw     = $storage['url'] ?? null;

		if ( is_string( $raw ) && '' !== $raw ) {
			return $raw;
		}

		if ( is_array( $raw ) && isset( $raw[0] ) && is_string( $raw[0] ) && '' !== $raw[0] ) {
			return $raw[0];
		}

		return '';
	}
}
