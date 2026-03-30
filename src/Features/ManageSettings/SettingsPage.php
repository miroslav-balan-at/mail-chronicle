<?php
/**
 * Settings Admin Page
 * Part of ManageSettings feature
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\ManageSettings;

use MailChronicle\Common\Entities\Email_Provider;
use MailChronicle\Common\Entities\Mailgun_Region;
use MailChronicle\Features\DeleteEmail\DeleteEmailInterface;
use MailChronicle\Features\SyncFromMailgun\SyncFromMailgun;
use MailChronicle\Features\SyncFromMailgun\SyncScheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the settings admin menu page and delegates rendering to a template.
 */
final class SettingsPage {

	private ManageSettingsInterface $handler;

	private DeleteEmailInterface $delete_email;

	private SyncFromMailgun $sync_mailgun;

	public function __construct( ManageSettingsInterface $handler, DeleteEmailInterface $delete_email, SyncFromMailgun $sync_mailgun ) {
		$this->handler      = $handler;
		$this->delete_email = $delete_email;
		$this->sync_mailgun = $sync_mailgun;
	}

	public function add_menu_page(): void {
		add_submenu_page(
			'mail-chronicle',
			__( 'Settings', 'mail-chronicle' ),
			__( 'Settings', 'mail-chronicle' ),
			'manage_options',
			'mail-chronicle-settings',
			[ $this, 'render' ]
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'mail-chronicle' ) );
		}

		$this->handle_form_submissions();
		$this->enqueue_page_assets();

		$active_tab = $this->get_active_tab();
		$settings   = $this->handler->get();

		$template_vars = [
			'active_tab'        => $active_tab,
			'settings'          => $settings,
			'settings_url'      => admin_url( 'admin.php?page=mail-chronicle-settings' ),
			'providers'         => Email_Provider::cases(),
			'regions'           => Mailgun_Region::cases(),
			'sync_intervals'    => SyncScheduler::intervals(),
			'sync_days_options' => $this->sync_days_options(),
			'retention_options' => $this->retention_options(),
			'default_retention' => ManageSettings::DEFAULT_RETENTION_DAYS,
			'default_sync_days' => ManageSettings::DEFAULT_SYNC_DAYS,
		];

		$this->load_template( 'settings-page', $template_vars );
	}

	private function get_active_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		return in_array( $tab, [ 'general', 'maintenance' ], true ) ? $tab : 'general';
	}

	private function handle_form_submissions(): void {
		if ( isset( $_POST['mail_chronicle_settings_nonce'] ) && is_string( $_POST['mail_chronicle_settings_nonce'] ) && false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mail_chronicle_settings_nonce'] ) ), 'mail_chronicle_settings' ) ) {
			$this->save_settings();
		}

		if ( isset( $_POST['mail_chronicle_delete_all_nonce'] ) && is_string( $_POST['mail_chronicle_delete_all_nonce'] ) && false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mail_chronicle_delete_all_nonce'] ) ), 'mail_chronicle_delete_all' ) ) {
			$this->delete_all_logs();
		}
	}

	private function enqueue_page_assets(): void {
		$css_url     = MAIL_CHRONICLE_URL . 'assets/src/admin/settings/settings-page.css';
		$js_url      = MAIL_CHRONICLE_URL . 'assets/src/admin/settings/settings-page.js';
		$css_path    = MAIL_CHRONICLE_PATH . 'assets/src/admin/settings/settings-page.css';
		$js_path     = MAIL_CHRONICLE_PATH . 'assets/src/admin/settings/settings-page.js';
		$css_version = file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1';
		$js_version  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1';

		wp_enqueue_style( 'mail-chronicle-settings', $css_url, [], $css_version );
		wp_enqueue_script( 'mail-chronicle-settings', $js_url, [], $js_version, true );

		wp_localize_script(
			'mail-chronicle-settings',
			'mailChronicleSettings',
			[
				'i18n' => [
					'copy'   => __( 'Copy', 'mail-chronicle' ),
					'copied' => __( 'Copied!', 'mail-chronicle' ),
				],
			]
		);
	}

	private function save_settings(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in handle_form_submissions().
		$data = [
			'enabled'            => isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false,
			'provider'           => sanitize_text_field( wp_unslash( is_string( $_POST['provider'] ?? null ) ? $_POST['provider'] : Email_Provider::WordPress->value ) ),
			'mailgun_api_key'    => sanitize_text_field( wp_unslash( is_string( $_POST['mailgun_api_key'] ?? null ) ? $_POST['mailgun_api_key'] : '' ) ),
			'mailgun_domain'     => sanitize_text_field( wp_unslash( is_string( $_POST['mailgun_domain'] ?? null ) ? $_POST['mailgun_domain'] : '' ) ),
			'mailgun_region'     => sanitize_text_field( wp_unslash( is_string( $_POST['mailgun_region'] ?? null ) ? $_POST['mailgun_region'] : Mailgun_Region::US->value ) ),
			'log_retention_days' => absint( is_numeric( $_POST['log_retention_days'] ?? null ) ? $_POST['log_retention_days'] : ManageSettings::DEFAULT_RETENTION_DAYS ),
			'default_domain'     => sanitize_text_field( wp_unslash( is_string( $_POST['default_domain'] ?? null ) ? $_POST['default_domain'] : '' ) ),
		];
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $data['mailgun_api_key'] ) {
			$current                 = $this->handler->get();
			$data['mailgun_api_key'] = $current['mailgun_api_key'];
		}

		if ( $this->handler->update( $data ) ) {
			add_settings_error( 'mail_chronicle_settings', 'settings_updated', __( 'Settings saved successfully.', 'mail-chronicle' ), 'success' );
		}
	}

	private function delete_all_logs(): void {
		$this->delete_email->delete_all();
		$this->sync_mailgun->reset_cursor();

		add_settings_error( 'mail_chronicle_settings', 'logs_deleted', __( 'All email logs have been deleted.', 'mail-chronicle' ), 'success' );
	}

	/**
	 * Load a template file with extracted variables.
	 *
	 * @param array<string, mixed> $vars Passed as $args inside the template.
	 */
	private function load_template( string $name, array $vars ): void {
		$template = MAIL_CHRONICLE_PATH . "templates/{$name}.php";

		if ( ! file_exists( $template ) ) {
			return;
		}

		load_template( $template, false, $vars );
	}

	// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Return type annotation only; description in method name.
	/** @return array<int, string> */
	private function sync_days_options(): array {
		return [
			1  => __( '1 day', 'mail-chronicle' ),
			3  => __( '3 days', 'mail-chronicle' ),
			7  => __( '7 days', 'mail-chronicle' ),
			14 => __( '14 days', 'mail-chronicle' ),
			30 => __( '30 days', 'mail-chronicle' ),
		];
	}

	// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Return type annotation only; description in method name.
	/** @return array<int, string> */
	private function retention_options(): array {
		return [
			7   => __( '1 week', 'mail-chronicle' ),
			14  => __( '2 weeks', 'mail-chronicle' ),
			30  => __( '1 month', 'mail-chronicle' ),
			60  => __( '2 months', 'mail-chronicle' ),
			90  => __( '3 months', 'mail-chronicle' ),
			120 => __( '4 months', 'mail-chronicle' ),
			180 => __( '6 months', 'mail-chronicle' ),
			365 => __( '1 year', 'mail-chronicle' ),
			730 => __( '2 years', 'mail-chronicle' ),
			0   => __( 'Forever (never delete)', 'mail-chronicle' ),
		];
	}
}
