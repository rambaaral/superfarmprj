<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\CircuitBreaker;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use WP_Error;

/**
 * Functionality Blocker
 * 
 * Provides methods to gracefully disable plugin functionality when
 * circuit breakers are open. Acts as a central point for blocking
 * various plugin features.
 */
class FunctionalityBlocker {
    
    use RcLoggerTrait;
    
    private static ?FunctionalityBlocker $instance = null;
    private CircuitBreakerManager $circuit_breaker;
    
    public static function instance(): FunctionalityBlocker {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->circuit_breaker = CircuitBreakerManager::instance();
        $this->init_hooks();
    }

    /**
     * Get the Circuit Breaker Manager instance
     */
    public function getCircuitBreaker(): CircuitBreakerManager {
        return $this->circuit_breaker;
    }
    
    /**
     * Initialize hooks to block functionality
     */
    private function init_hooks(): void {
        // Block module loading if circuit is open
        add_filter('rankingcoach_should_load_modules', [$this, 'should_load_modules']);
        
        // Block REST API endpoints
        add_filter('rest_pre_dispatch', [$this, 'block_rest_endpoints'], 10, 3);
        
        // Block AJAX requests
        add_action('wp_ajax_nopriv_rankingcoach_*', [$this, 'block_ajax_request'], 1);
        add_action('wp_ajax_rankingcoach_*', [$this, 'block_ajax_request'], 1);
        
        // Block admin page rendering
        add_action('admin_init', [$this, 'block_admin_pages'], 5);
        
        // Block frontend functionality
        add_action('wp', [$this, 'block_frontend_functionality'], 5);
        
        // Block cron jobs
        add_filter('pre_schedule_event', [$this, 'block_cron_events'], 10, 2);
        
        // Show placeholder content
        add_action('admin_notices', [$this, 'show_admin_placeholder'], 1);

        // Enqueue blocker styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_blocker_styles']);
    }
    
    /**
     * Check if modules should be loaded
     */
    public function should_load_modules(bool $should_load): bool {
        if ($this->circuit_breaker->should_block_functionality()) {
            $this->log('Blocking module loading due to critical circuit breaker failures', 'WARNING', true);
            return false;
        }
        return $should_load;
    }
    
    /**
     * Block REST API endpoints when circuit is open
     */
    public function block_rest_endpoints($result, $server, $request) {
        if (!$this->circuit_breaker->should_block_functionality()) {
            return $result;
        }
        
        $route = $request->get_route();
        
        // Only block RankingCoach endpoints
        if (str_contains($route, '/rankingcoach/')) {
            $this->log('Blocking REST endpoint: ' . $route, 'DEBUG');
            
            return new WP_Error(
                'rankingcoach_circuit_breaker_open',
                sprintf(esc_html('%s functionality is temporarily unavailable due to system requirements not being met.'), RANKINGCOACH_BRAND_NAME),
                ['status' => 503]
            );
        }
        
        return $result;
    }
    
    /**
     * Block AJAX requests when circuit is open
     */
    public function block_ajax_request(): void {
        if (!$this->circuit_breaker->should_block_functionality()) {
            return;
        }

        $action = WordpressHelpers::sanitize_input('REQUEST', 'action');

        if ( $action && (
            str_contains($action, 'beyondseo') ||
            str_contains($action, 'beyond-seo') ||
            str_contains($action, 'rc_')
        ) ) {
            $this->log('Blocking AJAX request: ' . $action, 'DEBUG');

            // translators: %s is the plugin/brand name.
            wp_send_json_error([
                'message' => sprintf(
                // translators: %s is the brand/plugin name.
                esc_html__('%s functionality is temporarily unavailable due to system requirements not being met.', 'beyondseo'),
                    RANKINGCOACH_BRAND_NAME
                ),
                'code' => 'circuit_breaker_open'
            ], 503);
        }
    }
    
    /**
     * Block admin pages when circuit is open
     */
    public function block_admin_pages(): void {
        if (!$this->circuit_breaker->should_block_functionality()) {
            return;
        }
        
        $current_screen = get_current_screen();
        if (!$current_screen) {
            return;
        }
        
        // Block RankingCoach admin pages
        if (
            str_contains($current_screen->id, 'beyondseo') ||
            str_contains($current_screen->id, 'beyond-seo')
        ) {
            $this->log('Blocking admin page: ' . $current_screen->id, 'DEBUG');
            
            // Redirect to plugins page with error message
            wp_safe_redirect(admin_url('plugins.php?rankingcoach_blocked=1'));
            exit;
        }
    }
    
    /**
     * Block frontend functionality
     */
    public function block_frontend_functionality(): void {
        if (!$this->circuit_breaker->should_block_functionality()) {
            return;
        }
        
        // Remove frontend hooks and filters
        $this->remove_frontend_hooks();
        
        // Block meta tag generation
        add_filter('beyondseo_generate_meta_tags', '__return_false');
        
        // Block schema markup
        add_filter('beyondseo_generate_schema', '__return_false');
        
        // Block sitemap generation
        add_filter('beyondseo_generate_sitemap', '__return_false');
        
        $this->log('Frontend functionality blocked due to circuit breaker', 'DEBUG');
    }
    
    /**
     * Block cron events
     * 
     * CRITICAL: WordPress pre_schedule_event filter expects NULL to continue
     * normal processing. Returning false blocks ALL events, not just ours.
     */
    public function block_cron_events($pre, $event) {
        if (!$this->circuit_breaker->should_block_functionality()) {
            // Circuit closed - allow all events by preserving original $pre value
            return $pre;
        }
        
        // Circuit open - only block BeyondSEO cron events
        if (isset($event->hook) &&
            (
                // Backward compatibility for old hook names containing rankingcoach or beyond-seo
                str_contains($event->hook, 'beyondseo') ||
                str_contains($event->hook, 'beyond-seo') ||
                str_contains($event->hook, 'rankingcoach')
            )
        ) {
            $this->log('Blocking cron event: ' . $event->hook, 'DEBUG');
            return true; // Prevent scheduling
        }
        
        // For non-BeyondSEO events, preserve original $pre value
        return $pre;
    }
    
    /**
     * Show admin placeholder when functionality is blocked
     */
    public function show_admin_placeholder(): void {
        if (!$this->circuit_breaker->should_block_functionality()) {
            return;
        }
        
        $current_screen = get_current_screen();
        if (
            !$current_screen ||
            !in_array($current_screen->id, ALLOWED_RANKINGCOACH_PAGES)
        ) {
            return;
        }
        
        // Show placeholder content instead of normal admin interface
        echo '<div class="wrap rankingcoach-blocked-placeholder">';
        echo '<h1>' . esc_html(RANKINGCOACH_BRAND_NAME) . ' - Temporarily Unavailable</h1>';
        echo '<div class="notice notice-error">';
        echo '<p><strong>Plugin functionality is currently disabled due to system requirements not being met.</strong></p>';
        
        $failures = $this->circuit_breaker->get_critical_failures();
        if (!empty($failures)) {
            echo '<p>Critical issues detected:</p>';
            echo '<ul>';
            foreach ($failures as $failure) {
                echo '<li><strong>' . esc_html($failure['name']) . ':</strong> ' . esc_html($failure['message']) . '</li>';
            }
            echo '</ul>';
        }
        
        echo '<p>Plugin functionality will be restored automatically once these issues are resolved.</p>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Enqueue styles for the functionality blocker
     */
    public function enqueue_blocker_styles(): void {
        if (!$this->circuit_breaker->should_block_functionality()) {
            return;
        }

        $current_screen = get_current_screen();
        if (
            !$current_screen ||
            !in_array($current_screen->id, ALLOWED_RANKINGCOACH_PAGES)
        ) {
            return;
        }

        wp_register_style('beyondseo-blocker-style', false, [], RANKINGCOACH_VERSION);
        wp_enqueue_style('beyondseo-blocker-style', false, [], RANKINGCOACH_VERSION);
        wp_add_inline_style('beyondseo-blocker-style', '.rankingcoach-blocked-placeholder ~ * { display: none !important; }');
    }

    /**
     * Remove frontend hooks to prevent functionality
     */
    private function remove_frontend_hooks(): void {
        // Remove common WordPress hooks that RankingCoach might use
        $hooks_to_remove = [
            'wp_head',
            'wp_footer',
            'the_content',
            'the_excerpt',
            'wp_title',
            'document_title_parts',
            'wp_robots'
        ];
        
        foreach ($hooks_to_remove as $hook) {
            // Remove all RankingCoach callbacks from these hooks
            global $wp_filter;
            if (isset($wp_filter[$hook])) {
                foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $key => $callback) {
                        if ($this->is_rankingcoach_callback($callback)) {
                            remove_action($hook, $callback['function'], $priority);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Check if a callback belongs to BeyondSEO
     */
    private function is_rankingcoach_callback(array $callback): bool {
        if (!isset($callback['function'])) {
            return false;
        }
        
        $function = $callback['function'];
        
        // Check for class methods
        if (is_array($function) && isset($function[0])) {
            $class = is_object($function[0]) ? get_class($function[0]) : $function[0];
            // Backward compatibility for old class names RankingCoach and new classname BeyondSEO
            return (str_contains($class, 'BeyondSEO') || str_contains($class, 'RankingCoach'));
        }
        
        // Check for function names
        if (is_string($function)) {
            return
                // Backward compatibility for old function names containing rankingcoach or rc_
                str_contains($function, 'beyondseo') ||
                str_contains($function, 'rankingcoach') ||
                str_contains($function, 'rc_')
            ;
        }
        
        return false;
    }
    
    /**
     * Get blocked functionality status
     */
    public function get_blocked_status(): array {
        return [
            'is_blocked' => $this->circuit_breaker->should_block_functionality(),
            'critical_failures' => $this->circuit_breaker->get_critical_failures(),
            'all_failures' => $this->circuit_breaker->get_failed_checks()
        ];
    }
    
    /**
     * Manually block functionality (for testing)
     */
    public function force_block(): void {
        add_filter('rankingcoach_circuit_breaker_force_block', '__return_true');
    }
    
    /**
     * Manually unblock functionality (for testing)
     */
    public function force_unblock(): void {
        remove_filter('rankingcoach_circuit_breaker_force_block', '__return_true');
    }
}
