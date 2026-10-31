<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * ConfigManager defines methods to manage configuration values in a plugin or theme.
 */
trait ConfigManager {

	/**
	 * Load settings into the class.
	 *
	 * @param array $settings Configuration values.
     * @return void
	 */
	protected function config( array $settings = [] ): void {
		// Exit if no settings.
		if ( empty( $settings ) ) {
			return;
		}

		foreach ( $settings as $key => $value ) {
			/** @noinspection PhpVariableVariableInspection */
			$this->$key = $value;
		}
	}
}