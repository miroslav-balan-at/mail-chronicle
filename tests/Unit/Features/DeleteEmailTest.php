<?php
/**
 * DeleteEmail Feature Tests
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests\Unit\Features;

use MailChronicle\Tests\TestCase;
use MailChronicle\Common\Repository\EmailRepositoryInterface;
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
	 * Test handle deletes email successfully
	 */
	public function test_handle_deletes_email_successfully() {
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$email_repository->shouldReceive( 'delete' )
			->once()
			->with( 123 )
			->andReturn( true );

		$handler = new DeleteEmail( $email_repository );
		$result  = $handler->handle( 123 );

		$this->assertTrue( $result );
	}

	/**
	 * Test handle returns false when delete fails
	 */
	public function test_handle_returns_false_when_delete_fails() {
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$email_repository->shouldReceive( 'delete' )
			->once()
			->with( 999 )
			->andReturn( false );

		$handler = new DeleteEmail( $email_repository );
		$result  = $handler->handle( 999 );

		$this->assertFalse( $result );
	}

	/**
	 * Test handle returns false when email not found
	 */
	public function test_handle_returns_false_when_email_not_found() {
		$email_repository = Mockery::mock( EmailRepositoryInterface::class );
		$email_repository->shouldReceive( 'delete' )
			->once()
			->with( 999 )
			->andReturn( false );

		$handler = new DeleteEmail( $email_repository );
		$result  = $handler->handle( 999 );

		$this->assertFalse( $result );
	}
}
