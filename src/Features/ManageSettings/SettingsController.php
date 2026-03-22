<?php
/**
 * Settings REST API Controller
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\ManageSettings;

use MailChronicle\Common\Constants;
use MailChronicle\Common\Entities\Mailgun_Region;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Settings Controller Class
 */
class SettingsController extends WP_REST_Controller {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'mail-chronicle/v1';
		$this->rest_base = 'settings';
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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);
	}

	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$raw_settings = get_option( Constants::OPTION_SETTINGS, [] );
		$settings     = is_array( $raw_settings ) ? $raw_settings : [];

		return new WP_REST_Response(
			[
				'mailgun_api_key'  => ( isset( $settings['mailgun_api_key'] ) && '' !== $settings['mailgun_api_key'] ) ? '***' : '',
				'mailgun_domain'   => is_string( $settings['mailgun_domain'] ?? null ) ? $settings['mailgun_domain'] : '',
				'mailgun_region'   => is_string( $settings['mailgun_region'] ?? null ) ? $settings['mailgun_region'] : Mailgun_Region::US->value,
				'sendgrid_api_key' => ( isset( $settings['sendgrid_api_key'] ) && '' !== $settings['sendgrid_api_key'] ) ? '***' : '',
				'logging_enabled'  => isset( $settings['logging_enabled'] ) ? (bool) $settings['logging_enabled'] : true,
			],
			200
		);
	}

	/**
	 * Check permissions
	 */
	public function permissions_check(): bool|\WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access settings.', 'mail-chronicle' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}
}
