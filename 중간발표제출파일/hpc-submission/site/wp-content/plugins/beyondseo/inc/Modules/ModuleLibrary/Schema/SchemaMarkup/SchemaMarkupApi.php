<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Helpers\RestHelpers;
use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleApi;
use Throwable;
use WP_REST_Server;

/**
 * Class SchemaMarkupApi
 */
class SchemaMarkupApi extends BaseSubmoduleApi {


	/**
	 * @inheritDoc
	 * @throws Throwable
	 */
	public function initializeApi(): void {
		/** @var SchemaMarkup $module */
		$module = $this->module;

		// Register REST API routes
		add_action('rest_api_init', function () use ($module) {

			$this->registerRouteRead(
                [$module, 'getSchemaData']
            );

            $argsFinalArray = array_merge($this->pathArgsValidate(), []);

			try {
				RestHelpers::registerRoute(
					$this->getLegacyApiBase(),
					'/' . $this->module->getModuleName() . '/(?P<id>\d+)/save',
					[
						'methods' => WP_REST_Server::CREATABLE,
						'callback' => [ $module, 'saveSchemaDataForPost' ],
						'permission_callback' => function() {
							return $this->checkPermissionsPost($this->module->getModuleName());
						},
						'args' => $argsFinalArray,
					]
				);
			} catch ( Throwable $e) {
				$this->log('Failed to register POST route with error message: ' . $e->getMessage(), 'ERROR');
			}
		});
	}
}
