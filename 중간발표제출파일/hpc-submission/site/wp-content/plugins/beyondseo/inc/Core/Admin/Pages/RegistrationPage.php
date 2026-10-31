<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Traits\ResponseErrorTrait;
use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\ChannelFlow\ChannelEnforcer;
use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\FlowState;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait;
use RankingCoach\Inc\Core\Api\Register\RegisterApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Review\ReviewState;
use RankingCoach\Inc\Traits\SingletonTrait;
use Throwable;
use WP_Error;
use WP_REST_Request;

class RegistrationPage extends AdminPage
{
    use FlowGuardTrait;
    use SingletonTrait;
    use ResponseErrorTrait;

    public string $name = 'registration';

    public static ?AdminManager $managerInstance = null;

    /** Feature flag: when true, RegistrationPage will use FlowManager to guard or render. */
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

        // Register registration routes (real endpoints)
        add_action('rest_api_init', [$this, 'registerRegistrationRoutes']);

        // Load React app for registration page
        add_action('current_screen', function($screen) {
            // Ensure the screen object is available
            if (!is_object($screen) || !isset($screen->id)) {
                return;
            }

            if(!in_array($screen->id, ALLOWED_RANKINGCOACH_PAGES)) {
                return;
            }

            ReactApp::get([
                'registration'
            ]);
        });

        if (WordpressHelpers::sanitize_input('GET', 'bypass_flow')) {
            $this->flowGuardEnabled = false;
        } else {
            $this->flowGuardEnabled = OptionStore::isFlowGuardActive();
        }
        parent::__construct();
    }

    /**
     * Retrieve the page name.
     *
     * @return string
     */
    public function page_name(): string
    {
        return $this->name;
    }

    /**
     * Render the Registration page content.
     *
     * @return void
     */
    public function page_content(): void
    {
        // If access control was already handled by the load-{$page_hook} hook,
        // skip the FlowGuard logic here (it runs before headers are sent in that pattern).
        if (!$this->accessControlHandled) {
            $this->redirectActivatedAccountToOnboarding();

            $store = new OptionStore();
            $resolver = new ChannelResolver($store);
            $channel = $resolver->resolve();
            if (!WordpressHelpers::sanitize_input('GET','bypass_flow')) {
                ChannelEnforcer::enforcePageAccess('registration', $channel);
            }

            // Optional flow guard (disabled by default; see $this->flowGuardEnabled)
            $this->applyFlowGuard();
        }

        $accountStatus = 'new_account';
        $inEmailValidation = false;

        // Load channel and flow state BEFORE enqueue to determine state
        $channelMeta = OptionStore::retrieveChannel();
        $flowState = OptionStore::retrieveFlowState();
        // Determine if we're in email validation state
        // This happens when user is registered but email is not yet verified
        $inEmailValidation = ($flowState->registered === true && $flowState->emailVerified === false);

        // Extract account status from flowState meta
        $accountStatus = $flowState->meta['accountStatus'] ?? $accountStatus;

        // Enqueue page-specific assets BEFORE FlowGuard (which may exit early)
        wp_enqueue_script(
            'rankingcoach-registration',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/registration.js',
            [],
            RANKINGCOACH_VERSION,
            true
        );

        // Localize runtime config for JS
        $nextStepUrl = AdminManager::getPageUrl(AdminManager::PAGE_ONBOARDING);
        wp_localize_script('rankingcoach-registration', 'rcRegistration', [
            'debug'                 => RANKINGCOACH_ENVIRONMENT !== RANKINGCOACH_PRODUCTION_ENVIRONMENT,
            'registerUrl'           => esc_url_raw( rest_url( RANKINGCOACH_REST_API_BASE . '/account/register' ) ),
            'finalizeRegisterUrl'   => esc_url_raw( rest_url( RANKINGCOACH_REST_API_BASE . '/account/finalizeRegister' ) ),
            'verificationStatusUrl' => esc_url_raw( rest_url( RANKINGCOACH_REST_API_BASE . '/account/verificationStatus' ) ),
            'flowGuardStateUrl'     => esc_url_raw( rest_url( RANKINGCOACH_REST_API_BASE . '/flowguard/state' ) ),
            'flowGuardResetUrl'     => esc_url_raw( rest_url( RANKINGCOACH_REST_API_BASE . '/flowguard/state/reset' ) ),
            'nonce'                 => wp_create_nonce('wp_rest'),
            'nextStepUrl'           => $nextStepUrl,
            'inEmailValidation'     => $inEmailValidation,
            'accountStatus'         => $accountStatus,
            // i18n strings used by registration.js
            'i18nVerificationExpired'         => __('Verification link expired. Resend the email and try again.', 'beyondseo'),
            'i18nVerificationStatusError'     => __("We couldn't check your email verification status. Please try again.", 'beyondseo'),
            'i18nFinalizeRegistrationFailed'  => __("We couldn't complete your registration. Please try again.", 'beyondseo'),
            'i18nVerificationInitiationFailed'=> __("We couldn't send the verification email. Please try again.", 'beyondseo'),
            'i18nRegistrationSetupError'      => __('Could not start registration. Please reload and try again.', 'beyondseo'),
            'i18nResendVerification'          => __('Resend verification email', 'beyondseo'),
            'i18nEmailInvalid'                => __('Please enter a valid email address.', 'beyondseo'),
            'i18nCountryRequired'             => __('Please select a country.', 'beyondseo'),
            'i18nConsentRequired'             => __('Please accept marketing communications to continue.', 'beyondseo'),
        ]);

        // Retrieve activation code from database
        $lastEmailForRegister = get_option(BaseConstants::OPTION_REGISTRATION_EMAIL_ADDRESS);

        // Fallback when guard is disabled: render external template
        include __DIR__ . '/views/registration-page-react.php';

        // Keep old PHP template for reference (commented out)
        // include __DIR__ . '/views/registration-page.php';
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

        // Partner-provisioned installs have no email registration flow at all:
        // send them straight to the activation-code page (runs before headers are sent).
        if (WordpressHelpers::isPartnerIntegration()
            && !WordpressHelpers::sanitize_input('GET', 'bypass_flow')) {
            wp_safe_redirect(AdminManager::getPageUrl(AdminManager::PAGE_ACTIVATION));
            exit;
        }

        // Check channel enforcer first
        if (!WordpressHelpers::sanitize_input('GET','bypass_flow')) {
            $store = new OptionStore();
            $resolver = new ChannelResolver($store);
            $channel = $resolver->resolve();
            // ChannelEnforcer may exit internally if access is denied
//            ChannelEnforcer::enforcePageAccess('registration', $channel);
        }

        if (!$this->flowGuardEnabled) {
            return;
        }

        try {
            $result = $this->evaluateFlow();
            $step   = $result['next_step'] ?? '';

            // Allowed on RegistrationPage: register, email_validation, finalizing
            if ($step === 'register' || $step === 'email_validation' || $step === 'finalizing') {
                return; // proceed to rendering
            }

            // Redirect mapping - use AdminManager::PAGE_* constants (full slugs)
            $destination = match ($step) {
                'activate'   => AdminManager::PAGE_REGISTRATION,
                'onboarding' => AdminManager::PAGE_ONBOARDING,
                'done'       => AdminManager::PAGE_MAIN,
                default      => AdminManager::PAGE_MAIN
            };

            // Since this runs before headers are sent (via load-{$page_hook}),
            // we can redirect directly without output buffering!
            $redirectUrl = AdminManager::getPageUrl($destination);
            wp_safe_redirect($redirectUrl);
            exit;
        } catch (Throwable $e) {
            // Fail-open: if evaluation fails, fall back to page default rendering (no redirect)
            return;
        }
    }

    /**
     * Guard Registration page using FlowManager mapping. Exits after render/redirect when enabled.
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
            $channel = $result['channel'] ?? '';

            // Allowed on RegistrationPage: register, email_validation
            if ($step === 'register' || $step === 'email_validation' || $step === 'finalizing') {
                // We're on the correct page, just return and let normal rendering continue
                // This allows WordPress to print enqueued scripts in footer
                return;
            }

            // Otherwise redirect by flow mapping
            $destination = match ($step) {
                'activate'   => AdminPage::PAGE_REGISTRATION,
                'onboarding' => AdminPage::PAGE_ONBOARDING,
                'done'       => AdminPage::PAGE_MAIN,
                default      => AdminPage::PAGE_MAIN
            };

            if (self::$managerInstance instanceof AdminManager) {
                self::$managerInstance->redirectPage($destination);
            }
            exit;
        } catch (Throwable $e) {
            // Fail-open: if evaluation fails, fall back to page default rendering (no redirect)
            return;
        }
    }

    /**
     * Register REST endpoints for registration flow.
     * - POST /beyondseo/v1/registration/challenge
     */
    public function registerRegistrationRoutes(): void
    {
        register_rest_route(
            RANKINGCOACH_REST_API_BASE,
            '/account/challenge',
            [
                'methods'  => 'POST',
                'callback' => [$this, 'handleRegistrationChallenge'],
                'permission_callback' => function (WP_REST_Request $request) {
                    // Capability check
                    if (!current_user_can('manage_options')) {
                        return new WP_Error('forbidden', __('You do not have sufficient permissions.', 'beyondseo'), ['status' => 403]);
                    }
                    // REST nonce check
                    if (!$this->verifyRestNonce($request)) {
                        return new WP_Error('invalid_nonce', __('Nonce verification failed.', 'beyondseo'), ['status' => 403]);
                    }
                    return true;
                },
                'args' => [
                    'email' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_string($param) && is_email($param);
                        },
                    ],
                ],
            ]
        );

        // Phase 1b: GET /account/verificationStatus
        register_rest_route(
            RANKINGCOACH_REST_API_BASE,
            '/account/verificationStatus',
            [
                'methods'  => 'GET',
                'callback' => [$this, 'handleVerificationStatus'],
                'permission_callback' => function (WP_REST_Request $request) {
                    if (!current_user_can('manage_options')) {
                        return new WP_Error('forbidden', __('You do not have sufficient permissions.', 'beyondseo'), ['status' => 403]);
                    }
                    if (!$this->verifyRestNonce($request)) {
                        return new WP_Error('invalid_nonce', __('Nonce verification failed.', 'beyondseo'), ['status' => 403]);
                    }
                    return true;
                },
                'args' => [
                    'pollToken' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            $s = trim((string)$param);
                            return $s !== '';
                        },
                    ],
                    'status' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            $s = trim((string)$param);
                            return $s !== '';
                        },
                    ],
                ],
            ]
        );

        register_rest_route(
            RANKINGCOACH_REST_API_BASE,
            '/account/register',
            [
                'methods'  => 'POST',
                'callback' => [$this, 'handleRegister'],
                'permission_callback' => function (WP_REST_Request $request) {
                    if (!current_user_can('manage_options')) {
                        return new WP_Error('forbidden', __('You do not have sufficient permissions.', 'beyondseo'), ['status' => 403]);
                    }
                    if (!$this->verifyRestNonce($request)) {
                        return new WP_Error('invalid_nonce', __('Nonce verification failed.', 'beyondseo'), ['status' => 403]);
                    }
                    return true;
                },
                'args' => [
                    'email' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_string($param) && is_email($param);
                        },
                    ],
                    'country' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            $p = strtoupper(trim((string)$param));
                            return (bool) preg_match('/^[A-Z]{2,3}$/', $p);
                        },
                        'sanitize_callback' => function ($param) {
                            return strtoupper(trim((string)$param));
                        },
                    ],
                    'type' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_string($param) && !empty($param);
                        },
                    ],
                    'marketingConsent' => [
                        'required' => true,
                        'default'  => false,
                        'sanitize_callback' => 'rest_sanitize_boolean',
                        'validate_callback' => function ($param) {
                            return is_bool($param) || $param === 0 || $param === 1 || $param === '0' || $param === '1' || $param === 'true' || $param === 'false';
                        },
                    ]
                ],
            ]
        );

        register_rest_route(
            RANKINGCOACH_REST_API_BASE,
            '/account/finalizeRegister',
            [
                'methods'  => 'POST',
                'callback' => [$this, 'handleFinalizeRegister'],
                'permission_callback' => function (WP_REST_Request $request) {
                    if (!current_user_can('manage_options')) {
                        return new WP_Error('forbidden', __('You do not have sufficient permissions.', 'beyondseo'), ['status' => 403]);
                    }
                    if (!$this->verifyRestNonce($request)) {
                        return new WP_Error('invalid_nonce', __('Nonce verification failed.', 'beyondseo'), ['status' => 403]);
                    }
                    return true;
                },
                'args' => [
                    'email' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_string($param) && is_email($param);
                        },
                    ],
                    'country' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            $p = strtoupper(trim((string)$param));
                            return (bool) preg_match('/^[A-Z]{2,3}$/', $p);
                        },
                        'sanitize_callback' => function ($param) {
                            return strtoupper(trim((string)$param));
                        },
                    ],
                    'type' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_string($param) && !empty($param);
                        },
                    ],
                    'pollToken' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            $s = trim((string)$param);
                            return $s !== '';
                        },
                    ],
                ],
            ]
        );

        // FlowGuard state endpoint
        register_rest_route(
            RANKINGCOACH_REST_API_BASE,
            '/flowguard/state',
            [
                'methods'  => 'GET',
                'callback' => [$this, 'handleFlowGuardState'],
                'permission_callback' => function (WP_REST_Request $request) {
                    if (!current_user_can('manage_options')) {
                        return new WP_Error('forbidden', __('You do not have sufficient permissions.', 'beyondseo'), ['status' => 403]);
                    }
                    if (!$this->verifyRestNonce($request)) {
                        return new WP_Error('invalid_nonce', __('Nonce verification failed.', 'beyondseo'), ['status' => 403]);
                    }
                    return true;
                },
            ]
        );

        register_rest_route(
            RANKINGCOACH_REST_API_BASE,
            '/flowguard/state/reset',
            [
                'methods'  => 'POST',
                'callback' => [$this, 'handleFlowGuardStateReset'],
                'permission_callback' => function (WP_REST_Request $request) {
                    if (!current_user_can('manage_options')) {
                        return new WP_Error('forbidden', __('You do not have sufficient permissions.', 'beyondseo'), ['status' => 403]);
                    }
                    if (!$this->verifyRestNonce($request)) {
                        return new WP_Error('invalid_nonce', __('Nonce verification failed.', 'beyondseo'), ['status' => 403]);
                    }
                    return true;
                },
            ]
        );
    }

    /**
     * Handle account challenge creation (starts secure registration flow).
     * Body: { email: string }
     * Returns: { challengeHash, challengeTimestamp, ttl }
     */
    public function handleRegistrationChallenge(WP_REST_Request $request): array|WP_Error
    {
        $email = (string) ($request->get_param('email') ?? '');
        $email = trim($email);

        if ($email === '' || !is_email($email)) {
            return new WP_Error('invalid_email', __('Please provide a valid email.', 'beyondseo'), ['status' => 400]);
        }

        try {
            $api = RegisterApiManager::getInstance();
            $result = $api->requestChallenge($email);

            // Expected $result keys: challengeHash, challengeTimestamp, ttl

            return $result;
        } catch (Throwable $e) {
            return new WP_Error('challenge_failed', __('Challenge creation failed.', 'beyondseo'), ['status' => 500]);
        }
    }

    /**
     * Poll email verification status (Phase 1b).
     *
     * Query: ?pollToken=string
     * Maps upstream statuses:
     *  - pending  => { ok:true, validated:false, status:'pending' }
     *  - verified => { ok:true, validated:true, status:'verified' }
     *  - expired  => 410 GONE error
     */
    public function handleVerificationStatus(WP_REST_Request $request): array|WP_Error
    {
        $pollToken = trim((string)$request->get_param('pollToken'));
        if ($pollToken === '') {
            return new WP_Error('poll_invalid_token', 'Invalid poll token', ['status' => 400]);
        }
        /** @var string $registeredAccountStatus the register account status: existing_account, new_account */
        $registeredAccountStatus = trim((string)$request->get_param('status'));

        try {
            $api    = RegisterApiManager::getInstance();
            $result = $api->pollEmailVerificationStatus($pollToken, $registeredAccountStatus);
        } catch (Throwable $e) {
            $result = false;
        }

        if (!is_array($result) || empty($result)) {
            return new WP_Error('poll_upstream_error', 'Failed to poll verification status', ['status' => 502]);
        }

        $account = $result['account'] ?? null;
        $pollToken = (string)($result['pollToken'] ?? $pollToken);
        $status = strtolower((string)($result['status'] ?? ''));
        $emailChecked = isset($result['emailChecked']) && (bool)$result['emailChecked'];
        if ($status === 'pending') {
            return [
                'success' => true,
                'validated' => false,
                'status' => 'pending',
                'emailChecked' => $emailChecked,
                'pollToken' => $pollToken,
                'account' => $account,
                'verificationStatusSetting' => $result['verificationStatusSetting'] ?? null,
            ];
        }

        if ($status === 'verified') {
            $existing = $this->readPollContext($pollToken) ?? [];
            $expiresAtIso = isset($existing['expiresAt']) ? (string)$existing['expiresAt'] : '';
            $remainingTtl = 3600;
            if ($expiresAtIso !== '') {
                $remaining = strtotime($expiresAtIso) - time();
                $remainingTtl = $remaining > 60 ? $remaining : 3600;
            }
            $existing['verifiedAt'] = time();
            $this->savePollContext($pollToken, $existing, $remainingTtl);

            // Mark email verification as complete in flow state
            $store = new OptionStore();
            $store->updateFlowState(function($flowState) {
                $flowState->registered = true;
                $flowState->emailVerified = true;
                return $flowState;
            });

            return [
                'success' => true,
                'validated' => true,
                'status' => 'verified',
                'emailChecked' => $emailChecked,
                'account' => $account,
	            'verificationStatusSetting' => $result['verificationStatusSetting'] ?? null,
            ];
        }

        if ($status === 'expired') {
            return new WP_Error('poll_expired', 'Verification expired', ['status' => 410]);
        }

        return new WP_Error('poll_upstream_error', 'Unexpected verification status', ['status' => 502]);
    }

    /**
     * Registration
     * @throws Throwable
     */
    public function handleRegister(WP_REST_Request $request): array|WP_Error
    {
        // Partner-provisioned installs must use the activation-code flow only
        if (WordpressHelpers::isPartnerIntegration()) {
            return new WP_Error(
                'registration_disabled',
                __('Email registration is not available for this installation. Please use your activation code.', 'beyondseo'),
                ['status' => 403]
            );
        }

        // INPUT PARAMS
        $email     = trim((string)$request->get_param('email'));
        $country   = strtoupper(trim((string)$request->get_param('country')));
        $typeRaw   = (string)($request->get_param('type') ?? '');
        $type      = $this->normalizeType($typeRaw);
        $consent   = (bool) ($request->get_param('marketingConsent') ?? false);

        if(!$consent) {
            return new WP_Error('consent_required', 'Consent is required to proceed.', ['status' => 400]);
        }

        // Validate basic fields
        $invalid = [];
        if ($email === '' || !is_email($email)) {
            $invalid['email'] = 'invalid';
        }
        if ($country === '' || !preg_match('/^[A-Z]{2,3}$/', $country)) {
            $invalid['country'] = 'invalid';
        }
        if ($type === '') {
            $invalid['type'] = 'invalid';
        }
        if (!empty($invalid)) {
            return new WP_Error('invalid_params', 'Invalid parameters', ['status' => 400, 'details' => $invalid]);
        }

        // Activate filter to throw exceptions instead of wp_die
        add_filter('rankingcoach_http_api_response_throw_exception', '__return_true', 10, 2);

        try {
            // Call registration API
            $registration = RegisterApiManager::getInstance()->register($email, $country, $type);
        } catch (Throwable $e) {
            $this->log(
                message: sprintf('Exception during registration: %s', $e->getMessage()),
                level: 'ERROR',
                verbose: false,
                contextKey: 'registration',
                additionalData: [
                    'exception_type' => get_class($e),
                    'exception_code' => $e->getCode(),
                    'exception_file' => basename($e->getFile()),
                    'exception_line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'email' => $email,
                    'country' => $country,
                    'type' => $type
                ],
                options: [
                    'context_id' => 'REG-' . uniqid(),
                ]
            );

            // Return generic error without sensitive data
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'details' => [
                    'exception_type' => get_class($e),
                ],
                'timestamp' => gmdate('c'),
            ];
        } finally {
            // Deactivate filter to not affect other requests
            remove_filter('rankingcoach_http_api_response_throw_exception', '__return_true');
        }

        // Validate upstream response and success flag
        if (!is_array($registration) || empty($registration)) {
            return new WP_Error('register_failed', 'Registration failed.', ['status' => 502]);
        }

        // Check if response contains an 'error' key and return custom array with error details
        if (isset($registration['error'])) {
            $this->log(
                message: 'API error response during registration',
                level: 'WARNING',
                verbose: false,
                contextKey: 'registration',
                additionalData: [
                    'error_response' => $registration['error'] ?? 'Unknown error',
                    'email' => $email,
                    'country' => $country
                ]
            );

            return [
                'success' => false,
                'error' => $registration['error'],
                'timestamp' => gmdate('c'),
            ];
        }

        // Enforce boolean semantics if 'success' is present
        if (array_key_exists('success', $registration)) {
            $successVal = $registration['success'];
            $isSuccess = ($successVal === true || $successVal === 1 || $successVal === '1' || $successVal === 'true');
            if (!$isSuccess) {
                $message = (string)($registration['message'] ?? $registration['error'] ?? 'Registration failed.');
                
                $this->log(
                    message: 'Registration API returned success=false',
                    level: 'WARNING',
                    verbose: false,
                    contextKey: 'registration',
                    additionalData: [
                        'api_message' => $message,
                        'email' => $email
                    ]
                );

                return [
                    'success' => false,
                    'message' => $message,
                ];
            }
        }

        // If upstream declares success, ensure required fields exist to proceed
        // Required for next step: pollToken and accountId
        $accountId = (isset($registration['accountId']) && is_scalar($registration['accountId'])) ? (int)$registration['accountId'] : null;
        $pollToken = isset($registration['pollToken']) ? trim((string)$registration['pollToken']) : '';
        if ($pollToken === '' || $accountId === null) {
            return new WP_Error('register_incomplete_payload', 'Incomplete registration payload received.', ['status' => 502, 'payload' => $registration]);
        }
        $ttlRaw = null;
        if (isset($registration['expiresIn']) && is_scalar($registration['expiresIn'])) {
            $ttlRaw = (int)$registration['expiresIn'];
        } elseif (isset($registration['ttl']) && is_scalar($registration['ttl'])) {
            $ttlRaw = (int)$registration['ttl'];
        }
        $ttl = ($ttlRaw !== null && $ttlRaw > 0) ? $ttlRaw : 60;
        // Normalize account status
        $accountStatusRaw = (string)($registration['accountStatus'] ?? $registration['status'] ?? '');
        $accountStatus = ($accountStatusRaw === 'existing_account') ? 'existing_account' : 'new_account';

        $issuedAt   = time();
        $expiresIn  = $ttl > 0 ? $ttl : 60;
        $expiresAt  = gmdate('c', $issuedAt + $expiresIn);

        $this->savePollContext($pollToken, [
            'email'           => $email,
            'country'         => $country,
            'type'            => $type,
            'consent'         => true,
            'issuedAt'        => $issuedAt,
            'expiresAt'       => $expiresAt,
            'status'          => $accountStatus,
        ], $expiresIn);

        // FlowGuard: move to email validation step for both scenarios
        $store = new OptionStore();
        $store->updateFlowState(function($flowState) use ($accountStatus) {
            $flowState->registered = true;
            $flowState->emailVerified = false;
            $flowState->meta['accountStatus'] = $accountStatus;
            return $flowState;
        });

        try {
            (new ReviewState())->recordCustomerStarted();
        } catch (Throwable $e) {
        }

        $nextStepUrl = AdminManager::getPageUrl(AdminManager::PAGE_ONBOARDING);

        return [
            'success'     => true,
            'pollToken'   => $pollToken,
            'accountId'   => $accountId,
            'expiresIn'   => $expiresIn,
            'expiresAt'   => $expiresAt,
            'status'      => $accountStatus,
            'nextStepUrl' => $nextStepUrl,
        ];
    }

    /**
     * Finalize registration
     * @throws Throwable
     */
    public function handleFinalizeRegister(WP_REST_Request $request): array|WP_Error
    {
        // Partner-provisioned installs must use the activation-code flow only
        if (WordpressHelpers::isPartnerIntegration()) {
            return new WP_Error(
                'registration_disabled',
                __('Email registration is not available for this installation. Please use your activation code.', 'beyondseo'),
                ['status' => 403]
            );
        }

        $email = trim((string) ($request->get_param('email') ?? ''));
        $country   = strtoupper(trim((string)$request->get_param('country')));
        $typeRaw   = (string)($request->get_param('type') ?? '');
        $type      = $this->normalizeType($typeRaw);
        $pollToken = trim((string)$request->get_param('pollToken'));

        // Enhanced email validation
        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address', ['status' => 400]);
        }
        if ($country === '' || !preg_match('/^[A-Z]{2,3}$/', $country)) {
            return new WP_Error('invalid_country', 'Invalid country', ['status' => 400]);
        }

        // Store country code for later use (e.g. upsell magic link)
        update_option('rankingcoach_country_code', $country);

        if ($type === '') {
            return new WP_Error('invalid_type', 'Invalid registration type', ['status' => 400]);
        }
        if ($pollToken === '') {
            return new WP_Error('poll_invalid_token', 'Invalid poll token', ['status' => 400]);
        }

        // Activate filter to throw exceptions instead of wp_die
        add_filter('rankingcoach_http_api_response_throw_exception', '__return_true', 10, 2);

        try {
            $registration = RegisterApiManager::getInstance()->finalizeRegister($email, $country, $type, $pollToken);
        } catch (Throwable $e) {
            // Return error without exposing sensitive data
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'details' => [
                    'exception_type' => get_class($e),
                ],
                'timestamp' => gmdate('c'),
            ];
        } finally {
            // Deactivate filter to not affect other requests
            remove_filter('rankingcoach_http_api_response_throw_exception', '__return_true');
        }

        if (!is_array($registration) || empty($registration)) {
            return new WP_Error('final_register_failed', 'Failed to finalize the registration.', ['status' => 502]);
        }

        // Validate tokens before saving
        $refreshToken = $registration['refreshToken'] ?? null;
        $accessToken = $registration['accessToken'] ?? null;
        $accountId = isset($registration['accountId']) && is_scalar($registration['accountId']) ? (int)$registration['accountId'] : null;

        if (!is_string($accessToken) || empty($accessToken)) {
            return new WP_Error('invalid_access_token', 'Invalid access token received from API', ['status' => 502]);
        }
        if (!is_string($refreshToken) || empty($refreshToken)) {
            return new WP_Error('invalid_refresh_token', 'Invalid refresh token received from API', ['status' => 502]);
        }
        if ($accountId === null) {
            return new WP_Error('invalid_account_id', 'Invalid account ID received from API', ['status' => 502]);
        }

        // Store old values for rollback capability
        $oldRefreshToken = get_option(BaseConstants::OPTION_REFRESH_TOKEN);
        $oldAccessToken = get_option(BaseConstants::OPTION_ACCESS_TOKEN);
        $oldAccountId = get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID);

        // Save new tokens
        update_option(BaseConstants::OPTION_REFRESH_TOKEN, $refreshToken);
        update_option(BaseConstants::OPTION_ACCESS_TOKEN, $accessToken);
        update_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, $accountId);

        try {
            // Fetch and store account data locally
            $instance = UserApiManager::getInstance();
            $instance->addDefaultHeader('Authorization', 'Bearer ' . $accessToken);
            $accountData = $instance->fetchAndInsertAccountData(true, 'registration');
        } catch (Throwable $e) {
            // ROLLBACK: Restore old values on failure
            if ($oldRefreshToken !== false) {
                update_option(BaseConstants::OPTION_REFRESH_TOKEN, $oldRefreshToken);
            } else {
                delete_option(BaseConstants::OPTION_REFRESH_TOKEN);
            }
            if ($oldAccessToken !== false) {
                update_option(BaseConstants::OPTION_ACCESS_TOKEN, $oldAccessToken);
            } else {
                delete_option(BaseConstants::OPTION_ACCESS_TOKEN);
            }
            if ($oldAccountId !== false) {
                update_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, $oldAccountId);
            } else {
                delete_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID);
            }

            return new WP_Error('final_register_failed', 'Failed to finalize the registration.', ['status' => 500]);
        }

        // Mark activation as complete in flow state
        $store = new OptionStore();
        $store->updateFlowState(function($flowState) {
            $flowState->registered = true;
            $flowState->emailVerified = true;
            $flowState->activated = true;
            $flowState->meta['connectionMethod'] = 'email';
            return $flowState;
        });

        try {
            $reviewSuccess = !isset($registration['error'])
                && (!array_key_exists('success', $registration)
                    || in_array($registration['success'], [true, 1, '1', 'true'], true));
            if ($reviewSuccess && $accountData !== false) {
                (new ReviewState())->recordCustomerStarted();
            }
        } catch (Throwable $e) {
        }

        $nextStepUrl = AdminManager::getPageUrl(AdminManager::PAGE_ONBOARDING);

        return [
            'success'     => true,
            'status'      => 'completed',
            'nextStepUrl' => $nextStepUrl,
            'accessToken' => $accessToken,
            'accountData' => $accountData,
        ];
    }

    /**
     * Verify the REST nonce from headers.
     */
    private function verifyRestNonce(WP_REST_Request $request): bool
    {
        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce === '') {
            return false;
        }
        return (bool) wp_verify_nonce($nonce, 'wp_rest');
    }

    /**
     * Normalize and validate registration type.
     * Allowed: ionos | extendify | direct
     */
    private function normalizeType(string $type): string
    {
        $t = strtolower(trim($type));
        $allowed = ['ionos','extendify','direct'];
        return in_array($t, $allowed, true) ? $t : '';
    }

    /**
     * Build a unique transient key for a given pollToken tied to this installation/site.
     */
    private function getTransientKeyForPollToken(string $pollToken): string
    {
        $installationId = (string) get_option(BaseConstants::OPTION_INSTALLATION_ID, '');
        if ($installationId === '') {
            $installationId = md5((string) home_url());
        }
        $hash = md5($installationId . '|' . $pollToken);
        return 'rc_reg_poll_' . $hash;
    }

    /**
     * Save context for a poll token in a transient.
     *
     * @param string $pollToken
     * @param array<string,mixed> $ctx
     * @param int $ttlSeconds
     */
    private function savePollContext(string $pollToken, array $ctx, int $ttlSeconds): void
    {
        $key = $this->getTransientKeyForPollToken($pollToken);
        set_transient($key, $ctx, $ttlSeconds);
    }

    /**
     * Read context for a poll token from transient storage.
     *
     * @param string $pollToken
     * @return array<string,mixed>|null
     */
    private function readPollContext(string $pollToken): ?array
    {
        $key = $this->getTransientKeyForPollToken($pollToken);
        $val = get_transient($key);
        return is_array($val) ? $val : null;
    }

    /**
     * Handle FlowGuard state fetch request.
     * Returns current channel and flow state for panel updates.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function handleFlowGuardState(WP_REST_Request $request): array|WP_Error
    {
        if(!$this->flowGuardEnabled) {
            return new WP_Error('flowguard_disabled', 'FlowGuard is disabled', ['status' => 503]);
        }

        try {
            $channelMeta = OptionStore::retrieveChannel();
            $flowState = OptionStore::retrieveFlowState();

            $channel = $channelMeta['channel'] ?? 'direct';
            $isRegistered = isset($flowState->registered) ? (bool)$flowState->registered : false;
            $isEmailVerified = isset($flowState->emailVerified) ? (bool)$flowState->emailVerified : false;
            $isActivated = isset($flowState->activated) ? (bool)$flowState->activated : false;
            $isOnboarded = isset($flowState->onboarded) ? (bool)$flowState->onboarded : false;

            // Calculate progress
            $progress = 0;
            if ($channel === 'direct') {
                if ($isOnboarded) {
                    $progress = 100;
                } elseif ($isActivated) {
                    $progress = 75;
                } elseif ($isEmailVerified) {
                    $progress = 50;
                } elseif ($isRegistered) {
                    $progress = 25;
                }
            } else {
                if ($isOnboarded) {
                    $progress = 100;
                } elseif ($isActivated) {
                    $progress = 66;
                } elseif ($isRegistered) {
                    $progress = 33;
                }
            }

            return [
                'ok' => true,
                'channel' => $channel,
                'registered' => $isRegistered,
                'emailVerified' => $isEmailVerified,
                'activated' => $isActivated,
                'onboarded' => $isOnboarded,
                'progress' => $progress,
                'meta' => isset($flowState->meta) ? (array)$flowState->meta : [],
            ];
        } catch (Throwable $e) {
            return new WP_Error('flowguard_state_error', 'Failed to fetch flow state', ['status' => 500]);
        }
    }

    /**
     * Handle FlowGuard state reset request.
     * Resets the flow state to initial values.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function handleFlowGuardStateReset(WP_REST_Request $request): array|WP_Error
    {
        if(!$this->flowGuardEnabled) {
            return new WP_Error('flowguard_disabled', 'FlowGuard is disabled', ['status' => 503]);
        }

        try {
            $store = new OptionStore();
            $store->updateFlowState(function (FlowState $flowState) {
                $flowState->registered = false;
                $flowState->emailVerified = false;
                $flowState->activated = false;
                $flowState->onboarded = false;
                return $flowState;
            });

            $channelMeta = OptionStore::retrieveChannel();
            $channel = $channelMeta['channel'] ?? 'direct';
            $flowState = OptionStore::retrieveFlowState();

            return [
                'ok' => true,
                'channel' => $channel,
                'registered' => isset($flowState->registered) && (bool)$flowState->registered,
                'emailVerified' => isset($flowState->emailVerified) && (bool)$flowState->emailVerified,
                'activated' => isset($flowState->activated) && (bool)$flowState->activated,
                'onboarded' => isset($flowState->onboarded) && (bool)$flowState->onboarded,
                'progress' => 0,
                'meta' => [],
            ];
        } catch (Throwable $e) {
            return new WP_Error('flowguard_reset_error', 'Failed to reset flow state', ['status' => 500]);
        }
    }

    /**
     * Mock email validation polling handler.
     * - On first GET: store start timestamp in a transient and return validated=false
     * - After ~5 seconds: return validated=true
     */
    public function handleMockEmailStatus(WP_REST_Request $request): array
    {
        $userId = get_current_user_id();
        if (!$userId) {
            return ['validated' => false];
        }

        $key = 'rc_email_validation_' . $userId;
        $start = get_transient($key);

        if (!$start) {
            // Start a 5s window; transient TTL 60s for safety
            set_transient($key, time(), 60);
            return ['validated' => false];
        }

        $elapsed = time() - (int)$start;
        if ($elapsed >= 15) {
            return ['validated' => true];
        }

        return ['validated' => false];
    }
}
