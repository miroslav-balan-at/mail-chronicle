<?php
/**
 * Service Container (Dependency Injection)
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle;

/**
 * Service Container Class
 */
final class ServiceContainer {

	/**
	 * Container instance
	 *
	 * @var ServiceContainer|null
	 */
	private static ?ServiceContainer $instance = null;

	/**
	 * Registered services
	 *
	 * @var array<string, callable(ServiceContainer): mixed>
	 */
	private array $services = [];

	/**
	 * Resolved services (singletons)
	 *
	 * @var array<string, mixed>
	 */
	private array $resolved = [];

	/**
	 * Get container instance
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
	}

	/**
	 * Register a service
	 *
	 * @param callable(ServiceContainer): mixed $resolver Service resolver function.
	 */
	public function register( string $key, callable $resolver ): void {
		$this->services[ $key ] = $resolver;
	}

	/**
	 * Get a service (singleton)
	 *
	 * @throws \Exception If service not found.
	 */
	public function get( string $key ): mixed {
		if ( isset( $this->resolved[ $key ] ) ) {
			return $this->resolved[ $key ];
		}

		if ( ! isset( $this->services[ $key ] ) ) {
			throw new \Exception( "Service '{$key}' not found in container." );
		}

		$this->resolved[ $key ] = ( $this->services[ $key ] )( $this );
		return $this->resolved[ $key ];
	}

	public function has( string $key ): bool {
		return isset( $this->services[ $key ] );
	}

	/**
	 * Create a new instance (not singleton)
	 *
	 * @throws \Exception If service not found.
	 */
	public function make( string $key ): mixed {
		if ( ! isset( $this->services[ $key ] ) ) {
			throw new \Exception( "Service '{$key}' not found in container." );
		}

		return ( $this->services[ $key ] )( $this );
	}
}
