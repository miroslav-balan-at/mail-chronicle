<?php
/**
 * Email Logs REST API Controller
 * Part of GetEmails feature
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\GetEmails;

use MailChronicle\Features\DeleteEmail\DeleteEmail;
use MailChronicle\Features\DeleteEmail\DeleteEmailInterface;
use MailChronicle\Features\FetchStoredContent\FetchStoredContentInterface;
use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

/**
 * Email Logs Controller Class
 */
class EmailLogsController extends WP_REST_Controller {

	private GetEmailsInterface $handler;

	private DeleteEmailInterface $delete_handler;

	private FetchStoredContentInterface $fetch_content;

	/**
	 * Constructor
	 *
	 * @param GetEmailsInterface          $handler       Query handler.
	 * @param DeleteEmailInterface        $delete_handler Delete handler.
	 * @param FetchStoredContentInterface $fetch_content  On-demand body fetcher.
	 */
	public function __construct( GetEmailsInterface $handler, DeleteEmailInterface $delete_handler, FetchStoredContentInterface $fetch_content ) {
		$this->namespace      = 'mail-chronicle/v1';
		$this->rest_base      = 'emails';
		$this->handler        = $handler;
		$this->delete_handler = $delete_handler;
		$this->fetch_content  = $fetch_content;
	}

	/**
	 * Register routes
	 */
	public function register_routes(): void {
		// GET /emails - List emails.
		// DELETE /emails - Delete all emails.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_all_items' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		// GET /emails/{id} - Get single email.
		// DELETE /emails/{id} - Delete email.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		// GET /emails/{id}/events - Get email events.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/events',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_events' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);
	}

	public function get_items( mixed $request ): \WP_REST_Response {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Narrows mixed to WP_REST_Request for PHPStan.
		/** @var \WP_REST_Request $request */
		$args = [
			'per_page'  => $request->get_param( 'per_page' ) ?? 20,
			'page'      => $request->get_param( 'page' ) ?? 1,
			'orderby'   => $request->get_param( 'orderby' ) ?? 'sent_at',
			'order'     => $request->get_param( 'order' ) ?? 'DESC',
			'status'    => $request->get_param( 'status' ) ?? '',
			'provider'  => $request->get_param( 'provider' ) ?? '',
			'search'    => $request->get_param( 'search' ) ?? '',
			'date_from' => $request->get_param( 'date_from' ) ?? '',
			'date_to'   => $request->get_param( 'date_to' ) ?? '',
			'domain'    => $request->get_param( 'domain' ) ?? '',
		];

		$result = $this->handler->handle( $args );

		$data = array_map(
			function ( $email ) {
				return $email->to_array();
			},
			$result['emails']
		);

		$response = rest_ensure_response( $data );
		$total    = is_int( $result['total'] ) ? $result['total'] : (int) $result['total'];
		$per_page = is_numeric( $args['per_page'] ) ? (int) $args['per_page'] : 20;
		$per_page = max( 1, $per_page );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) ceil( $total / $per_page ) );

		return $response;
	}

	public function get_item( mixed $request ): \WP_REST_Response|WP_Error {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Narrows mixed to WP_REST_Request for PHPStan.
		/** @var \WP_REST_Request $request */
		$id_param = $request->get_param( 'id' );
		$id       = is_numeric( $id_param ) ? (int) $id_param : 0;

		$email = $this->fetch_content->handle( $id );

		if ( null === $email ) {
			return new WP_Error( 'not_found', __( 'Email not found', 'mail-chronicle' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $email->to_array() );
	}

	public function get_events( \WP_REST_Request $request ): \WP_REST_Response {
		$id_param = $request->get_param( 'id' );
		$events   = $this->handler->get_events( is_numeric( $id_param ) ? (int) $id_param : 0 );
		return rest_ensure_response( $events );
	}

	/**
	 * Delete all emails
	 */
	public function delete_all_items(): \WP_REST_Response|WP_Error {
		$result = $this->delete_handler->delete_all();

		if ( $result < 0 ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete all emails', 'mail-chronicle' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [ 'deleted' => true ] );
	}

	public function delete_item( mixed $request ): \WP_REST_Response|WP_Error {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Narrows mixed to WP_REST_Request for PHPStan.
		/** @var \WP_REST_Request $request */
		$id_param = $request->get_param( 'id' );
		$result   = $this->delete_handler->handle( is_numeric( $id_param ) ? (int) $id_param : 0 );

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete email', 'mail-chronicle' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [ 'deleted' => true ] );
	}

	/**
	 * Check permissions
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get collection params
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		return [
			'per_page'  => [
				'type'    => 'integer',
				'default' => 20,
			],
			'page'      => [
				'type'    => 'integer',
				'default' => 1,
			],
			'orderby'   => [
				'type'    => 'string',
				'default' => 'sent_at',
			],
			'order'     => [
				'type'    => 'string',
				'default' => 'DESC',
			],
			'status'    => [
				'type' => 'string',
			],
			'provider'  => [
				'type' => 'string',
			],
			'search'    => [
				'type' => 'string',
			],
			'date_from' => [
				'type' => 'string',
			],
			'date_to'   => [
				'type' => 'string',
			],
			'domain'    => [
				'type' => 'string',
			],
		];
	}
}
