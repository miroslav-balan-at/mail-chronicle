<?php
/**
 * Plugin Name: Mail Chronicle
 * Plugin URI: https://hygienemitsystem.at
 * Description: Professional email logging with multi-provider support (Mailgun, SendGrid, etc.), event tracking, and comprehensive admin interface
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Miroslav Balan
 * Author URI: https://hygienemitsystem.at
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mail-chronicle
 * Domain Path: /languages
 *
 * @package MailChronicle
 */

namespace MailChronicle;

defined( 'ABSPATH' ) || exit;

// ── Plugin file constants ─────────────────────────────────────────────────────
define( 'MAIL_CHRONICLE_VERSION', '1.0.0' );
define( 'MAIL_CHRONICLE_FILE', __FILE__ );
define( 'MAIL_CHRONICLE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAIL_CHRONICLE_URL', plugin_dir_url( __FILE__ ) );
define( 'MAIL_CHRONICLE_BASENAME', plugin_basename( __FILE__ ) );

// Composer autoloader.
$mail_chronicle_autoloader = MAIL_CHRONICLE_PATH . 'vendor/autoload.php';
if ( file_exists( $mail_chronicle_autoloader ) ) {
	require_once $mail_chronicle_autoloader;
} else {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: plugin name */
							__( '<strong>%s</strong> requires Composer dependencies. Please run <code>composer install</code> in the plugin directory.', 'mail-chronicle' ),
							'Mail Chronicle'
						)
					);
					?>
				</p>
			</div>
			<?php
		}
	);
	return;
}

// Bootstrap the plugin.
Plugin::instance();

