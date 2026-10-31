<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\CircuitBreaker\Breakers;

use RankingCoach\Inc\Core\CircuitBreaker\CircuitBreakerInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Circuit Breaker
 * 
 * Monitors WordPress REST API availability and functionality.
 */
class RestApiBreaker implements CircuitBreakerInterface {
    
    public function get_id(): string {
        return 'rest_api';
    }
    
    public function get_name(): string {
        return 'WordPress REST API';
    }
    
    public function is_healthy(): bool|int {
        // Check if REST API is enabled
        if (!function_exists('rest_url')) {
            return false;
        }
        
        // Defer REST API checks until WordPress is fully loaded
        // This prevents the null $wp_rewrite error during early initialization
        if (!did_action('wp_loaded')) {
            // During early initialization, just check basic availability
            return function_exists('rest_url') && !$this->is_rest_api_explicitly_disabled();
        }
        
        // Check if REST API is accessible (safe to call after wp_loaded)
        $rest_url = rest_url();
        if (empty($rest_url)) {
            return false;
        }
        
        // Check if REST API is not disabled by plugins/themes
        if (defined('REST_REQUEST') && !REST_REQUEST) {
            return false;
        }
        
        // Additional check for common REST API blocking scenarios
        if ($this->is_rest_api_blocked()) {
            return false;
        }
        
        return true;
    }
    
    public function get_failure_message(): string {
        // translators: %s is the brand name of the plugin
        return sprintf(esc_html('WordPress REST API is not available or has been disabled. %s requires REST API functionality to operate properly. The plugin has been temporarily disabled.'), RANKINGCOACH_BRAND_NAME);
    }
    
    public function get_severity(): string {
        return self::SEVERITY_CRITICAL;
    }
    
    public function get_recovery_action(): string {
        return 'Ensure that the WordPress REST API is not disabled by security plugins, themes, or server configurations. Check with your hosting provider if needed. The plugin will automatically reactivate once REST API access is restored.';
    }
    
    public function get_context(): array {
        $context = [
            'rest_request_defined' => defined('REST_REQUEST'),
            'rest_request_value' => defined('REST_REQUEST') ? REST_REQUEST : null,
            'wp_loaded' => did_action('wp_loaded') > 0,
            'explicitly_disabled' => $this->is_rest_api_explicitly_disabled()
        ];
        
        // Only get rest_url and blocking checks after WordPress is loaded
        if (did_action('wp_loaded')) {
            $context['rest_url'] = function_exists('rest_url') ? rest_url() : null;
            $context['is_blocked'] = $this->is_rest_api_blocked();
        } else {
            $context['rest_url'] = 'deferred_until_wp_loaded';
            $context['is_blocked'] = 'deferred_until_wp_loaded';
        }
        
        return $context;
    }
    
    /**
     * Check if REST API is explicitly disabled during early initialization
     */
    private function is_rest_api_explicitly_disabled(): bool {
        // Check for common constants that disable REST API
        if (defined('DISABLE_WP_REST_API') && DISABLE_WP_REST_API) {
            return true;
        }
        
        // Check for common filter that completely disables REST API
        if (has_filter('rest_enabled', '__return_false')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if REST API is blocked by common methods
     */
    private function is_rest_api_blocked(): bool {
        // Check for common REST API blocking filters
        if (has_filter('rest_authentication_errors')) {
            $filters = $GLOBALS['wp_filter']['rest_authentication_errors'] ?? null;
            if ($filters && !empty($filters->callbacks)) {
                // Check if any filter returns WP_Error for non-authenticated users
                foreach ($filters->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        if (is_array($callback['function']) && 
                            isset($callback['function'][1]) && 
                            strpos($callback['function'][1], 'disable') !== false) {
                            return true;
                        }
                    }
                }
            }
        }
        
        // Check for .htaccess blocks (common with security plugins)
        if (function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) {
            // This is a simplified check - in practice, you'd need to parse .htaccess
            // or test actual REST endpoint accessibility
        }
        
        return false;
    }
}