<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleBase;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Helpers\RestHelpers;
use ReflectionClass;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;

/**
 * Class BaseSubmoduleApi
 */
abstract class BaseSubmoduleApi {

	use RcLoggerTrait;
	use RcApiTrait;

	/** @var ModuleInterface The module instance. */
	protected ModuleInterface $module;

	/** @var string The module name. */
	protected string $module_name;

	/**
	 * Constructor
	 * @param ModuleInterface $module
	 * @param array|null $params
	 */
	public function __construct(ModuleInterface $module, ?array $params = null) {
		$this->module = $module;
		$this->module_name = strtolower((new ReflectionClass($module))->getShortName());
	}

	/**
	 * Registers a GET REST API route
	 * @param callable|null $dataCallback
	 * @param callable|null $argsCallback
	 * @return void
	 * @throws Throwable
	 */
	protected function registerRouteRead(?callable $dataCallback = null, ?callable $argsCallback = null, ?bool $singleItem = true): void {
		[$resolvedCallback, $resolvedArgs] = $this->resolveCallbacks($dataCallback, $argsCallback);

		try {
			RestHelpers::registerRoute(
				$this->getLegacyApiBase(),
				'/' . $this->module->getModuleName() . ($singleItem ? '/(?P<id>\d+)' : ''),
				[
					'methods' => WP_REST_Server::READABLE,
					'callback' => $resolvedCallback,
					'permission_callback' => function() {
						return $this->checkPermissionsGet($this->module->getModuleName());
					},
					'args' => $resolvedArgs,
				]
			);
		} catch ( Throwable $e) {
			$this->log('Failed to register GET route with error message: ' . $e->getMessage(), 'ERROR');
		}
	}

	/**
	 * Registers a POST REST API route
	 * @param callable|null $dataCallback
	 * @param callable|null $argsCallback
	 * @return void
	 * @throws Throwable
	 */
	protected function registerRouteCreate(?callable $dataCallback = null, ?callable $argsCallback = null): void {
		[$resolvedCallback, $resolvedArgs] = $this->resolveCallbacks($dataCallback, $argsCallback);

		try {
			RestHelpers::registerRoute(
				$this->getLegacyApiBase(),
				'/' . $this->module->getModuleName(),
				[
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => $resolvedCallback,
					'permission_callback' => function() {
						return $this->checkPermissionsPost($this->module->getModuleName());
					},
					'args' => $resolvedArgs,
				]
			);
		} catch ( Throwable $e) {
			$this->log('Failed to register POST route with error message: ' . $e->getMessage(), 'ERROR');
		}
	}
	
	/**
	 * Registers a PUT/PATCH REST API route for updating resources
	 * @param callable|null $dataCallback
	 * @param callable|null $argsCallback
	 * @return void
	 * @throws Throwable
	 */
	protected function registerRouteEdit(?callable $dataCallback = null, ?callable $argsCallback = null): void {
		[$resolvedCallback, $resolvedArgs] = $this->resolveCallbacks($dataCallback, $argsCallback);

		try {
			RestHelpers::registerRoute(
				$this->getLegacyApiBase(),
				'/' . $this->module->getModuleName() . '/(?P<id>\d+)',
				[
					'methods' => 'PATCH',
					'callback' => $resolvedCallback,
					'permission_callback' => function() {
						return $this->checkPermissionsPost($this->module->getModuleName());
					},
					'args' => $resolvedArgs,
				]
			);
		} catch ( Throwable $e) {
			$this->log('Failed to register PUT/PATCH route with error message: ' . $e->getMessage(), 'ERROR');
		}
	}
	
	/**
	 * Registers a DELETE REST API route
	 * @param callable|null $dataCallback
	 * @param callable|null $argsCallback
	 * @return void
	 * @throws Throwable
	 */
	protected function registerRouteDelete(?callable $dataCallback = null, ?callable $argsCallback = null): void {
		[$resolvedCallback, $resolvedArgs] = $this->resolveCallbacks($dataCallback, $argsCallback);

		try {
			RestHelpers::registerRoute(
				$this->getLegacyApiBase(),
				'/' . $this->module->getModuleName() . '/(?P<id>\d+)',
				[
					'methods' => WP_REST_Server::DELETABLE,
					'callback' => $resolvedCallback,
					'permission_callback' => function() {
						return $this->checkPermissionsPost($this->module->getModuleName());
					},
					'args' => $resolvedArgs,
				]
			);
		} catch ( Throwable $e) {
			$this->log('Failed to register DELETE route with error message: ' . $e->getMessage(), 'ERROR');
		}
	}
	
	/**
	 * Registers a custom REST API route with flexible parameters
	 * 
	 * @param string $urlSuffix The URL suffix to append to the module name
	 * @param string|null $idParam The parameter name for ID in the URL (optional)
	 * @param string $method The HTTP method (GET, POST, PUT, PATCH, DELETE)
	 * @param callable|null $dataCallback The callback function to handle the request
	 * @param callable|null $argsCallback The callback function to provide arguments
	 * @return void
	 * @throws Throwable
	 */
	protected function registerCustomRoute(
		string $urlSuffix = '',
		?string $idParam = null,
		string $method = WP_REST_Server::READABLE,
		?callable $dataCallback = null,
		?callable $argsCallback = null
	): void {
		[$resolvedCallback, $resolvedArgs] = $this->resolveCallbacks($dataCallback, $argsCallback);
		
		// Build the route path
		$routePath = '/' . $this->module->getModuleName();
		
		// Add URL suffix if provided
		if (!empty($urlSuffix)) {
			$routePath .= '/' . ltrim($urlSuffix, '/');
		}
		
		// Add ID parameter if provided
		if (!empty($idParam)) {
			$routePath .= '/(?P<' . $idParam . '>\d+)';
		}
		
		// Determine permission callback based on method
		$permissionCallback = $method === WP_REST_Server::READABLE
			? function() { return $this->checkPermissionsGet($this->module->getModuleName()); }
			: function(WP_REST_Request $request) {
				return $this->checkPermissionsPost($this->module->getModuleName())
					&& (!$request->has_param('post_id') || current_user_can('edit_post', (int) $request->get_param('post_id')));
			};
		
		try {
			RestHelpers::registerRoute(
				$this->getLegacyApiBase(),
				$routePath,
				[
					'methods' => $method,
					'callback' => $resolvedCallback,
					'permission_callback' => $permissionCallback,
					'args' => $resolvedArgs,
				]
			);
		} catch (Throwable $e) {
			$this->log('Failed to register custom route with error message: ' . $e->getMessage(), 'ERROR');
		}
	}

	/**
	 * Resolves callbacks and arguments for the routes
	 * @param callable|null $dataCallback
	 * @param callable|null $argsCallback
	 * @return array
	 */
	protected function resolveCallbacks(?callable $dataCallback, ?callable $argsCallback): array {
		$resolvedCallback = ($dataCallback && is_callable($dataCallback))
			? $dataCallback
			: function (WP_REST_Request $request): WP_REST_Response {
				if ($request->get_method() === 'GET') {
					return $this->getData($request->get_params());
				} elseif ($request->get_method() === 'POST') {
					return $this->postData($request->get_json_params());
				} elseif ($request->get_method() === 'PATCH' || $request->get_method() === 'PUT') {
                    return $this->postData($request->get_json_params());
                } elseif ($request->get_method() === 'DELETE') {
                    // Handle DELETE requests if needed
                    return $this->getData($request->get_params());
                }
				return $this->generateErrorResponse(null, 'Invalid HTTP method', 405);
			};

		$resolvedArgs = ($argsCallback && is_callable($argsCallback)) ? $argsCallback() : [];
		$resolvedArgs = array_merge($this->pathArgsValidate(), $resolvedArgs);

		return [$resolvedCallback, $resolvedArgs];
	}

	/**
	 * Route arguments validation
	 * @return array
	 */
	public function pathArgsValidate(): array {
		return [
			'id' => [
				'description' => 'The ID of the item',
				'required' => true,
				'type' => 'integer',
				'in' => 'path',
				'validate_callback' => [$this, 'isNumeric' ],
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Validates the parameter is numeric
	 * @param mixed $param
	 * @return bool
	 */
	public function isNumeric( mixed $param): bool {
		return is_numeric($param) && $param >= 0;
	}

	/**
	 * Validates the parameter is a string
	 * @param mixed $param
	 * @return bool
	 */
	public function isString( mixed $param): bool {
		return is_string($param) && !empty($param);
	}


	/**
	 * Checks user permissions for accessing the route
	 * @param string $moduleName
	 * @return bool
	 */
	public function checkPermissionsGet(string $moduleName): bool {
		return current_user_can('edit_posts');
	}

	/**
	 * Check the current user permissions for POST.
	 * @param string $moduleName
	 * @return bool
	 */
	public function checkPermissionsPost(string $moduleName): bool {
		return current_user_can('edit_posts');
	}

	/**
	 * Handles GET data requests
	 * @param array $params
	 * @return WP_REST_Response
	 */
	public function getData(array $params ): WP_REST_Response {
		return $this->generateSuccessResponse($params);
	}

	/**
	 * Handles POST data requests
	 * @param array $data
	 * @return WP_REST_Response
	 */
	public function postData(array $data ): WP_REST_Response {
		// Process and return the POST data
		return $this->generateSuccessResponse($data);
	}

	/**
	 * Initializes the API.
	 * This method should register all required routes and setup necessary configurations
	 * for the module's API integration.
	 * @return void
	 */
	abstract public function initializeApi(): void;
}
