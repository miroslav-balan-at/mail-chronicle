<?php
/**
 * Fetch Stored Content Interface
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\FetchStoredContent;

use MailChronicle\Common\Entities\Email;

/**
 * Contract for on-demand retrieval of Mailgun stored message content.
 */
interface FetchStoredContentInterface {

	/**
	 * If the email has no body content but a stored message URL in its headers,
	 * fetch the body from Mailgun, persist it, and return the enriched Email.
	 * Returns null when the email does not exist.
	 *
	 * @param int $email_id Log entry ID.
	 * @return Email|null
	 */
	public function handle( int $email_id ): ?Email;
}
