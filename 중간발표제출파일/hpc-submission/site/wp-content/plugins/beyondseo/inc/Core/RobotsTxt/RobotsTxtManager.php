<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\RobotsTxt;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Core\Sitemap\Sitemap;

/**
 * Manages the robots.txt functionality.
 * 
 * This class handles the generation and modification of robots.txt content
 * based on plugin settings, including the ability to include sitemap URLs.
 */
class RobotsTxtManager
{
    /**
     * Singleton instance.
     *
     * @var RobotsTxtManager|null
     */
    private static ?RobotsTxtManager $instance = null;

    /**
     * Settings manager instance.
     *
     * @var SettingsManager
     */
    private SettingsManager $settingsManager;

    /**
     * Class constructor.
     */
    private function __construct() {
        $this->settingsManager = SettingsManager::instance();
    }

    /**
     * Get singleton instance.
     *
     * @return RobotsTxtManager
     */
    public static function getInstance(): RobotsTxtManager {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the robots.txt manager.
     *
     * @return void
     */
    public function init(): void {
        $this->initializeHooks();
    }

    /**
     * Initialize WordPress hooks.
     *
     * @return void
     */
    private function initializeHooks(): void {
        // Only hook into robots.txt if the feature is enabled
        if ($this->isRobotsTxtEnabled()) {
            add_filter('robots_txt', [$this, 'modifyRobotsTxt'], 10, 2);
        }
    }

    /**
     * Check if robots.txt functionality is enabled.
     *
     * @return bool
     */
    private function isRobotsTxtEnabled(): bool {
        return (bool) $this->settingsManager->get_option('enable_robots_txt', true);
    }

    /**
     * Check if sitemap should be included in robots.txt.
     *
     * @return bool
     */
    private function shouldIncludeSitemap(): bool {
        return (bool) $this->settingsManager->get_option('include_sitemap_in_robots', false);
    }

    /**
     * Modify the robots.txt output.
     *
     * @param string $output The current robots.txt output.
     * @param bool   $public Whether the site is public.
     * @return string The modified robots.txt output.
     */
    public function modifyRobotsTxt(string $output, bool $public): string {

        // Only modify if the site is public and robots.txt is enabled
        if (!$this->isRobotsTxtEnabled()) {
            return $output;
        }

        // Ensure default Disallow rules are present (aligned with SEO validation expectations)
        $site_url = wp_parse_url(site_url());
        $path = (!empty($site_url['path'])) ? $site_url['path'] : '';
        $defaultDisallows = [
            "Disallow: $path/wp-includes/",
            "Disallow: $path/wp-content/plugins/",
            "Disallow: $path/wp-login.php",
        ];

        foreach ($defaultDisallows as $rule) {
            if (!str_contains($output, $rule)) {
                $output .= (str_ends_with($output, "\n") ? '' : "\n") . $rule . "\n";
            }
        }

        // Add sitemap URL if enabled
        if ($this->shouldIncludeSitemap()) {
            $sitemapUrl = $this->getSitemapUrl();
            if ($sitemapUrl) {
                // Check if sitemap is not already present to avoid duplicates
                if (!str_contains($output, 'Sitemap:')) {
                    $output .= "\nSitemap: " . esc_url($sitemapUrl) . "\n";
                }
            }
        }

        return $output;
    }

    /**
     * Get the sitemap URL.
     *
     * @return string|false The sitemap URL or false if not available.
     */
    private function getSitemapUrl(): string|false {
        // Try custom sitemap location first
        $customUrl = home_url('/sitemap.xml');
        if (Sitemap::sitemapExists($customUrl)) {
            return $customUrl;
        }

        // Use WordPress core function to get sitemap URL if available (WP 5.5+)
        if (function_exists('get_sitemap_url')) {
            return get_sitemap_url('index');
        }

        // Fallback for older WordPress versions or if sitemaps are disabled
        return $this->getFallbackSitemapUrl();
    }

    /**
     * Get fallback sitemap URL for older WordPress versions.
     *
     * @return string|false The fallback sitemap URL or false if not available.
     */
    private function getFallbackSitemapUrl(): string|false {
        // Check if WordPress sitemaps are enabled
        if (!get_option('blog_public')) {
            return false;
        }

        global $wp_rewrite;

        // Check if permalinks are enabled
        if (!$wp_rewrite || !method_exists($wp_rewrite, 'using_permalinks') || !$wp_rewrite->using_permalinks()) {
            return home_url('/?sitemap=index');
        }

        return home_url('/wp-sitemap.xml');
    }

    /**
     * Enable robots.txt functionality.
     *
     * @return void
     */
    public function enableRobotsTxt(): void {
        $this->settingsManager->update_option('enable_robots_txt', true);
        
        // Re-initialize hooks if not already done
        if (!has_filter('robots_txt', [$this, 'modifyRobotsTxt'])) {
            add_filter('robots_txt', [$this, 'modifyRobotsTxt'], 10, 2);
        }
    }

    /**
     * Disable robots.txt functionality.
     *
     * @return void
     */
    public function disableRobotsTxt(): void {
        $this->settingsManager->update_option('enable_robots_txt', false);
        
        // Remove the filter to stop modifying robots.txt
        remove_filter('robots_txt', [$this, 'modifyRobotsTxt']);
    }

    /**
     * Enable sitemap inclusion in robots.txt.
     *
     * @return void
     */
    public function enableSitemapInRobots(): void {
        $this->settingsManager->update_option('include_sitemap_in_robots', true);
    }

    /**
     * Disable sitemap inclusion in robots.txt.
     *
     * @return void
     */
    public function disableSitemapInRobots(): void {
        $this->settingsManager->update_option('include_sitemap_in_robots', false);
    }

    /**
     * Get the current robots.txt content.
     *
     * @return string The current robots.txt content.
     */
    public function getRobotsTxtContent(): string {
        // Simulate the robots.txt generation process
        $output = "User-agent: *\n";
        $public = (bool) get_option('blog_public');

        $site_url = wp_parse_url(site_url());
        $path = (!empty($site_url['path'])) ? $site_url['path'] : '';

        // Default disallow rules aligned with SEO validation expectations
        $admin_path = wp_parse_url(admin_url(), PHP_URL_PATH);
        $output .= "Disallow: $admin_path\n";
        $output .= 'Allow: ' . admin_url('admin-ajax.php') . "\n";
        $output .= "Disallow: $path/wp-includes/\n";
        $output .= "Disallow: $path/wp-content/plugins/\n";
        $output .= "Disallow: $path/wp-login.php\n";

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Apply the robots_txt filter to get the final content
        return apply_filters('robots_txt', $output, $public);
    }

    /**
     * Check if the current site has a robots.txt file.
     *
     * @return bool True if robots.txt exists, false otherwise.
     */
    public function robotsTxtExists(): bool {
        $robots_url = home_url('/robots.txt');
        $response = wp_remote_head($robots_url);
        
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Get robots.txt URL.
     *
     * @return string The robots.txt URL.
     */
    public function getRobotsTxtUrl(): string {
        return home_url('/robots.txt');
    }

    /**
     * Update robots.txt settings.
     *
     * @param array $settings Array of settings to update.
     * @return bool True on success, false on failure.
     */
    public function updateSettings(array $settings): bool {
        $updated = true;

        if (isset($settings['enable_robots_txt'])) {
            if ($settings['enable_robots_txt']) {
                $this->enableRobotsTxt();
            } else {
                $this->disableRobotsTxt();
            }
        }

        if (isset($settings['include_sitemap_in_robots'])) {
            if ($settings['include_sitemap_in_robots']) {
                $this->enableSitemapInRobots();
            } else {
                $this->disableSitemapInRobots();
            }
        }

        return $updated;
    }

    /**
     * Get current robots.txt settings.
     *
     * @return array Current robots.txt settings.
     */
    public function getSettings(): array {
        return [
            'enable_robots_txt' => $this->isRobotsTxtEnabled(),
            'include_sitemap_in_robots' => $this->shouldIncludeSitemap(),
            'robots_txt_url' => $this->getRobotsTxtUrl(),
            'sitemap_url' => $this->getSitemapUrl(),
        ];
    }
}
