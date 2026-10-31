<?php
declare(strict_types=1);

namespace RankingCoach\inc\Core\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use JetBrains\PhpStorm\NoReturn;
use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\ChannelFlow\ChannelEnforcer;
use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Review\ReviewState;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Traits\SingletonTrait;
use ReflectionException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class ActivationPage
 *
 * Handles plugin activation flow, validation, and user redirection.
 * Responsible for rendering the activation form and processing submitted codes.
 *
 * @method static ActivationPage getInstance()
 */
class ActivationPage extends AdminPage
{
    use FlowGuardTrait;
    use SingletonTrait;

    /** @var string Action name used in form submission */
    protected const SAVE_ACTIVATION_ACTION = 'save_rankingcoach_activation';

    /** Plugin setting (settings row in wp_options) counting the recovery mails actually sent for this website. */
    private const OPTION_ACTIVATION_CODE_RECOVERY_COUNT = 'activation_code_recovery_count';

    /** Recovery mails allowed per website before the flow refers the admin to customer support (rc-webapp enforces the same cap per subscription). */
    private const MAX_ACTIVATION_CODE_RECOVERIES = 3;

    /** @var string Page slug name */
    public string $name = 'activation';

    /** @var AdminManager|null Reference to the main admin manager */
    public static ?AdminManager $managerInstance = null;

    /**
     * Flag to track if access control was already handled by the load-{$page_hook} hook.
     * When true, page_content() will skip the FlowGuard logic since it was already processed.
     *
     * @var bool
     */
    private bool $accessControlHandled = false;

    /** @var bool Indicates whether API error filter is active */
    private bool $apiErrorFilterEnabled = false;

    /** Feature flag: when true, ActivationPage will use FlowManager to guard access (redirect if next_step !== 'activate'). */
    // We keep it disabled by default to avoid automatic redirects that could be confuse
    // The case when rc_activation_saved=1 and the refresh is made automatically.
    private bool $flowGuardEnabled = false;

    public function __construct() {
        parent::__construct();
        // Flow guard is intentionally kept disabled on ActivationPage to avoid redirect
        // loops when the flow's next_step is 'register' but the user is reconnecting/
        // re-entering an activation code. Use ?bypass_flow=1 is no longer required.
        $this->flowGuardEnabled = false;
        $this->handleActivationFormSaves();
        add_action('rest_api_init', [$this, 'registerActivationRoutes']);
        add_action('current_screen', function ($screen) {
            if (!is_object($screen) || !isset($screen->id)) {
                return;
            }
            if (!in_array($screen->id, ALLOWED_RANKINGCOACH_PAGES)) {
                return;
            }
            ReactApp::get(['activation']);
        });
    }

    /**
     * Returns the page name slug.
     */
    public function page_name(): string
    {
        return $this->name;
    }

    /**
     * Displays the activation page content.
     * Evaluates flow, handles redirects, and loads the activation view.
     *
     * @param callable|null $failCallback Optional callback executed on failure.
     */
    public function page_content(?callable $failCallback = null): void
    {
        // If access control was already handled by the load-{$page_hook} hook,
        // skip the FlowGuard logic here (it runs before headers are sent in that pattern).
        if (!$this->accessControlHandled) {
            $this->maybeResumeOnboarding();

            $store = new OptionStore();
            $resolver = new ChannelResolver($store);
            $channel = $resolver->resolve();
            if (!isset($_GET['bypass_flow'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flow control flag
                ChannelEnforcer::enforcePageAccess('activation', $channel);
            }

            // Optional flow guard (disabled by default; see $this->flowGuardEnabled)
            $this->applyFlowGuard($failCallback);
        }

        // REF: get message if exists
        $errorMessage = $this->getQueryParamString('message');
        $activationSaved = $this->getQueryParamInt('rc_activation_saved');

        // Retrieve activation code from database
        $activationCode = get_option(BaseConstants::OPTION_ACTIVATION_CODE);

        include __DIR__ . '/views/activation-page-react.php';
    }

    /**
     * Registers hooks for form processing and notice handling.
     */
    public function handleActivationFormSaves(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }

        add_action('admin_post_' . self::SAVE_ACTIVATION_ACTION, [$this, 'handleActivationCodeSubmission']);
        add_action('admin_notices', [$this, 'handleActivationNotices']);
        $registered = true;
    }

    public function registerActivationRoutes(): void
    {
        register_rest_route(
            RANKINGCOACH_REST_API_BASE,
            '/account/activate',
            [
                'methods'  => 'POST',
                'callback' => [$this, 'handleActivateRestRequest'],
                'permission_callback' => fn(WP_REST_Request $request): bool|WP_Error => $this->activationRoutePermissionCallback($request),
                'args' => [
                    'activationCode' => [
                        'required'          => true,
                        'validate_callback' => fn($p) => is_string($p) && trim($p) !== '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'commOptIn' => [
                        'required'          => false,
                        'default'           => false,
                        'sanitize_callback' => 'rest_sanitize_boolean',
                    ],
                ],
            ]
        );

        register_rest_route(
            RANKINGCOACH_REST_API_BASE,
            '/account/recoverActivationCode',
            [
                'methods'  => 'POST',
                'callback' => [$this, 'handleRecoverActivationCodeRestRequest'],
                'permission_callback' => fn(WP_REST_Request $request): bool|WP_Error => $this->activationRoutePermissionCallback($request),
                'args' => [
                    'email' => [
                        'required'          => true,
                        'validate_callback' => fn($p) => is_string($p) && is_email($p),
                        'sanitize_callback' => 'sanitize_email',
                    ],
                ],
            ]
        );
    }

    /**
     * Shared permission check for the activation REST routes: manage_options + wp_rest nonce.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    private function activationRoutePermissionCallback(WP_REST_Request $request): bool|WP_Error
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('You do not have sufficient permissions.', 'beyondseo'), ['status' => 403]);
        }
        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('invalid_nonce', __('Nonce verification failed.', 'beyondseo'), ['status' => 403]);
        }
        return true;
    }

    public function handleActivateRestRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $activationCode = $request->get_param('activationCode');
        $commOptIn      = $request->get_param('commOptIn');

        SettingsManager::instance()->update_option('beyondseo_comm_opt_in', $commOptIn);

        [$success, $message, $result] = $this->processActivationCode($activationCode);

        if (!$success || !$result) {
            return new WP_REST_Response(['success' => false, 'message' => $message ?: __('Invalid activation code.', 'beyondseo')], 422);
        }

        $refreshToken    = $result->refreshToken ?? '';
        $accountId       = $result->accountId ?? null;
        $setupSetting    = $result->locationSetupSetting ?? null;
        $resellerAccount = $result->resellerAccount ?? true;

        add_option(BaseConstants::OPTION_IS_RESELLER_ACCOUNT, $resellerAccount ? 1 : 0);
        update_option(BaseConstants::OPTION_ACTIVATION_CODE, $activationCode);
        update_option(BaseConstants::OPTION_LOCATION_SETUP_SETTINGS, $setupSetting);

        if ($refreshToken === '') {
            return new WP_REST_Response(['success' => false, 'message' => __('Missing refresh token in activation response.', 'beyondseo')], 422);
        }

        try {
            $isHandled = $this->saveRefreshTokenAndFetchData($refreshToken);
        } catch (Throwable $e) {
            TokensManager::instance()->deleteTokens();
            delete_option(BaseConstants::OPTION_ACTIVATION_CODE);
            return new WP_REST_Response(['success' => false, 'message' => $e->getMessage() ?: __('Error fetching account data.', 'beyondseo')], 422);
        }

        if (!$isHandled) {
            TokensManager::instance()->deleteTokens();
            delete_option(BaseConstants::OPTION_ACTIVATION_CODE);
            return new WP_REST_Response(['success' => false, 'message' => __('Activation succeeded but data could not be saved.', 'beyondseo')], 422);
        }

        update_option(BaseConstants::OPTION_ACTIVATION_CODE, $activationCode);
        update_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, $accountId);

        $store = new OptionStore();
        $store->updateFlowState(function ($flowState) {
            $flowState->registered   = true;
            $flowState->emailVerified = true;
            $flowState->activated    = true;
            return $flowState;
        });

        try {
            (new ReviewState())->recordCustomerStarted();
        } catch (Throwable $e) {
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Handles the "lost activation code" request: asks rankingCoach to re-send the activation
     * code to the given email. Only an email is sent - no flow state, tokens or options change,
     * and the communication opt-in is deliberately not stored here (no consent step on this form).
     * Failures return a translated, cause-specific `message` plus a `details` string (reason code
     * and the remote error text) so the admin and customer support can see what actually happened.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handleRecoverActivationCodeRestRequest(WP_REST_Request $request): WP_REST_Response
    {
        $email = (string) $request->get_param('email');

        // Per-installation brake, kept in the plugin's settings row in wp_options: once MAX_ACTIVATION_CODE_RECOVERIES
        // recovery mails were actually sent for this website, the flow points at customer support instead of the API.
        $recoveryCount = (int) SettingsManager::instance()->get_option(self::OPTION_ACTIVATION_CODE_RECOVERY_COUNT, 0);
        if ($recoveryCount >= self::MAX_ACTIVATION_CODE_RECOVERIES) {
            $this->log('Activation code recovery refused: local limit reached', 'WARNING', false, 'activation', [
                'reason'        => 'local_recovery_limit_reached',
                'recoveryCount' => $recoveryCount,
            ]);
            return $this->recoveryFailureResponse(
                __('The activation code has already been re-sent the maximum number of times for this website. Please contact customer support.', 'beyondseo'),
                'local_recovery_limit_reached'
            );
        }

        $this->enableApiErrorPropagation();
        try {
            $result = (new UserApiManager())->recoverActivationCode($email);
        } catch (Throwable $e) {
            $this->log('Activation code recovery failed', 'WARNING', false, 'activation', [
                'reason'        => 'exception',
                'httpCode'      => $e->getCode(),
                'remoteMessage' => $e->getMessage(),
            ]);
            if ($e instanceof HttpApiException && $e->getCode() >= 400) {
                /* translators: 1: HTTP status code, 2: error text returned by the rankingCoach service */
                $message = sprintf(__('The rankingCoach service rejected the request (HTTP %1$d): %2$s', 'beyondseo'), $e->getCode(), $e->getMessage());
                $code    = 'http_' . $e->getCode();
            } else {
                /* translators: %s: error text */
                $message = sprintf(__('Could not reach the rankingCoach service: %s', 'beyondseo'), $e->getMessage());
                $code    = 'transport_error';
            }
            return $this->recoveryFailureResponse($message, $code);
        } finally {
            $this->disableApiErrorPropagation();
        }

        if ($result && ($result->success ?? false) === true) {
            SettingsManager::instance()->update_option(self::OPTION_ACTIVATION_CODE_RECOVERY_COUNT, $recoveryCount + 1);
            return new WP_REST_Response(['success' => true], 200);
        }

        $reason        = (string) ($result->reason ?? '');
        $remoteMessage = (string) ($result->message ?? '');
        $this->log('Activation code recovery failed', 'WARNING', false, 'activation', [
            'reason'        => $reason,
            'remoteMessage' => $remoteMessage,
        ]);

        return $this->recoveryFailureResponse(
            $this->recoveryFailureMessage($reason, $remoteMessage),
            $reason !== '' ? $reason : 'unexpected_response',
            $remoteMessage
        );
    }

    /**
     * Builds the 422 failure response: translated `message` for the user, `details` (reason code plus the remote
     * error text when it adds information) for support.
     *
     * @param string $message
     * @param string $code
     * @param string $remoteMessage
     * @return WP_REST_Response
     */
    private function recoveryFailureResponse(string $message, string $code, string $remoteMessage = ''): WP_REST_Response
    {
        $details = $code;
        if ($remoteMessage !== '' && $remoteMessage !== $message) {
            $details .= ' - ' . $remoteMessage;
        }
        return new WP_REST_Response(['success' => false, 'message' => $message, 'details' => $details], 422);
    }

    /**
     * Maps the rankingCoach recovery `reason` code onto a translated, cause-specific message.
     * The other website's domain is never revealed for subscription_bound_to_other_website.
     * Unknown or missing reasons fall back to the remote message, then to a generic text.
     *
     * @param string $reason
     * @param string $remoteMessage
     * @return string
     */
    private function recoveryFailureMessage(string $reason, string $remoteMessage = ''): string
    {
        return match ($reason) {
            'account_not_found'                   => __('We could not find a rankingCoach account with this email address. Use the address your activation code was originally sent to.', 'beyondseo'),
            'no_wordpress_subscription'           => __('This account has no active WordPress subscription.', 'beyondseo'),
            'subscription_bound_to_other_website' => __('The WordPress subscription for this account is already linked to a different website.', 'beyondseo'),
            'recovery_limit_reached'              => __('The activation code for this subscription has already been re-sent the maximum number of times. Please contact customer support.', 'beyondseo'),
            'email_send_failed'                   => __('We could not send the email. Please try again later.', 'beyondseo'),
            ''                                    => $remoteMessage !== '' ? $remoteMessage : __('The rankingCoach service returned an unexpected response. Please try again later.', 'beyondseo'),
            default                               => $remoteMessage !== '' ? $remoteMessage : __('We could not send a recovery email. Please contact customer support.', 'beyondseo'),
        };
    }

    /** Enables API error propagation through filters. */
    private function enableApiErrorPropagation(): void
    {
        if ($this->apiErrorFilterEnabled) {
            return;
        }
        add_filter('rankingcoach_http_api_response_throw_exception', '__return_true', 10, 2);
        $this->apiErrorFilterEnabled = true;
    }

    /** Disables API error propagation. */
    private function disableApiErrorPropagation(): void
    {
        if (!$this->apiErrorFilterEnabled) {
            return;
        }
        remove_filter('rankingcoach_http_api_response_throw_exception', '__return_true', 10);
        $this->apiErrorFilterEnabled = false;
    }

    /** Forces HTTP API responses to throw exceptions for uniform handling. */
    public function forceHttpApiExceptions(bool $shouldThrow, array $errorDetails = []): bool
    {
        return true;
    }

    /** Redirects back to the activation page with an error message. */
    #[NoReturn]
    private function redirectToActivationWithMessage(string $message): void
    {
        $this->disableApiErrorPropagation();
        AdminManager::getInstance()->redirectPage($this->name, '&message=' . urlencode($message));
        exit;
    }

    /** Redirects to activation success state. */
    #[NoReturn]
    private function redirectToActivationSuccess(): void
    {
        $this->disableApiErrorPropagation();
        AdminManager::getInstance()->redirectPage($this->name, '&rc_activation_saved=1');
        exit;
    }

    /**
     * Handles form submission for the activation code.
     *
     */
    #[NoReturn]
    public function handleActivationCodeSubmission(): void
    {
        $this->checkUserPermissions();
        $this->enableApiErrorPropagation();

        $activationCode = WordpressHelpers::sanitize_input('POST', 'activation_code');
        if (empty($activationCode)) {
            $this->redirectToActivationWithMessage(__('Activation code is required.', 'beyondseo'));
        }

        $commOptIn = isset($_POST['comm_opt_in']) && $_POST['comm_opt_in'] === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in checkUserPermissions()
        SettingsManager::instance()->update_option('beyondseo_comm_opt_in', $commOptIn);

        try {
            [$success, $message, $result] = $this->processActivationCode($activationCode);
        } catch (Throwable $e) {
            $message = $e->getMessage() ?: __('Invalid response during account activation.', 'beyondseo');
            $this->redirectToActivationWithMessage($message);
        }

        if (!$success || !$result) {
            $this->redirectToActivationWithMessage($message ?: __('Invalid activation code.', 'beyondseo'));
        }

        // REF: token handling
        $refreshToken = $result->refreshToken ?? '';
        $accountId = $result->accountId ?? null;
        $setupSetting = $result->locationSetupSetting ?? null;
        $resellerAccount = $result->resellerAccount ?? true;

        add_option(BaseConstants::OPTION_IS_RESELLER_ACCOUNT, $resellerAccount ? 1 : 0);
        update_option(BaseConstants::OPTION_ACTIVATION_CODE, $activationCode);
        update_option(BaseConstants::OPTION_LOCATION_SETUP_SETTINGS, $setupSetting);

        if ($refreshToken === '') {
            $this->redirectToActivationWithMessage(__('Missing refresh token in activation response.', 'beyondseo'));
        }

        try {
            $isHandled = $this->saveRefreshTokenAndFetchData($refreshToken);
        } catch (Throwable $e) {
            TokensManager::instance()->deleteTokens();
            delete_option( BaseConstants::OPTION_ACTIVATION_CODE );
            if($e->getMessage() == 'Website cannot be changed') {
                $this->redirectToActivationWithMessage(__('This activation code is already linked to another website URL. Changing the website URL address is not allowed. Please use a different code or contact support.', 'beyondseo'));
            }
            $this->redirectToActivationWithMessage($e->getMessage() ?: __('Error fetching account data.', 'beyondseo'));
        }

        if (!$isHandled) {
            TokensManager::instance()->deleteTokens();
            delete_option( BaseConstants::OPTION_ACTIVATION_CODE );
            $this->redirectToActivationWithMessage(__('Activation succeeded but data could not be saved.', 'beyondseo'));
        }

        update_option(BaseConstants::OPTION_ACTIVATION_CODE, $activationCode);
        update_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, $accountId);

        // Mark activation as complete in flow state
        $store = new OptionStore();
        $store->updateFlowState(function($flowState) {
            $flowState->registered = true;
            $flowState->emailVerified = true;
            $flowState->activated = true;
            $flowState->meta['connectionMethod'] = 'activation';
            return $flowState;
        });

        try {
            (new ReviewState())->recordCustomerStarted();
        } catch (Throwable $e) {
        }

        $this->redirectToActivationSuccess();
    }

    /**
     * Displays only plugin-specific notices on the activation page.
     */
    public function handleActivationNotices(): void
    {
        $current = get_current_screen();
        if (!$current || !str_contains($current->id, 'rankingcoach-activation')) {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        $this->displayRankingCoachNotices();
    }

    /** Displays custom messages for activation form result. */
    public function displayRankingCoachNotices(): void
    {
        $formFieldSaved = WordpressHelpers::sanitize_input('GET', 'rc_form_field_saved');
        if (!empty($formFieldSaved)) {
            $status = (int)$formFieldSaved;
            $msg = $status > 0
                    ? __('Refresh token saved successfully.', 'beyondseo')
                    : __('Failed to save refresh token.', 'beyondseo');

            printf('<p style="text-align:center;color:#555;margin-bottom:30px;">%s</p>', esc_html($msg));
        }
    }

    /**
     * Validates and processes the activation code through the API.
     *
     * @param string $activationCode
     * @return array{0:bool,1:?string,2:?object}
     */
    private function processActivationCode(string $activationCode): array
    {
        try {
            $uam = new UserApiManager();
            $result = $uam->checkActivationCode($activationCode);
            if (!$result || !$result->success) {
                return [false, $result->message ?? __('Invalid activation code.', 'beyondseo'), null];
            }
            return [true, null, $result];
        } catch (Exception $e) {
            return [false, $e->getMessage(), null];
        }
    }

    /**
     * Saves the refresh token and fetches account details.
     *
     * @param string $refreshToken
     * @return bool
     * @throws ReflectionException
     * @throws Throwable
     * @throws HttpApiException
     */
    private function saveRefreshTokenAndFetchData(string $refreshToken): bool
    {
        $currentToken = get_option(TokensManager::REFRESH_TOKEN);
        $tokens = TokensManager::instance();

        if ($currentToken !== $refreshToken && $tokens->generateAndSaveAccessToken($refreshToken)) {
            $account = UserApiManager::getInstance(bearerToken: true)->fetchAndInsertAccountData(true);
            //do_action(Hooks::RANKINGCOACH_ACTION_COLLECT_DATA_FROM_ALL_AVAILABLE_COLLECTORS, 'activation');
            return $account !== false;
        }
        return false;
    }

    /**
     * Validates user permissions and nonce integrity.
     */
    public function checkUserPermissions(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'beyondseo'));
        }

        $nonce = WordpressHelpers::sanitize_input('POST', '_wpnonce');
        if (empty($nonce) || !wp_verify_nonce($nonce, self::SAVE_ACTIVATION_ACTION)) {
            wp_die(esc_html__('Nonce verification failed.', 'beyondseo'));
        }
    }


    private function maybeResumeOnboarding(): void
    {
        if (isset($_GET['bypass_flow'])
            || $this->getQueryParamInt('rc_activation_saved') > 0
            || $this->getQueryParamString('message') !== '') {
            return;
        }

        $this->redirectActivatedAccountToOnboarding();
    }

    /**
     * Handle access control before headers are sent.
     *
     * This method is designed to be called from the WordPress `load-{$page_hook}` action,
     * which fires BEFORE any output is sent. This allows us to perform redirects using
     * wp_safe_redirect() without output buffering.
     *
     * @return void
     */
    public function handleAccessControl(): void
    {
        // Mark that access control has been handled, so page_content() skips FlowGuard
        $this->accessControlHandled = true;

        $this->maybeResumeOnboarding();

        // Check channel enforcer first
        if (!isset($_GET['bypass_flow'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flow control flag
            $store = new OptionStore();
            $resolver = new ChannelResolver($store);
            $channel = $resolver->resolve();
            // ChannelEnforcer may exit internally if access is denied
            ChannelEnforcer::enforcePageAccess('activation', $channel);
        }

        if (!$this->flowGuardEnabled) {
            return;
        }

        // On successfully activate state, show a success message instead of redirecting away.
        $activationSaved = $this->getQueryParamInt('rc_activation_saved');
        if (!empty($activationSaved) && $activationSaved > 0) {
            return;
        }

        $message = $this->getQueryParamString('message');
        if (!empty($message)) {
            return;
        }

        try {
            $result = $this->evaluateFlow();
            $step   = $result['next_step'] ?? '';

            if ($step === 'activate') {
                return; // proceed to rendering
            }

            $destination = match ($step) {
                'email_validation', 'register', 'finalizing' => AdminManager::PAGE_REGISTRATION,
                'onboarding'                                  => AdminManager::PAGE_ONBOARDING,
                'done', 'main'                                => AdminManager::PAGE_MAIN,
                default                                       => AdminManager::PAGE_MAIN,
            };

            $redirectUrl = AdminManager::getPageUrl($destination);
            wp_safe_redirect($redirectUrl);
            exit;
        } catch (\Throwable $e) {
            // Fail closed: if flow evaluation fails, we do not block the activation page.
            return;
        }
    }

    /**
     * If enabled via $flowGuardEnabled, redirect according to flow decision when activation isn't the next step.
     * Kept separate to make it easy to toggle/remove without touching page_content.
     *
     * @param callable|null $failCallback
     * @return void
     */
    private function applyFlowGuard(?callable $failCallback = null): void
    {
        if (!$this->flowGuardEnabled) {
            return;
        }

        // On successfully activate state,. need to show a success message instead of redirecting away immediately. This is determined by the presence of rc_activation_saved=1 in query params, which is added on successful activation.
        $activationSaved = $this->getQueryParamInt('rc_activation_saved');
        if(!empty($activationSaved) && $activationSaved > 0) {
            return;
        }

        $message = $this->getQueryParamString('message');
        if(!empty($message)) {
            return;
        }

        try {
            $result = $this->evaluateFlow();
            $step   = $result['next_step'] ?? '';
            if ($step !== 'activate') {
                $this->redirectByFlowStep($step, $failCallback);
            }
        } catch (\Throwable $e) {
            // Fail closed: if flow evaluation fails, we do not block the activation page.
        }
    }

    /* ------------------------------------------------------------------------
     * Internal Helpers
     * --------------------------------------------------------------------- */

    /** Retrieves sanitized string parameter from query. */
    private function getQueryParamString(string $key): string
    {
        $value = WordpressHelpers::sanitize_input('GET', $key);
        return !empty($value) ? urldecode($value) : '';
    }

    /** Retrieves integer parameter safely from query. */
    private function getQueryParamInt(string $key): int
    {
        $val = WordpressHelpers::sanitize_input('GET', $key);
        return ($val !== false && $val !== null) ? (int)$val : 0;
    }

    /**
     * Handles redirection based on current flow step.
     *
     * @param string $step
     * @param callable|null $failCallback
     */
    private function redirectByFlowStep(string $step, ?callable $failCallback = null): void
    {
        if (!self::$managerInstance instanceof AdminManager) {
            return;
        }

        $redirects = [
            'email_validation' => 'registration',
            'register' => 'registration',
            'finalizing' => 'registration',
            'onboarding' => 'onboarding',
            'done' => 'main',
            'main' => 'main',
        ];

        $target = $redirects[$step] ?? 'main';
        self::$managerInstance->redirectPage($target);

        if (is_callable($failCallback)) {
            $failCallback();
        }

        exit;
    }
}
