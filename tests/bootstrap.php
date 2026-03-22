<?php
/**
 * PHPUnit bootstrap file
 *
 * @package MailChronicle
 */

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define constants for testing.
if ( ! defined( 'MAIL_CHRONICLE_PATH' ) ) {
	define( 'MAIL_CHRONICLE_PATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'MAIL_CHRONICLE_URL' ) ) {
	define( 'MAIL_CHRONICLE_URL', 'http://example.com/wp-content/plugins/mail-chronicle/' );
}

if ( ! defined( 'MAIL_CHRONICLE_VERSION' ) ) {
	define( 'MAIL_CHRONICLE_VERSION', '1.0.0' );
}

if ( ! defined( 'MAIL_CHRONICLE_FILE' ) ) {
	define( 'MAIL_CHRONICLE_FILE', dirname( __DIR__ ) . '/mail-chronicle.php' );
}

if ( ! defined( 'MAIL_CHRONICLE_BASENAME' ) ) {
	define( 'MAIL_CHRONICLE_BASENAME', 'mail-chronicle/mail-chronicle.php' );
}

// WordPress test library.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Check if WordPress test library exists.
if ( file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// Give access to tests_add_filter() function.
	require_once $_tests_dir . '/includes/functions.php';

	/**
	 * Manually load the plugin being tested.
	 */
	function _manually_load_plugin() {
		require dirname( __DIR__ ) . '/mail-chronicle.php';
	}
	tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

	// Start up the WP testing environment.
	require $_tests_dir . '/includes/bootstrap.php';
} else {
	// Fallback for unit tests without WordPress.
	echo "WordPress test library not found. Running unit tests without WordPress integration.\n";

	// Mock WordPress functions for unit tests.
	require_once __DIR__ . '/wordpress-mocks.php';
}

