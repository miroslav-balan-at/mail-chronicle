<?php
/**
 * Get Emails Interface
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\GetEmails;

use MailChronicle\Common\Entities\Email;

/**
 * Contract for querying email logs.
 */
interface GetEmailsInterface {

	/**
	 * Handle query
	 *
	 * @param array $args Query arguments.
	 * @return array{emails: Email[], total: int}
	 */
	public function handle( array $args = [] ): array;

	/**
	 * Get single email by ID
	 *
	 * @param int $id Email ID.
	 * @return Email|null
	 */
	public function get_by_id( int $id ): ?Email;

	/**
	 * Get events for email
	 *
	 * @param int $email_id Email ID.
	 * @return list<array<mixed>>
	 */
	public function get_events( int $email_id ): array;
}
