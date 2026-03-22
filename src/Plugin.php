<?php
/**
 * Main Plugin Class
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle;

use MailChronicle\Common\WordPress\Activator;
use MailChronicle\Common\WordPress\Deactivator;
use MailChronicle\Common\WordPress\HooksLoader;
use MailChronicle\Common\Constants;
use MailChronicle\Common\Entities\Email_Status;
use MailChronicle\Features\ManageSettings\ManageSettings;
use MailChronicle\Features\PurgeOldLogs\PurgeScheduler;
use MailChronicle\Features\SyncFromMailgun\SyncScheduler;
use MailChronicle\Features\GetEmails\EmailLogsPage;
use MailChronicle\Features\GetEmails\EmailLogsController;
use MailChronicle\Features\ManageSettings\SettingsPage;
use MailChronicle\Features\LogEmail\LogEmail;
use MailChronicle\Features\ProcessMailgunWebhook\WebhookController;
use MailChronicle\Features\Sync\SyncController;
use MailChronicle\Features\ManageSettings\SettingsController;

/**
 * Main Plugin Class
 */
final class Plugin {

	private static ?self $instance = null;

	private ServiceContainer $container;

	private ServiceProvider $service_provider;

	private HooksLoader $hooks_loader;

	/**
	 * Get plugin instance
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->container        = ServiceContainer::instance();
		$this->service_provider = new ServiceProvider( $this->container );
		$this->hooks_loader     = new HooksLoader();

		$this->service_provider->register();
		$this->register_hooks();
		$this->init();
	}


	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Activation/Deactivation.
		register_activation_hook( MAIL_CHRONICLE_FILE, [ Activator::class, 'activate' ] );
		register_deactivation_hook( MAIL_CHRONICLE_FILE, [ Deactivator::class, 'deactivate' ] );

		// Feature: Log Email.
		$log_email = $this->container->get( 'feature.log_email' );
		assert( $log_email instanceof LogEmail );
		$log_email->register_hooks();

		// Feature: Purge Old Logs.
		$purge_scheduler = $this->container->get( 'feature.purge_old_logs.scheduler' );
		assert( $purge_scheduler instanceof PurgeScheduler );
		$purge_scheduler->register_hooks();

		// Feature: Sync From Mailgun (cron_schedules must be registered early).
		$sync_scheduler = $this->container->get( 'feature.sync_mailgun.scheduler' );
		assert( $sync_scheduler instanceof SyncScheduler );
		$sync_scheduler->register_hooks();
	}

	/**
	 * Initialize plugin
	 *
	 * @return void
	 */
	private function init(): void {
		// Load text domain.
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

		// Auto-upgrade DB schema when version changes (e.g. new indexes).
		add_action( 'plugins_loaded', [ $this, 'maybe_upgrade_schema' ] );

		// Initialize admin.
		if ( is_admin() ) {
			$this->init_admin();
		}

		// Initialize REST API.
		add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );

		// Run hooks.
		$this->hooks_loader->run();
	}

	/**
	 * Initialize admin
	 *
	 * @return void
	 */
	private function init_admin(): void {
		$settings_page = $this->container->get( 'feature.manage_settings.page' );
		assert( $settings_page instanceof SettingsPage );
		$email_logs_page = $this->container->get( 'feature.get_emails.page' );
		assert( $email_logs_page instanceof EmailLogsPage );

		$this->hooks_loader->add_action( 'admin_menu', $settings_page, 'add_menu_page' );
		$this->hooks_loader->add_action( 'admin_menu', $email_logs_page, 'add_menu_page' );

		// Assets.
		$this->hooks_loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_assets' );
	}

	/**
	 * Initialize REST API
	 *
	 * @return void
	 */
	public function init_rest_api(): void {
		$email_logs_controller = $this->container->get( 'feature.get_emails.controller' );
		assert( $email_logs_controller instanceof EmailLogsController );
		$webhook_controller = $this->container->get( 'feature.process_webhook.controller' );
		assert( $webhook_controller instanceof WebhookController );
		$sync_controller = $this->container->get( 'feature.sync_mailgun.controller' );
		assert( $sync_controller instanceof SyncController );
		$settings_controller = $this->container->get( 'feature.manage_settings.controller' );
		assert( $settings_controller instanceof SettingsController );

		$email_logs_controller->register_routes();
		$webhook_controller->register_routes();
		$sync_controller->register_routes();
		$settings_controller->register_routes();
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only load on our plugin pages.
		$plugin_pages = [
			'toplevel_page_mail-chronicle',
			'mail-chronicle_page_mail-chronicle-settings',
			'mail-chronicle_page_mail-chronicle-logs',
		];

		if ( ! in_array( $hook, $plugin_pages, true ) ) {
			return;
		}

		// Enqueue WordPress components styles.
		wp_enqueue_style( 'wp-components' );

		// Enqueue our React app (if built).
		$asset_file = MAIL_CHRONICLE_PATH . 'assets/build/index.asset.php';

		if ( file_exists( $asset_file ) ) {
			/** @var array{dependencies: string[], version: string} $asset Webpack asset manifest. */
			$asset = require $asset_file;

			wp_enqueue_script(
				'mail-chronicle-admin',
				MAIL_CHRONICLE_URL . 'assets/build/index.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_enqueue_style(
				'mail-chronicle-admin',
				MAIL_CHRONICLE_URL . 'assets/build/index.css',
				[ 'wp-components' ],
				$asset['version']
			);

			// Localize script.
			$mc_settings   = get_option( Constants::OPTION_SETTINGS, [] );
			$mc_settings   = is_array( $mc_settings ) ? $mc_settings : [];
			$status_labels = [];
			foreach ( Email_Status::cases() as $status ) {
				$status_labels[ $status->value ] = $status->label();
			}

			wp_localize_script(
				'mail-chronicle-admin',
				'mailChronicle',
				[
					'apiUrl'       => rest_url( 'mail-chronicle/v1' ),
					'nonce'        => wp_create_nonce( 'wp_rest' ),
					'settings'     => $mc_settings,
					'syncDays'     => is_int( $mc_settings['sync_days'] ?? null ) ? $mc_settings['sync_days'] : ManageSettings::DEFAULT_SYNC_DAYS,
					'statusLabels' => $status_labels,
					'i18n'         => [
						'emailLogs' => __( 'Email Logs', 'mail-chronicle' ),
						'settings'  => __( 'Settings', 'mail-chronicle' ),
					],
				]
			);
		}
	}

	/**
	 * Run dbDelta if the stored schema version is behind the current one.
	 * Runs once per request when needed, harmless on up-to-date installs.
	 *
	 * @return void
	 */
	public function maybe_upgrade_schema(): void {
		$schema = new \MailChronicle\Common\Database\Schema();
		if ( $schema->needs_update() ) {
			$schema->create_tables();
		}
	}

	/**
	 * Load text domain
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'mail-chronicle',
			false,
			dirname( MAIL_CHRONICLE_BASENAME ) . '/languages'
		);
	}

	/**
	 * Get container
	 */
	public function get_container(): ServiceContainer {
		return $this->container;
	}
}
