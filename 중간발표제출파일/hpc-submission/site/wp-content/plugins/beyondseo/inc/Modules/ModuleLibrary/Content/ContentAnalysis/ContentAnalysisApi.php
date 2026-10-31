<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Content\ContentAnalysis;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Helpers\RestHelpers;
use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleApi;
use Throwable;
use WP_REST_Server;

/**
 * Class ContentAnalysisApi
 */
class ContentAnalysisApi extends BaseSubmoduleApi {

	/**
	 * Initializes the REST API for the submodule.
	 * @return void
	 * @throws Throwable
	 */
	public function initializeApi(): void {
		/** @var ContentAnalysis $module */
		$module = $this->module;

		// Register REST API routes
		add_action('rest_api_init', function () use ($module) {

			// Register GET and POST routes
		});
	}

	/**
	 * Custom arguments validation for this submodule
	 * @return array
	 */
	public function queryArgsValidate(): array {
		return [
			'optimise' => [
				'in' => 'query',
				'required' => false,
				'type' => 'string',
				'description' => 'The optimisation flag',
				'validate_callback' => [$this, 'isString'],
				'sanitize_callback' => 'sanitize_text_field',
			]
		];
	}

	/**
	 * Custom arguments validation for this submodule
	 * @return array
	 */
	public function bodyArgsValidate(): array {
		$defaultArgs = [];

		return array_merge( $defaultArgs, [] );
	}
}