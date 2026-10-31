<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * HookManagerInterface defines methods to manage actions and filters in a plugin or theme.
 */
interface InstallerManagerInterface
{
	/**
	 * Activates the plugin or theme.
	 *
	 * @param bool $entireNetwork
	 * @return void
	 */
	public function activation(bool $entireNetwork = false): void;

	/**
	 * Deactivates the plugin or theme.
	 *
	 * @param bool $entireNetwork
	 * @return void
	 */
	public function deactivation(bool $entireNetwork = false): void;
}