<?php
/**
 * ProcessMailgunWebhook Feature Tests
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests\Unit\Features;

use MailChronicle\Tests\TestCase;
use MailChronicle\Common\Repository\EmailRepositoryInterface;
use MailChronicle\Common\Repository\ProviderEventRepositoryInterface;
use MailChronicle\Features\ManageSettings\ManageSettingsInterface;
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
		$manage_settings = Mockery::mock( ManageSettingsInterface::class );
		$manage_settings->shouldReceive( 'get' )
			->andReturn( array( 'mailgun_api_key' => 'test-key' ) );

		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$event_repository = Mockery::mock( ProviderEventRepositoryInterface::class );
		$handler          = new ProcessMailgunWebhook( $email_repository, $event_repository, $manage_settings );

		$payload = array(
			'signature'  => array(
				'token'     => 'token',
				'timestamp' => (string) time(),
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
		$manage_settings = Mockery::mock( ManageSettingsInterface::class );
		$manage_settings->shouldReceive( 'get' )
			->andReturn( array( 'mailgun_api_key' => '' ) );

		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$event_repository = Mockery::mock( ProviderEventRepositoryInterface::class );
		$handler          = new ProcessMailgunWebhook( $email_repository, $event_repository, $manage_settings );

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
		$manage_settings = Mockery::mock( ManageSettingsInterface::class );
		$manage_settings->shouldReceive( 'get' )
			->andReturn( array( 'mailgun_api_key' => '' ) );

		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$event_repository = Mockery::mock( ProviderEventRepositoryInterface::class );
		$handler          = new ProcessMailgunWebhook( $email_repository, $event_repository, $manage_settings );

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
	 * Test handle processes valid webhook for a new message (not in DB)
	 */
	public function test_handle_processes_valid_webhook() {
		$timestamp = (string) time();
		$token     = 'test-token';
		$api_key   = 'test-key';

		$manage_settings = Mockery::mock( ManageSettingsInterface::class );
		$manage_settings->shouldReceive( 'get' )
			->andReturn( array( 'mailgun_api_key' => $api_key ) );

		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$event_repository = Mockery::mock( ProviderEventRepositoryInterface::class );

		// find_or_create_log: message not in DB → save new log.
		$email_repository->shouldReceive( 'find_id_by_provider_message_id' )
			->once()
			->andReturn( null );

		$email_repository->shouldReceive( 'save' )
			->once()
			->andReturn( 123 );

		// maybe_update_status: 'delivered' maps to Email_Status::Delivered.
		$email_repository->shouldReceive( 'get_status' )
			->once()
			->with( 123 )
			->andReturn( 'pending' );

		$email_repository->shouldReceive( 'update_status' )
			->once();

		// save_event via event repository.
		$event_repository->shouldReceive( 'save' )
			->once()
			->andReturn( 1 );

		$signature = hash_hmac( 'sha256', $timestamp . $token, $api_key );
		$handler   = new ProcessMailgunWebhook( $email_repository, $event_repository, $manage_settings );

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
	 * Test handle returns false when insert fails (no log ID)
	 */
	public function test_handle_returns_false_when_email_not_found() {
		$timestamp = (string) time();
		$token     = 'test-token';
		$api_key   = 'test-key';

		$manage_settings = Mockery::mock( ManageSettingsInterface::class );
		$manage_settings->shouldReceive( 'get' )
			->andReturn( array( 'mailgun_api_key' => $api_key ) );

		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$event_repository = Mockery::mock( ProviderEventRepositoryInterface::class );

		// Not found in DB, and insert fails.
		$email_repository->shouldReceive( 'find_id_by_provider_message_id' )
			->once()
			->andReturn( null );

		$email_repository->shouldReceive( 'save' )
			->once()
			->andReturn( false );

		$signature = hash_hmac( 'sha256', $timestamp . $token, $api_key );
		$handler   = new ProcessMailgunWebhook( $email_repository, $event_repository, $manage_settings );

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
}
