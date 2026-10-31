<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface SettingsManagerInterface
 */
interface SettingsManagerInterface {

	/**
	 * Gets the option value.
	 *
	 * @param string $option The option name.
	 * @param mixed|null $default The default value.
	 *
	 * @return mixed The option value.
	 */
	public function get_option(string $option, mixed $default = null): mixed;

	/**
	 * Gets all options.
	 * @return mixed The options.
	 */
	public function get_options(): mixed;

}
