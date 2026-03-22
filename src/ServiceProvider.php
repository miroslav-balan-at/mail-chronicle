<?php
/**
 * Service Provider - Registers all features
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle;

use MailChronicle\Features\LogEmail\LogEmail;
use MailChronicle\Features\GetEmails\GetEmails;
use MailChronicle\Features\GetEmails\EmailLogsPage;
use MailChronicle\Features\GetEmails\EmailLogsController;
use MailChronicle\Features\DeleteEmail\DeleteEmail;
use MailChronicle\Features\ProcessMailgunWebhook\ProcessMailgunWebhook;
use MailChronicle\Features\ProcessMailgunWebhook\WebhookController;
use MailChronicle\Features\Sync\SyncController;
use MailChronicle\Features\SyncFromMailgun\SyncFromMailgun;
use MailChronicle\Features\SyncFromMailgun\SyncScheduler;
use MailChronicle\Features\ManageSettings\ManageSettings;
use MailChronicle\Features\ManageSettings\SettingsPage;
use MailChronicle\Features\PurgeOldLogs\PurgeOldLogs;
use MailChronicle\Features\PurgeOldLogs\PurgeScheduler;
use MailChronicle\Common\Database\Schema;

/**
 * Service Provider Class
 */
final class ServiceProvider {

	public function __construct( private ServiceContainer $container ) {
	}

	/**
	 * Register all services
	 */
	public function register(): void {
		$this->register_common();
		$this->register_features();
	}

	/**
	 * Register common/shared services
	 */
	private function register_common(): void {
		// Database schema.
		$this->container->register(
			'common.database.schema',
			function ( $_container ) {
				return new Schema();
			}
		);
	}

	/**
	 * Register features (vertical slices)
	 */
	private function register_features(): void {
		// Feature: Log Email.
		$this->container->register(
			'feature.log_email',
			function ( $_container ) {
				return new LogEmail();
			}
		);

		// Feature: Get Emails.
		$this->container->register(
			'feature.get_emails',
			function ( $_container ) {
				return new GetEmails();
			}
		);

		$this->container->register(
			'feature.get_emails.page',
			function ( $_container ) {
				return new EmailLogsPage();
			}
		);

		$this->container->register(
			'feature.get_emails.controller',
			function ( $_container ) {
				return new EmailLogsController();
			}
		);

		// Feature: Delete Email.
		$this->container->register(
			'feature.delete_email',
			function ( $_container ) {
				return new DeleteEmail();
			}
		);

		// Feature: Process Mailgun Webhook.
		$this->container->register(
			'feature.process_webhook',
			function ( $_container ) {
				return new ProcessMailgunWebhook();
			}
		);

		$this->container->register(
			'feature.process_webhook.controller',
			function ( $_container ) {
				return new WebhookController();
			}
		);

		// Feature: Sync From Mailgun.
		$this->container->register(
			'feature.sync_mailgun',
			function ( $_container ) {
				return new SyncFromMailgun();
			}
		);

		$this->container->register(
			'feature.sync_mailgun.controller',
			function ( $_container ) {
				return new SyncController();
			}
		);

		$this->container->register(
			'feature.sync_mailgun.scheduler',
			function ( $_container ) {
				return new SyncScheduler();
			}
		);

		// Feature: Purge Old Logs.
		$this->container->register(
			'feature.purge_old_logs',
			function ( $_container ) {
				return new PurgeOldLogs();
			}
		);

		$this->container->register(
			'feature.purge_old_logs.scheduler',
			function ( $_container ) {
				return new PurgeScheduler();
			}
		);

		// Feature: Manage Settings.
		$this->container->register(
			'feature.manage_settings',
			function ( $_container ) {
				return new ManageSettings();
			}
		);

		$this->container->register(
			'feature.manage_settings.page',
			function ( $_container ) {
				return new SettingsPage();
			}
		);

		$this->container->register(
			'feature.manage_settings.controller',
			function ( $_container ) {
				return new \MailChronicle\Features\ManageSettings\SettingsController();
			}
		);
	}
}
