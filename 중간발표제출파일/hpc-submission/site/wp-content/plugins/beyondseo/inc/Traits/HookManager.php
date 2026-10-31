<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * HookManager defines methods to manage actions and filters in a plugin or theme.
 */
trait HookManager {

	/**
	 * Attach a callback to an action.
	 *
	 * @param string $event Action name.
	 * @param string $callback Function to call.
	 * @param int $level Execution order. Default is 10.
	 * @param int $args_count Number of arguments the function accepts. Default is 1.
	 *
	 * @return bool
	 */
	protected function action( string $event, string $callback, int $level = 10, int $args_count = 1 ): bool {
		return add_action( $event, [ $this, $callback ], $level, $args_count );
	}

	/**
	 * Attach a callback to a filter.
	 *
	 * @param string $event Filter name.
	 * @param string $callback Function to call.
	 * @param int $level Execution order. Default is 10.
	 * @param int $args_count Number of arguments the function accepts. Default is 1.
	 *
	 * @return bool
	 */
	protected function filter( string $event, string $callback, int $level = 10, int $args_count = 1 ): bool {
		return add_filter( $event, [ $this, $callback ], $level, $args_count );
	}

	/**
	 * Detach a callback from an action.
	 *
	 * @param string $event Action name.
	 * @param string $callback Function to remove.
	 * @param int $level Execution order. Default is 10.
	 *
	 * @return bool
	 */
	protected function remove_action( string $event, string $callback, int $level = 10 ): bool {
		return remove_action( $event, [ $this, $callback ], $level );
	}

	/**
	 * Detach a callback from a filter.
	 *
	 * @param string $event Filter name.
	 * @param string $callback Function to remove.
	 * @param int $level Execution order. Default is 10.
	 *
	 * @return bool
	 */
	protected function remove_filter( string $event, string $callback, int $level = 10 ): bool {
		return remove_filter( $event, [ $this, $callback ], $level );
	}

	/**
	 * Run an action with a prefix.
	 *
	 * @param array ...$parameters Arguments for the action.
     * @return void
	 */
	protected function do_action( ...$parameters ): void {
		if ( empty( $parameters[0] ) ) {
			return;
		}

		/** @noinspection PhpArrayToStringConversionInspection */
		$action = 'rankingcoach/' . $parameters[0];
		unset( $parameters[0] );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		do_action_ref_array( $action, array_merge( [], $parameters ) );
	}

	/**
	 * Apply a filter with a prefix.
	 *
	 * @param array ...$parameters Arguments for the filter.
     * @return mixed
	 */
	protected function do_filter( ...$parameters ) {
		if ( empty( $parameters[0] ) ) {
			/** @noinspection PhpInconsistentReturnPointsInspection */
			return;
		}

		/** @noinspection PhpArrayToStringConversionInspection */
		$action = 'rankingcoach/' . $parameters[0];
		unset( $parameters[0] );

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		return apply_filters_ref_array( $action, array_merge( [], $parameters ) );
	}

	/**
	 * Remove 'view_query_monitor' permission.
	 *
	 * @param array $caps User capabilities.
	 *
	 * @return array
	 */
	public function filter_user_has_cap( array $caps ): array {
		$caps['view_query_monitor'] = false;

		return $caps;
	}
}