<?php
/**
 * LogEmail Feature Tests
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests\Unit\Features;

use MailChronicle\Tests\TestCase;
use MailChronicle\Features\LogEmail\LogEmail;
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
		// Mock get_option to return disabled settings.
		$GLOBALS['test_options'] = array( 'enabled' => false );

		$logger = $this->create_logger_with_mock_wpdb();

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
		// Mock get_option to return enabled settings.
		$this->set_mock_option(
			'mail_chronicle_settings',
			array(
				'enabled'  => true,
				'provider' => 'mailgun',
			)
		);

		$wpdb   = $this->create_mock_wpdb();
		$logger = $this->create_logger_with_wpdb( $wpdb );

		// Expect insert to be called.
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$wpdb->insert_id = 123;

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
		$this->set_mock_option(
			'mail_chronicle_settings',
			array(
				'enabled'  => true,
				'provider' => 'wordpress',
			)
		);

		$wpdb   = $this->create_mock_wpdb();
		$logger = $this->create_logger_with_wpdb( $wpdb );

		// Capture the data passed to insert.
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
			'subject' => '<script>alert("xss")</script>Test',
			'message' => 'Test message',
			'headers' => '',
		);

		$logger->handle( $args );

		$this->assertNotNull( $inserted_data );
		$this->assertEquals( 'test@example.com', $inserted_data['recipient'] );
		$this->assertStringNotContainsString( '<script>', $inserted_data['subject'] );
	}

	/**
	 * Test handle converts plain text to HTML
	 */
	public function test_handle_converts_plain_text_to_html() {
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
			'message' => "Line 1\nLine 2",
			'headers' => '',
		);

		$logger->handle( $args );

		$this->assertStringContainsString( '<br', $inserted_data['message_html'] );
	}

	/**
	 * Test handle preserves HTML message
	 */
	public function test_handle_preserves_html_message() {
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

		$html_message = '<p>Test message</p>';
		$args         = array(
			'to'      => 'test@example.com',
			'subject' => 'Test',
			'message' => $html_message,
			'headers' => '',
		);

		$logger->handle( $args );

		$this->assertEquals( $html_message, $inserted_data['message_html'] );
	}

	/**
	 * Create logger with mock wpdb
	 *
	 * @return LogEmail
	 */
	private function create_logger_with_mock_wpdb() {
		$wpdb = $this->create_mock_wpdb();
		return $this->create_logger_with_wpdb( $wpdb );
	}

	/**
	 * Create logger with specific wpdb
	 *
	 * @param mixed $wpdb WordPress database.
	 * @return LogEmail
	 */
	private function create_logger_with_wpdb( $wpdb ) {
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

		return new LogEmail();
	}
}

