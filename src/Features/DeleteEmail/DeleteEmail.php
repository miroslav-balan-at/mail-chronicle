<?php
/**
 * Feature: Delete Email
 *
 * Application service — delegates persistence to EmailRepositoryInterface.
 * No SQL in this class.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\DeleteEmail;

use MailChronicle\Common\Repository\EmailRepositoryInterface;

/**
 * Delete Email Handler
 */
final class DeleteEmail implements DeleteEmailInterface {

	private EmailRepositoryInterface $email_repository;

	/**
	 * Constructor
	 */
	public function __construct( EmailRepositoryInterface $email_repository ) {
		$this->email_repository = $email_repository;
	}

	public function handle( int $id ): bool {
		/**
		 * Fires before an email log entry and its events are deleted.
		 *
		 * @since 1.0.0
		 *
		 * @param int $id Log entry ID about to be deleted.
		 */
		do_action( 'mail_chronicle_before_email_deleted', $id );

		$deleted = $this->email_repository->delete( $id );

		if ( $deleted ) {
			/**
			 * Fires after an email log entry has been deleted.
			 *
			 * @since 1.0.0
			 *
			 * @param int $id Log entry ID that was deleted.
			 */
			do_action( 'mail_chronicle_after_email_deleted', $id );
		}

		return $deleted;
	}

	/**
	 * Delete all email logs and their events.
	 */
	public function delete_all(): int {
		/**
		 * Fires before all email logs and events are truncated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'mail_chronicle_before_all_emails_deleted' );

		$result = $this->email_repository->delete_all();

		/**
		 * Fires after all email logs and events have been truncated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'mail_chronicle_after_all_emails_deleted' );

		return $result;
	}
}
