<?php
/**
 * LogEmail Edge Cases Tests
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests\Unit\Features;

use MailChronicle\Tests\TestCase;
use MailChronicle\Features\LogEmail\LogEmail;
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
		$this->set_mock_option(
			'mail_chronicle_settings',
			array(
				'enabled'  => true,
				'provider' => 'wordpress',
			)
		);

		$wpdb   = $this->create_mock_wpdb();
		$logger = $this->create_logger_with_wpdb( $wpdb );

		$inserted_data = null;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturnUsing(
				function ( $table, $data ) use ( &$inserted_data ) {
					$inserted_data = $data;
					return 1;
				}
			);

		$wpdb->insert_id = 123;

		$args = array(
			'to'      => array( 'first@example.com', 'second@example.com' ),
			'subject' => 'Test',
			'message' => 'Test',
			'headers' => '',
		);

		$logger->handle( $args );

		// Should use first recipient.
		$this->assertEquals( 'first@example.com', $inserted_data['recipient'] );
	}

	/**
	 * Test handle with empty subject
	 */
	public function test_handle_with_empty_subject() {
		$this->set_mock_option(
			'mail_chronicle_settings',
			array(
				'enabled'  => true,
				'provider' => 'wordpress',
			)
		);

		$wpdb   = $this->create_mock_wpdb();
		$logger = $this->create_logger_with_wpdb( $wpdb );

		$inserted_data = null;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturnUsing(
				function ( $table, $data ) use ( &$inserted_data ) {
					$inserted_data = $data;
					return 1;
				}
			);

		$wpdb->insert_id = 123;

		$args = array(
			'to'      => 'test@example.com',
			'subject' => '',
			'message' => 'Test',
			'headers' => '',
		);

		$logger->handle( $args );

		$this->assertEquals( '', $inserted_data['subject'] );
	}

	/**
	 * Test handle with array headers
	 */
	public function test_handle_with_array_headers() {
		$this->set_mock_option(
			'mail_chronicle_settings',
			array(
				'enabled'  => true,
				'provider' => 'wordpress',
			)
		);

		$wpdb   = $this->create_mock_wpdb();
		$logger = $this->create_logger_with_wpdb( $wpdb );

		$inserted_data = null;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturnUsing(
				function ( $table, $data ) use ( &$inserted_data ) {
					$inserted_data = $data;
					return 1;
				}
			);

		$wpdb->insert_id = 123;

		$args = array(
			'to'      => 'test@example.com',
			'subject' => 'Test',
			'message' => 'Test',
			'headers' => array( 'Content-Type: text/html', 'From: sender@example.com' ),
		);

		$logger->handle( $args );

		$this->assertStringContainsString( 'Content-Type: text/html', $inserted_data['headers'] );
		$this->assertStringContainsString( 'From: sender@example.com', $inserted_data['headers'] );
	}

	/**
	 * Test handle with attachments array
	 */
	public function test_handle_with_attachments_array() {
		$this->set_mock_option(
			'mail_chronicle_settings',
			array(
				'enabled'  => true,
				'provider' => 'wordpress',
			)
		);

		$wpdb   = $this->create_mock_wpdb();
		$logger = $this->create_logger_with_wpdb( $wpdb );

		$inserted_data = null;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturnUsing(
				function ( $table, $data ) use ( &$inserted_data ) {
					$inserted_data = $data;
					return 1;
				}
			);

		$wpdb->insert_id = 123;

		$args = array(
			'to'          => 'test@example.com',
			'subject'     => 'Test',
			'message'     => 'Test',
			'headers'     => '',
			'attachments' => array( '/path/to/file1.pdf', '/path/to/file2.jpg' ),
		);

		$logger->handle( $args );

		$this->assertNotEmpty( $inserted_data['attachments'] );
		$this->assertStringContainsString( 'file1.pdf', $inserted_data['attachments'] );
	}

	/**
	 * Test handle with very long message
	 */
	public function test_handle_with_very_long_message() {
		$this->set_mock_option(
			'mail_chronicle_settings',
			array(
				'enabled'  => true,
				'provider' => 'wordpress',
			)
		);

		$wpdb   = $this->create_mock_wpdb();
		$logger = $this->create_logger_with_wpdb( $wpdb );

		$inserted_data = null;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturnUsing(
				function ( $table, $data ) use ( &$inserted_data ) {
					$inserted_data = $data;
					return 1;
				}
			);

		$wpdb->insert_id = 123;

		$long_message = str_repeat( 'This is a very long message. ', 1000 );

		$args = array(
			'to'      => 'test@example.com',
			'subject' => 'Test',
			'message' => $long_message,
			'headers' => '',
		);

		$logger->handle( $args );

		$this->assertNotEmpty( $inserted_data['message_html'] );
		$this->assertNotEmpty( $inserted_data['message_plain'] );
	}

	/**
	 * Create logger with wpdb
	 *
	 * @param mixed $wpdb WordPress database.
	 * @return LogEmail
	 */
	private function create_logger_with_wpdb( $wpdb ) {
		$GLOBALS['wpdb'] = $wpdb;

		if ( ! function_exists( 'MailChronicle\Tests\Unit\Features\get_option' ) ) {
			eval(
				'namespace MailChronicle\Tests\Unit\Features;
				function get_option($option, $default = false) {
					return $GLOBALS["test_options"] ?? $default;
				}'
			);
		}

		return new LogEmail();
	}
}

