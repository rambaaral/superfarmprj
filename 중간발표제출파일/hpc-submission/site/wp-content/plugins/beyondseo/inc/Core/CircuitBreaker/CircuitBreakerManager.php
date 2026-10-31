<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\CircuitBreaker;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\CircuitBreaker\Breakers\PermalinkStructureBreaker;
use RankingCoach\Inc\Core\CircuitBreaker\Breakers\RestApiBreaker;
use RankingCoach\Inc\Core\NotificationManager;
use RankingCoach\Inc\Core\Notification;
use RuntimeException;
use Throwable;

/**
 * Circuit Breaker Manager
 * 
 * Monitors critical plugin dependencies and gracefully degrades functionality
 * when requirements are not met. Acts as a central control system that can
 * disable plugin features and display appropriate notifications.
 */
class CircuitBreakerManager {
    
    use RcLoggerTrait;
    
    private const OPTION_CIRCUIT_STATE = 'rankingcoach_circuit_breaker_state';
    private const OPTION_CIRCUIT_LOCK = 'rankingcoach_circuit_breaker_lock';
    private const NOTIFICATION_ID_PREFIX = 'rankingcoach-circuit-breaker-';
    private const LOCK_TIMEOUT = 30; // seconds
    private const MAX_RETRY_ATTEMPTS = 3;
    private const CHECK_THROTTLE_SECONDS = 10;
    
    private static ?CircuitBreakerManager $instance = null;
    private static bool $initializing = false;
    
    /** @var array<string, CircuitBreakerInterface> */
    private array $breakers = [];
    
    /** @var bool */
    private bool $is_circuit_open = false;
    
    /** @var array */
    private array $failed_checks = [];
    
    /** @var array */
    private array $circuit_state = [];
    
    /** @var bool */
    private bool $hooks_initialized = false;
    
    /** @var bool */
    private bool $checking_circuit = false;
    
    /** @var array|null */
    private ?array $pending_critical_failures = null;
    
    /** @var int */
    private int $state_version = 0;
    
    public static function instance(): CircuitBreakerManager {
        if (self::$instance === null) {
            if (self::$initializing) {
                throw new RuntimeException('CircuitBreakerManager circular initialization detected');
            }
            
            self::$initializing = true;
            try {
                self::$instance = new self();
            } finally {
                self::$initializing = false;
            }
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_circuit_state();
        $this->register_default_breakers();
        // Defer hook initialization to prevent recursion during construction
        // Only initialize hooks in admin context where circuit breakers are relevant
        if (is_admin()) {
            add_action('admin_init', [$this, 'init_hooks_deferred'], 1);
        } else {
            // For frontend, hooks are not needed as breakers are admin-only
            $this->hooks_initialized = true;
        }
    }

    /**
     * Register default circuit breakers
     */
    private function register_default_breakers(): void {
        $this->register_breaker(new PermalinkStructureBreaker());
        $this->register_breaker(new RestApiBreaker());
    }
    
    /**
     * Register a circuit breaker
     */
    public function register_breaker(CircuitBreakerInterface $breaker): void {
        $this->breakers[$breaker->get_id()] = $breaker;
    }
    
    /**
     * Deferred hook initialization to prevent recursion
     */
    public function init_hooks_deferred(): void {
        if ($this->hooks_initialized) {
            return;
        }
        $this->hooks_initialized = true;
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Check circuit state on critical WordPress actions
        add_action('admin_init', [$this, 'check_circuit_state'], 5);
        add_action('init', [$this, 'check_circuit_state'], 5);
        // Re-check after WordPress is fully loaded to catch deferred checks
        add_action('wp_loaded', [$this, 'check_circuit_state'], 5);
        
        // Monitor permalink changes
        add_action('permalink_structure_changed', [$this, 'on_permalink_structure_changed']);
        
        // Monitor plugin activation/deactivation
        add_action('activated_plugin', [$this, 'on_plugin_state_changed']);
        add_action('deactivated_plugin', [$this, 'on_plugin_state_changed']);
        
        // Monitor theme changes
        add_action('switch_theme', [$this, 'check_circuit_state']);
        
        // Check on options update - immediate for critical options
        add_action('updated_option', [$this, 'on_option_updated'], 10, 3);
        
        // Force immediate check on admin page loads for better UX
        add_action('current_screen', [$this, 'on_admin_screen_load']);
    }
    
    /**
     * Check all circuit breakers and update state
     * 
     * Some breakers may defer their checks until WordPress is fully loaded
     * to avoid timing issues with global objects like $wp_rewrite.
     */
    public function check_circuit_state(): bool {
        // Prevent recursion during initialization or active checking
        if (self::$initializing || $this->checking_circuit) {
            return !$this->is_circuit_open;
        }
        
        // Throttle checks - skip if checked recently
        if ($this->should_throttle_check()) {
            return !$this->is_circuit_open;
        }
        
        $this->checking_circuit = true;
        
        try {
        $previous_state = $this->is_circuit_open;
        $this->failed_checks = [];
        
        foreach ($this->breakers as $breaker) {
            try {
                if (!$breaker->is_healthy()) {
                    $this->failed_checks[$breaker->get_id()] = [
                        'name' => $this->sanitize_breaker_output($breaker->get_name()),
                        'message' => $this->sanitize_breaker_output($breaker->get_failure_message()),
                        'severity' => $breaker->get_severity(),
                        'recovery_action' => $this->sanitize_breaker_output($breaker->get_recovery_action()),
                        'context' => $breaker->get_context(),
                        'timestamp' => time(),
                    ];
                }
                else {
                    $this->update_notifications();
                }
            } catch (Throwable $e) {
                // Breaker threw exception - treat as critical failure
                $breaker_id = $this->get_safe_breaker_id($breaker);
                $breaker_name = $this->get_safe_breaker_name($breaker);
                
                $this->failed_checks[$breaker_id] = [
                    'name' => $this->sanitize_breaker_output($breaker_name),
                    'message' => $this->sanitize_breaker_output('Circuit breaker crashed: ' . $e->getMessage()),
                    'severity' => CircuitBreakerInterface::SEVERITY_CRITICAL,
                    'recovery_action' => $this->sanitize_breaker_output('Check system logs and contact support'),
                    'context' => [
                        'exception_class' => get_class($e),
                        'exception_file' => $e->getFile(),
                        'exception_line' => $e->getLine(),
                        'breaker_class' => get_class($breaker)
                    ],
                    'timestamp' => time(),
                ];
                
                $this->log(
                    sprintf(
                        'Circuit breaker %s (%s) threw exception: %s in %s:%d',
                        $breaker_name,
                        get_class($breaker),
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine()
                    ),
                    'ERROR'
                );
            }
        }
        
        $this->is_circuit_open = !empty($this->failed_checks);

        // State changed - update notifications and save state
        if ($previous_state !== $this->is_circuit_open) {
            $this->update_notifications();
            $this->save_circuit_state();
            
            if ($this->is_circuit_open) {
                $this->log('Circuit breaker opened due to failed checks: ' . implode(', ', array_keys($this->failed_checks)), 'WARNING');
                do_action('rankingcoach_circuit_breaker_opened', $this->failed_checks);
                
                // Force immediate notification display for critical issues
                $this->force_immediate_notification_display();
            } else {
                $this->log('Circuit breaker closed - all checks passed', 'INFO', true);
                do_action('rankingcoach_circuit_breaker_closed');
            }
        }
        
        return !$this->is_circuit_open;
        } finally {
            $this->checking_circuit = false;
        }
    }
    
    /**
     * Check if plugin functionality should be blocked
     */
    public function is_circuit_open(): bool {
        return $this->is_circuit_open;
    }
    
    /**
     * Get failed checks
     */
    public function get_failed_checks(): array {
        return $this->failed_checks;
    }
    
    /**
     * Get critical failed checks that should block all functionality
     */
    public function get_critical_failures(): array {
        return array_filter($this->failed_checks, function($check) {
            return $check['severity'] === CircuitBreakerInterface::SEVERITY_CRITICAL;
        });
    }
    
    /**
     * Check if functionality should be completely blocked
     */
    public function should_block_functionality(): bool {
        return !empty($this->get_critical_failures());
    }
    
    /**
     * Update notifications based on circuit state
     */
    private function update_notifications(): void {
        $notification_manager = NotificationManager::instance();
        if (!$notification_manager) {
            return;
        }
        
        // Clear existing circuit breaker notifications
        $this->clear_circuit_notifications();
        
        if ($this->is_circuit_open) {
            // Remove all existing circuit notifications to avoid duplicates
            $notification_manager->removeAllNotifications();

            // Group failures by severity
            $critical_failures = [];
            $warning_failures = [];
            
            foreach ($this->failed_checks as $id => $failure) {
                if ($failure['severity'] === CircuitBreakerInterface::SEVERITY_CRITICAL) {
                    $critical_failures[$id] = $failure;
                } else {
                    $warning_failures[$id] = $failure;
                }
            }
            
            // Show critical failures first - only if not already present
            if (!empty($critical_failures)) {
                $critical_notification_id = self::NOTIFICATION_ID_PREFIX . 'critical';
                if (!$notification_manager->get_notification_by_id($critical_notification_id)) {
                    $this->show_critical_failure_notification($critical_failures);
                }
            }
            
            // Show warnings - only if not already present
            foreach ($warning_failures as $id => $failure) {
                $notification_id = self::NOTIFICATION_ID_PREFIX . $id;
                if (!$notification_manager->get_notification_by_id($notification_id)) {
                    $this->show_warning_notification($id, $failure);
                }
            }
        }
    }
    
    /**
     * Show critical failure notification that blocks functionality
     */
    private function show_critical_failure_notification(array $failures): void {
        $notification_manager = NotificationManager::instance();
        
        $message = '<div class="rankingcoach-circuit-breaker-critical-panel" style="padding: 5px 0; background: #fff; margin: 10px 0;">';
        $message .= '<h3 style="margin-top: 0; color: #dc3232;"><strong>🚫 ' . RANKINGCOACH_BRAND_NAME . ' - Plugin Temporarily Disabled</strong></h3>';
        $message .= '<p><strong>The plugin has been automatically disabled due to critical system requirements not being met:</strong></p>';
        $message .= '<ul style="margin: 10px 0; padding-left: 20px;">';
        
        foreach ($failures as $failure) {
            $message .= '<li style="margin: 8px 0;"><strong>' . esc_html($failure['name']) . ':</strong> ' . wp_kses_post($failure['message']);
            if (!empty($failure['recovery_action'])) {
                $message .= '<br><span style="color: #0073aa; font-weight: 500;">→ ' . wp_kses_post($failure['recovery_action']) . '</span>';
            }
            $message .= '</li>';
        }
        
        $message .= '</ul>';
        $message .= '<div style="background: #f0f6fc; border: 1px solid #c3d9ff; padding: 10px; margin-top: 15px; border-radius: 4px;">';
        $message .= '<p style="margin: 0; color: #0073aa;"><strong>ℹ️ Important:</strong> The plugin will automatically reactivate once all issues are resolved. No manual reactivation is required.</p>';
        $message .= '</div>';
        $message .= '</div>';
        
        $notification_manager->add($message, [
            'id' => self::NOTIFICATION_ID_PREFIX . 'critical',
            'type' => Notification::ERROR,
            'screen' => Notification::SCREEN_ANY,
            'dismissible' => false,
            'persistent' => true,
        ]);
    }
    
    /**
     * Show warning notification for non-critical issues
     */
    private function show_warning_notification(string $id, array $failure): void {
        $notification_manager = NotificationManager::instance();
        
        $message = '<div class="rankingcoach-circuit-breaker-warning" style="border-left: 4px solid #ffb900; padding: 15px; background: #fff; margin: 10px 0;">';
        $message .= '<h4 style="margin-top: 0; color: #ffb900;"><strong>⚠️ ' . RANKINGCOACH_BRAND_NAME . ' - ' . esc_html($failure['name']) . ' Issue</strong></h4>';
        $message .= '<p>' . wp_kses_post($failure['message']) . '</p>';
        
        if (!empty($failure['recovery_action'])) {
            $message .= '<div style="background: #fff8e1; border: 1px solid #ffcc02; padding: 10px; margin-top: 10px; border-radius: 4px;">';
            $message .= '<p style="margin: 0; color: #8a6914;"><strong>Recommended action:</strong> ' . wp_kses_post($failure['recovery_action']) . '</p>';
            $message .= '</div>';
        }
        
        $message .= '</div>';
        
        $notification_manager->add($message, [
            'id' => self::NOTIFICATION_ID_PREFIX . $id,
            'type' => Notification::WARNING,
            'screen' => Notification::SCREEN_ANY,
            'dismissible' => true,
            'persistent' => true,
        ]);
    }
    
    /**
     * Clear all circuit breaker notifications
     */
    private function clear_circuit_notifications(): void {
        $notification_manager = NotificationManager::instance();
        if (!$notification_manager) {
            return;
        }
        
        // Remove critical notification
        $notification_manager->remove_by_id(self::NOTIFICATION_ID_PREFIX . 'critical');
        
        // Remove individual breaker notifications
        foreach ($this->breakers as $breaker) {
            $notification_manager->remove_by_id(self::NOTIFICATION_ID_PREFIX . $breaker->get_id());
        }
    }
    
    /**
     * Event handlers
     */
    public function on_permalink_structure_changed(): void {
        $this->check_circuit_state();
    }
    
    public function on_plugin_state_changed(): void {
        $this->check_circuit_state();
    }
    
    public function on_option_updated(string $option, $old_value, $new_value): void {
        // Check for critical option changes
        $critical_options = [
            'permalink_structure',
            'active_plugins',
            'stylesheet',
            'template'
        ];
        
        if (in_array($option, $critical_options)) {
            // Force immediate check and notification update for critical options
            $this->check_circuit_state();
            
            // If this is a permalink structure change, ensure immediate notification
            if ($option === 'permalink_structure') {
                $this->log('Permalink structure changed, forcing immediate circuit check', 'INFO', true);
            }
        }
    }
    
    /**
     * Handle admin screen loads for immediate feedback
     */
    public function on_admin_screen_load($current_screen): void {
        if (!$current_screen) {
            return;
        }
        
        // Force check on admin pages where users might see notifications
        $check_screens = [
            'dashboard',
            'plugins',
            'options-permalink',
            'update-core'
        ];
        
        foreach ($check_screens as $screen) {
            if (strpos($current_screen->id, $screen) !== false) {
                $this->check_circuit_state();
                break;
            }
        }
    }
    
    /**
     * Force immediate notification display for critical issues
     */
    private function force_immediate_notification_display(): void {
        // Only force display in admin context
        if (!is_admin()) {
            return;
        }
        
        // Get critical failures
        $critical_failures = $this->get_critical_failures();
        if (empty($critical_failures)) {
            return;
        }
        
        // Store failures for callback access without closure capture
        $this->pending_critical_failures = $critical_failures;
        
        // Add a high-priority action to display notifications immediately
        add_action('admin_notices', [$this, 'display_pending_critical_notification'], 1);
    }
    
    /**
     * Display pending critical notification callback
     */
    public function display_pending_critical_notification(): void {
        if ($this->pending_critical_failures === null) {
            return;
        }
        
        $failures = $this->pending_critical_failures;
        $this->pending_critical_failures = null; // Clear to prevent re-display
        
        $this->show_immediate_critical_notification($failures);
    }
    
    /**
     * Show immediate critical notification (bypasses normal notification system for urgency)
     */
    private function show_immediate_critical_notification(array $failures): void {
        // Check if critical notification already exists in the notification system
        $notification_manager = NotificationManager::instance();
        if ($notification_manager && $notification_manager->get_notification_by_id(self::NOTIFICATION_ID_PREFIX . 'critical')) {
            return; // Don't show duplicate immediate notification
        }
        
        // Only show once per page load
        static $shown = false;
        if ($shown) {
            return;
        }
        $shown = true;
        
        echo '<div class="notice notice-error" style="border-left: 4px solid #dc3232; padding: 15px; background: #fff; margin: 10px 0;">';
        echo '<h3 style="margin-top: 0; color: #dc3232;"><strong>🚫 ' . esc_html(RANKINGCOACH_BRAND_NAME) . ' - Plugin Temporarily Disabled</strong></h3>';
        echo '<p><strong>The plugin has been automatically disabled due to critical system requirements not being met:</strong></p>';
        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
        
        foreach ($failures as $failure) {
            echo '<li style="margin: 8px 0;"><strong>' . esc_html($failure['name']) . ':</strong> ' . wp_kses_post($failure['message']);
            if (!empty($failure['recovery_action'])) {
                echo '<br><span style="color: #0073aa; font-weight: 500;">→ ' . wp_kses_post($failure['recovery_action']) . '</span>';
            }
            echo '</li>';
        }
        
        echo '</ul>';
        echo '<div style="background: #f0f6fc; border: 1px solid #c3d9ff; padding: 10px; margin-top: 15px; border-radius: 4px;">';
        echo '<p style="margin: 0; color: #0073aa;"><strong>ℹ️ Important:</strong> The plugin will automatically reactivate once all issues are resolved. No manual reactivation is required.</p>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Load circuit state from database
     */
    private function load_circuit_state(): void {
        $this->circuit_state = get_option(self::OPTION_CIRCUIT_STATE, [
            'is_open' => false,
            'failed_checks' => [],
            'last_check' => 0,
            'version' => 1
        ]);
        
        $this->is_circuit_open = $this->circuit_state['is_open'] ?? false;
        $this->failed_checks = $this->circuit_state['failed_checks'] ?? [];
        $this->state_version = $this->circuit_state['version'] ?? 1;
    }
    
    /**
     * Save circuit state to database with atomic compare-and-swap
     */
    private function save_circuit_state(): void {
        $attempts = 0;
        
        while ($attempts < self::MAX_RETRY_ATTEMPTS) {
            if ($this->acquire_lock()) {
                try {
                    // Re-read current state to check version
                    $current_state = get_option(self::OPTION_CIRCUIT_STATE, [
                        'is_open' => false,
                        'failed_checks' => [],
                        'last_check' => 0,
                        'version' => 1
                    ]);
                    
                    $current_version = $current_state['version'] ?? 1;
                    
                    // Version mismatch - state was modified by another process
                    if ($current_version !== $this->state_version) {
                        $this->log('Circuit state version conflict detected, retrying...', 'WARNING', true);
                        $this->release_lock();
                        $this->load_circuit_state(); // Reload current state
                        $attempts++;
                        continue;
                    }
                    
                    // Atomic update with version increment
                    $new_version = $current_version + 1;
                    $new_state = [
                        'is_open' => $this->is_circuit_open,
                        'failed_checks' => $this->failed_checks,
                        'last_check' => time(),
                        'version' => $new_version
                    ];
                    
                    $success = update_option(self::OPTION_CIRCUIT_STATE, $new_state);
                    
                    if ($success) {
                        $this->circuit_state = $new_state;
                        $this->state_version = $new_version;
                        $this->release_lock();
                        return;
                    }
                    
                    $this->log('Failed to update circuit state option', 'ERROR');
                    
                } finally {
                    $this->release_lock();
                }
            }
            
            $attempts++;
            if ($attempts < self::MAX_RETRY_ATTEMPTS) {
                usleep(wp_rand(10000, 50000)); // Random backoff 10-50ms
            }
        }
        
        $this->log('Failed to save circuit state after ' . self::MAX_RETRY_ATTEMPTS . ' attempts', 'ERROR');
    }
    
    /**
     * Acquire distributed lock for state updates
     */
    private function acquire_lock(): bool {
        $lock_key = self::OPTION_CIRCUIT_LOCK;
        $lock_value = [
            'process_id' => getmypid(),
            'timestamp' => time(),
            'expires' => time() + self::LOCK_TIMEOUT
        ];
        
        // Try to acquire lock
        $existing_lock = get_option($lock_key, null);
        
        // Check if existing lock is expired
        if ($existing_lock && isset($existing_lock['expires']) && $existing_lock['expires'] < time()) {
            delete_option($lock_key);
            $existing_lock = null;
        }
        
        // Lock is available
        if (!$existing_lock) {
            return add_option($lock_key, $lock_value, '', false); // No autoload, not cached
        }
        
        return false;
    }
    
    /**
     * Release distributed lock
     */
    private function release_lock(): void {
        $lock_key = self::OPTION_CIRCUIT_LOCK;
        $existing_lock = get_option($lock_key, null);
        
        // Only release if we own the lock
        if ($existing_lock && isset($existing_lock['process_id']) && $existing_lock['process_id'] === getmypid()) {
            delete_option($lock_key);
        }
    }
    
    /**
     * Force circuit check (useful for testing)
     */
    public function force_check(): bool {
        return $this->check_circuit_state();
    }
    
    /**
     * Safely get breaker ID without throwing exceptions
     */
    private function get_safe_breaker_id($breaker): string {
        try {
            return $breaker->get_id();
        } catch (Throwable $e) {
            return 'unknown_breaker_' . spl_object_hash($breaker);
        }
    }
    
    /**
     * Safely get breaker name without throwing exceptions
     */
    private function get_safe_breaker_name($breaker): string {
        try {
            return $breaker->get_name();
        } catch (Throwable $e) {
            return 'Unknown Breaker (' . get_class($breaker) . ')';
        }
    }
    
    /**
     * Sanitize breaker output to prevent XSS attacks
     */
    private function sanitize_breaker_output(string $input): string {
        // Strip all HTML tags except safe ones, limit length to prevent DoS
        $sanitized = wp_kses($input, [
            'strong' => [],
            'em' => [],
            'code' => [],
            'br' => [],
            'p' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
        ]);
        
        // Limit length to prevent notification bloat and potential DoS
        if (strlen($sanitized) > 500) {
            $sanitized = substr($sanitized, 0, 497) . '...';
        }
        
        return $sanitized;
    }
    
    /**
     * Check if circuit state check should be throttled
     */
    private function should_throttle_check(): bool {
        // Do not throttle when circuit is open, so we can recover immediately
        // and remove critical notifications as soon as the environment is healthy.
        if ($this->is_circuit_open) {
            return false;
        }

        $last_check = $this->circuit_state['last_check'] ?? 0;
        $time_since_check = time() - $last_check;
        
        return $time_since_check < self::CHECK_THROTTLE_SECONDS;
    }
}
