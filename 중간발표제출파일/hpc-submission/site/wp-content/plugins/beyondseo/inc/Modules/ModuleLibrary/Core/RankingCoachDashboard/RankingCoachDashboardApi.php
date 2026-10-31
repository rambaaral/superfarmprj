<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Core\RankingCoachDashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleApi;
use Throwable;
use WP_REST_Server;

/**
 * Class RankingCoachDashboardApi
 */
class RankingCoachDashboardApi extends BaseSubmoduleApi {

	/**
	 * Initializes the REST API for the RankingCoach dashboard.
	 * @return void
	 * @throws Throwable
	 */
	public function initializeApi(): void {

		/** @var RankingCoachDashboard $module */
		$module = $this->module;

		// Register REST API routes
		add_action( 'rest_api_init', function () use ( $module ) {
			// Register GET and POST routes
		} );
	}
}