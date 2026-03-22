<?php
/**
 * ProcessMailgunWebhook Feature Tests
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests\Unit\Features;

use MailChronicle\Tests\TestCase;
use MailChronicle\Features\ProcessMailgunWebhook\ProcessMailgunWebhook;
use Mockery;

/**
 * ProcessMailgunWebhook Test Class
 */
class ProcessMailgunWebhookTest extends TestCase {

	/**
	 * Set up
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->mock_wordpress_functions();
	}

	/**
	 * Test handle returns false when the signature is invalid
	 */
	public function test_handle_returns_false_when_signature_invalid() {
		$GLOBALS['test_options'] = array( 'mailgun_api_key' => 'test-key' );

		$handler = $this->create_handler();

		$payload = array(
			'signature'  => array(
				'token'     => 'token',
				'timestamp' => time(),
				'signature' => 'invalid-signature',
			),
			'event-data' => array(
				'event'   => 'delivered',
				'message' => array(
					'headers' => array(
						'message-id' => '<test@example.com>',
					),
				),
			),
		);

		$result = $handler->handle( $payload );

		$this->assertFalse( $result );
	}

	/**
	 * Test handle returns false when event data is missing
	 */
	public function test_handle_returns_false_when_event_data_missing() {
		$handler = $this->create_handler();

		$payload = array(
			'signature' => array(
				'token'     => 'token',
				'timestamp' => time(),
				'signature' => 'sig',
			),
		);

		$result = $handler->handle( $payload );

		$this->assertFalse( $result );
	}

	/**
	 * Test handle returns false when message ID is missing
	 */
	public function test_handle_returns_false_when_message_id_missing() {
		$handler = $this->create_handler();

		$payload = array(
			'signature'  => array(
				'token'     => 'token',
				'timestamp' => time(),
				'signature' => 'sig',
			),
			'event-data' => array(
				'event'   => 'delivered',
				'message' => array(),
			),
		);

		$result = $handler->handle( $payload );

		$this->assertFalse( $result );
	}

	/**
	 * Test handle processes valid webhook
	 */
	public function test_handle_processes_valid_webhook() {
		$timestamp = time();
		$token     = 'test-token';
		$api_key   = 'test-key';

		$this->set_mock_option(
			'mail_chronicle_settings',
			array( 'mailgun_api_key' => $api_key )
		);

		$wpdb    = $this->create_mock_wpdb();
		$handler = $this->create_handler_with_wpdb( $wpdb );

		// Calculate valid signature.
		$signature = hash_hmac( 'sha256', $timestamp . $token, $api_key );

		// Mock get_row to find email.
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $query, $values ) {
					return $query;
				}
			);

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn(
				(object) array(
					'id'        => 123,
					'recipient' => 'test@example.com',
				)
			);

		// Mock insert for event.
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with( 'wp_mail_chronicle_events', Mockery::any() )
			->andReturn( 1 );

		// Mock update for email status.
		$wpdb->shouldReceive( 'update' )
			->once()
			->with( 'wp_mail_chronicle_logs', Mockery::any(), array( 'id' => 123 ) )
			->andReturn( 1 );

		$payload = array(
			'signature'  => array(
				'token'     => $token,
				'timestamp' => $timestamp,
				'signature' => $signature,
			),
			'event-data' => array(
				'event'     => 'delivered',
				'timestamp' => time(),
				'message'   => array(
					'headers' => array(
						'message-id' => '<test@example.com>',
					),
				),
			),
		);

		$result = $handler->handle( $payload );

		$this->assertTrue( $result );
	}

	/**
	 * Test handle returns false when email not found
	 */
	public function test_handle_returns_false_when_email_not_found() {
		$timestamp = time();
		$token     = 'test-token';
		$api_key   = 'test-key';

		$this->set_mock_option(
			'mail_chronicle_settings',
			array( 'mailgun_api_key' => $api_key )
		);

		$wpdb    = $this->create_mock_wpdb();
		$handler = $this->create_handler_with_wpdb( $wpdb );

		$signature = hash_hmac( 'sha256', $timestamp . $token, $api_key );

		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $query, $values ) {
					return $query;
				}
			);

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( null );

		$payload = array(
			'signature'  => array(
				'token'     => $token,
				'timestamp' => $timestamp,
				'signature' => $signature,
			),
			'event-data' => array(
				'event'   => 'delivered',
				'message' => array(
					'headers' => array(
						'message-id' => '<unknown@example.com>',
					),
				),
			),
		);

		$result = $handler->handle( $payload );

		$this->assertFalse( $result );
	}

	/**
	 * Create handler
	 *
	 * @return ProcessMailgunWebhook
	 */
	private function create_handler() {
		$wpdb = $this->create_mock_wpdb();
		return $this->create_handler_with_wpdb( $wpdb );
	}

	/**
	 * Create handler with wpdb
	 *
	 * @param mixed $wpdb WordPress database.
	 * @return ProcessMailgunWebhook
	 */
	private function create_handler_with_wpdb( $wpdb ) {
		$GLOBALS['wpdb'] = $wpdb;

		// Override get_option for this test.
		if ( ! function_exists( 'MailChronicle\Tests\Unit\Features\get_option' ) ) {
			eval(
				'namespace MailChronicle\Tests\Unit\Features;
				function get_option($option, $default = false) {
					return $GLOBALS["test_options"] ?? $default;
				}'
			);
		}

		return new ProcessMailgunWebhook();
	}
}

