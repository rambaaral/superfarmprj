<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\Api\HttpApiClient;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Jobs\AccountSyncJob;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Traits\SingletonTrait;
use ReflectionException;
use RankingCoach\Inc\Core\Settings\SettingsManager;

use Throwable;
use function beyondseo_rceh;

/**
 * Class IframePage
 *
 * Singleton AdminIframePage Class
 * @method IframePage getInstance(): static
 */
class IframePage extends AdminPage
{
    use SingletonTrait;
    use RcLoggerTrait;
    use FlowGuardTrait;

    public string $name = 'main';

    public static ?AdminManager $managerInstance = null;

    /** Feature flag: when true, IframePage will use FlowManager to guard access (step must be 'main' or 'done'). */
    private bool $flowGuardEnabled = false;

    /**
     * Flag to track if access control was already handled by the load-{$page_hook} hook.
     * When true, page_content() will skip the FlowGuard logic since it was already processed.
     *
     * @var bool
     */
    private bool $accessControlHandled = false;

    /**
     * IframePage constructor.
     * Initializes the IframePage instance.
     */
    public function __construct() {
        parent::__construct();
        $this->flowGuardEnabled = OptionStore::isFlowGuardActive();
        $this->handleResetAndReactivate();
    }

    /**
     * @return string
     */
    public function page_name(): string
    {
        return $this->name;
    }

    /**
     * Registers the reset and reactivate form submission handler.
     */
    public function handleResetAndReactivate(): void {
        add_action('admin_post_rc_reset_and_reactivate', [$this, 'processResetAndReactivate']);
    }

    /**
     * Processes the reset and reactivate form submission.
     */
    public function processResetAndReactivate(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'beyondseo'));
        }

        $nonce = WordpressHelpers::sanitize_input('POST', '_wpnonce');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'rc_reset_and_reactivate')) {
            wp_die(esc_html__('Nonce verification failed.', 'beyondseo'));
        }

        $this->log('Reset and Reactivate triggered.', 'INFO');

        // Determine channel BEFORE resetting data, as reset deletes channel proof options
        $store = new OptionStore();
        $resolver = new ChannelResolver($store);
        $channel = $resolver->resolve();

        TokensManager::getInstance()->resetActivationData();

        $nextPage = ($channel === 'ionos' || $channel === 'extendify') ? 'activation' : 'connect';

        if (self::$managerInstance instanceof AdminManager) {
            self::$managerInstance->redirectPage($nextPage);
        }
        exit;
    }

    /**
     * Main page content renderer (dashboard iframe).
     * - Decoupled cookie detection UI into template: views/cookie/third-party-cookie-warning.php
     * - Decoupled iframe UI into template: views/iframe-page.php
     * - Optional Flow guard (feature-flagged)
     *
     * @return void
     * @throws HttpApiException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function page_content(): void
    {
        // If access control was already handled by the load-{$page_hook} hook,
        // skip the FlowGuard logic here (it runs before headers are sent in that pattern).
        if (!$this->accessControlHandled) {
            // Optional flow guard (disabled by default; enable by flipping $this->flowGuardEnabled)
            $this->applyFlowGuard();
        }

        if ($this->hasAuthenticatedAccountWithoutProject()) {
            $accountUrl = add_query_arg('section', 'account', admin_url('admin.php?page=rankingcoach-generalSettings'));
            printf(
                '<div class="notice notice-info inline"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('Your account is connected, but a project is required to use the BeyondSEO dashboard. Create a project in Settings > Account to continue.', 'beyondseo'),
                esc_url($accountUrl),
                esc_html__('Go to Account', 'beyondseo')
            );
            return;
        }

        /** @var TokensManager $tokensManager */
        $tokensManager = TokensManager::getInstance();
        $locationId   = (int) get_option(BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID, 0);

        try {
            // Ensure we have a valid access token (refresh if needed)
            $accessToken = $tokensManager->getAccessToken(static::class);
        } catch (Throwable $e) {
            $this->log('Unexpected error during token retrieval: ' . $e->getMessage(), 'ERROR');
            $accessToken = '';
        }

        if (!$accessToken || !$tokensManager::validateToken($accessToken)) {
            $this->log('Access token is invalid or expired. Attempting reactivation.', 'WARNING');
            $activationCode = get_option(BaseConstants::OPTION_ACTIVATION_CODE);
            $revalidated = false;

            if (!empty($activationCode)) {
                try {
                    $apiClient = new HttpApiClient();
                    $revalidated = $apiClient->revalidateWithActivationCode($activationCode);
                } catch (Throwable $ex) {
                    $this->log('Automatic reactivation attempt failed: ' . $ex->getMessage(), 'ERROR');
                }
            }

            if ($revalidated) {
                try {
                    $accessToken = $tokensManager->getAccessToken(static::class);
                } catch (Throwable $ex) {
                    $this->log('Token retrieval failed after successful automatic reactivation: ' . $ex->getMessage(), 'ERROR');
                }
            }

            if (!$accessToken || !$tokensManager::validateToken($accessToken)) {
                // Show reactivation page instead of throwing or wp_die()
                wp_enqueue_style(
                    'rankingcoach-activation',
                    RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/css/activation.css',
                    [],
                    RANKINGCOACH_VERSION
                );
                wp_enqueue_script(
                    'rankingcoach-activation',
                    RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/js/activation.js',
                    ['jquery'],
                    RANKINGCOACH_VERSION,
                    true
                );
                wp_localize_script('rankingcoach-activation', 'rcActivation', [
                    'errorEmptyCode' => __('Activation code is required.', 'beyondseo'),
                ]);
                include __DIR__ . '/views/reactivate-page.php';
                return;
            }
        }

        if ( !SettingsManager::instance()->get_option('beyondseo_comm_opt_in', false)) {
            $settingsUrl = admin_url('admin.php?page=rankingcoach-generalSettings');
            printf(
                '<div class="notice notice-warning inline"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('Communication Opt-In is disabled. The BeyondSEO dashboard requires it to be enabled.', 'beyondseo'),
                esc_url($settingsUrl),
                esc_html__('Go to Settings', 'beyondseo')
            );
            return;
        }


        // Handle 'ref' query parameter for account sync
        // ===============================================
        // If 'ref=account_sync' means that a successful upsell occurred and we need to trigger an account sync
        // ===============================================
        $ref = WordpressHelpers::sanitize_input('GET', 'ref');
        if ($ref === 'account_sync') {
            try {
                $accountSyncJob = AccountSyncJob::instance();
                $syncSuccess = $accountSyncJob->forceSync();
                if ($syncSuccess) {
                    $this->log('Account sync triggered by ref parameter', 'INFO');
                } else {
                    $this->log('Account sync failed', 'ERROR');
                }
            } catch (Exception $e) {
                $this->log('Error during account sync: ' . $e->getMessage(), 'ERROR');
            }
        }

        // Build iframe URL from config
        $config    = require RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Api/externalIntegrations.php';
        $language  = WordpressHelpers::current_language_code_helper(WordpressHelpers::get_wp_locale()) ?? 'en';
        $locale    = WordpressHelpers::get_wp_locale();
        $baseEnv   = RankingCoachPlugin::isProductionMode() ? 'liveEnv' : 'devEnv';
        $installationId = (string)get_option(BaseConstants::OPTION_INSTALLATION_ID, '');
        $parentOrigin = urlencode(site_url());
        $beyondSeoIframeUrl = sprintf($config['iframeUrl'], $config[$baseEnv], $language, $locationId, $installationId, $parentOrigin, $accessToken);
        if(get_option(BaseConstants::OPTION_RANKINGCOACH_COUPON_CODE)) {
            $couponCode = (string)get_option(BaseConstants::OPTION_RANKINGCOACH_COUPON_CODE);
            //$beyondSeoIframeUrl = sprintf($config['codeUrl'], $config[$baseEnv], $locale, $couponCode, urlencode($beyondSeoIframeUrl));
        }

        // Check if iframe destination requires authentication
        $status = $tokensManager->checkIframeUrlStatus($beyondSeoIframeUrl);
        if ($status === 401) {
            $this->log('Iframe returned 401. Attempting reactivation.', 'WARNING');
            $activationCode = get_option(BaseConstants::OPTION_ACTIVATION_CODE);
            $revalidated = false;
            if (!empty($activationCode)) {
                try {
                    $apiClient = new HttpApiClient();
                    $revalidated = $apiClient->revalidateWithActivationCode($activationCode);
                } catch (Throwable $e) {
                    $this->log('Reactivation attempt failed: ' . $e->getMessage(), 'ERROR');
                }
            }

            if ($revalidated) {
                // Refresh tokens and rebuild URL
                $accessToken = $tokensManager->getAccessToken(static::class);
                $beyondSeoIframeUrl = sprintf($config['iframeUrl'], $config[$baseEnv], $language, $locationId, $installationId, $parentOrigin, $accessToken);
                if(get_option(BaseConstants::OPTION_RANKINGCOACH_COUPON_CODE)) {
                    $couponCode = (string)get_option(BaseConstants::OPTION_RANKINGCOACH_COUPON_CODE);
                    //$beyondSeoIframeUrl = sprintf($config['codeUrl'], $config[$baseEnv], $locale, $couponCode, urlencode($beyondSeoIframeUrl));
                }
            } else {
                // Show reactivation page instead
                wp_enqueue_style(
                    'rankingcoach-activation',
                    RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/css/activation.css',
                    [],
                    RANKINGCOACH_VERSION
                );
                wp_enqueue_script(
                    'rankingcoach-activation',
                    RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/js/activation.js',
                    ['jquery'],
                    RANKINGCOACH_VERSION,
                    true
                );
                wp_localize_script('rankingcoach-activation', 'rcActivation', [
                    'errorEmptyCode' => __('Activation code is required.', 'beyondseo'),
                ]);
                include __DIR__ . '/views/reactivate-page.php';
                return;
            }
        }

        $settingsManager = SettingsManager::instance();
        $beyondSeoOpenPageInNewTab = (bool)$settingsManager->get_option('open_rc_dashboard_in_new_tab', false);
        $beyondSeoPlanLvl3 = !CoreHelper::hasUpgradePlans();

        // 1) Cookie detection UI (JS + warning detection UI)
        include __DIR__ . '/views/cookie/third-party-cookie-warning.php';

        wp_add_inline_script('rankingcoach-admin-script',
            "
(function() {
    var rc_cookie_cookieCheckCompleted = false;
    var rc_cookie_fallbackTimeout = null;
    var RC_COOKIE_FALLBACK_DELAY = 5000; // 5 seconds timeout for cookie check

    function rc_cookie_detectBrowser() {
        var userAgent = navigator.userAgent.toLowerCase();
        if (userAgent.indexOf('firefox') !== -1) return 'firefox';
        if (userAgent.indexOf('edg') !== -1) return 'edge';
        if (userAgent.indexOf('chrome') !== -1 || userAgent.indexOf('chromium') !== -1) return 'chrome';
        if (userAgent.indexOf('safari') !== -1) return 'safari';
        return 'default';
    }

    function rc_cookie_showBrowserInstructions(browser) {
        var ids = ['safari-instructions','chrome-instructions','firefox-instructions','edge-instructions','default-instructions'];
        ids.forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
        var target = document.getElementById(browser + '-instructions');
        if (target) target.style.display = 'block';
    }

    function rc_cookie_initializeIframe() {
        if (rc_cookie_cookieCheckCompleted) return;
        rc_cookie_cookieCheckCompleted = true;
        if (rc_cookie_fallbackTimeout) {
            clearTimeout(rc_cookie_fallbackTimeout);
            rc_cookie_fallbackTimeout = null;
        }
        var warn = document.getElementById('rc-cookie-warning');
        if (warn) warn.style.display = 'none';
        // rest handled by rc_cookie_handleIframeLoaded
    }

    function rc_cookie_fallbackToNormalIframe() {
        if (rc_cookie_cookieCheckCompleted) return;
        rc_cookie_initializeIframe();
    }

    var rc_cookie_receiveMessage = function (evt) {
        if (evt.origin !== 'https://thirdpartycookies.rankingcoach.com') return;

        if (evt.data === '3PCunsupported') {
            if (rc_cookie_cookieCheckCompleted) return;
            rc_cookie_cookieCheckCompleted = true;
            if (rc_cookie_fallbackTimeout) {
                clearTimeout(rc_cookie_fallbackTimeout);
                rc_cookie_fallbackTimeout = null;
            }
            var browser = rc_cookie_detectBrowser();
            rc_cookie_showBrowserInstructions(browser);
            var warn = document.getElementById('rc-cookie-warning');
            if (warn) warn.style.display = 'block';
            var loader = document.getElementById('rc-seo-iframe-loader');
            if (loader) loader.style.display = 'none';
            var iframe = document.getElementById('rc-seo-iframe');
            if (iframe) iframe.style.display = 'none';
        } else if (evt.data === '3PCsupported') {
            if (rc_cookie_cookieCheckCompleted) return;
            rc_cookie_initializeIframe();
        }
    };
    window.addEventListener('message', rc_cookie_receiveMessage, false);

    // Provide a generic loader completion hook for the iframe
    window.rc_cookie_handleIframeLoaded = function() {
        if (document.getElementById('rc-cookie-warning').style.display === 'none') {
            var loader = document.getElementById('rc-seo-iframe-loader');
            if (loader) loader.style.display = 'none';
            var iframe = document.getElementById('rc-seo-iframe');
            if (iframe) iframe.style.visibility = 'visible';
        }
    };

    function rc_cookie_handleCookieCheckError() {
        rc_cookie_fallbackToNormalIframe();
    }

    function rc_cookie_initializeCookieCheck() {
        var cookieCheckIframe = document.getElementById('rc-cookie-check-iframe');
        if (cookieCheckIframe) {
            cookieCheckIframe.onerror = rc_cookie_handleCookieCheckError;
            cookieCheckIframe.onabort = rc_cookie_handleCookieCheckError;

            rc_cookie_fallbackTimeout = setTimeout(function() {
                rc_cookie_fallbackToNormalIframe();
            }, RC_COOKIE_FALLBACK_DELAY);

            cookieCheckIframe.onload = function() {
                setTimeout(function() {
                    if (!rc_cookie_cookieCheckCompleted) {
                        rc_cookie_fallbackToNormalIframe();
                    }
                }, 2000);
            };
        } else {
            rc_cookie_fallbackToNormalIframe();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', rc_cookie_initializeCookieCheck);
    } else {
        rc_cookie_initializeCookieCheck();
    }
})();"
        );
        // 2) Iframe UI (skeleton + main iframe)
        include __DIR__ . '/views/iframe-page.php';
    }

    private function hasAuthenticatedAccountWithoutProject(): bool
    {
        $isPositiveIdentifier = static function (mixed $value): bool {
            if (!is_int($value) && (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', $value))) {
                return false;
            }

            return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
        };
        $accountId = get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID);
        $refreshToken = get_option(BaseConstants::OPTION_REFRESH_TOKEN, '');
        $projectId = get_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID);
        $locationId = get_option(BaseConstants::OPTION_RANKINGCOACH_LOCATION_ID);

        return $isPositiveIdentifier($accountId)
            && is_string($refreshToken)
            && $refreshToken !== ''
            && (!$isPositiveIdentifier($projectId) || !$isPositiveIdentifier($locationId));
    }

    /**
     * Evaluate flow and determine if a redirect is needed.
     * Centralized logic used by both handleAccessControl() and applyFlowGuard().
     *
     * @return array{needsRedirect: bool, destination: string|null}
     */
    private function evaluateFlowRedirect(): array
    {
        if (!$this->flowGuardEnabled || $this->hasAuthenticatedAccountWithoutProject()) {
            return ['needsRedirect' => false, 'destination' => null];
        }

        try {
            $result = $this->evaluateFlow();
            $step   = $result['next_step'] ?? '';

            // Allowed steps for the dashboard (proceed to rendering)
            if ($step === 'done') {
                return ['needsRedirect' => false, 'destination' => null];
            }

            // Redirect mapping - use AdminManager::PAGE_* constants (full slugs like 'rankingcoach-registration')
            // NOT AdminPage::PAGE_* (short names like 'registration') because AdminManager::getPageUrl()
            // expects the full page slug.
            $destination = match ($step) {
                'register' => AdminManager::PAGE_UPSELL,
                'onboarding' => AdminManager::PAGE_ONBOARDING,
                'email_validation', 'finalizing' => AdminManager::PAGE_REGISTRATION,
                // 'email_validation', 'register', 'finalizing' => AdminManager::PAGE_REGISTRATION,
                // 'activate'                                    => AdminManager::PAGE_ACTIVATION,
                // 'onboarding'                                  => AdminManager::PAGE_ONBOARDING,
                default                                       => AdminManager::PAGE_MAIN,
            };

            // Only redirect if destination is not the main page
            if ($destination !== AdminManager::PAGE_MAIN) {
                return ['needsRedirect' => true, 'destination' => $destination];
            }

            return ['needsRedirect' => false, 'destination' => null];

        } catch (Throwable $e) {
            // Fail-open: if evaluation fails, let the page render
            $this->log('Flow evaluation failed: ' . $e->getMessage(), 'WARNING');
            return ['needsRedirect' => false, 'destination' => null];
        }
    }

    /**
     * Handle access control before headers are sent.
     *
     * This method is designed to be called from the WordPress `load-{$page_hook}` action,
     * which fires BEFORE any output is sent. This allows us to perform redirects using
     * wp_safe_redirect()
     *
     * PROOF OF CONCEPT: This demonstrates the load-{$page_hook} pattern for redirect handling.
     * If a redirect is needed, it happens here and the script exits.
     * If no redirect is needed, the page renders normally via page_content().
     *
     * @return void
     */
    public function handleAccessControl(): void
    {
        // Mark that access control has been handled, so page_content() skips FlowGuard
        $this->accessControlHandled = true;

        $redirectDecision = $this->evaluateFlowRedirect();

        if ($redirectDecision['needsRedirect']) {
            $redirectUrl = AdminManager::getPageUrl($redirectDecision['destination']);
            wp_safe_redirect($redirectUrl);
            exit;
        }
    }

    /**
     * Flow guard for IframePage (dashboard). Allowed when flow step is "main" or "done".
     * Otherwise redirect to the corresponding step page.
     *
     * LEGACY METHOD: This is the original implementation that uses output buffering.
     * It's kept for backward compatibility with pages that don't use the load-{$page_hook} pattern.
     *
     * @return void
     */
    private function applyFlowGuard(): void
    {
        $redirectDecision = $this->evaluateFlowRedirect();

        if ($redirectDecision['needsRedirect'] && self::$managerInstance instanceof AdminManager) {
            self::$managerInstance->redirectPage($redirectDecision['destination']);
            exit;
        }
    }
}
