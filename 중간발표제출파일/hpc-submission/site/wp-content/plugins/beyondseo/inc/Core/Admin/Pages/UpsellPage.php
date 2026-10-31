<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Traits\SingletonTrait;
use ReflectionException;
use WP_REST_Request;
use WP_Error;

/**
 * Class DashboardPage
 * @method UpsellPage getInstance(): static
 */
class UpsellPage extends AdminPage
{

    use RcApiTrait;
    use SingletonTrait;
    use RcLoggerTrait;
    use FlowGuardTrait;

    /** @var string $name The name of the upsell page */
    public string $name = 'connect';

    /** @var AdminManager|null $managerInstance */
    public static AdminManager|null $managerInstance = null;

    /** Feature flag: when true, UpsellPage will use FlowManager to guard access. */
    private bool $flowGuardEnabled = false;

    /**
     * Flag to track if access control was already handled by the load-{$page_hook} hook.
     * When true, page_content() will skip the FlowGuard logic since it was already processed.
     *
     * @var bool
     */
    private bool $accessControlHandled = false;

    /**
     * UpsellPage constructor.
     * Initializes the UpsellPage instance and registers necessary scripts.
     */
    public function __construct() {
        // Register scripts loading
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('rest_api_init', [$this, 'registerUpsellRoutes']);
        $this->flowGuardEnabled = OptionStore::isFlowGuardActive();

        // Load React app for upsell page
        add_action('current_screen', function($screen) {
            // Ensure the screen object is available
            if (!is_object($screen) || !isset($screen->id)) {
                return;
            }

            if (
                $screen->id !== RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-' . $this->name &&
                $screen->id !== 'admin_page_rankingcoach-' . $this->name
            ) {
                return;
            }

            ReactApp::get([
                'upsell'
            ]);
        });

        parent::__construct();
    }

    /**
     * Enqueue necessary scripts and styles for the upsell page
     *
     * @param string $hook The current admin page hook
     * @throws HttpApiException
     * @throws ReflectionException
     */
    public function enqueue_scripts(string $hook): void {

        if (
            $hook === RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-' . $this->name ||
            $hook === 'admin_page_rankingcoach-' . $this->name
        ) {
            $upsell_script_url = RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/js/upsell-page.js';
            $upsell_window_script_url = RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/js/upsell-window.js';
            $css_url = RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/css/admin-style.css';
            $upsell_css_url = RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/css/upsell-page.css';
            $version = defined('RANKINGCOACH_VERSION') ? RANKINGCOACH_VERSION : '1.0.0';

            // Cache-bust the upsell page JS by its file modification time so script
            // changes are picked up immediately instead of being served stale.
            $upsell_script_path = plugin_dir_path(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/js/upsell-page.js';
            $upsell_script_version = file_exists($upsell_script_path)
                ? (string) filemtime($upsell_script_path)
                : $version;

            // Enqueue Admin CSS
            wp_enqueue_style(
                'rankingcoach-admin-style',
                $css_url,
                [],
                $version
            );

            // Enqueue Upsell Page CSS
            wp_enqueue_style(
                'rankingcoach-connect-page-style',
                $upsell_css_url,
                [],
                $version
            );

            // Enqueue JavaScript
            wp_enqueue_script(
                'rankingcoach-connect-page-js',
                $upsell_script_url,
                ['jquery'],
                $upsell_script_version,
                true // Load in footer
            );

            wp_enqueue_script(
                'rankingcoach-connect-window-js',
                $upsell_window_script_url,
                ['jquery'],
                $version,
                true // Load in footer
            );

            wp_localize_script('rankingcoach-connect-window-js', 'rcWindowConfig', [
                'loadingTitle' => __('Loading...', 'beyondseo'),
                'connectingMessage' => __('Connecting to server...', 'beyondseo'),
                'loadingCss' => 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f0f0f1; color: #3c434a; }
                        .spinner { width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #2271b1; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20px; }
                        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                        p { font-size: 14px; }'
            ]);

            // Initialize the registration handler with custom callbacks
            $main_page_url = AdminManager::getPageUrl(AdminManager::PAGE_MAIN, '?ref=account_sync');
            wp_add_inline_script(
                'rankingcoach-connect-window-js',
                "if (typeof BSEORegistration !== 'undefined') {
                    BSEORegistration.init({
                        onSuccess: function(payload) {
                            console.log('[RankingCoach] Registration successful, redirecting to main page');
                            window.location.href = '" . esc_js($main_page_url) . "';
                        },
                        onError: function(errorMessage, payload) {
                            console.error('[RankingCoach] Registration error:', errorMessage);
                            alert('Registration Error: ' + errorMessage);
                        },
                        onCancel: function(payload) {
                            console.log('[RankingCoach] Registration cancelled by user');
                        }
                    });
                }",
                'after'
            );

            // Localize script for API calls
            wp_localize_script('rankingcoach-connect-page-js', 'rcUpsell', [
                'apiUrl' => esc_url_raw(rest_url(RANKINGCOACH_REST_API_BASE . '/upsell/url')),
                'baseUrl' => UserApiManager::getInstance()->getClientDashboardUrl(),
                'locale' => WordpressHelpers::get_wp_locale() ?? 'en_US',
                'nonce'  => wp_create_nonce('wp_rest')
            ]);
        }
    }

    /**
     * Register REST endpoints for upsell flow.
     */
    public function registerUpsellRoutes(): void
    {
        register_rest_route(RANKINGCOACH_REST_API_BASE, '/upsell/url', [
            'methods' => 'POST',
            'callback' => [$this, 'handleGetUpsellUrl'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'paymentType' => [
                    'required'          => true,
                    'type'              => 'string',
                    'description'       => 'The payment type (monthly or annual)',
                    'validate_callback' => function ($param) {
                        return in_array($param, ['monthly', 'annual'], true);
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Handle request to get upsell magic link.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function handleGetUpsellUrl(WP_REST_Request $request): array|WP_Error
    {
        // paymentType is already validated and sanitized by the REST API
        $paymentType = $request->get_param('paymentType');
        $country = get_option(BaseConstants::OPTION_RANKINGCOACH_COUNTRY_CODE, 'US');

        try {
            $result = UserApiManager::getInstance(bearerToken: true)->fetchUpsellMagicLink($paymentType, $country);

            if (!$result) {
                return new WP_Error('upsell_error', 'Could not fetch upsell URL', ['status' => 500]);
            }

            update_option(BaseConstants::OPTION_UPSELL_FORCE_CHECK, true, true);
            update_option(BaseConstants::OPTION_UPSELL_RETRY_COUNT, 0, true);
            update_option(BaseConstants::OPTION_UPSELL_LAST_CHECK_TIMESTAMP, 0, true);

            return $result;
        } catch (Exception $e) {
            return new WP_Error('upsell_exception', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * @return string
     */
    public function page_name(): string
    {
        return $this->name;
    }

    /**
     * Admin UpsellPage constructor.
     * @throws HttpApiException
     * @throws ReflectionException
     */
    public function page_content(): void
    {
        if (!$this->accessControlHandled) {
            $this->redirectActivatedAccountToOnboarding();
        }

        // For new users who haven't completed onboarding yet, we allow access to the
        // Connect page (UpsellPage) without token validation.
        if (!CoreHelper::isOnboarded()) {
            // Load the appropriate view template based on channel
            $channel = (new OptionStore())->getChannel();
            include __DIR__ . '/views/upsell-dc-page.php';
            return;
        }

        // Use ChannelResolver for consistent channel detection
        $store = new OptionStore();
        $resolver = new ChannelResolver($store);
        $channel = $resolver->resolve();

        // If access control was already handled by the load-{$page_hook} hook,
        // skip the FlowGuard logic here (it runs before headers are sent in that pattern).
        if (!$this->accessControlHandled) {
            // Guarded by feature flag; performs render/redirect and exits when enabled
            $this->applyFlowGuard();

            // Legacy path parity: when the load-{$page_hook} pattern did not run,
            // upgrade deep links still need to be resolved before the view renders.
            $this->maybeHandlePlanUpgradeRequest();
        }

        // Load the appropriate view template based on the detected channel.
        // IONOS gets its dedicated upsell view; every other channel falls back
        // to the direct-channel (DC) view. Decide from the resolved channel OR the live
        // IONOS brand signal (both normalized), so an IONOS install always gets its
        // dedicated view even if one of the two signals is momentarily unavailable.
        $resolvedChannel = strtolower(trim((string) $channel));
        $ionosBrand      = strtolower(trim((string) get_option('ionos_group_brand')));
        $isIonos         = $resolvedChannel === 'ionos' || $ionosBrand === 'ionos';

        // IONOS only gets its dedicated plans view once onboarding has finished;
        // before that the shared connect view (React app) renders, guiding the
        // user through the connect/registration flow first. Non-IONOS channels
        // always use the direct-channel view, which handles both states itself.
        if ($isIonos && WordpressHelpers::isOnboardingCompleted()) {
            include __DIR__ . '/views/upsell-ionos-page.php';
        } else {
            include __DIR__ . '/views/upsell-dc-page.php';
        }
    }

    /**
     * Get the URL for the new plans upgrade.
     *
     * @return string
     */
    public function getPlansToBuyUrl(): string
    {
        return esc_url(AdminManager::getPageUrl(AdminManager::PAGE_UPSELL));
    }

    /**
     * Handle access control before headers are sent.
     *
     * This method is designed to be called from the WordPress `load-{$page_hook}` action,
     * which fires BEFORE any output is sent. This allows us to perform redirects using
     * wp_safe_redirect()
     *
     * @return void
     */
    public function handleAccessControl(): void
    {
        // Mark that access control has been handled, so page_content() skips FlowGuard
        $this->accessControlHandled = true;

        $this->redirectActivatedAccountToOnboarding();

        // Upgrade deep links (step=upsell&planSelected=...) must be processed here,
        // before headers are sent, so the browser can be redirected to the purchase URL.
        $this->maybeHandlePlanUpgradeRequest();

        if (!$this->flowGuardEnabled) {
            return;
        }
    }

    /**
     * Redirect plan upgrade deep links to their upsell purchase URL.
     *
     * The channel upsell views (DC and IONOS) render their "Upgrade" buttons as links
     * back to this page carrying step=upsell&planSelected=<plan>. When such a request
     * arrives, fetch the plan's upsell magic link from the RC API and send the browser
     * there. Falls through to normal page rendering when the link cannot be resolved.
     *
     * @return void
     */
    private function maybeHandlePlanUpgradeRequest(): void
    {
        $step         = WordpressHelpers::sanitize_input('GET', 'step') ?: '';
        $planSelected = WordpressHelpers::sanitize_input('GET', 'planSelected') ?: '';

        if ($step !== 'upsell' || $planSelected === '') {
            return;
        }

        // Plan purchases are only possible once onboarding has finished; before
        // that the connect view renders and no upgrade buttons exist yet.
        if (!WordpressHelpers::isOnboardingCompleted()) {
            return;
        }

        try {
            $upsellingResult = UserApiManager::handleUpselling($planSelected);

            if ($upsellingResult && !empty($upsellingResult['upsellUrl'])) {
                wp_redirect($upsellingResult['upsellUrl']);
                exit;
            }

            $this->log(sprintf('Upselling: no upsell URL returned for plan "%s"', $planSelected), 'ERROR');
        } catch (\Throwable $e) {
            $this->log('Upselling API error: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Guard Upsell page using FlowManager mapping. Exits after render/redirect when enabled.
     *
     * LEGACY METHOD: This is the original implementation that uses output buffering.
     * It's kept for backward compatibility with pages that don't use the load-{$page_hook} pattern.
     *
     * @return void
     */
    private function applyFlowGuard(): void
    {
        if (!$this->flowGuardEnabled) {
            return;
        }

        try {
            $result = $this->evaluateFlow();
            $step   = $result['next_step'] ?? '';

            // Allowed on UpsellPage: done (which maps to main/upsell)
            if ($step === 'done') {
                return;
            }

            // Otherwise redirect by flow mapping
            $destination = match ($step) {
                'register', 'email_validation', 'finalizing' => AdminPage::PAGE_REGISTRATION,
                'activate' => AdminPage::PAGE_REGISTRATION,
                'onboarding' => AdminPage::PAGE_ONBOARDING,
                default => AdminPage::PAGE_MAIN,
            };

            if (self::$managerInstance instanceof AdminManager) {
                self::$managerInstance->redirectPage($destination);
            }
            exit;
        } catch (\Throwable $e) {
            // Fail-open
            return;
        }
    }
}
