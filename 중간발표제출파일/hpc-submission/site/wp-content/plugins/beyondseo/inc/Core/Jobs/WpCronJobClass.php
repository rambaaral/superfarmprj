<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RuntimeException;
use Throwable;

/**
 * Abstract base class for WP_Cron-based jobs.
 * 
 * Provides common functionality for scheduling, unscheduling, and managing
 * recurring jobs using standard WordPress WP_Cron. Concrete implementations
 * must define their specific execution logic and configuration.
 */
abstract class WpCronJobClass
{
    use RcLoggerTrait;

    /** @var SettingsManager Settings manager instance */
    protected SettingsManager $settingsManager;

    /** @var string The WP_Cron hook name - must be defined by concrete classes */
    protected const ACTION_HOOK = '';

    /** @var string The settings option key that controls job enablement */
    protected const ENABLE_SETTING_KEY = '';

    /** @var string The recurrence interval (hourly, twicedaily, daily, etc.) */
    protected const RECURRENCE = 'twicedaily';

    /** @var string Log context for this job type */
    protected const LOG_CONTEXT = 'cron';

    /** @var int Concurrency lock TTL in seconds, prevents overlapping job executions */
    protected const LOCK_TTL = 300;

    /**
     * Constructor - initializes settings manager.
     */
    protected function __construct()
    {
        $this->settingsManager = SettingsManager::instance();
    }

    /**
     * Initialize the job by registering hooks.
     * This should be called during plugin initialization (e.g. on the "init" hook).
     *
     * This method intentionally does NOT perform any scheduling checks
     * (no wp_next_scheduled() calls) since that would run on every single request.
     * Scheduling is handled separately via initializeScheduling(), which should only
     * be invoked on plugin activation/deactivation or when relevant settings/onboarding
     * state changes.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->validateConfiguration();

        // Always register the action hook (needed for execution)
        add_action(static::ACTION_HOOK, [$this, 'execute']);

        // Register settings change listener
        add_action('update_option_' . BaseConstants::OPTION_PLUGIN_SETTINGS, [$this, 'onSettingsUpdate'], 10, 3);
    }

    /**
     * Initialize job scheduling.
     *
     * @return void
     */
    public function initializeScheduling(): void
    {
        // Schedule job if conditions are met
        if ($this->shouldScheduleJob()) {
            $this->scheduleJob();
        } else {
            $this->unscheduleJob();
        }
    }

    /**
     * Abstract method for job execution logic.
     * Must be implemented by concrete classes.
     *
     * @param bool $forceExecute
     * @return void
     */
    abstract public function execute(bool $forceExecute = false): void;

    /**
     * Schedule the job if not already scheduled.
     *
     * @return bool True if scheduling was successful or already scheduled, false otherwise
     */
    public function scheduleJob(): bool
    {
        if (!$this->shouldScheduleJob()) {
            return false;
        }

        try {
            // Check if already scheduled
            if (wp_next_scheduled(static::ACTION_HOOK)) {
                return true;
            }

            // Schedule the recurring action
            $scheduled = wp_schedule_event(time(), static::RECURRENCE, static::ACTION_HOOK);

            if ($scheduled !== false) {
                $this->log_json([
                    'operation_type' => static::ACTION_HOOK . '_scheduled',
                    'operation_status' => 'success',
                    'context_entity' => static::LOG_CONTEXT,
                    'context_type' => 'scheduling',
                    'recurrence' => static::RECURRENCE,
                    'next_run' => gmdate('Y-m-d H:i:s', wp_next_scheduled(static::ACTION_HOOK))
                ], static::LOG_CONTEXT);
                return true;
            }

            $this->log('Failed to schedule action: ' . static::ACTION_HOOK, 'ERROR');
            return false;

        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => static::ACTION_HOOK . '_scheduling',
                'operation_status' => 'error',
                'context_entity' => static::LOG_CONTEXT,
                'context_type' => 'scheduling',
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ], static::LOG_CONTEXT);
            return false;
        }
    }

    /**
     * Unschedule all job actions.
     *
     * @return bool True if unscheduling was successful, false otherwise
     */
    public function unscheduleJob(): bool
    {
        try {
            $timestamp = wp_next_scheduled(static::ACTION_HOOK);
            if ($timestamp) {
                wp_unschedule_event($timestamp, static::ACTION_HOOK);

                $this->log_json([
                    'operation_type' => static::ACTION_HOOK . '_cleanup',
                    'operation_status' => 'success',
                    'context_entity' => static::LOG_CONTEXT,
                    'context_type' => 'cleanup',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
            }

            return true;

        } catch (Throwable $e) {
            $this->log_json([
                'operation_type' => static::ACTION_HOOK . '_cleanup',
                'operation_status' => 'error',
                'context_entity' => static::LOG_CONTEXT,
                'context_type' => 'cleanup',
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => current_time('mysql')
            ], static::LOG_CONTEXT);
            return false;
        }
    }

    /**
     * Handle settings update to monitor changes to job settings.
     *
     * @param mixed $old_value The old option value
     * @param mixed $value The new option value
     * @param string $option The option name
     * @return void
     */
    public function onSettingsUpdate($old_value, $value, string $option): void
    {
        $this->processSettingsUpdate($old_value, $value, $option);
    }

    /**
     * Process settings update.
     *
     * @param mixed $old_value The old option value
     * @param mixed $value The new option value
     * @param string $option The option name
     * @return void
     */
    protected function processSettingsUpdate($old_value, $value, string $option): void
    {
        // Extract the old and new values for job settings
        $oldSettings = is_array($old_value) ? $old_value : [];
        $newSettings = is_array($value) ? $value : [];

        $oldJobEnabled = $this->extractBooleanSetting($oldSettings, static::ENABLE_SETTING_KEY);
        $newJobEnabled = $this->extractBooleanSetting($newSettings, static::ENABLE_SETTING_KEY);

        // Handle job enablement changes
        if ($oldJobEnabled !== $newJobEnabled) {
            if (!$newJobEnabled) {
                // Job was disabled
                $this->unscheduleJob();
                $this->log_json([
                    'operation_type' => static::ACTION_HOOK . '_setting_changed',
                    'operation_status' => 'disabled',
                    'context_entity' => static::LOG_CONTEXT,
                    'context_type' => 'settings_update',
                    'message' => 'Job disabled, cleaned up scheduled actions',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
            } elseif ($newJobEnabled && $this->areAdditionalConditionsMet()) {
                // Job was enabled
                $this->scheduleJob();
                $this->log_json([
                    'operation_type' => static::ACTION_HOOK . '_setting_changed',
                    'operation_status' => 'enabled',
                    'context_entity' => static::LOG_CONTEXT,
                    'context_type' => 'settings_update',
                    'message' => 'Job enabled, scheduled actions',
                    'timestamp' => current_time('mysql')
                ], static::LOG_CONTEXT);
            }
        }
    }

    /**
     * Check if the job is enabled in settings.
     *
     * @return bool
     */
    public function isJobEnabled(): bool
    {
        if (empty(static::ENABLE_SETTING_KEY)) {
            return true; // If no setting key defined, assume enabled
        }
        return (bool)$this->settingsManager->get_option(static::ENABLE_SETTING_KEY, false);
    }

    /**
     * Check if job should be scheduled based on current conditions.
     * Can be overridden by concrete classes for additional conditions.
     *
     * @return bool
     */
    public function shouldScheduleJob(): bool
    {
        return $this->isJobEnabled() && 
               $this->areAdditionalConditionsMet();
    }

    /**
     * Check additional conditions for job scheduling.
     * Override in concrete classes for specific requirements.
     *
     * @return bool
     */
    protected function areAdditionalConditionsMet(): bool
    {
        return true;
    }

    /**
     * Check if the job action is currently scheduled.
     *
     * @return bool
     */
    public function isScheduled(): bool
    {
        return (bool)wp_next_scheduled(static::ACTION_HOOK);
    }

    /**
     * Get the transient key used for the concurrency lock of this job.
     *
     * @return string
     */
    protected function getLockKey(): string
    {
        return 'rc_cron_lock_' . static::ACTION_HOOK;
    }

    /**
     * Attempt to acquire the execution lock for this job.
     * Prevents overlapping runs of the same job (e.g. duplicate cron triggers,
     * or a scheduled run overlapping with a forced/manual run).
     *
     * @return bool True if the lock was acquired, false if another run is already in progress.
     */
    protected function acquireLock(): bool
    {
        if (get_transient($this->getLockKey())) {
            return false;
        }

        return set_transient($this->getLockKey(), time(), static::LOCK_TTL);
    }

    /**
     * Release the execution lock for this job.
     *
     * @return void
     */
    protected function releaseLock(): void
    {
        delete_transient($this->getLockKey());
    }

    /**
     * Validate that concrete class has properly configured constants.
     *
     * @throws RuntimeException If configuration is invalid
     */
    private function validateConfiguration(): void
    {
        if (empty(static::ACTION_HOOK)) {
            throw new RuntimeException('ACTION_HOOK constant must be defined in concrete class');
        }
    }

    /**
     * Extract boolean setting from settings array.
     *
     * @param array $settings Settings array
     * @param string $key Setting key
     * @return bool
     */
    private function extractBooleanSetting(array $settings, string $key): bool
    {
        if (empty($key)) {
            return false;
        }
        return isset($settings[$key]) && (bool)$settings[$key];
    }
}
