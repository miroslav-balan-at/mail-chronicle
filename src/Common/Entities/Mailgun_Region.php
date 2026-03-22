<?php
/**
 * Mailgun Region Enum
 *
 * @package MailChronicle\Common\Entities
 */

declare(strict_types=1);

namespace MailChronicle\Common\Entities;

defined( 'ABSPATH' ) || exit;

/**
 * Mailgun API region.
 * Values are stored as-is in the database / settings option.
 */
enum Mailgun_Region: string {

	/** US (global) Mailgun cluster. */
	case US = 'US';

	/** EU (Europe) Mailgun cluster — data stays in the EU. */
	case EU = 'EU';

	/**
	 * Base API URL for this region.
	 */
	public function api_base(): string {
		return match ( $this ) {
			self::US => 'https://api.mailgun.net',
			self::EU => 'https://api.eu.mailgun.net',
		};
	}

	/**
	 * Human-readable translated label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::US => __( 'United States (US)', 'mail-chronicle' ),
			self::EU => __( 'European Union (EU)', 'mail-chronicle' ),
		};
	}
}
