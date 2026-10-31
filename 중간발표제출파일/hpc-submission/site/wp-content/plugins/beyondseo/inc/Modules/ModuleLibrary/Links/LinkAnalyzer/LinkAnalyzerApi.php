<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkAnalyzer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleApi;
use RankingCoach\Inc\Modules\ModuleLibrary\Links\LinkAnalyzer\Dtos\GetAllLinksResponseDto;
use RankingCoach\Inc\Modules\ModuleManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class LinkAnalyzerApi
 * 
 * Handles API endpoints for the LinkAnalyzer module.
 */
class LinkAnalyzerApi extends BaseSubmoduleApi {

    /**
     * LinkAnalyzerApi constructor.
     * @param LinkAnalyzer $module
     * @param array|null $params
     */
    public function __construct(LinkAnalyzer $module, ?array $params = null) {
        $this->module = $module;
        parent::__construct($module, $params);
    }

    /**
     * Initialize API endpoints for the LinkAnalyzer module.
     *
     * @return void
     * @throws \Throwable
     */
    public function initializeApi(): void {
        // Register REST API routes for link operations
        $api = $this;
        add_action('rest_api_init', static function () use ($api) {
            $api->registerLinkRoutes();
        });
    }

    /**
     * Register all link-related REST API routes
     *
     * @return void
     * @throws \Throwable
     */
    protected function registerLinkRoutes(): void {
        // Register GET all links route
        $this->registerRouteRead(
            [$this, 'getAllLinks'],
            null,
            false
        );
        
        // Register GET links for post
        $this->registerRouteRead(
            [$this, 'getLinksForPost'],
            [$this, 'getLinksForPostArgs'],
            true
        );
        
        // Register POST index links route
        $this->registerCustomRoute(
            'index',
            'post_id',
            WP_REST_Server::CREATABLE,
            [$this, 'indexLinks'],
            [$this, 'indexLinksArgs']
        );
        
        // Register POST scan links route
        $this->registerCustomRoute(
            'scan',
            'post_id',
            WP_REST_Server::CREATABLE,
            [$this, 'scanLinks'],
            [$this, 'scanLinksArgs']
        );

        // Register POST scan links route
        $this->registerCustomRoute(
            'verify',
            'post_id',
            WP_REST_Server::CREATABLE,
            [$this, 'verifyLinks'],
            [$this, 'indexLinksArgs']
        );
    }
    
    /**
     * Arguments for the getLinksForPost route
     * 
     * @return array
     */
    public function getLinksForPostArgs(): array {
        return [
            'id' => [
                'description' => 'Post ID to get links for',
                'type' => 'integer',
                'required' => true,
                'in' => 'path',
                'validate_callback' => [$this, 'isNumeric'],
                'sanitize_callback' => 'absint',
            ],
        ];
    }
    
    /**
     * Arguments for the indexLinks route
     * 
     * @return array
     */
    public function indexLinksArgs(): array {
        return [
            'post_id' => [
                'description' => 'Post ID to get links for',
                'type' => 'integer',
                'required' => true,
                'in' => 'path',
                'validate_callback' => [$this, 'isNumeric'],
                'sanitize_callback' => 'absint',
            ],
        ];

    }
    
    /**
     * Arguments for the scanLinks route
     * 
     * @return array
     */
    public function scanLinksArgs(): array {
        return [
            'limit' => [
                'description' => 'Maximum number of links to scan',
                'type' => 'integer',
                'default' => 100,
                'in' => 'body',
                'validate_callback' => [$this, 'isNumeric'],
                'sanitize_callback' => 'absint',
            ],
            'post_id' => [
                'description' => 'Post ID to get links for',
                'type' => 'integer',
                'required' => true,
                'in' => 'path',
                'validate_callback' => [$this, 'isNumeric'],
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Get all links.
     *
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response The response
     * @throws \Exception
     */
    public function getAllLinks(WP_REST_Request $request): WP_REST_Response {
        $limit = (int) $request->get_param('limit') ?: 100;
        $offset = (int) $request->get_param('offset') ?: 0;
        
        $result = $this->module->getAllLinks(null, $limit, $offset);
        
        return $this->generateSuccessResponse($result);
    }

    /**
     * Get links for a specific post.
     * 
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response The response
     */
    public function getLinksForPost(WP_REST_Request $request): WP_REST_Response {
        $post_id = (int) $request->get_param('id');
        
        if (!get_post($post_id)) {
            return $this->generateErrorResponse(
                null,
                __('Invalid post ID', 'beyondseo'),
                404
            );
        }
        
        $links = $this->module->getLinksForPost($post_id);
        
        return $this->generateSuccessResponse($links);
    }
    
    /**
     * Index links from posts.
     * 
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response The response
     */
    public function indexLinks(WP_REST_Request $request): WP_REST_Response {
        $post_id = (int) $request->get_param('post_id') ?: 0;

        if (!get_post($post_id)) {
            return $this->generateErrorResponse(
                null,
                __('Invalid post ID', 'beyondseo'),
                404
            );
        }

        $this->module->getHooksComponent()->analyzeLinks($post_id);
        return $this->generateSuccessResponse($this->module->getLinksForPost($post_id));
    }

    /**
     * Scan links for broken status.
     *
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response The response
     * @throws \Exception
     */
    public function scanLinks(WP_REST_Request $request): WP_REST_Response {
        $postId = (int) $request->get_param('post_id');
        $links = $this->module->getLinksForPost($postId);
        if (empty($links)) {
            return $this->generateErrorResponse(
                null,
                'No links found for the specified post',
                404
            );
        }
        return $this->generateSuccessResponse([], 'Links are being scanned in the background. Please check back later for results.');
    }

    /**
     * Verify links for status.
     *
     * @throws \Exception
     */
    public function verifyLinks(WP_REST_Request $request): WP_REST_Response {
        $postId = (int) $request->get_param('post_id');
        $links = $this->module->getAllLinks($postId);
        $linkStatusIds = array_column($links['data'], 'link_status_id');
        if (count($linkStatusIds) > 0) {
            ModuleManager::instance()->brokenLinkChecker()->checkAllLinks($linkStatusIds);
        }
        return $this->generateSuccessResponse($this->module->getLinksForPost($postId), 'Links are being verified in the background. Please check back later for results.');
    }

    /**
     * Validate path arguments for the API.
     *
     * @return array
     */
    public function pathArgsValidate(): array {
        return [];
    }
}
