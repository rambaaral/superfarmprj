<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

/**
 * Class RcContainerService
 */
class RcContainerService {

	/**
	 * @var array $parameters The parameter array.
	 */
	private array $parameters;

	/**
	 * @var string $class The class string.
	 */
	private string $class;

	/**
	 * @var string $interface The alias string.
	 */
	private string $interface;

	/**
	 * @var bool $shared Whether the service is shared.
	 */
	private bool $shared;

	/**
	 * @var bool $public Whether the service is public.
	 */
	private bool $public;

	/**
	 * @var mixed $instance The service instance.
	 */
	public static mixed $instance;

	/**
	 * Service constructor.
	 *
	 * @param string $class The class string.
	 * @param string $interface The alias string.
	 * @param array $parameters The parameter array.
	 * @param bool $shared Whether the service is shared.
	 * @param bool $public Whether the service is public.
	 */
	public function __construct(string $class, string $interface, array $parameters = [], bool $shared = true, bool $public = true) {
		$this->class      = $class;
		$this->interface  = $interface;
		$this->parameters = $parameters;
		$this->shared = $shared;
		$this->public = $public;
	}

	/**
	 * Gets the singleton instance.
	 *
	 * @return mixed The service instance.
	 */
	public static function getInstance(string $class, string $alias, array $parameters = [], bool $shared = true, bool $public = true): mixed {
		if (self::$instance === null) {
			self::$instance = new self($class, $alias, $parameters, $shared, $public);
		}
		return self::$instance;
	}

	/**
	 * Checks if the service is shared.
	 *
	 * @return bool Whether the service
	 * is shared.
	 */
	public function isShared(): bool {
		return $this->shared;
	}

	/**
	 * Checks if the service is public.
	 *
	 * @return bool Whether the service
	 * is public.
	 */
	public function isPublic(): bool {
		return $this->public;
	}

	/**
	 * Gets the service class.
	 *
	 * @return string The service class.
	 */
	public function getClass(): string {
		return $this->class;
	}

	/**
	 * Gets the service alias.
	 *
	 * @return string The service alias.
	 */
	public function getInterface(): string {
		return $this->interface;
	}

	/**
	 * Gets the service parameters.
	 *
	 * @return array The service parameters.
	 */
	public function getParameters(): array {
		return $this->parameters;
	}
}