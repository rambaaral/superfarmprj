<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Modules\ModuleManager;
use Throwable;

/**
 * Class BrokenLinkCheckerJob
 * 
 * Handles scheduled broken link checking across all posts and pages using WP_Cron.
 * This job manages the scheduling, execution, and cleanup of link status verification operations
 * based on plugin settings and module availability.
 * 
 * The job scans all links stored in the database and updates their status (active/broken)
 * by performing HTTP requests to verify link accessibility.
 */
class BrokenLinkCheckerJob extends WpCronJobClass
{
    /** @var string The WP_Cron hook name for broken link checking */
    protected const ACTION_HOOK = 'rankingcoach_broken_link_checker_scan';

    /** @var string The settings option key that controls link checking enablement */
    protected const ENABLE_SETTING_KEY = 'enable_broken_link_checker_job';

    /** @var string The recurrence interval */
    protected const RECURRENCE = 'daily';

    /** @var string Log context for broken link checking operations */
    protected const LOG_CONTEXT = 'broken_link_checker_job';

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
     * Execute the broken link checking process.
     * This method is called by WP_Cron when the scheduled action runs.
     *
     * Performs comprehensive link status verification across all stored links,
     * updating their status based on HTTP response codes and accessibility.
     *
     * @param bool $forceExecute
     * @return void
     */
    public function execute(bool $forceExecute = false): void
    {
        try {
            // Validate that link checking is still enabled before processing
            if (!$this->isJobEnabled()) {
                $this->log_json([
                    'operation_type' => 'broken_link_checking',
                    'operation_status' => 'skipped_disabled',
                    'context_entity' => 'broken_link_checker_job',
                    'context_type' => 'link_verification',
                    'message' => 'Broken link checking disabled in settings, cleaning up schedule',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);

                // Clean up the schedule since link checking is now disabled
                $this->unscheduleJob();
                return;
            }

            // Verify that required modules are available
            $moduleManager = ModuleManager::instance();
            $brokenLinkChecker = $moduleManager->brokenLinkChecker();
            
            if (!$brokenLinkChecker || !$brokenLinkChecker->isActive()) {
                $this->log_json([
                    'operation_type' => 'broken_link_checking',
                    'operation_status' => 'skipped_module_unavailable',
                    'context_entity' => 'broken_link_checker_job',
                    'context_type' => 'link_verification',
                    'message' => 'BrokenLinkChecker module not available or inactive',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
                return;
            }

            // Prevent overlapping scans (e.g. duplicate cron triggers)
            if (!$this->acquireLock()) {
                $this->log_json([
                    'operation_type' => 'broken_link_checking',
                    'operation_status' => 'skipped_locked',
                    'context_entity' => 'broken_link_checker_job',
                    'context_type' => 'link_verification',
                    'message' => 'Another link checking run is already in progress, skipping',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
                return;
            }

            try {
                // Record job execution start
                $startTime = microtime(true);

                $this->log_json([
                    'operation_type' => 'broken_link_checking',
                    'operation_status' => 'started',
                    'context_entity' => 'broken_link_checker_job',
                    'context_type' => 'link_verification',
                    'message' => 'Starting comprehensive link status verification',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);

                // Execute the actual link checking process
                $brokenLinkChecker->checkAllLinks();

                // Calculate execution time
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                // Get post-execution statistics
                $stats = $this->getLinkCheckingStatistics();

                $this->log_json([
                    'operation_type' => 'broken_link_checking',
                    'operation_status' => 'completed_successfully',
                    'context_entity' => 'broken_link_checker_job',
                    'context_type' => 'link_verification',
                    'execution_time_ms' => $executionTime,
                    'statistics' => $stats,
                    'message' => 'Link status verification completed successfully',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
            } finally {
                $this->releaseLock();
            }

        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => 'broken_link_checking',
                'operation_status' => 'error',
                'context_entity' => 'broken_link_checker_job',
                'context_type' => 'link_verification',
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
        }
    }

    /**
     * Check additional conditions for broken link checking scheduling.
     * Requires onboarding to be completed and BrokenLinkChecker module to be active.
     *
     * @return bool
     * @throws Exception
     */
    protected function areAdditionalConditionsMet(): bool
    {
        // Ensure onboarding is completed
        if (!WordpressHelpers::isOnboardingCompleted()) {
            return false;
        }

        // Verify that BrokenLinkChecker module is available and active
        $moduleManager = ModuleManager::instance();
        $brokenLinkChecker = $moduleManager->brokenLinkChecker();
        
        return $brokenLinkChecker && $brokenLinkChecker->isActive();
    }

    /**
     * Get comprehensive statistics about link checking status.
     * 
     * @return array Statistics including total links, broken links, and last scan info
     */
    private function getLinkCheckingStatistics(): array
    {
        $dbManager = DatabaseManager::getInstance();
        
        try {
            $moduleManager = ModuleManager::instance();
            $brokenLinkChecker = $moduleManager->brokenLinkChecker();
            
            if (!$brokenLinkChecker) {
                return ['error' => 'BrokenLinkChecker module not available'];
            }
            
            $tableName = $brokenLinkChecker->getTableName();
            
            // Get comprehensive link statistics
            $stats = $dbManager->db()->queryRaw(
                /** @lang=MySQL */"SELECT 
                    COUNT(*) as total_links,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_links,
                    SUM(CASE WHEN status = 'broken' THEN 1 ELSE 0 END) as broken_links,
                    SUM(CASE WHEN status = 'unscanned' THEN 1 ELSE 0 END) as unscanned_links,
                    MAX(scan_date) as last_scan_date,
                    COUNT(CASE WHEN scan_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as scanned_last_24h
                FROM $tableName",
                'ARRAY_A'
            );
            
            $statsArray = is_array($stats) && count($stats) > 0 ? $stats[0] : [];
            
            return [
                'total_links' => (int) ($statsArray['total_links'] ?? 0),
                'active_links' => (int) ($statsArray['active_links'] ?? 0),
                'broken_links' => (int) ($statsArray['broken_links'] ?? 0),
                'unscanned_links' => (int) ($statsArray['unscanned_links'] ?? 0),
                'last_scan_date' => $statsArray['last_scan_date'] ?? null,
                'scanned_last_24h' => (int) ($statsArray['scanned_last_24h'] ?? 0),
                'broken_link_percentage' => ($statsArray['total_links'] ?? 0) > 0 
                    ? round(((int) ($statsArray['broken_links'] ?? 0) / (int) ($statsArray['total_links'] ?? 0)) * 100, 2) 
                    : 0
            ];
            
        } catch (Throwable $e) {
            return [
                'error' => 'Failed to retrieve statistics: ' . $e->getMessage()
            ];
        }
    }
}
