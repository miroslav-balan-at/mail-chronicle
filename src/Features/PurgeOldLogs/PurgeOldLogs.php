<?php
/**
 * Feature: Purge Old Logs
 *
 * Domain handler — deletes email logs (and their events) older than the
 * configured retention window.  Zero retention means "keep forever".
 * Delegates all persistence to EmailRepositoryInterface.  No SQL here.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\PurgeOldLogs;

use MailChronicle\Common\Repository\EmailRepositoryInterface;

/**
 * Purge Old Logs Handler
 */
final class PurgeOldLogs {

	private EmailRepositoryInterface $email_repository;

	/**
	 * Constructor
	 */
	public function __construct( EmailRepositoryInterface $email_repository ) {
		$this->email_repository = $email_repository;
	}

	/**
	 * Delete logs older than $days days.
	 *
	 * When $days is 0 nothing is deleted (retain forever).
	 */
	public function handle( int $days ): int {
		if ( $days <= 0 ) {
			return 0;
		}

		$timestamp = strtotime( "-{$days} days" );
		$cutoff    = gmdate( 'Y-m-d H:i:s', false !== $timestamp ? $timestamp : 0 );

		return $this->email_repository->delete_older_than( $cutoff );
	}
}
