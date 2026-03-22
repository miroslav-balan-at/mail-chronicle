<?php
/**
 * Hooks Loader
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Common\WordPress;

/**
 * Hooks Loader Class
 */
final class HooksLoader {

	// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Shaped array type for PHPStan.
	/** @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}> */
	private array $actions = [];

	// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Shaped array type for PHPStan.
	/** @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}> */
	private array $filters = [];

	public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions[] = [
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		];
	}

	public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters[] = [
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		];
	}

	/**
	 * Run hooks
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			$callback = [ $hook['component'], $hook['callback'] ];
			if ( is_callable( $callback ) ) {
				add_filter(
					$hook['hook'],
					$callback,
					$hook['priority'],
					$hook['accepted_args']
				);
			}
		}

		foreach ( $this->actions as $hook ) {
			$callback = [ $hook['component'], $hook['callback'] ];
			if ( is_callable( $callback ) ) {
				add_action(
					$hook['hook'],
					$callback,
					$hook['priority'],
					$hook['accepted_args']
				);
			}
		}
	}
}
