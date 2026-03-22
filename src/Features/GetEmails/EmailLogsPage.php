<?php
/**
 * Email Logs Admin Page
 * Part of GetEmails feature
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\GetEmails;

defined( 'ABSPATH' ) || exit;

/**
 * Email Logs Page Class
 */
final class EmailLogsPage {

	public function add_menu_page(): void {
		add_submenu_page(
			'mail-chronicle',
			__( 'Email Logs', 'mail-chronicle' ),
			__( 'Email Logs', 'mail-chronicle' ),
			'manage_options',
			'mail-chronicle-logs',
			[ $this, 'render' ]
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'mail-chronicle' ) );
		}

		load_template( MAIL_CHRONICLE_PATH . 'templates/email-logs-page.php', false );
	}
}
