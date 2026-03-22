<?php
/**
 * GetEmails Feature Tests
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests\Unit\Features;

use MailChronicle\Tests\TestCase;
use MailChronicle\Features\GetEmails\GetEmails;
use Mockery;

/**
 * GetEmails Test Class
 */
class GetEmailsTest extends TestCase {

	/**
	 * Set up
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->mock_wordpress_functions();
	}

	/**
	 * Test handle returns emails with default args
	 */
	public function test_handle_returns_emails_with_defaults() {
		$wpdb    = $this->create_mock_wpdb();
		$handler = $this->create_handler_with_wpdb( $wpdb );

		// Mock get_var for count.
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 5 );

		// Mock prepare.
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $query, $values ) {
					return $query;
				}
			);

		// Mock get_results.
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array(
						'id'        => 1,
						'recipient' => 'test@example.com',
						'subject'   => 'Test',
						'status'    => 'sent',
					),
				)
			);

		$result = $handler->handle();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'emails', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertEquals( 5, $result['total'] );
		$this->assertCount( 1, $result['emails'] );
	}

	/**
	 * Test handle applies status filter
	 */
	public function test_handle_applies_status_filter() {
		$wpdb    = $this->create_mock_wpdb();
		$handler = $this->create_handler_with_wpdb( $wpdb );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 2 );

		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $query, $values ) {
					// Verify status filter is in query.
					$this->assertStringContainsString( 'status = %s', $query );
					return $query;
				}
			);

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$handler->handle( array( 'status' => 'failed' ) );
	}

	/**
	 * Test handle applies search filter
	 */
	public function test_handle_applies_search_filter() {
		$wpdb    = $this->create_mock_wpdb();
		$handler = $this->create_handler_with_wpdb( $wpdb );

		$wpdb->shouldReceive( 'esc_like' )
			->once()
			->with( 'search term' )
			->andReturn( 'search term' );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 1 );

		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $query, $values ) {
					// Verify search filter is in query.
					$this->assertStringContainsString( 'l.recipient LIKE %s OR l.subject LIKE %s', $query );
					return $query;
				}
			);

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$handler->handle( array( 'search' => 'search term' ) );
	}

	/**
	 * Test handle applies pagination
	 */
	public function test_handle_applies_pagination() {
		$wpdb    = $this->create_mock_wpdb();
		$handler = $this->create_handler_with_wpdb( $wpdb );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 100 );

		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $query, $values ) {
					// Verify LIMIT and OFFSET.
					$this->assertStringContainsString( 'LIMIT %d OFFSET %d', $query );
					// Page 2 with 20 per page = offset 20.
					$this->assertEquals( 20, end( $values ) );
					return $query;
				}
			);

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		$handler->handle(
			array(
				'page'     => 2,
				'per_page' => 20,
			)
		);
	}

	/**
	 * Test get_by_id returns email
	 */
	public function test_get_by_id_returns_email() {
		$wpdb    = $this->create_mock_wpdb();
		$handler = $this->create_handler_with_wpdb( $wpdb );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'SELECT * FROM wp_mail_chronicle_logs WHERE id = 123' );

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn(
				array(
					'id'        => 123,
					'recipient' => 'test@example.com',
					'subject'   => 'Test',
				)
			);

		$email = $handler->get_by_id( 123 );

		$this->assertNotNull( $email );
		$this->assertEquals( 123, $email->get_id() );
	}

	/**
	 * Test get_by_id returns null when not found
	 */
	public function test_get_by_id_returns_null_when_not_found() {
		$wpdb    = $this->create_mock_wpdb();
		$handler = $this->create_handler_with_wpdb( $wpdb );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'SELECT * FROM wp_mail_chronicle_logs WHERE id = 999' );

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( null );

		$email = $handler->get_by_id( 999 );

		$this->assertNull( $email );
	}

	/**
	 * Test get_events returns events
	 */
	public function test_get_events_returns_events() {
		$wpdb    = $this->create_mock_wpdb();
		$handler = $this->create_handler_with_wpdb( $wpdb );

		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'SELECT * FROM wp_mail_chronicle_events WHERE email_log_id = 123' );

		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					array(
						'id'           => 1,
						'email_log_id' => 123,
						'event_type'   => 'delivered',
					),
					array(
						'id'           => 2,
						'email_log_id' => 123,
						'event_type'   => 'opened',
					),
				)
			);

		$events = $handler->get_events( 123 );

		$this->assertIsArray( $events );
		$this->assertCount( 2, $events );
	}

	/**
	 * Create handler with wpdb
	 *
	 * @param mixed $wpdb WordPress database.
	 * @return GetEmails
	 */
	private function create_handler_with_wpdb( $wpdb ) {
		$GLOBALS['wpdb'] = $wpdb;
		return new GetEmails();
	}
}

