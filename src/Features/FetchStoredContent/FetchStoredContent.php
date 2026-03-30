<?php
/**
 * Feature: Fetch Stored Content
 *
 * On-demand retrieval of a Mailgun stored message body.  During sync, only
 * the storage URL is recorded in the headers JSON (`mc_storage_url`).  When
 * the user opens the Content tab the REST endpoint calls this handler, which
 * fetches the body once, persists it, and returns the enriched Email entity.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\FetchStoredContent;

use MailChronicle\Common\Entities\Email;
use MailChronicle\Common\Repository\EmailRepositoryInterface;
use MailChronicle\Features\ManageSettings\ManageSettingsInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Fetch Stored Content Handler
 */
final class FetchStoredContent implements FetchStoredContentInterface {

	/**
	 * HTTP timeout in seconds for Mailgun Storage API calls.
	 */
	const HTTP_TIMEOUT = 15;

	private EmailRepositoryInterface $email_repository;

	private ManageSettingsInterface $settings;

	public function __construct( EmailRepositoryInterface $email_repository, ManageSettingsInterface $settings ) {
		$this->email_repository = $email_repository;
		$this->settings         = $settings;
	}

	/**
	 * Fetch and persist the stored message body for the given email, if needed.
	 *
	 * @param int $email_id Log entry ID.
	 * @return Email|null Enriched (or already-complete) Email, or null when not found.
	 */
	public function handle( int $email_id ): ?Email {
		$email = $this->email_repository->find_by_id( $email_id );

		if ( null === $email ) {
			return null;
		}

		// Body already present — nothing to fetch.
		if ( '' !== $email->get_message_html() ) {
			return $email;
		}

		$decoded_headers = json_decode( $email->get_headers(), true );
		$decoded_headers = is_array( $decoded_headers ) ? $decoded_headers : [];

		$storage_url = isset( $decoded_headers['mc_storage_url'] ) && is_string( $decoded_headers['mc_storage_url'] )
			? $decoded_headers['mc_storage_url']
			: '';

		if ( '' === $storage_url ) {
			return $email;
		}

		$mc_settings = $this->settings->get();
		$api_key     = is_string( $mc_settings['mailgun_api_key'] ?? null ) ? $mc_settings['mailgun_api_key'] : '';

		if ( '' === $api_key ) {
			return $email;
		}

		$auth = 'Basic ' . base64_encode( 'api:' . $api_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$response = wp_remote_get(
			$storage_url,
			[
				'headers' => [ 'Authorization' => $auth ],
				'timeout' => self::HTTP_TIMEOUT,
			]
		);

		$id = $email->get_id();

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Mark as resolved so it's not retried in the pending-body loop.
			if ( null !== $id ) {
				$this->email_repository->resolve_body( $id );
			}
			return $email;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : [];

		$html  = isset( $data['body-html'] ) && is_string( $data['body-html'] ) ? $data['body-html'] : '';
		$plain = isset( $data['body-plain'] ) && is_string( $data['body-plain'] ) ? $data['body-plain'] : '';

		if ( null !== $id ) {
			$this->email_repository->update_content( $id, $html, $plain );
			$this->email_repository->resolve_body( $id );
		}

		$email->set_message_html( $html );
		$email->set_message_plain( $plain );
		$email->resolve_body();

		return $email;
	}

	/**
	 * @inheritDoc
	 */
	public function fetch_next_pending(): array {
		$pending = $this->email_repository->count_pending_bodies();

		if ( 0 === $pending ) {
			return [
				'done'      => true,
				'remaining' => 0,
			];
		}

		$email = $this->email_repository->find_next_pending_body();

		if ( null === $email || null === $email->get_id() ) {
			return [
				'done'      => true,
				'remaining' => 0,
			];
		}

		$this->handle( $email->get_id() );

		$remaining = $this->email_repository->count_pending_bodies();

		return [
			'done'      => 0 === $remaining,
			'remaining' => $remaining,
		];
	}
}
