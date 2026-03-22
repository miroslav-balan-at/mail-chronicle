<?php
/**
 * Feature: Get Emails
 *
 * Application service — delegates persistence to EmailRepositoryInterface and
 * ProviderEventRepositoryInterface.  No SQL in this class.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\GetEmails;

use MailChronicle\Common\Entities\Email;
use MailChronicle\Common\Query\EmailQuery;
use MailChronicle\Common\Repository\EmailRepositoryInterface;
use MailChronicle\Common\Repository\ProviderEventRepositoryInterface;

/**
 * Get Emails Handler
 */
final class GetEmails implements GetEmailsInterface {

	private EmailRepositoryInterface $email_repository;

	private ProviderEventRepositoryInterface $event_repository;

	/**
	 * Constructor
	 */
	public function __construct(
		EmailRepositoryInterface $email_repository,
		ProviderEventRepositoryInterface $event_repository
	) {
		$this->email_repository = $email_repository;
		$this->event_repository = $event_repository;
	}

	/**
	 * Handle query
	 *
	 * @param array $args Query arguments.
	 * @return array{emails: Email[], total: int}
	 */
	public function handle( array $args = [] ): array {
		$defaults = [
			'per_page'  => 20,
			'page'      => 1,
			'orderby'   => 'sent_at',
			'order'     => 'DESC',
			'status'    => '',
			'provider'  => '',
			'search'    => '',
			'date_from' => '',
			'date_to'   => '',
		];

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filters the query arguments before emails are fetched from the database.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Merged query arguments (includes defaults).
		 */
		$args = apply_filters( 'mail_chronicle_get_emails_args', $args );
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Narrows apply_filters() return type for PHPStan.
		/** @var array<string, mixed> $args */
		$args = is_array( $args ) ? $args : [];

		$email_query = new EmailQuery( $args );
		$result      = $this->email_repository->query( $email_query );

		/**
		 * Filters the email log results before they are returned.
		 *
		 * @since 1.0.0
		 *
		 * @param Email[] $emails Array of Email entity objects.
		 * @param array   $args   The query arguments that produced these results.
		 */
		$emails = apply_filters( 'mail_chronicle_get_emails', $result['emails'], $args );

		return [
			'emails' => is_array( $emails ) ? $emails : [],
			'total'  => $result['total'],
		];
	}

	/**
	 * Get single email by ID
	 */
	public function get_by_id( int $id ): ?Email {
		return $this->email_repository->find_by_id( $id );
	}

	/**
	 * Get events for email
	 *
	 * @param int $email_id Email ID.
	 * @return list<array<mixed>>
	 */
	public function get_events( int $email_id ): array {
		return $this->event_repository->find_by_email_log_id( $email_id );
	}
}
