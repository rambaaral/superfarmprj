<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleApi;
use Throwable;

/**
 * Class MetaTagsApi
 */
class MetaTagsApi extends BaseSubmoduleApi {

	/**
	 * Initializes the REST API for the submodule.
	 * @return void
	 * @throws Throwable
	 */
	public function initializeApi(): void {
		/** @var MetaTags $module */
		$module = $this->module;

		// Register REST API routes
		add_action('rest_api_init', function () use ($module) {
            //
		});
	}
}