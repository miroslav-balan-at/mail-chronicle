<?php
/**
 * Email Status Enum
 *
 * Backed string enum so ->value can be passed directly to $wpdb and
 * round-tripped with tryFrom() when reading from the database or parsing
 * incoming webhook payloads.
 *
 * Statuses are stored in the DB as their ->value (the raw English slug).
 * Human-readable labels are produced by ->label() at render / API time only.
 *
 * @package MailChronicle\Common\Entities
 */

declare(strict_types=1);

namespace MailChronicle\Common\Entities;

defined( 'ABSPATH' ) || exit;

/**
 * Email delivery status.
 *
 * WordPress naming convention for enums mirrors class naming: PascalCase
 * with underscores (WPCS §3).
 */
enum Email_Status: string {

	/** Queued — wp_mail() has not fired yet, or provider accepted the message. */
	case Pending = 'pending';

	/** Accepted by PHPMailer — wp_mail() fired without error. */
	case Sent = 'sent';

	/** Provider confirmed delivery to the recipient mail server. */
	case Delivered = 'delivered';

	/** Recipient opened the email (tracked by provider pixel). */
	case Opened = 'opened';

	/** Recipient clicked a link inside the email. */
	case Clicked = 'clicked';

	/** Rejected permanently — wp_mail() threw an error or provider refused the message. */
	case Failed = 'failed';

	/** Provider returned a hard bounce (permanent address failure). */
	case Bounced = 'bounced';

	/** Recipient marked the message as spam. */
	case Complained = 'complained';

	// ── Priority (used for upgrade-guard logic) ────────────────────────────

	/**
	 * Numeric priority — higher = more terminal / informative.
	 * Used to prevent downgrading a status (e.g. overwriting "clicked" with "delivered").
	 */
	public function priority(): int {
		return match ( $this ) {
			self::Pending    => 0,
			self::Sent       => 1,
			self::Delivered  => 2,
			self::Opened     => 3,
			self::Clicked    => 4,
			self::Failed,
			self::Bounced,
			self::Complained => 5,
		};
	}

	/**
	 * Returns true only when $incoming should replace $current.
	 *
	 * @param self $current  Current status in the database.
	 * @param self $incoming Incoming status from provider.
	 */
	public static function is_upgrade( self $current, self $incoming ): bool {
		return $incoming->priority() >= $current->priority();
	}

	// ── Provider event mapping ────────────────────────────────────────────

	/**
	 * Map a Mailgun event name to an Email_Status.
	 * Returns null for unrecognised event names.
	 */
	public static function from_mailgun_event( string $event ): ?self {
		return match ( $event ) {
			'accepted'   => self::Pending,
			'delivered'  => self::Delivered,
			'opened'     => self::Opened,
			'clicked'    => self::Clicked,
			'failed'     => self::Failed,
			'rejected'   => self::Failed,
			'bounced'    => self::Bounced,
			'complained' => self::Complained,
			default      => null,
		};
	}

	// ── i18n ──────────────────────────────────────────────────────────────

	/**
	 * Human-readable translated label for admin UI display.
	 * Never store this value in the database.
	 */
	public function label(): string {
		return match ( $this ) {
			self::Pending    => __( 'Pending', 'mail-chronicle' ),
			self::Sent       => __( 'Sent', 'mail-chronicle' ),
			self::Delivered  => __( 'Delivered', 'mail-chronicle' ),
			self::Opened     => __( 'Opened', 'mail-chronicle' ),
			self::Clicked    => __( 'Clicked', 'mail-chronicle' ),
			self::Failed     => __( 'Failed', 'mail-chronicle' ),
			self::Bounced    => __( 'Bounced', 'mail-chronicle' ),
			self::Complained => __( 'Complained', 'mail-chronicle' ),
		};
	}
}
