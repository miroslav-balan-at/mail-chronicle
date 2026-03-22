<?php
/**
 * Webhook REST API Controller
 * Part of ProcessMailgunWebhook feature
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\ProcessMailgunWebhook;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

/**
 * Webhook Controller Class
 */
class WebhookController extends WP_REST_Controller {

	private ProcessMailgunWebhook $handler;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'mail-chronicle/v1';
		$this->rest_base = 'webhook';
		$this->handler   = new ProcessMailgunWebhook();
	}

	/**
	 * Register routes
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/mailgun',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_mailgun' ],
					'permission_callback' => '__return_true', // Signature verification in handler.
				],
			]
		);
	}

	public function handle_mailgun( \WP_REST_Request $request ): \WP_REST_Response|WP_Error {
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) || [] === $payload ) {
			$payload = $request->get_body_params();
		}

		$result = $this->handler->handle( $payload );

		if ( ! $result ) {
			return new WP_Error(
				'webhook_failed',
				__( 'Failed to process webhook', 'mail-chronicle' ),
				[ 'status' => 400 ]
			);
		}

		return rest_ensure_response( [ 'success' => true ] );
	}
}
