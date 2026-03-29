<?php
/**
 * Email Repository Interface
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Common\Repository;

use MailChronicle\Common\Entities\Email;
use MailChronicle\Common\Query\EmailQuery;

/**
 * Contract for all Email persistence operations.
 *
 * Implementations (e.g. WpdbEmailRepository) are infrastructure concerns and
 * must not be referenced directly by feature handlers — depend on this interface
 * instead (Dependency Inversion Principle).
 */
interface EmailRepositoryInterface {

	/**
	 * Persist a new Email and return its generated ID.
	 *
	 * @param Email $email Unsaved entity (id must be null).
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public function save( Email $email ): int|false;

	/**
	 * Update the status (and optionally provider_message_id) of an existing row.
	 *
	 * @param int         $id         Log entry ID.
	 * @param string      $status     New status value.
	 * @param string|null $message_id Provider message ID, or null to leave unchanged.
	 */
	public function update_status( int $id, string $status, ?string $message_id = null ): void;

	/**
	 * Find a single email by its primary key.
	 *
	 * @param int $id Log entry ID.
	 * @return Email|null
	 */
	public function find_by_id( int $id ): ?Email;

	/**
	 * Find a single email by its provider message ID.
	 *
	 * @param string $provider_message_id Provider-assigned message identifier.
	 * @return int|null Log entry ID, or null when not found.
	 */
	public function find_id_by_provider_message_id( string $provider_message_id ): ?int;

	/**
	 * Retrieve current status value for a given log entry.
	 *
	 * @param int $id Log entry ID.
	 * @return string|null Raw status string, or null when the row does not exist.
	 */
	public function get_status( int $id ): ?string;

	/**
	 * Query emails with filtering, sorting, and pagination.
	 *
	 * @param EmailQuery $query Value object carrying all query parameters.
	 * @return array{emails: Email[], total: int}
	 */
	public function query( EmailQuery $query ): array;

	/**
	 * Find which provider_message_ids already have a log row.
	 *
	 * Used by batch-sync to skip re-inserting known messages.
	 *
	 * @param string[] $provider_message_ids
	 * @return array<string, int> Map of provider_message_id → log entry ID.
	 */
	public function find_existing_ids_by_provider_message_ids( array $provider_message_ids ): array;

	/**
	 * Persist the fetched message body content for an existing log row.
	 *
	 * @param int    $id    Log entry ID.
	 * @param string $html  HTML body content.
	 * @param string $plain Plain-text body content.
	 */
	public function update_content( int $id, string $html, string $plain ): void;

	/**
	 * Replace the raw headers JSON for an existing log row.
	 *
	 * @param int    $id      Log entry ID.
	 * @param string $headers JSON-encoded headers string.
	 */
	public function update_headers( int $id, string $headers ): void;

	/**
	 * Delete a single email and its associated events.
	 *
	 * @param int $id Log entry ID.
	 * @return bool True when a row was actually removed.
	 */
	public function delete( int $id ): bool;

	/**
	 * Truncate both the logs and events tables.
	 *
	 * @return int 0 on success, -1 on failure.
	 */
	public function delete_all(): int;

	/**
	 * Delete log rows (and their events) older than the given cutoff datetime.
	 *
	 * @param string $cutoff MySQL datetime string (Y-m-d H:i:s).
	 * @return int Number of log rows deleted, or -1 on failure.
	 */
	public function delete_older_than( string $cutoff ): int;
}
