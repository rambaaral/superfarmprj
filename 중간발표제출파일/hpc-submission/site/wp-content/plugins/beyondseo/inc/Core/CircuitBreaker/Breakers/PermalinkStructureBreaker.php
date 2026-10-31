<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\CircuitBreaker\Breakers;

use RankingCoach\Inc\Core\CircuitBreaker\CircuitBreakerInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Permalink Structure Circuit Breaker
 * 
 * Monitors WordPress permalink structure to ensure REST API functionality.
 * This is critical because the plugin relies heavily on REST API endpoints.
 */
class PermalinkStructureBreaker implements CircuitBreakerInterface {
    
    public function get_id(): string {
        return 'permalink_structure';
    }
    
    public function get_name(): string {
        return 'Permalink Structure';
    }
    
    public function is_healthy(): bool|int {
        if (!function_exists('get_option')) {
            return true; // Assume healthy if WordPress not loaded
        }
        
        $permalink_structure = get_option('permalink_structure');
        return !empty($permalink_structure);
    }
    
    public function get_failure_message(): string {
        return sprintf(esc_html('WordPress is configured to use "Plain" permalinks, which breaks REST API functionality required by %s. The plugin has been temporarily disabled.'), RANKINGCOACH_BRAND_NAME);
    }
    
    public function get_severity(): string {
        return self::SEVERITY_CRITICAL;
    }
    
    public function get_recovery_action(): string {
        if (!function_exists('admin_url') || !function_exists('esc_url')) {
            return 'Go to Settings → Permalinks and select "Post name" or any structure except "Plain", then click Save Changes. The plugin will automatically reactivate once permalinks are configured properly.';
        }
        
        return 'Go to <a href="' . esc_url(admin_url('options-permalink.php')) . '"><strong>Settings → Permalinks</strong></a> and select <strong>"Post name"</strong> or any structure except "Plain", then click <strong>Save Changes</strong>. The plugin will automatically reactivate once permalinks are configured properly.';
    }
    
    public function get_context(): array {
        return [
            'current_structure' => get_option('permalink_structure', ''),
            'admin_url' => function_exists('admin_url') ? admin_url('options-permalink.php') : null
        ];
    }
}