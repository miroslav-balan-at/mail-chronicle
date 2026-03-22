<?php
/**
 * Provider Event Repository Interface
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Common\Repository;

use MailChronicle\Common\Entities\ProviderEvent;

/**
 * Contract for all ProviderEvent persistence operations.
 */
interface ProviderEventRepositoryInterface {

	/**
	 * Persist a new provider event.
	 *
	 * @param ProviderEvent $event Unsaved entity (id must be null).
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public function save( ProviderEvent $event ): int|false;

	/**
	 * Return all events for a given email log entry, newest first.
	 *
	 * @param int $email_log_id Log entry ID.
	 * @return list<array<mixed>> Raw row arrays (as returned by wpdb).
	 */
	public function find_by_email_log_id( int $email_log_id ): array;
}
