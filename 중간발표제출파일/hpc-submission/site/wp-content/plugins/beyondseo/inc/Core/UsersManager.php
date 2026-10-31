<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Traits\SingletonTrait;

/**
 * Class UsersManager
 *
 * This class is responsible for managing user-related data.
 */
class UsersManager {

    use SingletonTrait;

	/**
	 * Get user insights for the site.
	 *
	 * @return array
	 */
	public function get_users_data(): array {
		// Retrieve total user count
		$total_users = count(get_users(['fields' => 'ID']));

		// Retrieve user roles and their counts
		$roles = [];
		$users = get_users();
		foreach ($users as $user) {
			foreach ($user->roles as $role) {
				$roles[$role] = ($roles[$role] ?? 0) + 1;
			}
		}

		// Retrieve user growth trends
		$users_last_week = count(get_users([
			'date_query' => [
				'after' => gmdate('Y-m-d', strtotime('-7 days'))
			]
		]));
		$users_last_month = count(get_users([
			'date_query' => [
				'after' => gmdate('Y-m-d', strtotime('-30 days'))
			]
		]));

		// Fetch the last registered user
		$last_user_registered = get_users([
			'orderby' => 'registered',
			'order' => 'DESC',
			'number' => 1,
			'fields' => ['user_registered']
		]);
		$last_user_registered_date = !empty($last_user_registered) ? $last_user_registered[0]->user_registered : null;

		// Fetch the last login if tracked (requires a plugin or custom implementation)
		$last_user_login = null; // Placeholder if no custom tracking exists

		return [
			'total_users' => $total_users,
			'roles' => $roles,
			'growth' => [
				'last_7_days' => $users_last_week,
				'last_30_days' => $users_last_month
			],
			'last_user_registered' => $last_user_registered_date,
			'last_user_login' => $last_user_login
		];
	}

}
