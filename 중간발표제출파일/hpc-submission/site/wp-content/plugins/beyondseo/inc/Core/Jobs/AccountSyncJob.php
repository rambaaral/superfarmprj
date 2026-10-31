<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;
use Throwable;

/**
 * Class AccountSyncJob
 * 
 * Handles automatic synchronization of customer account data using WP_Cron.
 * This job runs periodically to check and update subscription data from the API,
 * ensuring the local data stays in sync with the remote account information.
 * When subscription data is successfully updated, it resets upsell retry mechanisms.
 */
class AccountSyncJob extends WpCronJobClass
{
    use RcLoggerTrait;

    /** @var string The WP_Cron hook name for account sync */
    protected const ACTION_HOOK = 'rankingcoach_account_sync';

    /** @var string The settings option key that controls sync enablement */
    protected const ENABLE_SETTING_KEY = 'enable_account_sync';

    /** @var string The recurrence interval */
    protected const RECURRENCE = 'twicedaily';

    /** @var string Log context for account sync operations */
    protected const LOG_CONTEXT = 'account_sync';

    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /**
     * Private constructor to enforce singleton pattern.
     */
    private function __construct()
    {
        parent::__construct();
    }

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Execute the account synchronization process.
     * This method is called by WP_Cron when the scheduled action runs.
     *
     * @param bool $forceExecute
     * @return void
     */
    public function execute(bool $forceExecute = false): void
    {
        try {
            // Validate that sync is still enabled before processing
            if (!$forceExecute && !$this->isJobEnabled()) {
                $this->log_json([
                    'operation_type' => 'account_sync',
                    'operation_status' => 'skipped_disabled',
                    'context_entity' => 'account_sync_job',
                    'context_type' => 'sync',
                    'message' => 'Account sync disabled in settings, cleaning up schedule',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);

                // Clean up the schedule since sync is now disabled
                $this->unscheduleJob();
                return;
            }

            // Prevent overlapping runs (e.g. duplicate cron triggers or API latency)
            if (!$this->acquireLock()) {
                $this->log_json([
                    'operation_type' => 'account_sync',
                    'operation_status' => 'skipped_locked',
                    'context_entity' => 'account_sync_job',
                    'context_type' => 'sync',
                    'message' => 'Another account sync run is already in progress, skipping',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
                return;
            }

            try {
                $currentSubscription = get_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION, null);

                // Get UserApiManager instance
                $uam = UserApiManager::getInstance(bearerToken: true);

                // Fetch account data from API
                $accountDetails = $uam->fetchAndUpdateAccountDetails();

                if (!$accountDetails) {
                    $this->log_json([
                        'operation_type' => 'account_sync',
                        'operation_status' => 'failed_update',
                        'context_entity' => 'account_sync_job',
                        'context_type' => 'sync',
                        'message' => 'Failed to update subscription data',
                        'timestamp' => current_time('mysql')
                    ], static::LOG_CONTEXT);
                    return;
                }


                // Check if subscription has changed
                $newSubscription = $accountDetails->subscription ?? null;
                $subscriptionChanged = ($currentSubscription !== $newSubscription);

                // Reset upsell retry mechanisms after successful sync
                $this->resetUpsellRetryMechanisms();

                $this->log_json([
                    'operation_type' => 'account_sync',
                    'operation_status' => 'completed_successfully',
                    'context_entity' => 'account_sync_job',
                    'context_type' => 'sync',
                    'subscription_changed' => $subscriptionChanged,
                    'old_subscription' => $currentSubscription,
                    'new_subscription' => $newSubscription,
                    'upsell_retry_reset' => true,
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
            } finally {
                $this->releaseLock();
            }

        } catch (HttpApiException $e) {
            $this->log_json([
                'operation_type' => 'account_sync',
                'operation_status' => 'api_error',
                'context_entity' => 'account_sync_job',
                'context_type' => 'sync',
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
        } catch (ReflectionException $e) {
            $this->log_json([
                'operation_type' => 'account_sync',
                'operation_status' => 'reflection_error',
                'context_entity' => 'account_sync_job',
                'context_type' => 'sync',
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => 'account_sync',
                'operation_status' => 'error',
                'context_entity' => 'account_sync_job',
                'context_type' => 'sync',
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
        }
    }

    /**
     * Reset upsell retry mechanisms after successful account sync.
     * This prevents unnecessary retries when account data is already up to date.
     *
     * @return void
     */
    private function resetUpsellRetryMechanisms(): void
    {
        try {
            // Reset upsell force check flag
            update_option(BaseConstants::OPTION_UPSELL_FORCE_CHECK, false, true);
            
            // Reset retry count
            update_option(BaseConstants::OPTION_UPSELL_RETRY_COUNT, 0);
            
            // Update last check timestamp to current time
            update_option(BaseConstants::OPTION_UPSELL_LAST_CHECK_TIMESTAMP, time());

            $this->log_json([
                'operation_type' => 'upsell_retry_reset',
                'operation_status' => 'completed',
                'context_entity' => 'account_sync_job',
                'context_type' => 'cleanup',
                'message' => 'Successfully reset upsell retry mechanisms',
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);

        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => 'upsell_retry_reset',
                'operation_status' => 'error',
                'context_entity' => 'account_sync_job',
                'context_type' => 'cleanup',
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
        }
    }

    /**
     * Override to provide default enabled state for account sync.
     * Account sync should be enabled by default unless explicitly disabled.
     *
     * @return bool
     */
    public function isJobEnabled(): bool
    {
        if (empty(static::ENABLE_SETTING_KEY)) {
            return true; // Default to enabled if no setting key defined
        }
        
        // Default to true (enabled) if setting doesn't exist
        return (bool)$this->settingsManager->get_option(static::ENABLE_SETTING_KEY, true);
    }

    /**
     * Force immediate account sync execution.
     * Used for upsell scenarios where immediate sync is needed.
     *
     * @return bool True if sync was successful, false otherwise
     */
    public function forceSync(): bool
    {
        try {
            $this->execute(true);
            return true;
        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => 'account_sync_force',
                'operation_status' => 'error',
                'context_entity' => 'account_sync_job',
                'context_type' => 'force_sync',
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
            return false;
        }
    }
}
