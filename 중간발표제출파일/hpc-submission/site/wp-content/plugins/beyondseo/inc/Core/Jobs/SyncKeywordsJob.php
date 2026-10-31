<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Api\Content\ContentApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use Throwable;

/**
 * Class SyncKeywordsJob
 * 
 * Handles keyword synchronization with RankingCoach platform using WP_Cron.
 * This job manages the scheduling, execution, and cleanup of keyword sync operations
 * based on plugin settings and onboarding status.
 */
class SyncKeywordsJob extends WpCronJobClass
{
    /** @var string The WP_Cron hook name for keyword synchronization */
    protected const ACTION_HOOK = 'rankingcoach_keywords_synchronization';

    /** @var string The settings option key that controls sync enablement */
    protected const ENABLE_SETTING_KEY = 'allow_sync_keywords_to_rankingcoach';

    /** @var string The recurrence interval */
    protected const RECURRENCE = 'twicedaily';

    /** @var string Log context for keyword sync operations */
    protected const LOG_CONTEXT = 'keywords_sync';

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
     * Execute the keyword synchronization process.
     * This method is called by WP_Cron when the scheduled action runs.
     *
     * @param bool $forceExecute
     * @return void
     */
    public function execute(bool $forceExecute = false): void
    {
        try {
            // Validate that synchronization is still enabled before processing
            if (!$this->isJobEnabled()) {
                $this->log_json([
                    'operation_type' => 'keywords_synchronization',
                    'operation_status' => 'skipped_disabled',
                    'context_entity' => 'sync_keywords_job',
                    'context_type' => 'synchronization',
                    'message' => 'Synchronization disabled in settings, cleaning up schedule',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);

                // Clean up the schedule since synchronization is now disabled
                $this->unscheduleJob();
                return;
            }

            // Prevent overlapping runs (e.g. duplicate cron triggers or API latency)
            if (!$this->acquireLock()) {
                $this->log_json([
                    'operation_type' => 'keywords_synchronization',
                    'operation_status' => 'skipped_locked',
                    'context_entity' => 'sync_keywords_job',
                    'context_type' => 'synchronization',
                    'message' => 'Another synchronization run is already in progress, skipping',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
                return;
            }

            try {
                // Execute the actual synchronization
                $result = ContentApiManager::handleKeywordsSynchronization();

                $this->log_json([
                    'operation_type' => 'keywords_synchronization',
                    'operation_status' => $result ? 'completed_successfully' : 'failed',
                    'context_entity' => 'sync_keywords_job',
                    'context_type' => 'synchronization',
                    'result' => $result,
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
            } finally {
                $this->releaseLock();
            }

        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => 'keywords_synchronization',
                'operation_status' => 'error',
                'context_entity' => 'sync_keywords_job',
                'context_type' => 'synchronization',
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
        }
    }

    /**
     * Check additional conditions for keyword sync scheduling.
     * Requires onboarding to be completed.
     *
     * @return bool
     */
    protected function areAdditionalConditionsMet(): bool
    {
        return WordpressHelpers::isOnboardingCompleted();
    }
}