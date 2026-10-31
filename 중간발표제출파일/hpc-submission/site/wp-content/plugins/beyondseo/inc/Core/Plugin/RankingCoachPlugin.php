<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Plugin;

if ( !defined( 'ABSPATH' ) ) {
    exit('Direct access denied.');
}

use Exception;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Initializers\Installer;
use RankingCoach\Inc\Core\Initializers\NotificationInitializer;
use RankingCoach\Inc\Core\Initializers\RestInitializer;
use RankingCoach\Inc\Core\CircuitBreaker\FunctionalityBlocker;
use RankingCoach\Inc\Core\CronJobManager;
use RankingCoach\Inc\Core\Sitemap\Sitemap;
use ReflectionClass;
use Throwable;
use function apply_filters;


/**
 * This class handles the core functionality of the plugin and initiates
 * the processes needed to launch and run the plugin effectively.
 */
final class RankingCoachPlugin
{
    use RcLoggerTrait;

    /** @var string $version Plugin version. */
    public string $plugin_version = RANKINGCOACH_VERSION;

    /** @var RankingCoachPlugin|null $instance The single instance of the class. */
    protected static ?RankingCoachPlugin $instance = null;

    /** @var bool $initialized Prevents re-initialization with different parameters. */
    private static bool $initialized = false;

    /** @var array<string, mixed> $context_cache Cached context validation results. */
    private static array $context_cache = [];

    /**
     * Returns the unique identifier for the plugin.
     *
     * @return string The unique plugin identifier.
     */
    public static function getPluginUniqueId():string
    {
        //
        $installationId = get_option(BaseConstants::OPTION_INSTALLATION_ID, '_');
        $pluginVersion = get_option(BaseConstants::OPTION_PLUGIN_VERSION, '_');
        $accountId = get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, '_');
        $projectId = get_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID, '_');

        $newFilename = $installationId . '_' . $pluginVersion . '_' . $accountId . '_' . $projectId;
        return $newFilename;
    }

    /**
     * Returns a single instance of the RankingCoach, initializing it if not already created.
     *
     * @return RankingCoachPlugin - The single instance of the RankingCoach class.
     */
    public static function instance(): RankingCoachPlugin {
        if ( self::$instance === null ) {
            self::$instance = new RankingCoachPlugin();
            self::$initialized = true;
        }

        return self::$instance;
    }

    /**
     * Constructor for the RankingCoach class.
     */
    private function __construct() {
        // Constructor logic if needed
    }

    /**
     * Initializes the plugin by setting up the core functionality.
     * @throws Throwable
     */
    public function initialize(): void {
        try {
            CronJobManager::instance()->registerSchedules();

            // Initialize circuit breaker system first
            $circuitBreaker = FunctionalityBlocker::instance()->getCircuitBreaker();

            // Evaluate request context before logging to avoid log spam on non-WordPress requests or system calls
            $isWpContext = $this->isWordPressContext();

            // Check circuit state once before proceeding
            $isCircuitClosed = $circuitBreaker->check_circuit_state();
            if (!$isCircuitClosed) {
                // Only log and initialize notifications in valid WP context
                if ($isWpContext) {
                    defined('RANKINGCOACH_API_CONTEXT') || define('RANKINGCOACH_API_CONTEXT', false);

                    // Reduce log spam: skip heartbeat and throttle logs to once/30s
                    $action = WordpressHelpers::sanitize_input( 'POST', 'action' );

                    if ( null === $action ) {
                        $action = WordpressHelpers::sanitize_input( 'GET', 'action' );
                    }

                    $isHeartbeat = ($action === 'heartbeat');

                    if (!$isHeartbeat) {
                        $lastLogTs = function_exists('get_transient') ? (int) get_transient('rankingcoach_last_circuit_open_log') : 0;
                        if (!$lastLogTs || (time() - $lastLogTs) >= 30) {
                            $failures = $circuitBreaker->get_failed_checks();
                            $critical = $circuitBreaker->get_critical_failures();

                            $openIds = array_keys($failures);
                            $criticalIds = array_keys($critical);

                            $message = 'Circuit breaker is open - plugin functionality limited';
                            if (!empty($openIds)) {
                                $message .= ' (open=' . implode(',', $openIds) . ')';
                            }
                            if (!empty($criticalIds)) {
                                $message .= ' (critical=' . implode(',', $criticalIds) . ')';
                            }

                            $this->log(
                                $message,
                                'WARNING',
                                false,
                                'core',
                                [
                                    'breaker_ids' => $openIds,
                                    'critical_breaker_ids' => $criticalIds,
                                    'breakers' => $failures,
                                ]
                            );

                            if (function_exists('set_transient')) {
                                set_transient('rankingcoach_last_circuit_open_log', time(), 30);
                            }
                        }
                    }

                    // Only initialize notifications to show circuit breaker messages
                    (new NotificationInitializer())->initialize();
                }

                do_action('rankingcoach_plugin/circuit_breaker_active');
                return;
            }

            // Initialize REST API first to ensure API routes are registered early
            (new RestInitializer())->initialize();

            // Sitemap hooks must be active in every context, not only browser page
            // requests: content and robots-meta changes arrive through core REST
            // (Gutenberg saves), WP-CLI and cron too, and each of them has to
            // invalidate the generated sitemap. Serving stays frontend-only via
            // template_redirect.
            add_action('plugins_loaded', static function () {
                (new Sitemap())->init();
            }, 99);

            // Allow just WordPress built-in context (filter cli, some ajax, and system requests etc)
            if ($this->isWordPressContext()) {
                defined('RANKINGCOACH_API_CONTEXT') || define('RANKINGCOACH_API_CONTEXT', false);
                $this->initializeWordPressComponents();
            }

            // Loaded action.
            do_action( 'rankingcoach_plugin/loaded' );

        } catch (Exception $e) {
            // Store raw error message for later translation when text domain is loaded
            $error = esc_html($e->getMessage());
            
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new Exception($error, $e->getCode(), $e);
        }
    }

    /**
     * Comprehensive context validation for WordPress execution environment.
     *
     * Validates that the current execution context is appropriate for WordPress-specific
     * plugin initialization, excluding API endpoints, system requests, and non-interactive contexts.
     *
     * @return bool True if context is valid for WordPress initialization
     */
    private function isWordPressContext(): bool
    {
        $cache_key = 'rc_wp_context_' . md5(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? 'no_request_uri')));

        if (isset(self::$context_cache[$cache_key])) {
            return self::$context_cache[$cache_key];
        }

        // Allow Legacy API endpoints for RankingCoach
        if ($this->validateLegacyApiEndpoint()) {
            self::$context_cache[$cache_key] = true;
            return true;
        }

        // Allow plugin AJAX requests
        if ($this->validatePluginAjaxRequest()) {
            self::$context_cache[$cache_key] = true;
            return true;
        }

        $context_checks = [
            'has_request_uri' => $this->validateRequestUri(),
            'not_app_api_endpoint' => $this->validateNoAppApiEndpoint(),
            'not_system_request' => $this->validateNotSystemRequest(),
            'not_cli_context' => $this->validateNotCliContext(),
            //'not_cron_context' => $this->validateNotCronContext(),
            'valid_http_method' => $this->validateHttpMethod(),
            'valid_user_agent' => $this->validateUserAgent(),
        ];

        $is_valid_context = !in_array(false, $context_checks, true);

        self::$context_cache[$cache_key] = $is_valid_context;
        return $is_valid_context;
    }

    /**
     * Validates REQUEST_URI exists and is not empty.
     */
    private function validateRequestUri(): bool {
        $requestUri = WordpressHelpers::sanitize_input(
            'SERVER',
            'REQUEST_URI'
        );

        return ! empty( $requestUri );
    }
    /**
     * Validates the request is not a system request.
     */
    private function validatePluginAjaxRequest(): bool {
        // Read and sanitize REQUEST_URI safely
        $request_uri = WordpressHelpers::sanitize_input(
            'SERVER',
            'REQUEST_URI'
        );

        // Collect 'action' safely from POST or GET (avoid $_REQUEST)
        $action = WordpressHelpers::sanitize_input( 'POST', 'action' );
        if ( null === $action ) {
            $action = WordpressHelpers::sanitize_input( 'GET', 'action' );
        }

        // If no action at all, nothing to validate
        if ( empty( $action ) ) {
            return false;
        }

        // Validate allowed plugin-specific AJAX actions
        $isAllowedAction =
            str_starts_with( $action, 'rankingcoach_' ) ||
            str_starts_with( $action, 'rc_' ) ||
            str_contains( $action, '_rc_' ) ||
            str_contains( $action, 'as_async_request_queue_runner' ) ||
            $action === 'heartbeat' ||
            in_array( $action, [ 'update-plugin', 'install-plugin', 'activate-plugin', 'delete-plugin' ], true );

        // Validate that request is for admin-ajax.php
        $isAdminAjax = str_contains( $request_uri, '/admin-ajax.php' );

        return $isAdminAjax && $isAllowedAction;
    }

    /**
     * Validates request is not a system/infrastructure request.
     */
    private function validateNotSystemRequest(): bool {
        $request_uri = WordpressHelpers::sanitize_input(
            'SERVER',
            'REQUEST_URI'
        );

        if ( $request_uri === '/robots.txt' ) {
            return true;
        }
        $system_patterns = [
            '/xmlrpc.php',
            '/wp-login.php',
            '/wp-register.php',
            '/.well-known/',
            '/favicon.ico',
            '/wp-content/uploads/',
            '/wp-includes/',
            '/admin-ajax.php',
            '/wp-json'
        ];
        $adminAjaxPage = admin_url( 'admin-ajax.php' );
        $system_patterns = array_merge( $system_patterns, [ $adminAjaxPage ] );

        foreach ($system_patterns as $pattern) {
            if (str_contains($request_uri, $pattern)) {
                return false;
            }
        }

        // Check file extensions that should be excluded
        $excluded_extensions = ['ico', 'json', 'txt', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ttf', 'eot'];
        $path_info = pathinfo($request_uri);

        return !(isset($path_info['extension']) && in_array(strtolower($path_info['extension']), $excluded_extensions, true));
    }

    /**
     * Validates request is not targeting RankingCoach App API endpoints (not the legacy API endpoints).
     */
    private function validateNoAppApiEndpoint(): bool {
        $request_uri = WordpressHelpers::sanitize_input(
            'SERVER',
            'REQUEST_URI'
        );

        return str_starts_with($request_uri, '/wp-json/rankingcoach/api/') === false;
    }

    /**
     * Validates request is not targeting RankingCoach API endpoints.
     */
    private function validateLegacyApiEndpoint(): bool {
        $request_uri = WordpressHelpers::sanitize_input(
            'SERVER',
            'REQUEST_URI'
        );

        return defined('RANKINGCOACH_REST_API_BASE') &&
            str_starts_with($request_uri, '/wp-json/' . RANKINGCOACH_REST_API_BASE);
    }

    /**
     * Validates execution is not in CLI context.
     */
    private function validateNotCliContext(): bool {
        return php_sapi_name() !== 'cli' && php_sapi_name() !== 'phpdbg';
    }

    /**
     * Validates execution is not in WordPress cron context.
     */
    private function validateNotCronContext(): bool {
        return !defined('DOING_CRON') || !DOING_CRON;
    }

    /**
     * Validates execution is not in AJAX context.
     */
    private function validateNotAjaxContext(): bool {
        return !defined('DOING_AJAX') || !DOING_AJAX;
    }

    /**
     * Validates HTTP method is appropriate for WordPress page requests.
     */
    private function validateHttpMethod(): bool {
        $method = WordpressHelpers::sanitize_input(
            'SERVER',
            'REQUEST_METHOD'
        );

        $allowed_methods = ['GET', 'POST', 'HEAD', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];

        return in_array($method, $allowed_methods, true);
    }

    /**
     * Validates user agent is not a bot or automated system.
     */
    private function validateUserAgent(): bool {
        $user_agent = WordpressHelpers::sanitize_input(
            'SERVER',
            'HTTP_USER_AGENT'
        );

        if (empty($user_agent)) {
            return false;
        }

        $user_agent_lower = strtolower($user_agent);

        $known_good_agents = ['googlebot', 'bingbot', 'slurp', 'duckduckbot', 'rankingcoach', 'beyondseo', 'beyond-seo'];
        foreach ($known_good_agents as $good_agent) {
            if (str_contains($user_agent_lower, $good_agent)) {
                return true;
            }
        }

        $bot_patterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python', 'java',
            'monitoring', 'uptime', 'pingdom', 'newrelic', 'datadog', 'nagios',
            'headless', 'phantom', 'selenium', 'webdriver', 'wordpress'
        ];

        foreach ($bot_patterns as $pattern) {
            if (str_contains($user_agent_lower, $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Initializes WordPress-specific plugin components.
     *
     * This method handles the initialization of components that should only
     * run in legitimate WordPress page request contexts.
     * @throws Throwable
     */
    private function initializeWordPressComponents(): void {

        // Initialize WordPress-specific components
        try {
            (new Installer())->initialize();
            (new NotificationInitializer())->initialize();
            //(new SeoOptimiserAjaxInitializer())->initialize();
        } catch (Exception $e) {
            $this->log('Failed to initialize WordPress components: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Clears the context validation cache.
     * Useful for testing or when server environment changes.
     */
    public static function clearContextCache(): void {
        self::$context_cache = [];
    }

    /**
     * Renders the admin error notice.
     *
     */
    public static function renderPluginErrorNotice(): void {
        $message = get_option(BaseConstants::OPTION_LAST_ERROR_MESSAGE);
        if ($message) {
            printf('<div class="notice notice-error rankingcoach-notice"><p>%s</p></div>', esc_html($message));
            delete_option(BaseConstants::OPTION_LAST_ERROR_MESSAGE); // Clean up
        }
    }

    /**
     * Determines if the site is running on production.
     *
     * @return bool True if WordPress is currently running on production, false for all other environments.
     */
    public static function isProductionMode(): bool
    {
        $override = apply_filters( 'rankingcoach_development_mode', false );
        if ( $override ) {
            return true;
        }

        return wp_get_environment_type() === 'production';
    }

    /**
     * @return bool
     */
    public static function isUpVersion(): bool
    {
        $paid = (bool)CoreHelper::isUpVersion();
        return $paid === true;
    }
}
