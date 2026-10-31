<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait;
use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use RankingCoach\Inc\Core\Helpers\JavaScriptHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Traits\SingletonTrait;
use ReflectionException;
use Throwable;

/**
 * Represents a page in the onboarding process.
 * @method OnboardingPage getInstance(): static
 */
class OnboardingPage extends AdminPage {

    use FlowGuardTrait;
    use SingletonTrait;

    public string $name = 'onboarding';

 public static AdminManager|null $managerInstance = null;

 /** Feature flag: when true, OnboardingPage will use FlowManager to guard access (next_step must be 'onboarding'). */
    private bool $flowGuardEnabled = false;

    /**
     * Flag to track if access control was already handled by the load-{$page_hook} hook.
     * When true, page_content() will skip the FlowGuard logic since it was already processed.
     *
     * @var bool
     */
    private bool $accessControlHandled = false;

    /**
     * OnboardingPage constructor.
     * Initializes the OnboardingPage instance and sets up the necessary hooks.
     */
	public function __construct() {
		add_action('current_screen', function($screen) {
			// Ensure the screen object is available
			if (!is_object($screen) || !isset($screen->id)) {
				return;
			}

			if ($screen->base === 'admin_page_rankingcoach-onboarding') {
				ReactApp::get([
					'onboarding'
				]);
			}
		});
         $this->flowGuardEnabled = OptionStore::isFlowGuardActive();
         parent::__construct();
	}

	/**
	 * @return string
	 */
	public function page_name(): string
	{
		return $this->name;
	}

    private function canRevisitCompletedOnboarding(): bool
    {
        return current_user_can('manage_options')
            && WordpressHelpers::isActivationCompleted()
            && WordpressHelpers::sanitize_input('GET', 'revisitOnboarding') === '1';
    }

    /**
     * Handles the generation or processing of page content within the application.
     *
     * @return void
     * @throws HttpApiException
     * @throws ReflectionException
     */
    public function page_content(): void
    {
        // Retrieve channel metadata and flow state for FlowGuard components
        $channelMeta = OptionStore::retrieveChannel();
        $flowState = OptionStore::retrieveFlowState();

        // If access control was already handled by the load-{$page_hook} hook,
        // skip the FlowGuard logic here (it runs before headers are sent in that pattern).
        if (!$this->accessControlHandled) {
            // Optional flow guard (disabled by default; see $this->flowGuardEnabled)
            $this->applyFlowGuard();

            try {
                /** @var TokensManager $tokensManager */
                $tokensManager = TokensManager::getInstance();
                $accessToken = $tokensManager->getAccessToken(static::class);
                if (TokensManager::validateToken($accessToken) !== true) {
                    throw new \RuntimeException('Invalid access token.');
                }
            } catch (Throwable $e) {
                // Use ChannelResolver for consistent channel detection
                $store = new OptionStore();
                $resolver = new ChannelResolver($store);
                $channel = $resolver->resolve();

                // Reset flow state since token is invalid
                $store->updateFlowState(function($flowState) {
                    $flowState->registered = false;
                    $flowState->emailVerified = false;
                    return $flowState;
                });

                $nextStepUrl = AdminManager::getPageUrl(AdminManager::PAGE_REGISTRATION);
                wp_safe_redirect($nextStepUrl);
                exit;
            }
        }

        include __DIR__ . '/views/onboarding-page.php';

        // Add login session expiration handler script
        // This script will handle the login modal state and refresh the page when the modal is closed.
        // Is not a mistake to add this script here.
        // The behaviour is happening on the login modal, which is opened when the user session expires.
        JavaScriptHelper::enqueueLoginSessionExpirationScript();
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

        // First, check token validity - redirect if invalid
        try {
            /** @var TokensManager $tokensManager */
            $tokensManager = TokensManager::getInstance();
            $accessToken = $tokensManager->getAccessToken(static::class);
            if (TokensManager::validateToken($accessToken) !== true) {
                throw new \RuntimeException('Invalid access token.');
            }
        } catch (Throwable $e) {
            // Use ChannelResolver for consistent channel detection
            $store = new OptionStore();
            $resolver = new ChannelResolver($store);
            $channel = $resolver->resolve();

            // Reset flow state since token is invalid
            $store->updateFlowState(function($flowState) {
                $flowState->registered = false;
                $flowState->emailVerified = false;
                return $flowState;
            });

            $redirectUrl = AdminManager::getPageUrl(AdminManager::PAGE_REGISTRATION);
            wp_safe_redirect($redirectUrl);
            exit;
        }

        if (!$this->flowGuardEnabled) {
            return;
        }

        try {
            $result = $this->evaluateFlow();
            $step   = $result['next_step'] ?? '';

            if ($step === 'onboarding' || $this->canResumeOnboarding() || ($step === 'done' && $this->canRevisitCompletedOnboarding())) {
                return; // proceed to rendering
            }

            // Redirect mapping - use AdminManager::PAGE_* constants (full slugs)
            $destination = match ($step) {
                'register', 'email_validation', 'finalizing', 'activate' => AdminManager::PAGE_REGISTRATION,
                'done'                                        => AdminManager::PAGE_MAIN,
                default                                       => AdminManager::PAGE_MAIN,
            };

            // Since this runs before headers are sent (via load-{$page_hook}),
            // we can redirect directly without output buffering!
            $redirectUrl = AdminManager::getPageUrl($destination);
            wp_safe_redirect($redirectUrl);
            exit;
        } catch (\Throwable $e) {
            // Fail-open: if evaluation fails, let the page render
            return;
        }
    }

    /**
     * If enabled via $flowGuardEnabled, redirect according to flow decision when onboarding isn't the next step.
     * Kept separate to make it easy to toggle/remove without touching page_content.
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

            if ($step === 'onboarding' || $this->canResumeOnboarding() || ($step === 'done' && $this->canRevisitCompletedOnboarding())) {
                return; // allowed, continue rendering page
            }

            // Redirect mapping consistent with existing behavior
            $destination = match ($step) {
                'register', 'email_validation', 'finalizing', 'activate' => AdminPage::PAGE_REGISTRATION,
                'done'                                        => AdminPage::PAGE_MAIN,
                default                                       => AdminPage::PAGE_MAIN,
            };
            if (self::$managerInstance instanceof AdminManager) {
                self::$managerInstance->redirectPage($destination);
            }

            exit;
        } catch (\Throwable $e) {
            // Fail-open: if evaluation fails, let the page render
            return;
        }
    }
 }
