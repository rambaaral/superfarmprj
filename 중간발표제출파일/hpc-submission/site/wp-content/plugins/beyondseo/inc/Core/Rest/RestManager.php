<?php
declare(strict_types=1);
/**
 * REST API Manager for rankingCoach SEO Plugin
 *
 * Manages all REST API endpoint registrations and authentication for the plugin.
 * Follows WordPress REST API best practices with explicit route registration.
 *
 * @package RankingCoach\Inc\Core\Rest
 */

namespace RankingCoach\Inc\Core\Rest;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Rest\Dtos\ModulesResponseDto;
use Exception;
use RankingCoach\Inc\Core\Rest\Controllers\AccountProjectController;
use RankingCoach\Inc\Core\Rest\Controllers\AdvancedSettingsController;
use RankingCoach\Inc\Core\Rest\Controllers\IntegrationController;
use RankingCoach\Inc\Core\Rest\Controllers\ImportController;
use RankingCoach\Inc\Core\Rest\Controllers\MetaTagsController;
use RankingCoach\Inc\Core\Rest\Controllers\OnboardingController;
use RankingCoach\Inc\Core\Rest\Controllers\OptimiserController;
use RankingCoach\Inc\Core\Rest\Controllers\PluginInformationController;
use RankingCoach\Inc\Core\Rest\Controllers\SocialController;
use RankingCoach\Inc\Core\Rest\Controllers\SupportController;
use RankingCoach\Inc\Core\Rest\Controllers\SyncController;
use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Breadcrumbs\BreadcrumbsMultipleResponseHandler;
use RankingCoach\Inc\Core\Breadcrumbs\Dtos\BreadcrumbsRequestDto;
use RankingCoach\Inc\Core\Breadcrumbs\Dtos\BreadcrumbsResponseDto;
use RankingCoach\Inc\Core\CurrentUserManager;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\PluginSettings;
use RankingCoach\Inc\Core\Review\ReviewState;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\RestHelpers;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Settings\Dtos\SettingsRequestDto;
use RankingCoach\Inc\Core\Settings\Dtos\SettingsResponseDto;
use RankingCoach\Inc\Core\Settings\Dtos\SingleSettingRequestDto;
use RankingCoach\Inc\Core\Settings\Dtos\SingleSettingResponseDto;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use Throwable;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class RestManager
 *
 * Handles all REST API endpoint registrations for the rankingCoach plugin.
 * Each endpoint category is registered explicitly with proper permission callbacks
 * using WordPress capabilities for access control.
 *
 * @since 1.0.0
 */
class RestManager extends WP_REST_Controller {

    use RcApiTrait;
    use RcLoggerTrait;

    /**
     * Prefix for download logs token options.
     *
     * @var string
     */
    private string $tokenOptionPrefix = 'rankingcoach_download_logs_token_';

    /**
     * Time-to-live for download tokens in seconds.
     *
     * @var int
     */
    private int $tokenTTL = 300;

    /**
     * Constructor.
     *
     * Initializes the REST manager and registers API routes.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->namespace = RANKINGCOACH_REST_API_BASE;

        // Register the API routes with WordPress
        add_action('rest_api_init', [$this, 'registerAllRoutes'], 5);

        // REST URL fallback filter for compatibility
        add_filter('rest_url', [$this, 'filterRestUrl'], 10, 1);
    }

    /**
     * Filter REST URL for fallback compatibility.
     *
     * @since 1.0.0
     * @param string $url The REST API URL.
     * @return string Modified URL if fallback is needed.
     */
    public function filterRestUrl(string $url): string {
        if ((bool) get_transient(RestHelpers::TRANSIENT_KEY)) {
            return trailingslashit(home_url('/index.php')) . '?rest_route=/';
        }
        return $url;
    }

    /**
     * Register all REST API routes.
     *
     * This method is the main entry point for route registration.
     * Routes are organized by category for better maintainability.
     *
     * @since 1.0.0
     * @return void
     */
    public function registerAllRoutes(): void {
        // Register legacy/native WordPress routes
        $this->registerLegacyRoutes();

        $this->registerV1Routes();
    }

    /*
    |--------------------------------------------------------------------------

    |--------------------------------------------------------------------------
    |


    |
    */





    /*
    |--------------------------------------------------------------------------
    | Service Integration Routes
    |--------------------------------------------------------------------------
    |
    | Routes for external service integrations.
    | Requires WordPress Application Password authentication.
    |
    */



    /**
     * Check permission for service integration endpoints.
     *
     * Validates Application Password authentication and capability.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authorized, WP_Error on failure.
     */
    public function checkServiceIntegrationPermission(WP_REST_Request $request): bool|WP_Error {
        // Verify Application Password authentication
        if (!$this->verifyAppPasswordAuth($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required. Use WordPress Application Password with Basic Auth.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access integration endpoints.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        $this->log('Integration endpoint access authenticated successfully.', 'INFO');
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Service Sync Routes
    |--------------------------------------------------------------------------
    |
    | Routes for data synchronization services.
    | Requires WordPress Application Password authentication.
    |
    */



    /**
     * Check permission for sync service endpoints.
     *
     * Validates Application Password authentication and capability.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authorized, WP_Error on failure.
     */
    public function checkServiceSyncPermission(WP_REST_Request $request): bool|WP_Error {
        // Verify Application Password authentication
        if (!$this->verifyAppPasswordAuth($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required. Use WordPress Application Password with Basic Auth.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access sync endpoints.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        $this->log('Sync endpoint access authenticated successfully.', 'INFO');
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Management Routes
    |--------------------------------------------------------------------------
    |
    | Routes for plugin administration.
    | Requires nonce authentication and admin capabilities.
    |
    */



    /**
     * Check permission for admin endpoints.
     *
     * Validates nonce authentication and admin capability.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authorized, WP_Error on failure.
     */
    public function checkAdminPermission(WP_REST_Request $request): bool|WP_Error {
        // Verify nonce authentication
        if (!$this->verifyNonceAuth($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid or missing security nonce.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        // Check admin capability
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access admin endpoints.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration Routes
    |--------------------------------------------------------------------------
    |
    | Routes for plugin configuration management.
    | Requires nonce authentication and admin capabilities.
    |
    */



    /**
     * Check permission for config endpoints.
     *
     * Validates nonce authentication and config capability.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authorized, WP_Error on failure.
     */
    public function checkConfigPermission(WP_REST_Request $request): bool|WP_Error {
        if (!$this->verifyNonceAuth($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid or missing security nonce.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access config endpoints.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Meta Tags Routes
    |--------------------------------------------------------------------------
    |
    | Routes for SEO meta tags management.
    | Requires nonce authentication and admin capabilities.
    |
    */



    /**
     * Check permission for metatags endpoints.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authorized, WP_Error on failure.
     */
    public function checkMetatagsPermission(WP_REST_Request $request): bool|WP_Error {
        if (!$this->verifyNonceAuth($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid or missing security nonce.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access metatags endpoints.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Content Analysis Routes
    |--------------------------------------------------------------------------
    |
    | Routes for SEO content analysis.
    | Requires nonce authentication and appropriate capabilities.
    |
    */



    /**
     * Check permission for content analysis endpoints.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authorized, WP_Error on failure.
     */
    public function checkContentAnalysisPermission(WP_REST_Request $request): bool|WP_Error {
        if (!$this->verifyNonceAuth($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid or missing security nonce.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        // Content analysis is available to users who can edit posts
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access content analysis.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Onboarding Routes
    |--------------------------------------------------------------------------
    |
    | Routes for plugin onboarding process.
    | Requires nonce authentication and admin capabilities.
    |
    */



    /**
     * Check permission for onboarding endpoints.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authorized, WP_Error on failure.
     */
    public function checkOnboardingPermission(WP_REST_Request $request): bool|WP_Error {
        if (!$this->verifyNonceAuth($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid or missing security nonce.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access onboarding.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Plugin Information Routes
    |--------------------------------------------------------------------------
    |
    | Routes for plugin information and status.
    | Requires nonce authentication and admin capabilities.
    |
    */



    /**
     * Check permission for plugin information endpoints.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authorized, WP_Error on failure.
     */
    public function checkPluginInformationPermission(WP_REST_Request $request): bool|WP_Error {
        if (!$this->verifyNonceAuth($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid or missing security nonce.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access plugin information.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Support Routes
    |--------------------------------------------------------------------------
    |
    | Routes for support/diagnostic information.
    | Requires nonce authentication and admin capabilities.
    |
    */

    /**
     * Check permission for support endpoints.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authorized, WP_Error on failure.
     */
    public function checkSupportPermission(WP_REST_Request $request): bool|WP_Error {
        if (!$this->verifyNonceAuth($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid or missing security nonce.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access support information.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | SEO Optimiser Routes
    |--------------------------------------------------------------------------
    |
    | Routes for SEO optimization features.
    | Requires nonce authentication, admin capabilities, and completed onboarding.
    |
    */



    /**
     * Check permission for optimizer endpoints.
     *
     * Validates nonce, capabilities, and onboarding completion.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authorized, WP_Error on failure.
     */
    public function checkOptimiserPermission(WP_REST_Request $request): bool|WP_Error {

        if (!$this->verifyNonceAuth($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid or missing security nonce.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        if (!current_user_can('manage_options')) {
            $this->log('Optimiser endpoint access denied: insufficient permissions', 'WARNING');
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access the SEO Optimiser.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    /*
|--------------------------------------------------------------------------
| Advanced Tab Settings Routes
|--------------------------------------------------------------------------
|
| Routes for advanced tab settings.
| Requires nonce authentication and admin capabilities.
|
*/



    /**
     * Check permission for social media endpoints.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authorized, WP_Error on failure.
     */
    public function checkAdvancedSettingsPermission(WP_REST_Request $request): bool|WP_Error {
        if (!$this->verifyNonceAuth($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid or missing security nonce.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access social media settings.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Social Media Routes
    |--------------------------------------------------------------------------
    |
    | Routes for social media optimization features.
    | Requires nonce authentication and admin capabilities.
    |
    */



    /**
     * Check permission for social media endpoints.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if authorized, WP_Error on failure.
     */
    public function checkSocialPermission(WP_REST_Request $request): bool|WP_Error {
        if (!$this->verifyNonceAuth($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid or missing security nonce.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access social media settings.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Legacy/Native WordPress Routes
    |--------------------------------------------------------------------------
    |
    | WordPress handles These routes directly.
    |
    */

    /**
     * Register legacy WordPress routes.
     *
     * These endpoints are handled natively by WordPress. Each route has explicit capability requirements.
     *
     * @since 1.0.0
     * @return void
     */
    public function registerLegacyRoutes(): void {
        $accountProjectController = new AccountProjectController();
        register_rest_route(
            $this->namespace,
            '/account/project',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$accountProjectController, 'get'],
                    'permission_callback' => [$this, 'checkAdminPermission'],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$accountProjectController, 'create'],
                    'permission_callback' => [$this, 'checkAdminPermission'],
                ],
            ]
        );
        register_rest_route(
            $this->namespace,
            '/account/project/(?P<locationId>[^/]+)',
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$accountProjectController, 'delete'],
                'permission_callback' => [$this, 'checkAdminPermission'],
                'args'                => [
                    'locationId' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'validate_callback' => [AccountProjectController::class, 'isValidLocationId'],
                    ],
                ],
            ]
        );

        // Feature modules endpoint
        register_rest_route(
            $this->namespace,
            '/feature_modules',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'rankingcoachModules'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]
        );

        // Account details endpoint
        register_rest_route(
            $this->namespace,
            '/account_details',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'rankingcoachAccountDetails'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ]
        );

        // Location keywords endpoint
        register_rest_route(
            $this->namespace,
            '/location_keywords',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'rankingcoachLocationKeywords'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ]
        );

        // Variables endpoint with post ID
        register_rest_route(
            $this->namespace,
            '/rc_variables/(?P<id>\d+)/data',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'rankingcoachVariables'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
                'args'                => [
                    'id' => [
                        'description'       => 'The ID of the post',
                        'type'              => 'integer',
                        'required'          => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param) && (int) $param > 0;
                        },
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        // General settings endpoint
        register_rest_route(
            $this->namespace,
            '/settings',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'handleGeneralSettings'],
                    'permission_callback' => function () {
                        return current_user_can('manage_options');
                    },
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'handleGeneralSettings'],
                    'permission_callback' => function () {
                        return current_user_can('manage_options');
                    },
                ],
            ]
        );

        // Single setting endpoint
        register_rest_route(
            $this->namespace,
            '/settings/(?P<key>[a-zA-Z0-9_-]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'handleSingleSetting'],
                    'permission_callback' => function () {
                        return current_user_can('manage_options');
                    },
                    'args'                => [
                        'key' => [
                            'description'       => 'The setting key to retrieve or update',
                            'type'              => 'string',
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return !empty($param) && is_string($param);
                            },
                            'sanitize_callback' => 'sanitize_key',
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'handleSingleSetting'],
                    'permission_callback' => function () {
                        return current_user_can('manage_options');
                    },
                    'args'                => [
                        'key' => [
                            'description'       => 'The setting key to update',
                            'type'              => 'string',
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return !empty($param) && is_string($param);
                            },
                            'sanitize_callback' => 'sanitize_key',
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'handleSingleSetting'],
                    'permission_callback' => function () {
                        return current_user_can('manage_options');
                    },
                    'args'                => [
                        'key' => [
                            'description'       => 'The setting key to reset',
                            'type'              => 'string',
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return !empty($param) && is_string($param);
                            },
                            'sanitize_callback' => 'sanitize_key',
                        ],
                    ],
                ],
            ]
        );

        // Breadcrumbs endpoint
        register_rest_route(
            $this->namespace,
            '/breadcrumbs',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleBreadcrumbs'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'args'                => [
                    'types' => [
                        'description'       => 'Array of breadcrumb types to generate',
                        'type'              => 'array',
                        'required'          => true,
                        'validate_callback' => function ($param) {
                            return is_array($param) && !empty($param);
                        },
                        'sanitize_callback' => function ($param) {
                            return array_map('sanitize_text_field', $param);
                        },
                    ],
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | V1 Native Routes
    |--------------------------------------------------------------------------
    */

    private function registerV1Routes(): void
    {
        $this->registerV1MetaTagsRoutes();
        $this->registerV1SocialRoutes();
        $this->registerV1AdvancedSettingsRoutes();
        $this->registerV1OnboardingPartialRoutes();
        $this->registerV1OnboardingRemoteRoutes();
        $this->registerV1OptimiserRoutes();
        $this->registerV1PluginInfoRoutes();
        $this->registerV1IntegrationRoutes();
        $this->registerV1SyncRoutes();
        $this->registerV1SupportRoutes();
        $this->registerV1ReviewRoutes();
        (new ImportController())->registerRoutes($this->namespace);
    }

    private function registerV1ReviewRoutes(): void
    {
        register_rest_route(
            $this->namespace,
            '/review',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'persistReview'],
                'permission_callback' => [$this, 'checkReviewPermission'],
                'args'                => [
                    'reviewEvent' => [
                        'type'              => 'string',
                        'required'          => true,
                        'enum'              => ['display', 'later', 'close', 'review', 'support'],
                        'validate_callback' => fn($value) => is_string($value)
                            && in_array($value, ['display', 'later', 'close', 'review', 'support'], true),
                    ],
                ],
            ]
        );
    }

    public function checkReviewPermission(WP_REST_Request $request): bool|WP_Error
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce)) {
            $nonce = $request->get_param('_ajax_nonce') ?? $request->get_param('_wpnonce');
        }

        if (!is_user_logged_in() || !is_string($nonce) || $nonce === ''
            || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid or missing security nonce.', 'beyondseo'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    public function persistReview(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $reviewEvent = $request->get_param('reviewEvent');
        if (!is_string($reviewEvent)
            || !in_array($reviewEvent, ['display', 'later', 'close', 'review', 'support'], true)) {
            return new WP_Error(
                'review_invalid_event',
                __('Invalid Review event.', 'beyondseo'),
                ['status' => 400]
            );
        }

        try {
            $reviewState = new ReviewState();
            if ($reviewEvent === 'display') {
                $reviewCountPersisted = $reviewState->incrementDisplayCount();
                $reviewCooldownPersisted = $reviewState->extendCooldown(time() + 14 * DAY_IN_SECONDS);
                $reviewPersisted = $reviewCountPersisted && $reviewCooldownPersisted;
            } elseif ($reviewEvent === 'later' || $reviewEvent === 'close') {
                $reviewPersisted = $reviewState->extendCooldown(time() + 14 * DAY_IN_SECONDS);
            } else {
                $reviewPersisted = $reviewState->stopPermanently();
            }

            if (!$reviewPersisted) {
                return new WP_Error(
                    'review_persistence_failed',
                    __('Unable to save Review state.', 'beyondseo'),
                    ['status' => 500]
                );
            }

            return new WP_REST_Response([
                'review' => [
                    'displayCount'       => $reviewState->getDisplayCount(),
                    'eligible'           => $reviewState->isEligible(),
                    'cooldownUntil'      => $reviewState->getCooldownUntil(),
                    'permanentlyStopped' => $reviewState->isPermanentlyStopped(),
                ],
            ], 200);
        } catch (Throwable $e) {
            return new WP_Error(
                'review_persistence_failed',
                __('Unable to save Review state.', 'beyondseo'),
                ['status' => 500]
            );
        }
    }

    private function registerV1MetaTagsRoutes(): void
    {
        $controller = new MetaTagsController();

        $postIdArg = [
            'postId' => [
                'description'       => 'Post ID',
                'type'              => 'integer',
                'required'          => true,
                'validate_callback' => fn($p) => is_numeric($p) && (int) $p > 0,
                'sanitize_callback' => 'absint',
            ],
        ];

        register_rest_route(
            $this->namespace,
            '/metatags/(?P<postId>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$controller, 'get'],
                    'permission_callback' => [$this, 'checkMetatagsPermission'],
                    'args'                => $postIdArg,
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$controller, 'save'],
                    'permission_callback' => [$this, 'checkMetatagsPermission'],
                    'args'                => $postIdArg,
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/metatags/(?P<postId>\d+)/keyword/swap',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$controller, 'swapKeyword'],
                'permission_callback' => [$this, 'checkMetatagsPermission'],
                'args'                => $postIdArg,
            ]
        );

        register_rest_route(
            $this->namespace,
            '/metatags/(?P<postId>\d+)/keyword',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$controller, 'assignKeyword'],
                    'permission_callback' => [$this, 'checkMetatagsPermission'],
                    'args'                => $postIdArg,
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$controller, 'detachKeyword'],
                    'permission_callback' => [$this, 'checkMetatagsPermission'],
                    'args'                => $postIdArg,
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/metatags/(?P<postId>\d+)/keywords',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$controller, 'listKeywords'],
                'permission_callback' => [$this, 'checkMetatagsPermission'],
                'args'                => $postIdArg,
            ]
        );

        register_rest_route(
            $this->namespace,
            '/metatags/(?P<postId>\d+)/content/keywords',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$controller, 'extractContentKeywords'],
                'permission_callback' => [$this, 'checkMetatagsPermission'],
                'args'                => $postIdArg,
            ]
        );

        register_rest_route(
            $this->namespace,
            '/metatags/(?P<postId>\d+)/separator',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$controller, 'getSeparator'],
                    'permission_callback' => [$this, 'checkMetatagsPermission'],
                    'args'                => $postIdArg,
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$controller, 'saveSeparator'],
                    'permission_callback' => [$this, 'checkMetatagsPermission'],
                    'args'                => $postIdArg,
                ],
            ]
        );
    }

    private function registerV1SocialRoutes(): void
    {
        $controller = new SocialController();

        $postIdArg = [
            'postId' => [
                'description'       => 'Post ID',
                'type'              => 'integer',
                'required'          => true,
                'validate_callback' => fn($p) => is_numeric($p) && (int) $p > 0,
                'sanitize_callback' => 'absint',
            ],
        ];

        register_rest_route(
            $this->namespace,
            '/social/(?P<postId>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$controller, 'get'],
                    'permission_callback' => [$this, 'checkSocialPermission'],
                    'args'                => $postIdArg,
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$controller, 'save'],
                    'permission_callback' => [$this, 'checkSocialPermission'],
                    'args'                => $postIdArg,
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/social/(?P<postId>\d+)/image_sources',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$controller, 'imageSources'],
                'permission_callback' => [$this, 'checkSocialPermission'],
                'args'                => $postIdArg,
            ]
        );
    }

    private function registerV1AdvancedSettingsRoutes(): void
    {
        $controller = new AdvancedSettingsController();

        $postIdArg = [
            'postId' => [
                'description'       => 'Post ID',
                'type'              => 'integer',
                'required'          => true,
                'validate_callback' => fn($p) => is_numeric($p) && (int) $p > 0,
                'sanitize_callback' => 'absint',
            ],
        ];

        register_rest_route(
            $this->namespace,
            '/advanced-settings/(?P<postId>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$controller, 'get'],
                    'permission_callback' => [$this, 'checkAdvancedSettingsPermission'],
                    'args'                => $postIdArg,
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$controller, 'save'],
                    'permission_callback' => [$this, 'checkSocialPermission'],
                    'args'                => $postIdArg,
                ],
            ]
        );
    }

    private function registerV1OnboardingPartialRoutes(): void
    {
        $controller = new OnboardingController();

        register_rest_route(
            $this->namespace,
            '/onboarding/requirements',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$controller, 'getRequirements'],
                    'permission_callback' => [$this, 'checkOnboardingPermission'],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$controller, 'addRequirement'],
                    'permission_callback' => [$this, 'checkOnboardingPermission'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/onboarding/requirements/(?P<requirementId>\d+)',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$controller, 'updateRequirement'],
                'permission_callback' => [$this, 'checkOnboardingPermission'],
                'args'                => [
                    'requirementId' => [
                        'description'       => 'Requirement ID',
                        'type'              => 'integer',
                        'required'          => true,
                        'validate_callback' => fn($p) => is_numeric($p) && (int) $p > 0,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/onboarding/categories',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$controller, 'getCategories'],
                'permission_callback' => [$this, 'checkOnboardingPermission'],
                'args'                => [
                    'search' => [
                        'description'       => 'Search term',
                        'type'              => 'string',
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/onboarding/steps/(?P<stepId>[a-zA-Z0-9_\-]+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$controller, 'getStep'],
                'permission_callback' => [$this, 'checkOnboardingPermission'],
                'args'                => [
                    'stepId' => [
                        'description'       => 'Step ID',
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );
    }

    private function registerV1OnboardingRemoteRoutes(): void
    {
        $controller = new OnboardingController();

        $remoteRoutes = [
            '/onboarding/submit'             => 'submit',
            '/onboarding/scan'               => 'scan',
            '/onboarding/steps/generate'     => 'generateSteps',
            '/onboarding/extract'            => 'extractAuto',
            '/onboarding/steps/submit'       => 'submitStep',
            '/onboarding/location/suggestions' => 'locationSuggestions',
        ];

        foreach ($remoteRoutes as $path => $method) {
            register_rest_route(
                $this->namespace,
                $path,
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$controller, $method],
                    'permission_callback' => [$this, 'checkOnboardingPermission'],
                ]
            );
        }
    }

    private function registerV1OptimiserRoutes(): void
    {
        $controller = new OptimiserController();

        register_rest_route(
            $this->namespace,
            '/optimiser/get_seo_issues',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$controller, 'getSeoIssuesRest'],
                'permission_callback' => [$this, 'checkOptimiserPermission'],
                'args'                => [
                    'suggestions' => [
                        'description'       => 'JSON array of suggestion keys',
                        'type'              => 'string',
                        'required'          => false,
                        'validate_callback' => static function ($value): bool {
                            if (!is_string($value)) {
                                return false;
                            }

                            $suggestions = json_decode($value);
                            if (json_last_error() !== JSON_ERROR_NONE || !is_array($suggestions)) {
                                return false;
                            }

                            foreach ($suggestions as $suggestion) {
                                if (!is_string($suggestion) || $suggestion === '') {
                                    return false;
                                }
                            }

                            return true;
                        },
                        'sanitize_callback' => static fn($value) => $value,
                    ],
                    'post_id' => [
                        'description'       => 'Optional post ID',
                        'type'              => 'integer',
                        'required'          => false,
                        'validate_callback' => static fn($value) => (is_int($value) || is_string($value))
                            && ctype_digit((string) $value)
                            && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false,
                        'sanitize_callback' => static fn($value) => (int) $value,
                    ],
                ],
            ]
        );

        $postIdArg = [
            'postId' => [
                'description'       => 'Post ID',
                'type'              => 'integer',
                'required'          => true,
                'validate_callback' => fn($p) => is_numeric($p) && (int) $p > 0,
                'sanitize_callback' => 'absint',
            ],
        ];

        register_rest_route(
            $this->namespace,
            '/optimiser/(?P<postId>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$controller, 'get'],
                    'permission_callback' => [$this, 'checkOptimiserPermission'],
                    'args'                => $postIdArg,
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$controller, 'run'],
                    'permission_callback' => [$this, 'checkOptimiserPermission'],
                    'args'                => $postIdArg,
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/optimiser/(?P<postId>\d+)/data',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$controller, 'exportData'],
                'permission_callback' => [$this, 'checkOptimiserPermission'],
                'args'                => array_merge($postIdArg, [
                    'export' => [
                        'description'       => 'Export format',
                        'type'              => 'string',
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ]),
            ]
        );
    }

    private function registerV1PluginInfoRoutes(): void
    {
        $controller = new PluginInformationController();

        register_rest_route(
            $this->namespace,
            '/pluginInformation',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$controller, 'info'],
                'permission_callback' => [$this, 'checkPluginInformationPermission'],
            ]
        );
    }

    private function registerV1IntegrationRoutes(): void
    {
        $controller = new IntegrationController();

        register_rest_route(
            $this->namespace,
            '/integration/status',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$controller, 'status'],
                'permission_callback' => [$this, 'checkServiceIntegrationPermission'],
            ]
        );
    }

    private function registerV1SyncRoutes(): void
    {
        $controller = new SyncController();

        register_rest_route(
            $this->namespace,
            '/sync/keywords',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$controller, 'keywords'],
                'permission_callback' => [$this, 'checkServiceSyncPermission'],
            ]
        );
    }

    private function registerV1SupportRoutes(): void
    {
        $controller = new SupportController();

        register_rest_route(
            $this->namespace,
            '/support/site-info',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$controller, 'siteInfo'],
                'permission_callback' => [$this, 'checkSupportPermission'],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Request Handlers
    |--------------------------------------------------------------------------
    */



    /**
     * Handle OPTIONS requests for CORS preflight.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Empty response with CORS headers.
     */
    public function handleOptionsRequest(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(null, 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Verify WordPress Application Password authentication.
     *
     * Validates Basic Auth credentials against WordPress Application Passwords.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return bool True if authenticated, false otherwise.
     */
    private function verifyAppPasswordAuth(WP_REST_Request $request): bool {
        // Get Authorization header
        $auth_header = $request->get_header('Authorization');
        if (empty($auth_header) || !str_starts_with($auth_header, 'Basic ')) {
            $this->log('App password authentication failed: Missing or invalid Authorization header', 'WARNING');
            return false;
        }

        // Decode credentials
        $auth = substr($auth_header, 6);
        $auth = base64_decode($auth);
        if (!$auth || !str_contains($auth, ':')) {
            $this->log('App password authentication failed: Invalid credential format', 'WARNING');
            return false;
        }

        [$username, $password] = explode(':', $auth, 2);

        if (empty($username) || empty($password)) {
            $this->log('App password authentication failed: Empty username or password', 'WARNING');
            return false;
        }

        // Verify user exists
        $user_data = get_user_by('login', $username);
        if (!$user_data) {
            wp_authenticate_application_password(null, $username, $password);
            $this->log('App password authentication failed: Invalid username', 'WARNING');
            return false;
        }

        // Authenticate using WordPress Application Passwords
        $user = wp_authenticate_application_password(null, $username, $password);

        if (is_wp_error($user)) {
            $this->log('App password authentication failed: ' . $user->get_error_message(), 'WARNING');
            return false;
        }

        // Verify basic capability
        if (!user_can($user, 'edit_posts')) {
            $this->log('App password authentication failed: User lacks required capabilities', 'WARNING');
            return false;
        }

        // Set current user
        wp_set_current_user($user->ID);

        $this->log('App password authentication successful for user: ' . $username, 'INFO');
        return true;
    }

    /**
     * Verify WordPress nonce authentication.
     *
     * Validates the REST API nonce for frontend requests.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return bool True if authenticated, false otherwise.
     */
    private function verifyNonceAuth(WP_REST_Request $request): bool {
        // Get nonce from header first
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce) && isset($_SERVER['HTTP_X_WP_NONCE'])) {
            $nonce = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE']));
        }

        // Fallback to body/query parameters
        if (empty($nonce)) {
            $body_params = $request->get_body_params();
            $nonce = $request->get_param('_ajax_nonce')
                ?? $request->get_param('_wpnonce')
                ?? ($body_params['_ajax_nonce'] ?? null)
                ?? ($body_params['_wpnonce'] ?? null)
                ?? null;
        }

        if (empty($nonce)) {
            return false;
        }

        // Verify nonce
        $verified = wp_verify_nonce($nonce, 'wp_rest');

        if (!$verified) {
            $this->log('Nonce verification failed', 'WARNING');
            return false;
        }

        return is_user_logged_in() && current_user_can('read');
    }

    /*
    |--------------------------------------------------------------------------
    | Callback Methods (Existing implementations)
    |--------------------------------------------------------------------------
    |
    | The following methods remain unchanged from the original implementation.
    | They handle the actual business logic for each endpoint.
    |
    */

    /**
     * Get the list of available modules.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response
     * @throws ReflectionException
     * @throws Exception
     */
    public function rankingcoachModules(WP_REST_Request $request): WP_REST_Response {
        if ('GET' !== $request->get_method()) {
            return $this->generateErrorResponse(null, 'Method not allowed', 405);
        }

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        try {
            $modulesData = apply_filters('rankingcoach_modules/data', $moduleManager->get_modules_names());

            return $this->generateApiResponse([
                'modules' => $modulesData,
            ]);
        } catch (Exception $e) {
            $this->log('RankingCoach Modules Error: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, $e->getMessage(), 500);
        }
    }

    /**
     * Get the account details.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response
     * @throws Throwable
     */
    public function rankingcoachAccountDetails(WP_REST_Request $request): WP_REST_Response {
        if ('GET' !== $request->get_method()) {
            return $this->generateErrorResponse(null, 'Method not allowed', 405);
        }

        try {
            $accountDetails = UserApiManager::getInstance(bearerToken: true)->fetchAndInsertAccountData();
            return $this->generateSuccessResponse($accountDetails);
        } catch (Exception $e) {
            $this->log('RankingCoach Account Details Error: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, $e->getMessage(), 500);
        }
    }

    /**
     * Get the location keywords.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response
     * @throws Throwable
     */
    public function rankingcoachLocationKeywords(WP_REST_Request $request): WP_REST_Response {
        if ('GET' !== $request->get_method()) {
            return $this->generateErrorResponse(null, 'Method not allowed', 405);
        }

        try {
            $keywordsDetails = UserApiManager::getInstance(bearerToken: true)->fetchAndInsertAccountData();
            return $this->generateSuccessResponse($keywordsDetails);
        } catch (Exception $e) {
            $this->log('Location keywords error: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, $e->getMessage(), 500);
        }
    }

    /**
     * Get available variables for a post.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response
     */
    public function rankingcoachVariables(WP_REST_Request $request): WP_REST_Response {
        if ('GET' !== $request->get_method()) {
            return $this->generateErrorResponse(null, 'Method not allowed', 405);
        }

        $postId = (int) $request->get_param('id');
        $content = get_post($postId);
        if (!$content) {
            return $this->generateErrorResponse(null, __('Invalid post ID', 'beyondseo'));
        }

        try {
            $variables = WordpressHelpers::get_available_WPVariables(['post' => $content]);
            return $this->generateSuccessResponse($variables);
        } catch (Exception $e) {
            $this->log('RankingCoach Variables Error: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, $e->getMessage(), 500);
        }
    }



    /**
     * Apply CORS headers for REST API requests.
     *
     * @since 1.0.0
     * @param bool $allowFullCors Force applying headers regardless of configuration.
     * @return void
     */
    public static function wpFullCorsHeaders(bool $allowFullCors = false): void {
        global $wp_version;

        if (!$allowFullCors) {
            return;
        }

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        $jwtHeaders = apply_filters('rankingcoach_jwt_auth_cors_allow_headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, Cookie');
        $restHeaders = 'Content-Type, Authorization, X-Requested-With';

        $allHeaders = $jwtHeaders . ', ' . $restHeaders;
        $split = preg_split('/[\s,]+/', $allHeaders);
        $uniqueHeaders = implode(', ', array_unique(array_filter($split)));

        if (version_compare($wp_version, '5.5.0', '>=') && !headers_sent()) {
            header(sprintf('Access-Control-Allow-Headers: %s', $uniqueHeaders));
            add_filter('rest_allowed_cors_headers', function (array $headers) use ($split) {
                return array_unique(array_merge($headers, $split));
            });
        } elseif (!headers_sent()) {
            header(sprintf('Access-Control-Allow-Headers: %s', $uniqueHeaders));
        }

        header('Access-Control-Allow-Credentials: true');

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    /**
     * Register post meta fields for REST API exposure.
     *
     * @since 1.0.0
     * @return void
     */
    public function registerMetaToRestResponse(): void {
        // Schema cache meta for pages
        register_post_meta('page', BaseConstants::OPTION_SCHEMA_CACHE, [
            'show_in_rest' => true,
            'type'         => 'string',
            'single'       => true,
        ]);

        // Schema cache meta for posts
        register_post_meta('post', BaseConstants::OPTION_SCHEMA_CACHE, [
            'show_in_rest' => true,
            'type'         => 'string',
            'single'       => true,
        ]);

        // SEO title meta for posts
        register_post_meta('post', BaseConstants::META_KEY_SEO_TITLE, [
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        // SEO description meta for posts
        register_post_meta('post', BaseConstants::META_KEY_SEO_DESCRIPTION, [
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        // SEO title meta for pages
        register_post_meta('page', BaseConstants::META_KEY_SEO_TITLE, [
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'auth_callback' => function () {
                return current_user_can('edit_pages');
            },
        ]);

        // SEO description meta for pages
        register_post_meta('page', BaseConstants::META_KEY_SEO_DESCRIPTION, [
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'auth_callback' => function () {
                return current_user_can('edit_pages');
            },
        ]);
    }

    /**
     * Add filtered excerpt to REST API response.
     *
     * @since 1.0.0
     * @param mixed $response The response object.
     * @param mixed $post The post object.
     * @param mixed $request The request object.
     * @return mixed Modified response.
     */
    public static function addFilteredExcerptToRestResponse($response, $post, $request): mixed {
        if ($post->post_type !== 'post' && $post->post_type !== 'page' && $post->post_type !== 'attachment') {
            return $response;
        }

        if (isset($response->data['excerpt']['rendered'])) {
            $excerpt = wp_strip_all_tags($response->data['excerpt']['rendered']);
            $max_chars = 300;
            $excerpt = trim($excerpt);

            if (strlen($excerpt) > $max_chars) {
                $excerpt = mb_substr($excerpt, 0, $max_chars - 3);
                $excerpt = preg_replace('/\s+\S*$/u', '', $excerpt);
                $excerpt .= '...';
            }

            $response->data['excerpt']['filtered'] = $excerpt;
        }

        return $response;
    }

    /**
     * Handle general settings endpoint for CRUD operations
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response
     */
    public function handleGeneralSettings(WP_REST_Request $request): WP_REST_Response {
        try {
            $method = $request->get_method();
            $settingsManager = SettingsManager::instance();

            switch ($method) {
                case 'GET':
                    return $this->getGeneralSettings($settingsManager);

                case 'POST':
                    return $this->updateGeneralSettings($request, $settingsManager);

                default:
                    return $this->generateErrorResponse(
                        null,
                        __('Method not allowed', 'beyondseo'),
                        405
                    );
            }
        } catch (Exception $e) {
            $this->log('Settings endpoint error: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                /* translators: %s: error message */
                sprintf(__('Internal server error: %s', 'beyondseo'), $e->getMessage()),
                500
            );
        }
    }

    /**
     * Handle single setting endpoint for individual setting operations
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response
     */
    public function handleSingleSetting(WP_REST_Request $request): WP_REST_Response {
        try {
            $method = $request->get_method();
            $key = $request->get_param('key');
            $settingsManager = SettingsManager::instance();

            switch ($method) {
                case 'GET':
                    return $this->getSingleSetting($key, $settingsManager);

                case 'POST':
                    return $this->updateSingleSetting($request, $key, $settingsManager);

                case 'DELETE':
                    return $this->resetSingleSetting($key, $settingsManager);

                default:
                    return $this->generateErrorResponse(
                        null,
                        'Method not allowed',
                        405
                    );
            }
        } catch (Exception $e) {
            $this->log('Single setting endpoint error: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                /* translators: %s: error message */
                sprintf(__('Internal server error: %s', 'beyondseo'), $e->getMessage()),
                500
            );
        }
    }

    /**
     * Handle breadcrumbs endpoint for generating breadcrumbs for multiple types
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response
     */
    public function handleBreadcrumbs(WP_REST_Request $request): WP_REST_Response {
        try {
            $method = $request->get_method();
            if ($method !== 'POST') {
                return $this->generateErrorResponse(
                    null,
                    'Method not allowed. Use POST.',
                    405
                );
            }

            // Check if breadcrumbs are enabled
            $settingsManager = SettingsManager::instance();
            if (!$settingsManager->enable_breadcrumbs) {
                return $this->generateErrorResponse(
                    null,
                    'Breadcrumbs are disabled in settings',
                    403
                );
            }

            $body = $request->get_json_params();

            // Create and validate DTO
            $requestDto = BreadcrumbsRequestDto::fromArray($body ?? []);
            $validationErrors = $requestDto->validate();

            if (!empty($validationErrors)) {
                return $this->generateErrorResponse(
                    null,
                    'Request validation failed: ' . implode(', ', $validationErrors),
                    400
                );
            }

            // Process breadcrumbs using the handler
            $responseHandler = new BreadcrumbsMultipleResponseHandler();
            $responseDto = $responseHandler->processMultipleTypes($requestDto->types, $requestDto->context);

            return $this->generateSuccessResponse(
                $responseDto->toArray(),
                'Breadcrumbs generated successfully for ' . count($requestDto->types) . ' type(s)'
            );

        } catch (Exception $e) {
            $this->log('Breadcrumbs endpoint error: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                /* translators: %s: error message */
                sprintf(__('Internal server error: %s', 'beyondseo'), $e->getMessage()),
                500
            );
        }
    }

    /**
     * Get all general settings
     *
     * @since 1.0.0
     * @param SettingsManager $settingsManager The settings manager instance.
     * @return WP_REST_Response
     */
    private function getGeneralSettings(SettingsManager $settingsManager): WP_REST_Response {
        try {
            $settings = $settingsManager->get_options();

            // Group settings by category for better frontend consumption
            $categorizedSettings = $this->categorizeSettings($settings);

            return $this->generateSuccessResponse([
                'settings' => $settings,
                'categorized' => $categorizedSettings,
                'meta' => [
                    'total_settings' => count($settings),
                    'timestamp' => wp_date('Y-m-d H:i:s'),
                    'version' => get_option('rankingcoach_version', '1.0.0')
                ]
            ]);
        } catch (Exception $e) {
            $this->log('Error retrieving general settings: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                __('Failed to retrieve settings', 'beyondseo'),
                500
            );
        }
    }

    /**
     * Update general settings (bulk update)
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @param SettingsManager $settingsManager The settings manager instance.
     * @return WP_REST_Response
     */
    private function updateGeneralSettings(WP_REST_Request $request, SettingsManager $settingsManager): WP_REST_Response {
        try {
            $body = $request->get_json_params();
            if (empty($body) || !is_array($body) || !isset($body['settings']) || !is_array($body['settings'])) {
                return $this->generateErrorResponse(
                    null,
                    __('Invalid request body. Expected JSON object with settings.', 'beyondseo'),
                    400
                );
            }

            $validatedSettings = $this->validateSettings($body['settings']);
            if (is_wp_error($validatedSettings)) {
                return $this->generateErrorResponse(
                    null,
                    $validatedSettings->get_error_message(),
                    400
                );
            }

            $updatedSettings = [];
            $errors = [];

            foreach ($validatedSettings as $key => $value) {
                try {
                    $settingsManager->update_option($key, $value);
                    $updatedSettings[$key] = $value;
                } catch (Exception $e) {
                    $errors[$key] = $e->getMessage();
                    $this->log("Failed to update setting '{$key}': " . $e->getMessage(), 'ERROR');
                }
            }

            if (!empty($errors)) {
                return $this->generateErrorResponse(
                    $e ?? null,
                    'Some settings could not be updated. Reason: ' . json_encode($errors),
                    207 // Multi-Status
                );
            }

            return $this->generateSuccessResponse([
                'updated_settings' => $updatedSettings,
                'count' => count($updatedSettings),
                'timestamp' => wp_date('Y-m-d H:i:s'),
                'review' => $this->recordReviewSettingsSave($request, $updatedSettings),
            ], 'Settings updated successfully');

        } catch (Exception $e) {
            $this->log('Error updating general settings: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                /* translators: %s: error message */
                sprintf(__('Failed to update settings: %s', 'beyondseo'), $e->getMessage()),
                500
            );
        }
    }

    private function recordReviewSettingsSave(WP_REST_Request $request, array $settings): ?array
    {
        try {
            if ($request->get_param('reviewPlacement') !== 'save' || $settings === []) {
                return null;
            }
            $persistedSettings = get_option(BaseConstants::OPTION_PLUGIN_SETTINGS, []);
            if (!is_array($persistedSettings)) {
                return null;
            }
            foreach ($settings as $key => $value) {
                if (!array_key_exists($key, $persistedSettings) || $persistedSettings[$key] !== $value) {
                    return null;
                }
            }
            $reviewState = new ReviewState();
            if (!$reviewState->recordMeaningfulUse()) {
                return null;
            }
            return [
                'displayCount'       => $reviewState->getDisplayCount(),
                'eligible'           => $reviewState->isEligible(),
                'cooldownUntil'      => $reviewState->getCooldownUntil(),
                'permanentlyStopped' => $reviewState->isPermanentlyStopped(),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Get a single setting
     *
     * @since 1.0.0
     * @param string $key The setting key.
     * @param SettingsManager $settingsManager The settings manager instance.
     * @return WP_REST_Response
     */
    private function getSingleSetting(string $key, SettingsManager $settingsManager): WP_REST_Response {
        try {
            if (!$this->isValidSettingKey($key)) {
                return $this->generateErrorResponse(
                    null,
                    /* translators: %s: setting key name */
                    sprintf(__('Invalid setting key: %s', 'beyondseo'), $key),
                    400
                );
            }

            $value = $settingsManager->get_option($key);

            if ($value === null) {
                return $this->generateErrorResponse(
                    null,
                    "Setting '{$key}' not found",
                    404
                );
            }

            return $this->generateSuccessResponse([
                'key' => $key,
                'value' => $value,
                'type' => gettype($value)
            ]);

        } catch (Exception $e) {
            $this->log("Error retrieving setting '{$key}': " . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                __('Failed to retrieve setting', 'beyondseo'),
                500
            );
        }
    }

    /**
     * Update a single setting
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The REST request object.
     * @param string $key The setting key.
     * @param SettingsManager $settingsManager The settings manager instance.
     * @return WP_REST_Response
     */
    private function updateSingleSetting(WP_REST_Request $request, string $key, SettingsManager $settingsManager): WP_REST_Response {
        try {
            if (!$this->isValidSettingKey($key)) {
                return $this->generateErrorResponse(
                    null,
                    /* translators: %s: setting key name */
                    sprintf(__('Invalid setting key: %s', 'beyondseo'), $key),
                    400
                );
            }

            $body = $request->get_json_params();
            if (!isset($body['value'])) {
                return $this->generateErrorResponse(
                    null,
                    "Missing 'value' parameter in request body",
                    400
                );
            }

            $value = $body['value'];
            $validatedValue = $this->validateSingleSetting($key, $value);

            if (is_wp_error($validatedValue)) {
                return $this->generateErrorResponse(
                    null,
                    $validatedValue->get_error_message(),
                    400
                );
            }

            // Get previous value before updating
            $previousValue = $settingsManager->get_option($key);
            $settingsManager->update_option($key, $validatedValue);

            return $this->generateSuccessResponse([
                'key' => $key,
                'value' => $validatedValue,
                'previous_value' => $previousValue,
                'timestamp' => wp_date('Y-m-d H:i:s')
            ], "Setting '{$key}' updated successfully");

        } catch (Exception $e) {
            $this->log("Error updating setting '{$key}': " . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                /* translators: %s: error message */
                sprintf(__('Failed to update setting: %s', 'beyondseo'), $e->getMessage()),
                500
            );
        }
    }

    /**
     * Reset a single setting to its default value
     *
     * @since 1.0.0
     * @param string $key The setting key.
     * @param SettingsManager $settingsManager The settings manager instance.
     * @return WP_REST_Response
     */
    private function resetSingleSetting(string $key, SettingsManager $settingsManager): WP_REST_Response {
        try {
            if (!$this->isValidSettingKey($key)) {
                return $this->generateErrorResponse(
                    null,
                    /* translators: %s: setting key name */
                    sprintf(__('Invalid setting key: %s', 'beyondseo'), $key),
                    400
                );
            }

            $defaultValue = $this->getDefaultSettingValue($key);
            if ($defaultValue === null) {
                return $this->generateErrorResponse(
                    null,
                    "No default value found for setting: {$key}",
                    400
                );
            }

            $previousValue = $settingsManager->get_option($key);
            $settingsManager->update_option($key, $defaultValue);

            $this->log("Setting '{$key}' reset to default value", 'INFO');

            return $this->generateSuccessResponse([
                'key' => $key,
                'value' => $defaultValue,
                'previous_value' => $previousValue,
                'timestamp' => wp_date('Y-m-d H:i:s')
            ], "Setting '{$key}' reset to default value");

        } catch (Exception $e) {
            $this->log("Error resetting setting '{$key}': " . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse(
                null,
                /* translators: %s: error message */
                sprintf(__('Failed to reset setting: %s', 'beyondseo'), $e->getMessage()),
                500
            );
        }
    }

    /**
     * Validate settings data
     *
     * @since 1.0.0
     * @param array $settings Array of settings to validate.
     * @return array|WP_Error Validated settings array or WP_Error on failure.
     */
    private function validateSettings(array $settings): array|WP_Error {
        $validatedSettings = [];
        $errors = [];

        foreach ($settings as $key => $value) {
            if (!$this->isValidSettingKey($key)) {
                /* translators: %s: setting key name */
                $errors[] = sprintf(__('Invalid setting key: %s', 'beyondseo'), $key);
                continue;
            }

            $validatedValue = $this->validateSingleSetting($key, $value);
            if (is_wp_error($validatedValue)) {
                /* translators: 1: setting key name, 2: error message */
                $errors[] = sprintf(__("Setting '%1\$s': %2\$s", 'beyondseo'), $key, $validatedValue->get_error_message());
                continue;
            }

            $validatedSettings[$key] = $validatedValue;
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode('; ', $errors));
        }

        return $validatedSettings;
    }

    /**
     * Validate a single setting value
     *
     * @since 1.0.0
     * @param string $key The setting key.
     * @param mixed $value The value to validate.
     * @return mixed|WP_Error Validated value or WP_Error on failure.
     */
    private function validateSingleSetting(string $key, mixed $value): mixed {
        // Get the expected type and validation rules for this setting
        $validationRules = $this->getSettingValidationRules($key);

        if (empty($validationRules)) {
            // If no specific validation rules, return the value as-is
            return $value;
        }

        // Type validation
        if (isset($validationRules['type'])) {
            $expectedType = $validationRules['type'];

            switch ($expectedType) {
                case 'boolean':
                    if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                        /* translators: %s: setting key name */
                        return new WP_Error('invalid_type', sprintf(__("Setting '%s' must be a boolean value", 'beyondseo'), $key));
                    }
                    $value = (bool) $value;
                    break;

                case 'integer':
                    if (!is_numeric($value)) {
                        /* translators: %s: setting key name */
                        return new WP_Error('invalid_type', sprintf(__("Setting '%s' must be a numeric value", 'beyondseo'), $key));
                    }
                    $value = (int) $value;
                    break;

                case 'string':
                    $value = (string) $value;
                    break;

                case 'array':
                    if (!is_array($value)) {
                        return new WP_Error('invalid_type', "Setting '{$key}' must be an array");
                    }
                    break;
            }
        }

        // Range validation for numeric values
        if (isset($validationRules['min']) && is_numeric($value) && $value < $validationRules['min']) {
            return new WP_Error('value_too_small', "Setting '{$key}' must be at least {$validationRules['min']}");
        }

        if (isset($validationRules['max']) && is_numeric($value) && $value > $validationRules['max']) {
            return new WP_Error('value_too_large', "Setting '{$key}' must not exceed {$validationRules['max']}");
        }

        // String length validation
        if (isset($validationRules['max_length']) && is_string($value) && strlen($value) > $validationRules['max_length']) {
            return new WP_Error('string_too_long', "Setting '{$key}' must not exceed {$validationRules['max_length']} characters");
        }

        // Enum validation
        if (isset($validationRules['enum']) && !in_array($value, $validationRules['enum'], true)) {
            $allowed = implode(', ', $validationRules['enum']);
            return new WP_Error('invalid_value', "Setting '{$key}' must be one of: {$allowed}");
        }

        return $value;
    }

    /**
     * Check if a setting key is valid
     *
     * @since 1.0.0
     * @param string $key The setting key to check.
     * @return bool True if valid, false otherwise.
     */
    private function isValidSettingKey(string $key): bool {
        $validKeys = $this->getValidSettingKeys();
        return in_array($key, $validKeys, true);
    }

    /**
     * Get all valid setting keys from WPSettings entity
     *
     * @since 1.0.0
     * @return array Array of valid setting keys.
     */
    private function getValidSettingKeys(): array {
        static $validKeys = null;

        if ($validKeys === null) {
            $settings = PluginSettings::instance()->get_all();
            $validKeys = array_map('strtolower', array_keys($settings));
        }

        return $validKeys;
    }

    /**
     * Get validation rules for a specific setting
     *
     * @since 1.0.0
     * @param string $key The setting key.
     * @return array Validation rules array.
     */
    private function getSettingValidationRules(string $key): array {
        $rules = [
            // SEO Analysis
            'seo_analysis' => ['type' => 'boolean'],
            'seo_score_threshold' => ['type' => 'integer', 'min' => 1, 'max' => 100],
            'setup_wizard' => ['type' => 'array'],
            'setup_wizard_email' => ['type' => 'array'],
            'enable_readability_check' => ['type' => 'boolean'],

            // Keyword Optimization
            'focus_keyword_limit' => ['type' => 'integer', 'min' => 1, 'max' => 20],
            'focus_keyword_analysis' => ['type' => 'boolean'],

            // Google Analytics
            'google_analytics_integration' => ['type' => 'boolean'],
            'ga_tracking_id' => ['type' => 'string', 'max_length' => 50],

            // Schema Markup
            'enable_schema_markup' => ['type' => 'boolean'],
            'default_schema_type' => ['type' => 'string', 'enum' => ['Article', 'BlogPosting', 'NewsArticle', 'WebPage']],
            'site_represents' => ['type' => 'string', 'enum' => ['organization', 'person']],
            'site_links' => ['type' => 'boolean'],
            'organisation_or_person_name' => ['type' => 'string', 'max_length' => 100],
            'organisation_email' => ['type' => 'string', 'max_length' => 100],
            'organisation_phone' => ['type' => 'string', 'max_length' => 20],
            'organisation_logo' => ['type' => 'string', 'max_length' => 255],
            'run_shortcodes' => ['type' => 'boolean'],
            'website_alternate_name' => ['type' => 'string', 'max_length' => 100],

            // Redirects and 404
            'redirect_manager' => ['type' => 'boolean'],
            'redirect_404_to_home' => ['type' => 'boolean'],
            'monitoring_404' => ['type' => 'boolean'],

            // Indexing Control
            'default_noindex_posts' => ['type' => 'boolean'],
            'default_noindex_pages' => ['type' => 'boolean'],
            'index_categories' => ['type' => 'boolean'],
            'index_tags' => ['type' => 'boolean'],

            // Social Media
            'enable_social_optimization' => ['type' => 'boolean'],
            'default_og_image' => ['type' => 'string', 'max_length' => 255],
            'default_twitter_card' => ['type' => 'string', 'enum' => ['summary', 'summary_large_image', 'app', 'player']],
            'organization_social_facebook' => ['type' => 'string', 'max_length' => 255],
            'organization_social_twitter' => ['type' => 'string', 'max_length' => 255],
            'organization_social_instagram' => ['type' => 'string', 'max_length' => 255],
            'organization_social_linkedin' => ['type' => 'string', 'max_length' => 255],
            'organization_social_youtube' => ['type' => 'string', 'max_length' => 255],
            'organization_social_tiktok' => ['type' => 'string', 'max_length' => 255],
            'organization_social_pinterest' => ['type' => 'string', 'max_length' => 255],
            'organization_social_github' => ['type' => 'string', 'max_length' => 255],
            'organization_social_snapchat' => ['type' => 'string', 'max_length' => 255],
            'organization_social_tumblr' => ['type' => 'string', 'max_length' => 255],
            'organization_social_reddit' => ['type' => 'string', 'max_length' => 255],
            'organization_social_whatsapp' => ['type' => 'string', 'max_length' => 255],
            'organization_social_telegram' => ['type' => 'string', 'max_length' => 255],
            'organization_social_mastodon' => ['type' => 'string', 'max_length' => 255],
            'organization_social_flickr' => ['type' => 'string', 'max_length' => 255],
            'organization_social_vimeo' => ['type' => 'string', 'max_length' => 255],
            'organization_social_foursquare' => ['type' => 'string', 'max_length' => 255],
            'organization_social_yelp' => ['type' => 'string', 'max_length' => 255],
            'organization_social_quora' => ['type' => 'string', 'max_length' => 255],
            'organization_social_discord' => ['type' => 'string', 'max_length' => 255],
            'organization_social_slack' => ['type' => 'string', 'max_length' => 255],
            'organization_social_wechat' => ['type' => 'string', 'max_length' => 255],
            'organization_social_weibo' => ['type' => 'string', 'max_length' => 255],
            'organization_social_line' => ['type' => 'string', 'max_length' => 255],
            'organization_social_vk' => ['type' => 'string', 'max_length' => 255],
            'organization_social_telegram_channel' => ['type' => 'string', 'max_length' => 255],
            'organization_social_telegram_group' => ['type' => 'string', 'max_length' => 255],
            'organization_social_messenger' => ['type' => 'string', 'max_length' => 255],
            'organization_social_whatsapp_group' => ['type' => 'string', 'max_length' => 255],
            'organization_social_signal' => ['type' => 'string', 'max_length' => 255],
            'organization_additional_social_urls' => ['type' => 'array', 'items' => ['type' => 'string', 'max_length' => 255]],

            // Sitemap
            'sitemap' => ['type' => 'array'],

            // Local SEO
            'enable_local_seo' => ['type' => 'boolean'],
            'default_business_type' => ['type' => 'string', 'max_length' => 50],
            'business_latitude' => ['type' => 'string', 'max_length' => 20],
            'business_longitude' => ['type' => 'string', 'max_length' => 20],

            // Internal Links
            'internal_link_suggestions' => ['type' => 'boolean'],
            'enable_breadcrumbs' => ['type' => 'boolean'],

            // Security
            'security_noopen' => ['type' => 'boolean'],
            'security_nosnippet' => ['type' => 'boolean'],

            // Performance
            'enable_lazy_loading' => ['type' => 'boolean'],
            'minify_html' => ['type' => 'boolean'],

            // Cache
            'account_details_cache_seconds' => ['type' => 'integer', 'min' => 300, 'max' => 86400],
            'gmb_categories_cache_seconds' => ['type' => 'integer', 'min' => 300, 'max' => 86400],

            // RSS
            'rss' => ['type' => 'array'],

            // Miscellaneous
            'beyondseo_comm_opt_in' => ['type' => 'boolean'],
            'enable_wp_cron_service' => ['type' => 'boolean'],
            'allow_seo_optimiser_on_saved_posts' => ['type' => 'boolean'],
            'allow_sync_keywords_to_rankingcoach' => ['type' => 'boolean'],

            // Cleanup and Uninstall
            'remove_settings_on_deactivation' => ['type' => 'boolean'],

            // Complex arrays
            'separators' => ['type' => 'array'],
            'organisation_number_of_employees' => ['type' => 'array'],
        ];

        return $rules[$key] ?? [];
    }

    /**
     * Get default value for a setting
     *
     * @since 1.0.0
     * @param string $key The setting key.
     * @return mixed|null Default value or null if not found.
     */
    private function getDefaultSettingValue(string $key): mixed {
        return PluginSettings::instance()->getDefault($key);
    }

    /**
     * Categorize settings for better frontend organization
     *
     * @since 1.0.0
     * @param array $settings Array of settings to categorize.
     * @return array Categorized settings array.
     */
    private function categorizeSettings(array $settings): array {
        $categories = [
            'seo' => [
                'label' => 'SEO Analysis',
                'settings' => []
            ],
            'keywords' => [
                'label' => 'Keywords',
                'settings' => []
            ],
            'analytics' => [
                'label' => 'Analytics',
                'settings' => []
            ],
            'schema' => [
                'label' => 'Schema Markup',
                'settings' => []
            ],
            'redirects' => [
                'label' => 'Redirects & 404',
                'settings' => []
            ],
            'indexing' => [
                'label' => 'Indexing',
                'settings' => []
            ],
            'social' => [
                'label' => 'Social Media',
                'settings' => []
            ],
            'sitemap' => [
                'label' => 'XML Sitemap',
                'settings' => []
            ],
            'local' => [
                'label' => 'Local SEO',
                'settings' => []
            ],
            'links' => [
                'label' => 'Internal Links',
                'settings' => []
            ],
            'security' => [
                'label' => 'Security',
                'settings' => []
            ],
            'performance' => [
                'label' => 'Performance',
                'settings' => []
            ],
            'advanced' => [
                'label' => 'Advanced',
                'settings' => []
            ]
        ];

        $categoryMapping = [
            'beyondseo_comm_opt_in' => 'advanced',
            'enable_wp_cron_service' => 'advanced',
            'allow_seo_optimiser_on_saved_posts' => 'advanced',
            'allow_sync_keywords_to_rankingcoach' => 'advanced',
            'remove_settings_on_deactivation' => 'advanced',
            'seo_analysis' => 'seo',
            'seo_score_threshold' => 'seo',
            'enable_readability_check' => 'seo',
            'focus_keyword_limit' => 'keywords',
            'focus_keyword_analysis' => 'keywords',
            'google_analytics_integration' => 'analytics',
            'ga_tracking_id' => 'analytics',
            'enable_schema_markup' => 'schema',
            'default_schema_type' => 'schema',
            'site_represents' => 'schema',
            'site_links' => 'schema',
            'organisation_or_person_name' => 'schema',
            'organisation_email' => 'schema',
            'organisation_phone' => 'schema',
            'organisation_logo' => 'schema',
            'organisation_founding_date' => 'schema',
            'organisation_number_of_employees' => 'schema',
            'run_shortcodes' => 'schema',
            'website_alternate_name' => 'schema',
            'person_manual_name' => 'schema',
            'person_manual_image' => 'schema',
            'organization_social_facebook' => 'social',
            'organization_social_twitter' => 'social',
            'organization_social_instagram' => 'social',
            'organization_social_linkedin' => 'social',
            'organization_social_youtube' => 'social',
            'organization_social_tiktok' => 'social',
            'organization_social_pinterest' => 'social',
            'organization_social_github' => 'social',
            'organization_social_tumblr' => 'social',
            'organization_social_snapchat' => 'social',
            'organization_social_wikipedia' => 'social',
            'organization_social_personal_website' => 'social',
            'organization_additional_social_urls' => 'social',
            'redirect_manager' => 'redirects',
            'redirect_404_to_home' => 'redirects',
            'monitoring_404' => 'redirects',
            'default_noindex_posts' => 'indexing',
            'default_noindex_pages' => 'indexing',
            'index_categories' => 'indexing',
            'index_tags' => 'indexing',
            'enable_social_optimization' => 'social',
            'default_og_image' => 'social',
            'default_twitter_card' => 'social',
            'sitemap' => 'sitemap',
            'enable_local_seo' => 'local',
            'default_business_type' => 'local',
            'business_latitude' => 'local',
            'business_longitude' => 'local',
            'internal_link_suggestions' => 'links',
            'enable_breadcrumbs' => 'links',
            'security_noopen' => 'security',
            'security_nosnippet' => 'security',
            'enable_lazy_loading' => 'performance',
            'minify_html' => 'performance',
        ];

        foreach ($settings as $key => $value) {
            $category = $categoryMapping[$key] ?? 'advanced';
            $categories[$category]['settings'][$key] = $value;
        }

        // Remove empty categories
        return array_filter($categories, fn($category) => !empty($category['settings']));
    }

    /**
     * Get the legacy API base namespace.
     *
     * @since 1.0.0
     * @return string The legacy API base.
     */
    private function getLegacyApiBase(): string {
        return $this->namespace;
    }
}
