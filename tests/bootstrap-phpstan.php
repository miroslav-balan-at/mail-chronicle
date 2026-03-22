<?php
/**
 * PHPStan bootstrap — defines plugin constants so src/ files can be analysed
 * without a real WordPress installation.
 *
 * @package MailChronicle
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
define( 'MAIL_CHRONICLE_VERSION', '1.0.0' );
define( 'MAIL_CHRONICLE_FILE', dirname( __DIR__ ) . '/mail-chronicle.php' );
define( 'MAIL_CHRONICLE_PATH', dirname( __DIR__ ) . '/' );
define( 'MAIL_CHRONICLE_URL', 'http://example.com/wp-content/plugins/mail-chronicle/' );
define( 'MAIL_CHRONICLE_BASENAME', 'mail-chronicle/mail-chronicle.php' );
