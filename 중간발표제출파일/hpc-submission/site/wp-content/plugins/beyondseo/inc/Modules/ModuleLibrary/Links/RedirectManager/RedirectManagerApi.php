<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\RedirectManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Helpers\RestHelpers;
use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleApi;
use RankingCoach\Inc\Modules\ModuleBase\ModuleInterface;
use RankingCoach\Inc\Modules\ModuleLibrary\Links\RedirectManager\Dtos\GetRedirectsRequestDto;
use RankingCoach\Inc\Modules\ModuleLibrary\Links\RedirectManager\Dtos\GetRedirectsResponseDto;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function Symfony\Component\Translation\t;

/**
 * Class RedirectManagerApi
 * Handles API endpoints for the RedirectManager module
 */
class RedirectManagerApi extends BaseSubmoduleApi {
    
    /**
     * @var RedirectManager $module
     */
    protected ModuleInterface $module;
    
    /**
     * RedirectManagerApi constructor.
     *
     * @param ModuleInterface $module
     * @param array|null $params
     */
    public function __construct(ModuleInterface $module, ?array $params = null) {
        parent::__construct($module, $params);
    }

    /**
     * Initialize API endpoints for the RedirectManager module
     *
     * @return void
     * @throws \Throwable
     */
    public function initializeApi(): void {
        // Register REST API routes for CRUD operations
        $rma = $this;
        add_action('rest_api_init', static function () use ($rma) {
            $rma->registerRedirectRoutes();
        });
    }

    /**
     * Register all redirect-related REST API routes
     *
     * @return void
     * @throws \Throwable
     */
    protected function registerRedirectRoutes(): void {
        // Register GET all redirects route
        $this->registerRouteRead(
            [$this, 'getRedirects'],
            null,
            false
        );
        
        // Register GET single redirect route
        $this->registerRouteRead(
            [$this, 'getRedirect'],
            [$this, 'getRedirectArgs'],
        );
        
        // Register CREATE redirect route
        $this->registerRouteCreate(
            [$this, 'createRedirect'],
            [$this, 'createRedirectArgs']
        );

        // Register UPDATE redirect route
        $this->registerRouteEdit(
            [$this, 'updateRedirect'],
            [$this, 'updateRedirectArgs']
        );

        // Register DELETE redirect route
        $this->registerRouteDelete(
            [$this, 'deleteRedirect'],
            [$this, 'deleteRedirectArgs']
        );
        $server = rest_get_server();
        $routes = $server->get_routes();
        return;
    }
    
    public function checkPermissionsPost(string $moduleName): bool {
        return current_user_can('manage_options');
    }

    /**
     * Arguments for the getRedirect route
     * 
     * @return array
     */
    public function getRedirectArgs(): array {
        return [
            'id' => [
                'description' => 'Unique identifier for the redirect',
                'type' => 'integer',
                'required' => true,
                'in' => 'path',
                'validate_callback' => [$this, 'isNumeric'],
                'sanitize_callback' => 'absint',
            ],
        ];
    }
    
    /**
     * Arguments for the createRedirect route
     * 
     * @return array
     */
    public function createRedirectArgs(): array {
        return [
            'source_uri' => [
                'description' => 'Source URI to redirect from',
                'type' => 'string',
                'in' => 'body',
                'required' => true,
                'sanitize_callback' => fn($v) => $this->sanitizeUriOrUrl($v),
                'validate_callback' => [$this, 'isValidUri'],
            ],
            'destination_url' => [
                'description' => 'Destination URL to redirect to',
                'type' => 'string',
                'in' => 'body',
                'required' => true,
                'validate_callback' => [$this, 'isValidDestinationUrl'],
                'sanitize_callback' => fn($v) => $this->sanitizeUriOrUrl($v, true),
            ],
            'redirect_code' => [
                'description' => 'HTTP redirect code (301 or 302)',
                'type' => 'integer',
                'default' => 301,
                'in' => 'body',
                'enum' => [301, 302],
                'validate_callback' => [$this, 'isNumeric'],
                'sanitize_callback' => 'absint',
            ],
            'active' => [
                'description' => 'Whether the redirect is active',
                'type' => 'integer',
                'default' => 1,
                'in' => 'body',
                'enum' => [0, 1],
                'sanitize_callback' => 'absint',
            ],
        ];
    }
    
    /**
     * Arguments for the updateRedirect route
     * 
     * @return array
     */
    public function updateRedirectArgs(): array {
        return [
            'id' => [
                'description' => 'Unique identifier for the redirect',
                'type' => 'integer',
                'required' => true,
                'in' => 'path',
                'validate_callback' => [$this, 'isNumeric'],
                'sanitize_callback' => 'absint',
            ],
            'source_uri' => [
                'description' => 'Source URI to redirect from',
                'in' => 'body',
                'type' => 'string',
                'sanitize_callback' => fn($v) => $this->sanitizeUriOrUrl($v),
                'validate_callback' => [$this, 'isValidUri'],
            ],
            'destination_url' => [
                'description' => 'Destination URL to redirect to',
                'in' => 'body',
                'type' => 'string',
                'validate_callback' => [$this, 'isValidDestinationUrl'],
                'sanitize_callback' => fn($v) => $this->sanitizeUriOrUrl($v, true),
            ],
            'redirect_code' => [
                'description' => 'HTTP redirect code (301 or 302)',
                'type' => 'integer',
                'in' => 'body',
                'default' => 301,
                'enum' => [301, 302],
                'validate_callback' => [$this, 'isNumeric'],
                'sanitize_callback' => 'absint',
            ],
            'active' => [
                'description' => 'Whether the redirect is active',
                'type' => 'integer',
                'in' => 'body',
                'enum' => [0, 1],
                'sanitize_callback' => 'absint',
                'validate_callback' => [$this, 'isNumeric'],
            ],
        ];
    }
    
    /**
     * Arguments for the deleteRedirect route
     * 
     * @return array
     */
    public function deleteRedirectArgs(): array {
        return [
            'id' => [
                'description' => 'Unique identifier for the redirect',
                'type' => 'integer',
                'required' => true,
                'in' => 'path',
                'validate_callback' => [$this, 'isNumeric'],
                'sanitize_callback' => 'absint',
            ],
        ];
    }
    
    /**
     * Get all redirects
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getRedirects(WP_REST_Request $request): WP_REST_Response {
        $result = $this->module->getRedirects();
        
        return $this->generateSuccessResponse($result);
    }
    
    /**
     * Get a single redirect by ID
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getRedirect(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        
        $redirect = $this->module->getRedirect($id);
        
        if (!$redirect) {
            return $this->generateErrorResponse(
                null,
                'Redirect not found',
                404
            );
        }
        
        return $this->generateSuccessResponse($redirect);
    }
    
    /**
     * Create a new redirect
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function createRedirect(WP_REST_Request $request): WP_REST_Response {
        $source_uri = $request->get_param('source_uri');
        $destination_url = $request->get_param('destination_url');
        $redirect_code = (int) $request->get_param('redirect_code');
        $active = (int) $request->get_param('active');
        
        $result = $this->module->createRedirect(
            $source_uri,
            $destination_url,
            $redirect_code,
            $active
        );
        
        if (!$result) {
            return $this->generateErrorResponse(
                null,
                'Failed to create redirect. The source URI may already exist.',
                400
            );
        }
        
        $new_redirect = $this->module->getRedirect($result);
        
        return $this->generateSuccessResponse(
            $new_redirect,
            'Redirect created successfully',
            201
        );
    }
    
    /**
     * Update an existing redirect
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function updateRedirect(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        
        // Prepare data for update
        $data = [];
        
        // Only include fields that were provided
        if ($request->has_param('source_uri')) {
            $data['source_uri'] = $request->get_param('source_uri');
        }
        
        if ($request->has_param('destination_url')) {
            $data['destination_url'] = $request->get_param('destination_url');
        }
        
        if ($request->has_param('redirect_code')) {
            $data['redirect_code'] = (int) $request->get_param('redirect_code');
        }
        
        if ($request->has_param('active')) {
            $data['active'] = (int) $request->get_param('active');
        }
        
        $result = $this->module->updateRedirect($id, $data);
        
        if (!$result) {
            return $this->generateErrorResponse(
                null,
                'Failed to update redirect. The redirect may not exist or the source URI may already be in use.',
                400
            );
        }
        
        $updated_redirect = $this->module->getRedirect($id);
        
        return $this->generateSuccessResponse(
            $updated_redirect,
            'Redirect updated successfully'
        );
    }
    
    /**
     * Delete a redirect
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function deleteRedirect(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        
        $result = $this->module->deleteRedirect($id);
        
        if (!$result) {
            return $this->generateErrorResponse(
                null,
                'Failed to delete redirect. The redirect may not exist.',
                400
            );
        }
        
        return $this->generateSuccessResponse(
            ['id' => $id],
            'Redirect deleted successfully'
        );
    }

    /**
     * Sanitize a value to a URI format
     * @param string $value
     * @param bool $preserveExternal
     * @return string
     */
    public function sanitizeUriOrUrl(string $value, bool $preserveExternal = false): string
    {
        $site_url = home_url();
        $site_url_no_scheme = preg_replace('#^https?://#', '', rtrim($site_url, '/'));

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $value_no_scheme = preg_replace('#^https?://#', '', $value);

            if (str_starts_with($value_no_scheme, $site_url_no_scheme)) {
                // Internal URL → return URI
                $path = wp_parse_url($value, PHP_URL_PATH) ?? '';
                return $path === '/' ? '/' : untrailingslashit('/' . ltrim($path, '/'));
            }

            // External URL
            if ($preserveExternal) {
                return esc_url_raw($value); // keep full external link
            }

            // Otherwise force to empty or throw
            return '';
        }

        // Relative URI handling
        $path = wp_parse_url($value, PHP_URL_PATH) ?? '';
        return $path === '/' ? '/' : untrailingslashit('/' . ltrim($path, '/'));
    }

    public function isValidUri( $value ): bool|WP_Error
    {
        $site_url = home_url();
        $site_url_no_scheme = preg_replace('#^https?://#', '', rtrim($site_url, '/'));

        // If it's a full URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $value_no_scheme = preg_replace('#^https?://#', '', $value);
            if (!str_starts_with($value_no_scheme, $site_url_no_scheme)) {
                return new WP_Error(
                    'invalid_source_uri',
                    'The provided source must be a URL on this site.',
                    ['status' => 400]
                );
            }
            return true;
        }

        // Allow relative URIs
        if (!str_starts_with($value, '/')) {
            return new WP_Error(
                'invalid_source_uri_format',
                'The source must be a relative URI starting with /.',
                ['status' => 400]
            );
        }

        return true;
    }

    public function isValidDestinationUrl( $value ): bool|WP_Error
    {
        // Allow local paths starting with '/'
        if (str_starts_with($value, '/')) {
            return true;
        }
        
        // For non-local paths, require a valid URL with http/https
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return new WP_Error(
                'invalid_destination_url',
                'The destination_url must be either a valid URL (including http/https) or a local path starting with "/".',
                ['status' => 400]
            );
        }

        return true;
    }

    public function pathArgsValidate(): array
    {
        return [];
    }
}
