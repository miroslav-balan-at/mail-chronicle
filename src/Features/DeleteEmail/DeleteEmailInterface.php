<?php
/**
 * Delete Email Interface
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\DeleteEmail;

/**
 * Contract for deleting email logs.
 */
interface DeleteEmailInterface {

	/**
	 * Delete a single email log and its events.
	 *
	 * @param int $id Email log ID.
	 * @return bool True on success, false on failure.
	 */
	public function handle( int $id ): bool;

	/**
	 * Delete all email logs and their events.
	 *
	 * @return int 0 on success, -1 on failure.
	 */
	public function delete_all(): int;
}
