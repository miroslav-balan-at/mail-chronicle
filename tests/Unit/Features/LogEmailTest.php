<?php
/**
 * LogEmail Feature Tests
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests\Unit\Features;

use MailChronicle\Tests\TestCase;
use MailChronicle\Features\LogEmail\LogEmail;
use MailChronicle\Features\ManageSettings\ManageSettingsInterface;
use MailChronicle\Common\Repository\EmailRepositoryInterface;
use Mockery;

/**
 * LogEmail Test Class
 */
class LogEmailTest extends TestCase {

	/**
	 * Set up
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->mock_wordpress_functions();
	}

	/**
	 * Test handle returns args unchanged when logging is disabled
	 */
	public function test_handle_returns_args_when_logging_disabled() {
		$manage_settings = Mockery::mock( ManageSettingsInterface::class );
		$manage_settings->shouldReceive( 'get' )
			->andReturn( array( 'enabled' => false ) );

		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$logger           = new LogEmail( $email_repository, $manage_settings );

		$args = array(
			'to'      => 'test@example.com',
			'subject' => 'Test Subject',
			'message' => 'Test message',
			'headers' => '',
		);

		$result = $logger->handle( $args );

		$this->assertEquals( $args, $result );
	}

	/**
	 * Test handle logs email when logging is enabled
	 */
	public function test_handle_logs_email_when_enabled() {
		$manage_settings = Mockery::mock( ManageSettingsInterface::class );
		$manage_settings->shouldReceive( 'get' )
			->andReturn( array( 'enabled' => true, 'provider' => 'mailgun' ) );

		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$email_repository->shouldReceive( 'save' )
			->once()
			->andReturn( 123 );

		$logger = new LogEmail( $email_repository, $manage_settings );

		$args = array(
			'to'          => 'test@example.com',
			'subject'     => 'Test Subject',
			'message'     => 'Test message',
			'headers'     => '',
			'attachments' => array(),
		);

		$result = $logger->handle( $args );

		$this->assertEquals( $args, $result );
	}

	/**
	 * Test handle sanitizes email data
	 */
	public function test_handle_sanitizes_email_data() {
		$manage_settings = Mockery::mock( ManageSettingsInterface::class );
		$manage_settings->shouldReceive( 'get' )
			->andReturn( array( 'enabled' => true, 'provider' => 'wordpress' ) );

		$saved_email      = null;
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$email_repository->shouldReceive( 'save' )
			->once()
			->andReturnUsing(
				function ( $email ) use ( &$saved_email ) {
					$saved_email = $email;
					return 123;
				}
			);

		$logger = new LogEmail( $email_repository, $manage_settings );

		$args = array(
			'to'      => 'test@example.com',
			'subject' => '<script>alert("xss")</script>Test',
			'message' => 'Test message',
			'headers' => '',
		);

		$logger->handle( $args );

		$this->assertNotNull( $saved_email );
		$this->assertEquals( 'test@example.com', $saved_email->get_recipient() );
		$this->assertStringNotContainsString( '<script>', $saved_email->get_subject() );
	}

	/**
	 * Test handle converts plain text to HTML
	 */
	public function test_handle_converts_plain_text_to_html() {
		$manage_settings = Mockery::mock( ManageSettingsInterface::class );
		$manage_settings->shouldReceive( 'get' )
			->andReturn( array( 'enabled' => true, 'provider' => 'wordpress' ) );

		$saved_email      = null;
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$email_repository->shouldReceive( 'save' )
			->once()
			->andReturnUsing(
				function ( $email ) use ( &$saved_email ) {
					$saved_email = $email;
					return 123;
				}
			);

		$logger = new LogEmail( $email_repository, $manage_settings );

		$args = array(
			'to'      => 'test@example.com',
			'subject' => 'Test',
			'message' => "Line 1\nLine 2",
			'headers' => '',
		);

		$logger->handle( $args );

		$this->assertStringContainsString( '<br', $saved_email->get_message_html() );
	}

	/**
	 * Test handle preserves HTML message
	 */
	public function test_handle_preserves_html_message() {
		$manage_settings = Mockery::mock( ManageSettingsInterface::class );
		$manage_settings->shouldReceive( 'get' )
			->andReturn( array( 'enabled' => true, 'provider' => 'wordpress' ) );

		$saved_email      = null;
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$email_repository->shouldReceive( 'save' )
			->once()
			->andReturnUsing(
				function ( $email ) use ( &$saved_email ) {
					$saved_email = $email;
					return 123;
				}
			);

		$logger = new LogEmail( $email_repository, $manage_settings );

		$html_message = '<p>Test message</p>';
		$args         = array(
			'to'      => 'test@example.com',
			'subject' => 'Test',
			'message' => $html_message,
			'headers' => '',
		);

		$logger->handle( $args );

		$this->assertEquals( $html_message, $saved_email->get_message_html() );
	}
}
