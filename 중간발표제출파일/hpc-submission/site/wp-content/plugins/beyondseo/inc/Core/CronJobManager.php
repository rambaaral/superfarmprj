<?php

declare (strict_types=1);
namespace RankingCoach\Inc\Core;

if (!defined('ABSPATH')) {
    exit;
}
use Exception;
use RankingCoach\Inc\Core\Api\Content\ContentApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\CircuitBreaker\FunctionalityBlocker;
use Throwable;
/**
 * Class CronJobManager
 */
class CronJobManager
{
    use RcLoggerTrait;
    /**
     * Singleton instance of CronJobManager.
     *
     * @var \CronJobManager|null
     */
    private static ?CronJobManager $instance = null;
    /**
     * Returns the singleton instance of CronJobManager.
     *
     * @return \CronJobManager|null
     */
    public static function instance(): ?CronJobManager
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * Registers the custom cron schedules filter only.
     * Must be called unconditionally on every request so that scheduled events
     * can always be rescheduled, even when the rest of the plugin is not fully
     * initialised (e.g. when the circuit breaker is open).
     *
     * @return void
     */
    public function registerSchedules(): void
    {
        add_filter('cron_schedules', [$this, 'define_cron_schedules']);
    }

    /**
     * Registers cron-related hooks and the token status check action.
     * This is cheap (no DB calls) and safe to run on every request/plugins_loaded.
     *
     * Actual scheduling (wp_schedule_event) is handled separately via
     * scheduleCronJobs()/clearAllCronJobs(), which should only be invoked on
     * plugin activation/deactivation to avoid wp_next_scheduled() checks on
     * every request.
     *
     * @return void
     */
    public function initialize(): void
    {
        // Register token validity check task
        add_action(BaseConstants::OPTION_CRONJOB_TWICE_HOURLY_EVENT, [$this, 'rankingcoach_twice_hourly_task_handler']);
    }

    /**
     * Schedules the plugin's recurring cron jobs.
     * Should only be called from plugin activation, not on every request.
     *
     * @return void
     */
    public function scheduleCronJobs(): void
    {
        $this->add_cron_job(BaseConstants::OPTION_CRONJOB_TWICE_HOURLY_EVENT, 'daily', [$this, 'rankingcoach_twice_hourly_task_handler']);
    }
    /**
     * Registers a new cron job with the specified recurrence interval.
     *
     * @param string $hook The action hook for the cron job.
     * @param string $recurrence Recurrence interval (e.g., 'hourly', 'daily').
     * @param callable $callback Callback function to execute.
     *
     * @return void
     */
    public function add_cron_job(string $hook, string $recurrence, callable $callback): void
    {
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $recurrence, $hook);
        }
    }
    /**
     * Removes a specified cron job.
     *
     * @param string $hook The action hook for the cron job.
     *
     * @return void
     */
    public function remove_cron_job(string $hook): void
    {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
            remove_action($hook, $hook);
        }
    }
    /**
     * Removes all cron jobs associated with the plugin.
     *
     * @return void
     */
    public function clearAllCronJobs(): void
    {
        $this->remove_cron_job(BaseConstants::OPTION_CRONJOB_TWICE_HOURLY_EVENT);
    }
    /**
     * Checks if a specified cron job is scheduled.
     *
     * @param string $hook The action hook for the cron job.
     *
     * @return bool True if scheduled, false otherwise.
     */
    public function is_cron_job_scheduled(string $hook): bool
    {
        return wp_next_scheduled($hook) !== false;
    }
    /**
     * Executes a specific cron job manually.
     *
     * @param string $beyondseo_cron_hook The action hook for the cron job.
     *
     * @return void
     */
    public function run_cron_job_now(string $beyondseo_cron_hook): void
    {
        if (has_action($beyondseo_cron_hook)) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
            do_action($beyondseo_cron_hook);
        }
    }
    /**
     * Handles the token status check task for this plugin.
     *
     * @return void
     * @throws Throwable
     */
    public function rankingcoach_twice_hourly_task_handler(): void
    {
        if (!FunctionalityBlocker::instance()->getCircuitBreaker()->check_circuit_state()) {
            return;
        }
        $this->log_event('Twice hourly task executed.');
        // Calculate the remaining days for the refresh token
        /** @var TokensManager $tokensManager */
        $tokensManager = TokensManager::getInstance();
        $tokensManager->calculateRefreshTokenRemainingDays();
    }
    /**
     * Logs cron events for debugging purposes.
     *
     * @param string $message The message to log.
     *
     * @return void
     */
    private function log_event(string $message): void
    {
        if ( defined( 'RANKINGCOACH_WP_DEBUG' ) && RANKINGCOACH_WP_DEBUG ) {
            $this->log('[CronJobManager] ' . $message);
        }
    }
    /**
     * Defines custom cron schedules.
     *
     * @param array $schedules The existing cron schedules.
     *
     * @return array The modified cron schedules.
     */
    public function define_cron_schedules(array $schedules): array
    {
        // WordPress already ships with a 'daily' schedule; no custom
        // intervals are required anymore now that all plugin cron jobs
        // run on WordPress' built-in recurrences.
        return $schedules;
    }
    /**
     * Retrieves all scheduled cron jobs.
     *
     * @return array|null An array of scheduled cron jobs.
     */
    public function get_all_scheduled_cron_jobs(): ?array
    {
        // Retrieve the cron option from the WordPress database
        $cron = _get_cron_array();
        if (empty($cron)) {
            return null;
        }
        $scheduled_cron_jobs = [];
        $current_time = time();
        foreach ($cron as $timestamp => $hooks) {
            foreach ($hooks as $hook => $details) {
                foreach ($details as $job) {
                    $scheduled_cron_jobs[] = ['hook' => $hook, 'schedule' => $job['schedule'] ?? 'One-time', 'args' => $job['args'], 'interval' => $job['interval'] ?? null, 'next_run' => gmdate('Y-m-d H:i:s', $timestamp), 'time_left' => $timestamp - $current_time];
                }
            }
        }
        return $scheduled_cron_jobs;
    }
    /**
     * Validate the refresh token
     * @param $cron_report
     * @return void
     */
    public function saveRefreshTokenRemainingDays($cron_report): void
    {
        // Save the report in the option table
        update_option(BaseConstants::OPTION_CRONJOB_REPORT, $cron_report);
    }
}
