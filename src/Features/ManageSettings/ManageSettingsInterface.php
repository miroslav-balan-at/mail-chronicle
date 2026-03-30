<?php
/**
 * Manage Settings Interface
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Features\ManageSettings;

/**
 * Contract for reading and persisting plugin settings.
 */
interface ManageSettingsInterface {

	/**
	 * Get current settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array;

	/**
	 * Validate and persist settings.
	 *
	 * @param array<string, mixed> $data Raw input data.
	 * @return bool True when the option was updated.
	 */
	public function update( array $data ): bool;
}
