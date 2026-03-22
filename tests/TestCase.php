<?php
/**
 * Base Test Case
 *
 * @package MailChronicle
 */

namespace MailChronicle\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Mockery;

/**
 * Base Test Case Class
 */
abstract class TestCase extends PHPUnitTestCase {

	/**
	 * Set up
	 */
	protected function setUp(): void {
		parent::setUp();
		// Reset global mock storage before each test
		global $_mock_options, $_mock_actions, $_mock_json_response;
		$_mock_options       = array();
		$_mock_actions       = array();
		$_mock_json_response = null;
	}

	/**
	 * Tear down
	 */
	protected function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Create mock wpdb
	 *
	 * @return \Mockery\MockInterface
	 */
	protected function create_mock_wpdb() {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		return $wpdb;
	}

	/**
	 * Set mock option value
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 */
	protected function set_mock_option( $option, $value ) {
		global $_mock_options;
		$_mock_options[ $option ] = $value;
	}

	/**
	 * Get mock option value
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	protected function get_mock_option( $option, $default = false ) {
		global $_mock_options;
		return $_mock_options[ $option ] ?? $default;
	}

	/**
	 * Mock WordPress functions
	 */
	protected function mock_wordpress_functions() {
		// Functions are mocked in bootstrap.php to avoid redeclaration errors.
	}
}

