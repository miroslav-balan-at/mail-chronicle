<?php
/**
 * Email Provider Enum
 *
 * @package MailChronicle\Common\Entities
 */

declare(strict_types=1);

namespace MailChronicle\Common\Entities;

defined( 'ABSPATH' ) || exit;

/**
 * Supported email provider identifiers.
 * Values are stored as-is in the database.
 */
enum Email_Provider: string {

	/** Native WordPress / PHPMailer (no transactional provider). */
	case WordPress = 'WordPress';

	/** Mailgun transactional email service. */
	case Mailgun = 'mailgun';

	/** SendGrid transactional email service (future). */
	case Sendgrid = 'sendgrid';

	// ── API endpoints ──────────────────────────────────────────────────────

	/**
	 * Base API URL for the provider.
	 * Returns null for providers that don't have a direct API integration.
	 *
	 * @param Mailgun_Region $region Region (only relevant for Mailgun).
	 */
	public function api_base( Mailgun_Region $region = Mailgun_Region::US ): ?string {
		return match ( $this ) {
			self::Mailgun   => $region->api_base(),
			default         => null,
		};
	}

	// ── i18n ──────────────────────────────────────────────────────────────

	/**
	 * Human-readable translated label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::WordPress => __( 'WordPress (default)', 'mail-chronicle' ),
			self::Mailgun   => __( 'Mailgun', 'mail-chronicle' ),
			self::Sendgrid  => __( 'SendGrid (coming soon)', 'mail-chronicle' ),
		};
	}
}
