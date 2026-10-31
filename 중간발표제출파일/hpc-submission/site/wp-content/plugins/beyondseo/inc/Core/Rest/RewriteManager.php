<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rest;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\NotificationManager;
use RankingCoach\Inc\Core\Notification;

/**
 * Class RewriteManager
 *
 * Handles permalink structure validation and REST API requirements.
 * Simplified to focus on permalink structure validation during activation.
 */
class RewriteManager {

    use RcLoggerTrait;

    private const NOTIFICATION_ID = 'rankingcoach-permalink-structure-required';

    /**
     * Called during plugin activation.
     * Validates permalink structure and shows notification if needed.
     */
    public static function initialize(): void {
        if (!self::hasValidPermalinkStructureStatic()) {
            self::showPermalinkNotificationStatic();
            // Remove exception - let plugin activate with notification
            return;
        }

        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
        }
    }

    /**
     * Checks if WordPress has a valid permalink structure for REST API.
     *
     * @return bool True if permalink structure is valid, false otherwise.
     */
    private function hasValidPermalinkStructure(): bool {
        return self::hasValidPermalinkStructureStatic();
    }

    /**
     * Static version for activation context.
     *
     * @return bool True if permalink structure is valid, false otherwise.
     */
    private static function hasValidPermalinkStructureStatic(): bool {
        if (!function_exists('get_option')) {
            return true; // Assume valid if WordPress not loaded
        }
        
        $permalink_structure = get_option('permalink_structure');
        
        // Empty permalink structure means "Plain" permalinks are selected
        // This breaks REST API endpoints as they require pretty permalinks
        return !empty($permalink_structure);
    }

    /**
     * Shows a notification to guide users to configure permalink structure.
     */
    private function showPermalinkNotification(): void {
        self::showPermalinkNotificationStatic();
    }

    /**
     * Static version for activation context.
     */
    private static function showPermalinkNotificationStatic(): void {
        // Fail gracefully if admin functions not available
        if (!function_exists('admin_url') || !function_exists('esc_url')) {
            return;
        }

        $notification_manager = NotificationManager::instance();
        if (!$notification_manager) {
            return;
        }

        // Use hardcoded message for critical activation scenarios
        $message = sprintf('<strong>%s</strong> requires pretty permalinks to function properly.<br><br>Please go to <a href="' . esc_url(admin_url('options-permalink.php')) . '"><strong>Settings → Permalinks</strong></a> and select <strong>"Post name"</strong> or any other structure except "Plain", then click <strong>Save Changes</strong>.',
            RANKINGCOACH_BRAND_NAME
        );

        if (!$notification_manager->has_notification(self::NOTIFICATION_ID)) {
            $notification_manager->add(
                $message,
                [
                    'id' => self::NOTIFICATION_ID,
                    'type' => Notification::ERROR,
                    'screen' => Notification::SCREEN_ANY,
                    'dismissible' => true,
                    'persistent' => true,
                ]
            );
        }
    }

    /**
     * Removes the permalink notification if permalink structure is now valid.
     */
    public static function clearPermalinkNotificationIfValid(): void {
        if (self::hasValidPermalinkStructureStatic()) {
            $notification_manager = NotificationManager::instance();
            if ($notification_manager) {
                $notification_manager->remove_by_id(self::NOTIFICATION_ID);
            }
        }
    }

    /**
     * Hook to check permalink structure on admin init.
     */
    public static function initHooks(): void {
        add_action('admin_init', [self::class, 'clearPermalinkNotificationIfValid'], 20);
    }
}
