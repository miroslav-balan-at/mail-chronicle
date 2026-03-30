<?php
/**
 * Email Logs Controller Integration Tests
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests\Integration;

use MailChronicle\Tests\TestCase;
use MailChronicle\Features\GetEmails\EmailLogsController;
use MailChronicle\Features\GetEmails\GetEmailsInterface;
use MailChronicle\Features\DeleteEmail\DeleteEmailInterface;
use MailChronicle\Features\FetchStoredContent\FetchStoredContentInterface;
use Mockery;

/**
 * Email Logs Controller Test Class
 */
class EmailLogsControllerTest extends TestCase {

	/**
	 * Test register routes registers all endpoints
	 */
	public function test_register_routes_registers_all_endpoints() {
		$wpdb       = $this->create_mock_wpdb();
		$get_emails    = Mockery::mock( GetEmailsInterface::class );
		$delete_emails = Mockery::mock( DeleteEmailInterface::class );
		$fetch_content = Mockery::mock( FetchStoredContentInterface::class );
		$controller    = new EmailLogsController( $get_emails, $delete_emails, $fetch_content );

		// Mock register_rest_route calls
		global $_mock_rest_routes;
		$_mock_rest_routes = array();

		$controller->register_routes();

		// Verify routes were registered
		$this->assertNotEmpty( $_mock_rest_routes );
	}

	/**
	 * Test permissions check requires manage_options
	 */
	public function test_permissions_check_requires_manage_options() {
		$wpdb       = $this->create_mock_wpdb();
		$get_emails    = Mockery::mock( GetEmailsInterface::class );
		$delete_emails = Mockery::mock( DeleteEmailInterface::class );
		$fetch_content = Mockery::mock( FetchStoredContentInterface::class );
		$controller    = new EmailLogsController( $get_emails, $delete_emails, $fetch_content );

		// Test with permission
		global $_mock_current_user_can;
		$_mock_current_user_can = true;

		$result = $controller->permissions_check();
		$this->assertTrue( $result );

		// Test without permission
		$_mock_current_user_can = false;
		$result                 = $controller->permissions_check();
		$this->assertFalse( $result );
	}

	/**
	 * Test get collection params returns correct structure
	 */
	public function test_get_collection_params_returns_correct_structure() {
		$wpdb       = $this->create_mock_wpdb();
		$get_emails    = Mockery::mock( GetEmailsInterface::class );
		$delete_emails = Mockery::mock( DeleteEmailInterface::class );
		$fetch_content = Mockery::mock( FetchStoredContentInterface::class );
		$controller    = new EmailLogsController( $get_emails, $delete_emails, $fetch_content );

		$params = $controller->get_collection_params();

		$this->assertIsArray( $params );
		$this->assertArrayHasKey( 'page', $params );
		$this->assertArrayHasKey( 'per_page', $params );
		$this->assertArrayHasKey( 'status', $params );
		$this->assertArrayHasKey( 'search', $params );
	}
}

