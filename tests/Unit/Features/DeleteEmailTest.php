<?php
/**
 * DeleteEmail Feature Tests
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests\Unit\Features;

use MailChronicle\Tests\TestCase;
use MailChronicle\Features\DeleteEmail\DeleteEmail;
use Mockery;

/**
 * DeleteEmail Test Class
 */
class DeleteEmailTest extends TestCase {

	/**
	 * Set up
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->mock_wordpress_functions();
	}

	/**
	 * Test handle deletes email and associated events successfully
	 */
	public function test_handle_deletes_email_successfully() {
		$wpdb    = $this->create_mock_wpdb();
		$handler = $this->create_handler_with_wpdb( $wpdb );

		// First call: delete associated events.
		$wpdb->shouldReceive( 'delete' )
			->once()
			->with(
				'wp_mail_chronicle_events',
				array( 'email_log_id' => 123 ),
				array( '%d' )
			)
			->andReturn( 2 );

		// Second call: delete the log itself.
		$wpdb->shouldReceive( 'delete' )
			->once()
			->with(
				'wp_mail_chronicle_logs',
				array( 'id' => 123 ),
				array( '%d' )
			)
			->andReturn( 1 );

		$result = $handler->handle( 123 );

		$this->assertTrue( $result );
	}

	/**
	 * Test handle returns false when delete fails
	 */
	public function test_handle_returns_false_when_delete_fails() {
		$wpdb    = $this->create_mock_wpdb();
		$handler = $this->create_handler_with_wpdb( $wpdb );

		// Events delete (may return 0 if none exist).
		$wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_mail_chronicle_events', Mockery::any(), Mockery::any() )
			->andReturn( 0 );

		// Log delete fails.
		$wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_mail_chronicle_logs', Mockery::any(), Mockery::any() )
			->andReturn( false );

		$result = $handler->handle( 999 );

		$this->assertFalse( $result );
	}

	/**
	 * Test handle returns false when email not found
	 */
	public function test_handle_returns_false_when_email_not_found() {
		$wpdb    = $this->create_mock_wpdb();
		$handler = $this->create_handler_with_wpdb( $wpdb );

		// Events delete (none to delete).
		$wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_mail_chronicle_events', Mockery::any(), Mockery::any() )
			->andReturn( 0 );

		// Log delete: 0 rows affected (email not found).
		$wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_mail_chronicle_logs', Mockery::any(), Mockery::any() )
			->andReturn( 0 );

		$result = $handler->handle( 999 );

		$this->assertFalse( $result );
	}

	/**
	 * Create handler with wpdb
	 *
	 * @param mixed $wpdb WordPress database.
	 * @return DeleteEmail
	 */
	private function create_handler_with_wpdb( $wpdb ) {
		$GLOBALS['wpdb'] = $wpdb;
		return new DeleteEmail();
	}
}

