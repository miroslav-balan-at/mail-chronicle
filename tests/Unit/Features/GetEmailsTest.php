<?php
/**
 * GetEmails Feature Tests
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests\Unit\Features;

use MailChronicle\Tests\TestCase;
use MailChronicle\Common\Entities\Email;
use MailChronicle\Common\Repository\EmailRepositoryInterface;
use MailChronicle\Common\Repository\ProviderEventRepositoryInterface;
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
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$event_repository = Mockery::mock( ProviderEventRepositoryInterface::class );

		$email_repository->shouldReceive( 'query' )
			->once()
			->andReturn(
				array(
					'emails' => array(
						new Email(
							array(
								'id'        => 1,
								'recipient' => 'test@example.com',
								'subject'   => 'Test',
								'status'    => 'sent',
							)
						),
					),
					'total'  => 5,
				)
			);

		$handler = new GetEmails( $email_repository, $event_repository );
		$result  = $handler->handle();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'emails', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertEquals( 5, $result['total'] );
		$this->assertCount( 1, $result['emails'] );
	}

	/**
	 * Test handle passes status filter to repository query
	 */
	public function test_handle_applies_status_filter() {
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$event_repository = Mockery::mock( ProviderEventRepositoryInterface::class );

		$captured_query = null;
		$email_repository->shouldReceive( 'query' )
			->once()
			->andReturnUsing(
				function ( $email_query ) use ( &$captured_query ) {
					$captured_query = $email_query;
					return array( 'emails' => array(), 'total' => 0 );
				}
			);

		$handler = new GetEmails( $email_repository, $event_repository );
		$handler->handle( array( 'status' => 'failed' ) );

		$this->assertNotNull( $captured_query );
		$this->assertEquals( 'failed', $captured_query->status );
	}

	/**
	 * Test handle passes search filter to repository query
	 */
	public function test_handle_applies_search_filter() {
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$event_repository = Mockery::mock( ProviderEventRepositoryInterface::class );

		$captured_query = null;
		$email_repository->shouldReceive( 'query' )
			->once()
			->andReturnUsing(
				function ( $email_query ) use ( &$captured_query ) {
					$captured_query = $email_query;
					return array( 'emails' => array(), 'total' => 0 );
				}
			);

		$handler = new GetEmails( $email_repository, $event_repository );
		$handler->handle( array( 'search' => 'search term' ) );

		$this->assertNotNull( $captured_query );
		$this->assertEquals( 'search term', $captured_query->search );
	}

	/**
	 * Test handle passes pagination to repository query
	 */
	public function test_handle_applies_pagination() {
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$event_repository = Mockery::mock( ProviderEventRepositoryInterface::class );

		$captured_query = null;
		$email_repository->shouldReceive( 'query' )
			->once()
			->andReturnUsing(
				function ( $email_query ) use ( &$captured_query ) {
					$captured_query = $email_query;
					return array( 'emails' => array(), 'total' => 100 );
				}
			);

		$handler = new GetEmails( $email_repository, $event_repository );
		$handler->handle(
			array(
				'page'     => 2,
				'per_page' => 20,
			)
		);

		$this->assertNotNull( $captured_query );
		$this->assertEquals( 2, $captured_query->page );
		$this->assertEquals( 20, $captured_query->per_page );
		$this->assertEquals( 20, $captured_query->offset() );
	}

	/**
	 * Test get_by_id delegates to repository
	 */
	public function test_get_by_id_returns_email() {
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$event_repository = Mockery::mock( ProviderEventRepositoryInterface::class );

		$expected_email = new Email( array( 'id' => 123, 'recipient' => 'test@example.com', 'subject' => 'Test' ) );

		$email_repository->shouldReceive( 'find_by_id' )
			->once()
			->with( 123 )
			->andReturn( $expected_email );

		$handler = new GetEmails( $email_repository, $event_repository );
		$email   = $handler->get_by_id( 123 );

		$this->assertNotNull( $email );
		$this->assertEquals( 123, $email->get_id() );
	}

	/**
	 * Test get_by_id returns null when not found
	 */
	public function test_get_by_id_returns_null_when_not_found() {
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$event_repository = Mockery::mock( ProviderEventRepositoryInterface::class );

		$email_repository->shouldReceive( 'find_by_id' )
			->once()
			->with( 999 )
			->andReturn( null );

		$handler = new GetEmails( $email_repository, $event_repository );
		$email   = $handler->get_by_id( 999 );

		$this->assertNull( $email );
	}

	/**
	 * Test get_events delegates to event repository
	 */
	public function test_get_events_returns_events() {
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$event_repository = Mockery::mock( ProviderEventRepositoryInterface::class );

		$event_repository->shouldReceive( 'find_by_email_log_id' )
			->once()
			->with( 123 )
			->andReturn(
				array(
					array( 'id' => 1, 'email_log_id' => 123, 'event_type' => 'delivered' ),
					array( 'id' => 2, 'email_log_id' => 123, 'event_type' => 'opened' ),
				)
			);

		$handler = new GetEmails( $email_repository, $event_repository );
		$events  = $handler->get_events( 123 );

		$this->assertIsArray( $events );
		$this->assertCount( 2, $events );
	}
}
