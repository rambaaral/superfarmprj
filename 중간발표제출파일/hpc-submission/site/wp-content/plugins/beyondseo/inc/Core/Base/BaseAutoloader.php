<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Composer\Autoload\ClassLoader;
use Exception;

/**
 * Class BaseAutoloader
 */
class BaseAutoloader {
	/**
	 * Path to the Composer autoload file.
	 */
	private string $autoloadFile;

	/**
	 * Plugin environment type (development, production).
	 */
	private string $environment;

	/**
	 * ClassLoader instance.
	 */
	private ?ClassLoader $loader = null;

	/**
	 * Constructor to initialize autoloader path and environment.
	 *
	 * @param string $autoloadFile - Path to the Composer autoload file.
	 * @param string $environment  - Plugin environment (development or production).
	 */
	public function __construct(string $autoloadFile, string $environment = 'production')
	{
		$this->autoloadFile = $autoloadFile;
		$this->environment = $environment;
	}

	/**
	 * Sets up the autoloader by requiring the Composer autoload file.
	 * @throws Exception
	 */
	public function setup(): void
	{
		if (!is_readable($this->autoloadFile)) {
			throw new Exception(esc_html__('Autoloader file not found or not readable.', 'beyondseo'));
		}

		$this->loader = require_once $this->autoloadFile;

		// If in development mode, enable class reloading.
		if ($this->loader && $this->environment === 'development') {
			$this->loader->unregister();
			$this->loader->register(true);
		}
	}

	/**
	 * Returns the autoloader instance, mainly for testing or advanced usage.
	 *
	 * @return ClassLoader|null
	 */
	public function getLoader(): ?ClassLoader
	{
		return $this->loader;
	}
}
