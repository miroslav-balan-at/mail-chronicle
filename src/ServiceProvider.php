<?php
/**
 * Service Provider - Registers all features
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle;

use MailChronicle\Common\Database\Schema;
use MailChronicle\Common\Infrastructure\WpdbEmailRepository;
use MailChronicle\Common\Infrastructure\WpdbProviderEventRepository;
use MailChronicle\Common\Repository\EmailRepositoryInterface;
use MailChronicle\Common\Repository\ProviderEventRepositoryInterface;
use MailChronicle\Features\DeleteEmail\DeleteEmail;
use MailChronicle\Features\DeleteEmail\DeleteEmailInterface;
use MailChronicle\Features\GetEmails\EmailLogsController;
use MailChronicle\Features\GetEmails\EmailLogsPage;
use MailChronicle\Features\GetEmails\GetEmails;
use MailChronicle\Features\GetEmails\GetEmailsInterface;
use MailChronicle\Features\LogEmail\LogEmail;
use MailChronicle\Features\ManageSettings\ManageSettings;
use MailChronicle\Features\ManageSettings\SettingsController;
use MailChronicle\Features\ManageSettings\SettingsPage;
use MailChronicle\Features\ProcessMailgunWebhook\ProcessMailgunWebhook;
use MailChronicle\Features\ProcessMailgunWebhook\WebhookController;
use MailChronicle\Features\PurgeOldLogs\PurgeOldLogs;
use MailChronicle\Features\PurgeOldLogs\PurgeScheduler;
use MailChronicle\Features\Sync\SyncController;
use MailChronicle\Features\SyncFromMailgun\SyncFromMailgun;
use MailChronicle\Features\SyncFromMailgun\SyncScheduler;

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
	 * Register common/shared services (schema + repositories)
	 */
	private function register_common(): void {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Declares type of WordPress $wpdb global.
		/** @var \wpdb $wpdb WordPress database instance. */
		global $wpdb;

		// Database schema.
		$this->container->register(
			'common.database.schema',
			function ( $_container ) use ( $wpdb ) {
				return new Schema( $wpdb );
			}
		);

		// Repositories — registered once, shared across all features.
		$this->container->register(
			'common.repository.email',
			function ( $_container ) use ( $wpdb ) {
				return new WpdbEmailRepository( $wpdb );
			}
		);

		$this->container->register(
			'common.repository.provider_event',
			function ( $_container ) use ( $wpdb ) {
				return new WpdbProviderEventRepository( $wpdb );
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
			function ( $container ) {
				/** @var EmailRepositoryInterface $email_repository */
				$email_repository = $container->get( 'common.repository.email' );
				return new LogEmail( $email_repository );
			}
		);

		// Feature: Get Emails.
		$this->container->register(
			'feature.get_emails',
			function ( $container ) {
				/** @var EmailRepositoryInterface $email_repository */
				$email_repository = $container->get( 'common.repository.email' );
				/** @var ProviderEventRepositoryInterface $event_repository */
				$event_repository = $container->get( 'common.repository.provider_event' );
				return new GetEmails( $email_repository, $event_repository );
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
			function ( $container ) {
				/** @var GetEmailsInterface $get_emails */
				$get_emails = $container->get( 'feature.get_emails' );
				/** @var DeleteEmailInterface $delete_email */
				$delete_email = $container->get( 'feature.delete_email' );
				return new EmailLogsController( $get_emails, $delete_email );
			}
		);

		// Feature: Delete Email.
		$this->container->register(
			'feature.delete_email',
			function ( $container ) {
				/** @var EmailRepositoryInterface $email_repository */
				$email_repository = $container->get( 'common.repository.email' );
				return new DeleteEmail( $email_repository );
			}
		);

		// Feature: Process Mailgun Webhook.
		$this->container->register(
			'feature.process_webhook',
			function ( $container ) {
				/** @var EmailRepositoryInterface $email_repository */
				$email_repository = $container->get( 'common.repository.email' );
				/** @var ProviderEventRepositoryInterface $event_repository */
				$event_repository = $container->get( 'common.repository.provider_event' );
				return new ProcessMailgunWebhook( $email_repository, $event_repository );
			}
		);

		$this->container->register(
			'feature.process_webhook.controller',
			function ( $container ) {
				/** @var ProcessMailgunWebhook $process_webhook */
				$process_webhook = $container->get( 'feature.process_webhook' );
				return new WebhookController( $process_webhook );
			}
		);

		// Feature: Sync From Mailgun.
		$this->container->register(
			'feature.sync_mailgun',
			function ( $container ) {
				/** @var EmailRepositoryInterface $email_repository */
				$email_repository = $container->get( 'common.repository.email' );
				return new SyncFromMailgun( $email_repository );
			}
		);

		$this->container->register(
			'feature.sync_mailgun.controller',
			function ( $container ) {
				/** @var SyncFromMailgun $sync_mailgun */
				$sync_mailgun = $container->get( 'feature.sync_mailgun' );
				return new SyncController( $sync_mailgun );
			}
		);

		$this->container->register(
			'feature.sync_mailgun.scheduler',
			function ( $container ) {
				/** @var SyncFromMailgun $sync_mailgun */
				$sync_mailgun = $container->get( 'feature.sync_mailgun' );
				/** @var ManageSettings $manage_settings */
				$manage_settings = $container->get( 'feature.manage_settings' );
				return new SyncScheduler( $sync_mailgun, $manage_settings );
			}
		);

		// Feature: Purge Old Logs.
		$this->container->register(
			'feature.purge_old_logs',
			function ( $container ) {
				/** @var EmailRepositoryInterface $email_repository */
				$email_repository = $container->get( 'common.repository.email' );
				return new PurgeOldLogs( $email_repository );
			}
		);

		$this->container->register(
			'feature.purge_old_logs.scheduler',
			function ( $container ) {
				/** @var PurgeOldLogs $purge_old_logs */
				$purge_old_logs = $container->get( 'feature.purge_old_logs' );
				/** @var ManageSettings $manage_settings */
				$manage_settings = $container->get( 'feature.manage_settings' );
				return new PurgeScheduler( $purge_old_logs, $manage_settings );
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
			function ( $container ) {
				/** @var ManageSettings $manage_settings */
				$manage_settings = $container->get( 'feature.manage_settings' );
				/** @var DeleteEmailInterface $delete_email */
				$delete_email = $container->get( 'feature.delete_email' );
				return new SettingsPage( $manage_settings, $delete_email );
			}
		);

		$this->container->register(
			'feature.manage_settings.controller',
			function ( $_container ) {
				return new SettingsController();
			}
		);
	}
}
