<?php
/**
 * Fetch Body REST API Controller
 * Part of FetchStoredContent feature
 *
 * Exposes a single endpoint that fetches one pending email body per call.
 * The frontend calls this sequentially after sync completes until no
 * pending bodies remain.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\FetchStoredContent;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Fetch Body Controller
 */
class FetchBodyController extends WP_REST_Controller {

	private FetchStoredContentInterface $handler;

	public function __construct( FetchStoredContentInterface $handler ) {
		$this->namespace = 'mail-chronicle/v1';
		$this->rest_base = 'emails/fetch-body';
		$this->handler   = $handler;
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'fetch_next' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);
	}

	/**
	 * Fetch one pending email body.
	 */
	public function fetch_next( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->handler->fetch_next_pending();

		return new WP_REST_Response( $result, 200 );
	}

	public function permissions_check(): bool|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to fetch email content.', 'mail-chronicle' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}
}
