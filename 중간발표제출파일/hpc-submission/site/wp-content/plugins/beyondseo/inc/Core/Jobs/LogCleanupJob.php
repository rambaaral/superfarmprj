<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use Throwable;


/**
 * Class LogCleanupJob
 * 
 * Handles automatic cleanup of old log files using WP_Cron.
 * This job runs daily to remove log files older than the configured retention period,
 * helping to manage disk space and maintain system performance.
 */
class LogCleanupJob extends WpCronJobClass
{
    use RcLoggerTrait;

    /** @var string The WP_Cron hook name for log cleanup */
    protected const ACTION_HOOK = 'rankingcoach_log_cleanup';

    /** @var string The settings option key that controls cleanup enablement */
    protected const ENABLE_SETTING_KEY = 'enable_log_cleanup';

    /** @var string The recurrence interval */
    protected const RECURRENCE = 'daily';

    /** @var int Number of days to keep log files (files older than this will be deleted) */
    protected const LOG_RETENTION_DAYS = 7;

    /** @var string Log context for log cleanup operations */
    protected const LOG_CONTEXT = 'log_cleanup';

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
     * Execute the log cleanup process.
     * This method is called by WP_Cron when the scheduled action runs.
     *
     * @param bool $forceExecute
     * @return void
     */
    public function execute(bool $forceExecute = false): void
    {
        try {
            // Validate that cleanup is still enabled before processing
            if (!$this->isJobEnabled()) {
                $this->log_json([
                    'operation_type' => 'log_cleanup',
                    'operation_status' => 'skipped_disabled',
                    'context_entity' => 'log_cleanup_job',
                    'context_type' => 'cleanup',
                    'message' => 'Log cleanup disabled in settings, cleaning up schedule',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);

                // Clean up the schedule since cleanup is now disabled
                $this->unscheduleJob();
                return;
            }

            // Execute file-based log cleanup using the trait method
            $deletedFilesCount = $this->deleteOldLogFiles(static::LOG_RETENTION_DAYS);
            
            // Calculate total deletions
            $totalDeletions = $deletedFilesCount;

            $this->log_json([
                'operation_type' => 'log_cleanup',
                'operation_status' => $totalDeletions > 0 ? 'completed_with_deletions' : 'completed_no_deletions',
                'context_entity' => 'log_cleanup_job',
                'context_type' => 'cleanup',
                'retention_days' => static::LOG_RETENTION_DAYS,
                'files_deleted' => $deletedFilesCount,
                'total_deletions' => $totalDeletions,
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);

        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => 'log_cleanup',
                'operation_status' => 'error',
                'context_entity' => 'log_cleanup_job',
                'context_type' => 'cleanup',
                'retention_days' => static::LOG_RETENTION_DAYS,
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
        }
    }

    /**
     * Override to provide default enabled state for log cleanup.
     * Log cleanup should be enabled by default unless explicitly disabled.
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
}
