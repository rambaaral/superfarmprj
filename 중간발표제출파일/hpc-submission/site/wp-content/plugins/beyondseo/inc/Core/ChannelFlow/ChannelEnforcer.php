<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\ChannelFlow;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Admin\AdminManager;

/**
 * ChannelEnforcer - Guides distribution channels to their recommended onboarding pages.
 *
 * This class provides soft routing guidance for different distribution channels,
 * suggesting the appropriate onboarding page without blocking access.
 *
 * Routing Guidance:
 * - IONOS/Extendify clients are guided to RegistrationPage (they need to register/connect)
 * - DIRECT clients are guided to RegistrationPage (they need to register first)
 *
 * Note: No access is blocked. All pages remain accessible to all channels.
 *
 * @package RankingCoach\Inc\Core\ChannelFlow
 */
final class ChannelEnforcer
{
    /**
     * Mapping of page names to channels that should be guided to alternative pages.
     * These are soft recommendations, not access restrictions.
     */
    private const RECOMMENDED_REDIRECTS = [
        'registration' => [],
    ];

    /**
     * Mapping of page/channel combinations to their recommended alternative pages.
     * Structure: [page_name => [channel => alternative_page]]
     */
    private const ALTERNATIVE_PAGES = [
        'registration' => [],
    ];

    /**
     * Guide a channel to the recommended page if they are on a non-optimal page.
     * This is a soft redirect — it does not block access.
     *
     * @param string $pageName The page name (e.g., 'registration', 'activation')
     * @param string $channel The detected channel (e.g., 'ionos', 'direct')
     * @return void
     */
    public static function enforcePageAccess(string $pageName, string $channel): void
    {
        // Check if redirect recommendations exist for this page
        if (!isset(self::RECOMMENDED_REDIRECTS[$pageName])) {
            return;
        }

        // Check if the channel should be guided to an alternative page
        if (in_array($channel, self::RECOMMENDED_REDIRECTS[$pageName], true)) {
            // Log the guidance
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    '[BeyondSEO] DEBUG: Guiding %s from %s to recommended page',
                    $channel,
                    $pageName
                ));
            }

            $alternativePage = self::getAlternativePageForChannel($pageName, $channel);
            if($alternativePage !== 'main') {
                $nextStepUrl = AdminManager::getPageUrl($alternativePage);
                wp_safe_redirect($nextStepUrl);
            }
        }
    }

    /**
     * Get the recommended redirect target for a channel.
     *
     * @param string $pageName The page being accessed
     * @param string $channel The channel accessing the page
     * @return string The recommended page slug
     */
    public static function getAlternativePageForChannel(string $pageName, string $channel): string
    {
        // Look up the alternative page
        if (isset(self::ALTERNATIVE_PAGES[$pageName][$channel])) {
            return self::ALTERNATIVE_PAGES[$pageName][$channel];
        }

        // Fallback to dashboard if no alternative is configured
        return 'main';
    }
}