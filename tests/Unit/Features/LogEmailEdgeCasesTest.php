<?php
/**
 * LogEmail Edge Cases Tests
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
 * LogEmail Edge Cases Test Class
 */
class LogEmailEdgeCasesTest extends TestCase {

	/**
	 * Set up
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->mock_wordpress_functions();
	}

	/**
	 * Test handle with array of recipients
	 */
	public function test_handle_with_array_of_recipients() {
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
			'to'      => array( 'first@example.com', 'second@example.com' ),
			'subject' => 'Test',
			'message' => 'Test',
			'headers' => '',
		);

		$logger->handle( $args );

		// Should use first recipient.
		$this->assertEquals( 'first@example.com', $saved_email->get_recipient() );
	}

	/**
	 * Test handle with empty subject
	 */
	public function test_handle_with_empty_subject() {
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
			'subject' => '',
			'message' => 'Test',
			'headers' => '',
		);

		$logger->handle( $args );

		$this->assertEquals( '', $saved_email->get_subject() );
	}

	/**
	 * Test handle with array headers
	 */
	public function test_handle_with_array_headers() {
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
			'message' => 'Test',
			'headers' => array( 'Content-Type: text/html', 'From: sender@example.com' ),
		);

		$logger->handle( $args );

		$this->assertStringContainsString( 'Content-Type: text/html', $saved_email->get_headers() );
		$this->assertStringContainsString( 'From: sender@example.com', $saved_email->get_headers() );
	}

	/**
	 * Test handle with attachments array
	 */
	public function test_handle_with_attachments_array() {
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
			'to'          => 'test@example.com',
			'subject'     => 'Test',
			'message'     => 'Test',
			'headers'     => '',
			'attachments' => array( '/path/to/file1.pdf', '/path/to/file2.jpg' ),
		);

		$logger->handle( $args );

		$this->assertNotEmpty( $saved_email->get_attachments() );
		$this->assertStringContainsString( 'file1.pdf', $saved_email->get_attachments() );
	}

	/**
	 * Test handle with very long message
	 */
	public function test_handle_with_very_long_message() {
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

		$long_message = str_repeat( 'This is a very long message. ', 1000 );

		$args = array(
			'to'      => 'test@example.com',
			'subject' => 'Test',
			'message' => $long_message,
			'headers' => '',
		);

		$logger->handle( $args );

		$this->assertNotEmpty( $saved_email->get_message_html() );
		$this->assertNotEmpty( $saved_email->get_message_plain() );
	}
}
