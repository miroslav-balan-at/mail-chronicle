<?php
/**
 * Feature: Sync — Generic REST Controller
 *
 * Provider-agnostic sync endpoint. Reads the configured provider from settings
 * and dispatches to the correct handler. Adding a new provider only requires
 * adding a case to the dispatch map — the endpoint URL never changes.
 *
 * POST /mail-chronicle/v1/sync
 *   - No body → cursor-based "sync latest" (picks up where cron left off)
 *   - Body { days: N } → force a full look-back of N days (resets cursor)
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\Sync;

use MailChronicle\Common\Constants;
use MailChronicle\Common\Entities\Email_Provider;
use MailChronicle\Features\SyncFromMailgun\SyncFromMailgun;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Generic Sync Controller
 */
class SyncController extends WP_REST_Controller {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'mail-chronicle/v1';
		$this->rest_base = 'sync';
	}

	/**
	 * Register routes
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'sync' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => $this->get_sync_params(),
				],
			]
		);
	}

	public function sync( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$raw_settings = get_option( Constants::OPTION_SETTINGS, [] );
		$settings     = is_array( $raw_settings ) ? $raw_settings : [];
		$provider_str = isset( $settings['provider'] ) && is_string( $settings['provider'] ) ? $settings['provider'] : '';
		$provider     = Email_Provider::tryFrom( $provider_str );

		if ( null === $provider ) {
			return new WP_Error(
				'unknown_provider',
				__( 'No supported email provider is configured.', 'mail-chronicle' ),
				[ 'status' => 400 ]
			);
		}

		$args = [];

		$limit_param = $request->get_param( 'limit' );
		if ( null !== $limit_param ) {
			$args['limit'] = is_numeric( $limit_param ) ? (int) $limit_param : 0;
		}

		// Only pass 'days' when explicitly provided — omitting it makes the handler
		// use the stored cursor (sync latest only, no forced look-back).
		$days_param = $request->get_param( 'days' );
		if ( null !== $days_param ) {
			$args['days'] = is_numeric( $days_param ) ? (int) $days_param : 0;
		}

		/**
		 * Fires before a provider sync is triggered via the REST API.
		 *
		 * @since 1.0.0
		 *
		 * @param Email_Provider $provider The provider that will be synced.
		 * @param array          $args     Sync arguments (may include 'days', 'limit').
		 */
		do_action( 'mail_chronicle_before_sync', $provider, $args );

		$result = $this->dispatch( $provider, $args );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'sync_failed',
				$result['message'] ?? __( 'Sync failed.', 'mail-chronicle' ),
				[ 'status' => 500 ]
			);
		}

		/**
		 * Fires after a provider sync has completed successfully.
		 *
		 * @since 1.0.0
		 *
		 * @param array          $result   Sync result: synced, updated, skipped, total counts.
		 * @param Email_Provider $provider The provider that was synced.
		 * @param array          $args     The sync arguments that were used.
		 */
		do_action( 'mail_chronicle_after_sync', $result, $provider, $args );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'synced'   => $result['synced'],
					'updated'  => $result['updated'],
					'skipped'  => $result['skipped'],
					'total'    => $result['total'],
					'provider' => $provider->value,
					'message'  => sprintf(
						/* translators: %1$d: number of new emails synced, %2$d: number of existing emails updated */
						__( 'Synced %1$d new emails and updated %2$d existing emails.', 'mail-chronicle' ),
						$result['synced'],
						$result['updated']
					),
				],
			],
			200
		);
	}

	/**
	 * Dispatch to the correct provider sync handler.
	 *
	 * @return array{success: bool, synced: int, updated: int, skipped: int, total: int, message?: string}
	 */
	private function dispatch( Email_Provider $provider, array $args ): array {
		return match ( $provider ) {
			Email_Provider::Mailgun => ( new SyncFromMailgun() )->handle( $args ),
			default                 => [
				'success' => false,
				'synced'  => 0,
				'updated' => 0,
				'skipped' => 0,
				'total'   => 0,
				/* translators: %s: provider name */
				'message' => sprintf( __( 'Sync is not supported for provider "%s".', 'mail-chronicle' ), $provider->value ),
			],
		};
	}

	/**
	 * Check permissions
	 */
	public function permissions_check(): bool|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to sync emails.', 'mail-chronicle' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Accepted request parameters.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_sync_params(): array {
		return [
			'limit' => [
				'description'       => __( 'Maximum number of events per page.', 'mail-chronicle' ),
				'type'              => 'integer',
				'minimum'           => 1,
				'maximum'           => 300,
				'sanitize_callback' => 'absint',
			],
			'days'  => [
				'description'       => __( 'Look-back window in days. Omit to use stored cursor (sync latest only).', 'mail-chronicle' ),
				'type'              => 'integer',
				'minimum'           => 1,
				'maximum'           => 30,
				'sanitize_callback' => 'absint',
			],
		];
	}
}
