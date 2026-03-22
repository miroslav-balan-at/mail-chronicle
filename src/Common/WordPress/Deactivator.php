<?php
/**
 * Plugin Deactivator
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Common\WordPress;

use MailChronicle\Features\PurgeOldLogs\PurgeScheduler;
use MailChronicle\Features\SyncFromMailgun\SyncScheduler;

/**
 * Deactivator Class
 */
final class Deactivator {

	/**
	 * Deactivate plugin
	 */
	public static function deactivate(): void {
		// Remove scheduled crons.
		PurgeScheduler::unschedule();
		SyncScheduler::unschedule();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Note: We don't delete data on deactivation, only on uninstall.
	}
}
