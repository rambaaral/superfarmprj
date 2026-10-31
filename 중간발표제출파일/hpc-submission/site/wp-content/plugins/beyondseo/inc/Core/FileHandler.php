<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Interfaces\FileHandlerInterface;
use RuntimeException;

/**
 * Class FileHandler
 */
class FileHandler implements FileHandlerInterface {

	/**
	 * Checks if a file exists.
	 *
	 * @param string $path The path to the file.
	 * @return bool True if the file exists, false otherwise.
	 */
	public function fileExists(string $path): bool {
		return file_exists($path);
	}

	/**
	 * Requires a file.
	 *
	 * @param string $path The path to the file.
	 * @throws RuntimeException If the file does not exist.
	 * @return void
	 */
	public function requireFile(string $path): void {
		if (!$this->fileExists($path)) {
			throw new RuntimeException('File not found: ' . esc_html($path));
		}
		require_once $path;
	}

	/**
	 * Checks if a path is valid.
	 *
	 * @param string $path The path to check.
	 * @return bool True if the path is valid, false otherwise.
	 */
	public function isValidPath(string $path): bool {
		return !empty($path) && strlen($path) > 0;
	}
}