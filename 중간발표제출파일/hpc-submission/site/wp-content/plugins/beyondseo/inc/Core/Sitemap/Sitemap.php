<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Sitemap;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;

/**
 * Handles our sitemaps.
 */
class Sitemap {

    /** @var string[] Post meta keys that change which URLs belong in the sitemap. */
    private const INVALIDATING_META_KEYS = [
        MetaTags::META_NOINDEX_FOR_PAGE,
        MetaTags::META_EXCLUDE_SITEMAP_FOR_PAGE,
    ];

    public function __construct() {
        $this->disableSitemap();
        $this->registerCleanupHooks();
    }

    /**
     * Register cleanup hooks for plugin deactivation
     */
    private function registerCleanupHooks(): void
    {
        register_deactivation_hook(RANKINGCOACH_FILE, function() {
            // Clean up rewrite rules
            delete_option(BaseConstants::OPTION_SITEMAP_INITIALIZED);
            delete_option(BaseConstants::OPTION_SITEMAP_TRAILING_SLASH_FIXED);
            delete_option(BaseConstants::OPTION_FLUSH_REWRITE_RULES);

            // Re-enable WordPress core sitemap
            remove_filter('wp_sitemaps_enabled', '__return_false');

            // Remove generated sitemap files
            self::deleteSitemapFiles();

            // Flush rewrite rules to clean up
            flush_rewrite_rules();
        });
    }

    /**
     * Initializes the sitemap functionality.
     */
    public function init(): void
    {
        // Schedule sitemap regeneration
        add_action('rankingcoach_static_sitemap_regeneration', function () {
            self::invalidate();
            (new Generator())->generate();
        });

        // Invalidate the sitemap when a post/page is published, unpublished or updated
        add_action('transition_post_status', function ($new_status, $old_status, $post) {
            if (($new_status === 'publish' || $old_status === 'publish')
                && in_array($post->post_type, EntryBuilder::POST_TYPES, true)
                && SettingsManager::instance()->sitemap->enabled
            ) {
                self::invalidate();
            }
        }, 10, 3);

        // Invalidate the sitemap when a post/page is permanently deleted
        add_action('deleted_post', function ($post_id, $post) {
            if ($post && in_array($post->post_type, EntryBuilder::POST_TYPES, true)
                && SettingsManager::instance()->sitemap->enabled
            ) {
                self::invalidate();
            }
        }, 10, 2);

        // Invalidate the sitemap when per-page robots/sitemap settings change,
        // so pages set to noindex or excluded drop out (and reappear when reverted)
        $onRobotsMetaChange = function ($meta_id, $post_id, $meta_key) {
            if (in_array($meta_key, self::INVALIDATING_META_KEYS, true)
                && SettingsManager::instance()->sitemap->enabled
            ) {
                self::invalidate();
            }
        };
        add_action('added_post_meta', $onRobotsMetaChange, 10, 3);
        add_action('updated_post_meta', $onRobotsMetaChange, 10, 3);
        add_action('deleted_post_meta', $onRobotsMetaChange, 10, 3);

        // Prevent WordPress from adding trailing slashes to sitemap.xml
        add_filter('redirect_canonical', function($redirect_url, $requested_url) {
            if (preg_match('/sitemap\.xml$/', $requested_url)) {
                return false;
            }
            return $redirect_url;
        }, 10, 2);

        // Add rewrite rule for sitemap.xml
        add_action('init', function() {
            $sitemap_rule = 'sitemap\.xml';

            // Handle multisite subdirectory installations
            if (is_multisite() && !is_subdomain_install()) {
                $current_blog = get_blog_details();
                if ($current_blog && $current_blog->path !== '/') {
                    $sitemap_rule = trim($current_blog->path, '/') . '/sitemap\.xml';
                }
            }

            // Handle both with and without trailing slash
            add_rewrite_rule('^' . $sitemap_rule . '$', 'index.php?rankingcoach_sitemap=general', 'top');
            add_rewrite_rule('^' . $sitemap_rule . '/$', 'index.php?rankingcoach_sitemap=general', 'top');
            add_rewrite_tag('%rankingcoach_sitemap%', '([^&]+)');

            // Check if we need to flush rewrite rules
            if (get_option(BaseConstants::OPTION_FLUSH_REWRITE_RULES, false)) {
                flush_rewrite_rules();
                delete_option(BaseConstants::OPTION_FLUSH_REWRITE_RULES);
            }
        });

        // Handle sitemap requests
        add_action('template_redirect', function() {
            global $wp_query;

            if (isset($wp_query->query_vars['rankingcoach_sitemap'])) {
                $type = $wp_query->query_vars['rankingcoach_sitemap'];

                // Generate sitemap if it doesn't exist
                $upload_dir = wp_upload_dir();
                $sitemap_path = trailingslashit($upload_dir['basedir']) . "sitemap-$type.xml";

                if (!file_exists($sitemap_path)) {
                    $xml = (new Generator())->generate($type);
                } else {
                    $xml = file_get_contents($sitemap_path);
                }

                // Output the sitemap
                header('Content-Type: application/xml; charset=UTF-8');
                if ($xml && preg_match('/<\?xml/i', $xml)) {
                    echo $xml;
                } else {
                    // Log error or handle invalid XML
                    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                        error_log('[BeyondSEO] DEBUG: Invalid sitemap XML detected');
                    }
                    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
                }
                exit;
            }
        });
    }

    /**
     * Invalidates the generated sitemap so the next sitemap request serves fresh data.
     *
     * Clears the cached sitemap entries and removes the static sitemap files;
     * the request handler regenerates the sitemap on demand once the file is gone.
     */
    public static function invalidate(): void
    {
        (new EntryBuilder())->clearCache();
        self::deleteSitemapFiles();
    }

    /**
     * Removes all generated static sitemap files from the uploads directory.
     */
    private static function deleteSitemapFiles(): void
    {
        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            return;
        }

        $pattern = trailingslashit($upload_dir['basedir']) . 'sitemap-*.xml';
        $sitemap_files = glob($pattern);

        if ($sitemap_files) {
            foreach ($sitemap_files as $file) {
                if (file_exists($file) && wp_is_writable($file)) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Checks if sitemap exists (either as physical file or via rewrite rules)
     */
    public static function sitemapExists(string $url): bool
    {
        // Check if our custom sitemap is enabled
        if (SettingsManager::instance()->sitemap->enabled) {
            // Parse URL to check if it's requesting sitemap.xml
            $parsed = wp_parse_url($url);
            $path = $parsed['path'] ?? '';
            
            // Handle multisite subdirectory installations
            if (is_multisite() && !is_subdomain_install()) {
                $current_blog = get_blog_details();
                if ($current_blog && $current_blog->path !== '/') {
                    $expected_path = rtrim($current_blog->path, '/') . '/sitemap.xml';
                } else {
                    $expected_path = '/sitemap.xml';
                }
            } else {
                $expected_path = '/sitemap.xml';
            }
            
            // Check if URL matches our sitemap path
            if ($path === $expected_path) {
                return true;
            }
        }
        
        // Check if physical file exists
        $upload_dir = wp_upload_dir();
        $sitemap_path = trailingslashit($upload_dir['basedir']) . 'sitemap-general.xml';
        
        return file_exists($sitemap_path);
    }

    /**
     * Disables the WordPress core sitemap if our sitemap is enabled.
     *
     * This prevents conflicts between the core sitemap and our custom sitemap.
     */
    protected function disableSitemap(): void
    {
        // Only disable WordPress core sitemap if our sitemap IS enabled
        // This fixes the critical logic error - was inverted before
        if (SettingsManager::instance()->sitemap->enabled) {
            remove_action('init', 'wp_sitemaps_get_server');
            add_filter('wp_sitemaps_enabled', '__return_false');
        }
    }
}
