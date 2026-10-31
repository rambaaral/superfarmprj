<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface FileHandlerInterface
 */
interface FileHandlerInterface {
	/**
	 * Checks if a file exists.
	 *
	 * @param string $path The path to the file.
	 * @return bool True if the file exists, false otherwise.
	 */
	public function fileExists(string $path): bool;

	/**
	 * Requires a file.
	 *
	 * @param string $path The path to the file.
	 * @return void
	 */
	public function requireFile(string $path): void;

	/**
	 * Checks if a path is valid.
	 *
	 * @param string $path The path to check.
	 * @return bool True if the path is valid, false otherwise.
	 */
	public function isValidPath(string $path): bool;
}