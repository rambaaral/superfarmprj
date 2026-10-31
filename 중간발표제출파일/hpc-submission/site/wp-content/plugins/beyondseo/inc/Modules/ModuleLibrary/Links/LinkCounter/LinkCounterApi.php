<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkCounter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleApi;
use RankingCoach\Inc\Modules\ModuleBase\ModuleInterface;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class LinkCounterApi
 */
class LinkCounterApi extends BaseSubmoduleApi {

	/**
	 * @param ModuleInterface $module
	 * @param array|null $params
	 */
	public function __construct(ModuleInterface $module, ?array $params = null) {
		parent::__construct($module, $params);
	}

	/**
	 * Gets arguments for the route
	 * @return array
	 */
	public function getDefaultDataRouteArgs(): array {
		return [
			'param2' => [
				'required' => true,
				'validate_callback' => function ($param) {
					return is_string($param);
				},
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Handles the REST API action
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handleDefaultData(WP_REST_Request $request): WP_REST_Response {
		$params = $request->get_params();
		$params['module'] = $this->module->getModuleName();
		// Process action based on $params
		return $this->generateApiResponse($params);
	}

	/**
	 * Initializes the REST API for the submodule.
	 * @throws Throwable
	 */
	public function initializeApi(): void {
		/** @var LinkCounter $module */
		$module = $this->module;

		// Register REST API routes
		add_action('rest_api_init', function () use ($module) {
			// route for read data
		});
	}
}