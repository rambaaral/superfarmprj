<?php
/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
/** @noinspection RegExpUnnecessaryNonCapturingGroup */
/** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Helpers\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Seo\Services\SeoOptimiserService;
use Elementor\Plugin;
use Exception;
use Normalizer;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RuntimeException;
use WP_Query;

/**
 * Trait RcContentRetrieveTrait
 *
 * This trait provides a set of methods for handling content-related operations.
 * It includes methods for fetching content, analyzing it, and generating suggestions.
 */
trait RcContentRetrieveTrait
{
    use RcLoggerTrait;

    /**
     * Cache for plugin activation status to avoid repeated checks
     *
     * @var array
     */
    private array $pluginActiveCache = [];

    /**
     * Memoized important pages per siteUrl
     * @var array<string, array>
     */
    private static array $importantPagesCache = [];

    /**
     * Memoized admin pages per siteUrl
     * @var array<string, array>
     */
    private static array $adminPagesCache = [];

    /**
     * RankingCoach meta keys that affect SEO optimization and should invalidate cache
     *
     * @var array
     */
    private array $seoMetaKeys = [
        'rankingcoach_secondary_keywords',
        BaseConstants::META_KEY_SEO_KEYWORDS_TEMPLATE,
        BaseConstants::META_KEY_SEO_KEYWORDS_VARIABLES,
        BaseConstants::META_KEY_PRIMARY_KEYWORD,
        BaseConstants::META_KEY_SEO_KEYWORDS,
        BaseConstants::META_KEY_SEO_TITLE_TEMPLATE,
        BaseConstants::META_KEY_SEO_TITLE_VARIABLES,
        BaseConstants::META_KEY_SEO_TITLE,
        BaseConstants::META_KEY_SEO_DESCRIPTION_TEMPLATE,
        BaseConstants::META_KEY_SEO_DESCRIPTION_VARIABLES,
        BaseConstants::META_KEY_SEO_DESCRIPTION,
    ];

    /**
     * Check if a plugin is active with caching
     *
     * @param string $pluginPath Plugin path (e.g., 'woocommerce/woocommerce.php')
     * @return bool
     */
    private function isPluginActive(string $pluginPath): bool
    {
        if (!isset($this->pluginActiveCache[$pluginPath])) {
            $this->pluginActiveCache[$pluginPath] = function_exists('is_plugin_active') && is_plugin_active($pluginPath);
        }

        return $this->pluginActiveCache[$pluginPath];
    }

    /**
     * Calculate hash of meta values for cache invalidation
     *
     * This method generates a hash based on specific meta fields
     * to ensure cache invalidation when SEO-related postmeta changes, even when
     * post_modified_gmt remains unchanged.
     *
     * @param int $postId The post ID
     * @return string Hash of SEO meta values
     */
    private function getRankingCoachSeoMetaHash(int $postId): string
    {
        $metaValues = [];

        foreach ($this->seoMetaKeys as $metaKey) {
            $metaValues[$metaKey] = get_post_meta($postId, $metaKey, true);
        }

        return md5(serialize($metaValues));
    }

    /**
     * Execute a callback with temporary post status change to 'publish'
     *
     * @param int $postId The post ID
     * @param callable $callback The callback to execute while post is temporarily published
     * @param bool $bypassPostStatus Whether to bypass post status check
     * @return mixed The result of the callback
     */
    private function executeWithTemporaryPublishStatus(int $postId, callable $callback, bool $bypassPostStatus = false): mixed
    {
        $post = $this->getValidatedPost($postId);
        if (!$post) {
            return $callback();
        }

        $original_status = null;

        // Handle non-published posts if bypass is requested
        if ($bypassPostStatus && $post->post_status !== 'publish') {
            // Save the original post status
            $original_status = $post->post_status;

            // Temporarily change the post status to 'publish'
            $dbManager = DatabaseManager::getInstance();
            $postsTable = $dbManager->db()->prefixTable('posts');
            $dbManager->db()->queryRaw(
                "UPDATE {$postsTable} SET post_status = 'publish' WHERE ID = " . (int)$postId
            );

            // Clear post cache to ensure the updated status is used
            clean_post_cache($postId);
            wp_cache_delete($postId, 'posts');
            wp_cache_delete($postId, 'post_meta');
        }

        try {
            // Execute the callback
            return $callback();
        } finally {
            // Restore the original post status if it was changed
            if ($original_status !== null) {
                $dbManager = DatabaseManager::getInstance();
                $postsTable = $dbManager->db()->prefixTable('posts');
                $escapedStatus = $dbManager->db()->escapeValue($original_status);
                $dbManager->db()->queryRaw(
                    "UPDATE {$postsTable} SET post_status = {$escapedStatus} WHERE ID = " . (int)$postId
                );

                // Clear post cache again
                clean_post_cache($postId);
                wp_cache_delete($postId, 'posts');
                wp_cache_delete($postId, 'post_meta');
            }
        }
    }

    /**
     * Deep UTF-8 cleanup for JSON safety.
     * Handles broken encoding, smart punctuation, invisible chars, and control bytes.
     */
    public function sanitize_utf8_for_json($input)
    {
        if ($input === null) {
            return null;
        }

        // Force string
        $input = (string)$input;

        // 1) Ensure valid UTF-8 (fix broken encodings)
        if (!mb_check_encoding($input, 'UTF-8')) {
            $input = mb_convert_encoding($input, 'UTF-8', 'auto');
        }

        // 2) Normalize Unicode (requires intl extension)
        if (class_exists('Normalizer')) {
            $input = Normalizer::normalize($input, Normalizer::FORM_C);
        }

        // 3) Replace smart quotes, dashes, ellipsis, etc.
        $map = [
            // Quotes
            '“' => '"', '”' => '"', '„' => '"', '‟' => '"',
            '‘' => "'", '’' => "'", '‚' => "'", '‛' => "'",

            // Dashes
            '–' => '-', '—' => '-', '―' => '-',

            // Ellipsis
            '…' => '...',

            // Non-breaking spaces
            "\xC2\xA0" => ' ',
            "\xE2\x80\xAF" => ' ',
            "\xE2\x81\x9F" => ' ',

            // Bullets & symbols
            '•' => '*',
            '·' => '*',
            '‣' => '*',
            '⁃' => '*',
        ];
        $input = strtr($input, $map);

        // 4) Remove zero-width and invisible characters
        $input = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}]/u', '', $input);

        // 5) Remove invalid control characters (except \n \r \t)
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);

        // 6) Force valid UTF-8 byte sequences (strip invalid bytes)
        $input = mb_convert_encoding($input, 'UTF-8', 'UTF-8');

        return $input;
    }

    /**
     * Clean content by removing HTML tags, excess whitespace, etc.
     *
     * @param string $content
     * @return string
     */
    public function cleanContent(string $content): string
    {
        // Decode HTML entities
        $cleanText = html_entity_decode($content);

        // Remove HTML tags
        $cleanText = wp_strip_all_tags($cleanText);

        // Remove excess whitespace
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);

        // Sanitize for UTF-8 JSON compatibility
        $cleanText = $this->sanitize_utf8_for_json($cleanText);

        return trim($cleanText);
    }

    /**
     * Gets an array of critical page paths that should be crawlable by search engines.
     *
     * This method intelligently identifies important pages on the WordPress site
     * based on site structure, content types, and active plugins.
     *
     * @return array An array of critical page paths that should be indexed
     */
    public function getCriticalPagePaths(): array
    {
        // Always include the homepage and common essential pages
        $criticalPaths = [
            '/', // Homepage
        ];

        // Add sitemap paths if they exist
        $sitemapPaths = $this->detectSitemapPaths();
        $criticalPaths = array_merge($criticalPaths, $sitemapPaths);

        // Add important WordPress pages based on actual site structure
        $criticalPaths = array_merge($criticalPaths, $this->detectImportantWordPressPages());

        // Add important pages from active plugins
        $criticalPaths = array_merge($criticalPaths, $this->detectPluginImportantPages());

        // Add top-level navigation menu items as they're likely important pages
        $menuPages = $this->detectMainNavigationPages();
        $criticalPaths = array_merge($criticalPaths, $menuPages);

        // Add high-traffic and high-value content
        $contentPaths = $this->detectHighValueContent();
        $criticalPaths = array_merge($criticalPaths, $contentPaths);

        // Remove duplicates and ensure all paths are properly formatted
        $criticalPaths = array_unique(array_map(function($path) {
            return '/' . ltrim($path, '/');
        }, $criticalPaths));

        // Filter to allow customization of critical pages
        return apply_filters('rankingcoach_critical_page_paths', $criticalPaths);
    }

    /**
     * Detects sitemap paths on the site.
     *
     * @return array Array of sitemap paths
     */
    private function detectSitemapPaths(): array
    {
        $sitemapPaths = ['/sitemap.xml'];

        // Check for Yoast SEO sitemap
        if ($this->isPluginActive('wordpress-seo/wp-seo.php')) {
            $sitemapPaths[] = '/sitemap_index.xml';
        }

        // Check for Rank Math sitemap
        if ($this->isPluginActive('seo-by-rank-math/rank-math.php')) {
            $sitemapPaths[] = '/sitemap_index.xml';
        }

        // Check for All in One SEO sitemap
        if ($this->isPluginActive('all-in-one-seo-pack/all_in_one_seo_pack.php')) {
            $sitemapPaths[] = '/sitemap.xml.gz';
        }

        return $sitemapPaths;
    }

    /**
     * Detects important WordPress pages based on actual site structure.
     *
     * @return array Array of important WordPress page paths
     */
    private function detectImportantWordPressPages(): array
    {
        $importantPages = [];

        // Common important pages - only add if they exist
        $potentialPages = ['about', 'contact', 'blog', 'news', 'faq', 'privacy-policy', 'terms-of-service', 'terms'];

        foreach ($potentialPages as $pageName) {
            // Check if page exists by slug
            $page = get_page_by_path($pageName);
            if ($page) {
                $importantPages[] = wp_make_link_relative(get_permalink($page->ID));
            }
        }

        // Add blog page if set in WordPress settings
        $blogPageId = get_option('page_for_posts');
        if ($blogPageId) {
            $importantPages[] = wp_make_link_relative(get_permalink($blogPageId));
        }

        // Add shop page if WooCommerce is active
        if ($this->isPluginActive('woocommerce/woocommerce.php')) {
            $shopPageId = wc_get_page_id('shop');
            if ($shopPageId > 0) {
                $importantPages[] = wp_make_link_relative(get_permalink($shopPageId));
            }
        }

        return $importantPages;
    }

    /**
     * Detects important pages from active plugins.
     *
     * @return array Array of important plugin page paths
     */
    private function detectPluginImportantPages(): array
    {
        $pluginPages = [];

        // Check for WooCommerce
        if ($this->isPluginActive('woocommerce/woocommerce.php')) {
            // Add product category pages
            $productCategories = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => true,
                'parent' => 0, // Only top-level categories
                'number' => 10, // Limit to top 10 categories
            ]);

            if (!is_wp_error($productCategories)) {
                foreach ($productCategories as $category) {
                    $pluginPages[] = wp_make_link_relative(get_term_link($category));
                }
            }

            // Add top products
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            $topProducts = get_posts([
                'post_type' => 'product',
                'posts_per_page' => 10,
                'meta_key' => 'total_sales',
                'orderby' => 'meta_value_num',
                'order' => 'DESC',
                //'post_status' => 'publish'
            ]);
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key

            foreach ($topProducts as $product) {
                $pluginPages[] = wp_make_link_relative(get_permalink($product->ID));
            }
        }

        // Check for Easy Digital Downloads
        if ($this->isPluginActive('easy-digital-downloads/easy-digital-downloads.php')) {
            // Add download category pages
            $downloadCategories = get_terms([
                'taxonomy' => 'download_category',
                'hide_empty' => true,
                'parent' => 0,
                'number' => 10,
            ]);

            if (!is_wp_error($downloadCategories)) {
                foreach ($downloadCategories as $category) {
                    $pluginPages[] = wp_make_link_relative(get_term_link($category));
                }
            }
        }

        // Check for LearnDash
        if ($this->isPluginActive('sfwd-lms/sfwd_lms.php')) {
            // Add course pages
            $courses = get_posts([
                'post_type' => 'sfwd-courses',
                'posts_per_page' => 10,
                //'post_status' => 'publish'
            ]);

            foreach ($courses as $course) {
                $pluginPages[] = wp_make_link_relative(get_permalink($course->ID));
            }
        }

        return $pluginPages;
    }

    /**
     * Detects main navigation menu pages.
     *
     * @return array Array of main navigation menu page paths
     */
    private function detectMainNavigationPages(): array
    {
        $menuPages = [];

        // Get locations of all menus
        $menuLocations = get_nav_menu_locations();

        // Look for primary/main/header menu
        $primaryMenuLocations = ['primary', 'main', 'header-menu', 'main-menu', 'primary-menu'];

        foreach ($primaryMenuLocations as $location) {
            if (isset($menuLocations[$location])) {
                $menu = wp_get_nav_menu_object($menuLocations[$location]);
                if ($menu) {
                    $menuItems = wp_get_nav_menu_items($menu->term_id);
                    if ($menuItems) {
                        foreach ($menuItems as $item) {
                            // Only include top-level items (no parent)
                            if ($item->menu_item_parent == 0) {
                                $menuPages[] = wp_make_link_relative($item->url);
                            }
                        }
                    }
                    break; // Found a primary menu, no need to check others
                }
            }
        }

        return $menuPages;
    }

    /**
     * Detects high-value content based on various metrics.
     *
     * @return array Array of high-value content paths
     */
    private function detectHighValueContent(): array
    {
        $contentPaths = [];

        // Get popular posts based on comment count (engagement metric)
        $popularPosts = get_posts([
            'post_type' => ['post', 'page'],
            'posts_per_page' => 10,
            'orderby' => 'comment_count',
            'order' => 'DESC',
            //'post_status' => 'publish'
        ]);

        foreach ($popularPosts as $post) {
            $contentPaths[] = wp_make_link_relative(get_permalink($post->ID));
        }

        // Get recent posts
        $recentPosts = get_posts([
            'post_type' => ['post'],
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            //'post_status' => 'publish'
        ]);

        foreach ($recentPosts as $post) {
            $contentPaths[] = wp_make_link_relative(get_permalink($post->ID));
        }

        // Get category archives for categories with most posts
        $popularCategories = get_terms([
            'taxonomy' => 'category',
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 5,
            'hide_empty' => true
        ]);

        if (!is_wp_error($popularCategories)) {
            foreach ($popularCategories as $category) {
                $contentPaths[] = wp_make_link_relative(get_term_link($category));
            }
        }

        return $contentPaths;
    }

    /**
     * Retrieves a list of important pages that should be indexed by search engines.
     *
     * @param string $siteUrl The site URL
     * @return array List of important page URLs
     */
    public function getImportantPages(string $siteUrl): array
    {
        $key = md5($siteUrl);
        if (isset(self::$importantPagesCache[$key])) {
            return self::$importantPagesCache[$key];
        }

        $paths = $this->getCriticalPagePaths();
        $urls = [];
        foreach ($paths as $path) {
            $urls[] = rtrim($siteUrl, '/') . $path;
        }

        self::$importantPagesCache[$key] = $urls;
        return $urls;
    }

    /**
     * Retrieves a comprehensive list of admin and private pages that should not be indexed by search engines.
     *
     * This method intelligently identifies administrative, private, and sensitive pages
     * based on WordPress core, active plugins, and common web application patterns.
     *
     * @param string $siteUrl The site URL
     * @return array List of admin and private page URLs that should not be indexed
     */
    public function getAdminPages(string $siteUrl): array
    {
        $siteUrl = rtrim($siteUrl, '/');
        $key = md5($siteUrl);
        if (isset(self::$adminPagesCache[$key])) {
            return self::$adminPagesCache[$key];
        }

        // Core WordPress admin pages that should never be indexed
        $adminPages = [
            // WordPress core admin areas
            admin_url(),
            $siteUrl . '/wp-login.php',
            $siteUrl . '/wp-register.php',
            $siteUrl . '/wp-includes/',
            $siteUrl . '/wp-content/plugins/',
            $siteUrl . '/wp-content/themes/',
            $siteUrl . '/wp-json/',
            $siteUrl . '/xmlrpc.php',

            // Common sensitive paths
            $siteUrl . '/login/',
            $siteUrl . '/register/',
            $siteUrl . '/signup/',
            $siteUrl . '/lost-password/',
            $siteUrl . '/reset-password/',
            $siteUrl . '/password-reset/',
            $siteUrl . '/account/',
            $siteUrl . '/my-account/',
            $siteUrl . '/profile/',
            $siteUrl . '/user/',
            $siteUrl . '/dashboard/',

            // Search, feed, and utility pages
            $siteUrl . '/search/',
            $siteUrl . '/feed/',
            $siteUrl . '/rss/',
            $siteUrl . '/atom/',
            $siteUrl . '/page/*/print/',
            $siteUrl . '/trackback/',
            $siteUrl . '/cgi-bin/',
        ];
//
//        // Add e-commerce pages
//        $adminPages = array_merge($adminPages, $this->detectEcommerceAdminPages($siteUrl));
//
//        // Add membership plugin pages
//        $adminPages = array_merge($adminPages, $this->detectMembershipPluginPages($siteUrl));
//
//        // Add form and utility plugin pages
//        $adminPages = array_merge($adminPages, $this->detectFormAndUtilityPages($siteUrl));
//
//        // Add custom post type admin pages
//        $adminPages = array_merge($adminPages, $this->detectCustomPostTypeAdminPages($siteUrl));

        // Filter to allow customization
        $result = apply_filters('rankingcoach_admin_pages', $adminPages);
        self::$adminPagesCache[$key] = $result;
        return $result;
    }

    /**
     * Detects e-commerce admin and checkout pages that should not be indexed.
     *
     * @param string $siteUrl The site URL
     * @return array Array of e-commerce admin page URLs
     */
    private function detectEcommerceAdminPages(string $siteUrl): array
    {
        $ecommercePages = [];

        // Common e-commerce pages regardless of plugin
        $commonEcommercePages = [
            '/cart/',
            '/checkout/',
            '/basket/',
            '/shopping-cart/',
            '/order-received/',
            '/order-confirmation/',
            '/thank-you/',
            '/payment/',
            '/payment-confirmation/',
            '/payment-failed/',
            '/payment-success/',
        ];

        foreach ($commonEcommercePages as $page) {
            $ecommercePages[] = $siteUrl . $page;
        }

        // WooCommerce specific pages
        if ($this->isPluginActive('woocommerce/woocommerce.php')) {
            $woocommercePages = [
                '/cart/',
                '/checkout/',
                '/my-account/',
                '/my-account/orders/',
                '/my-account/view-order/',
                '/my-account/downloads/',
                '/my-account/edit-account/',
                '/my-account/edit-address/',
                '/my-account/payment-methods/',
                '/my-account/lost-password/',
                '/my-account/customer-logout/',
                '/wc-api/',
                '/product/*/reviews/',
            ];

            // Get actual WooCommerce page URLs
            $wooPageIds = [
                'cart' => wc_get_page_id('cart'),
                'checkout' => wc_get_page_id('checkout'),
                'myaccount' => wc_get_page_id('myaccount'),
            ];

            foreach ($wooPageIds as $key => $pageId) {
                if ($pageId > 0) {
                    $ecommercePages[] = wp_make_link_relative(get_permalink($pageId));
                }
            }

            foreach ($woocommercePages as $page) {
                $ecommercePages[] = $siteUrl . $page;
            }
        }

        // Easy Digital Downloads specific pages
        if ($this->isPluginActive('easy-digital-downloads/easy-digital-downloads.php')) {
            $eddPages = [
                '/checkout/',
                '/purchase-confirmation/',
                '/purchase-history/',
                '/transaction-failed/',
                '/downloads/',
            ];

            foreach ($eddPages as $page) {
                $ecommercePages[] = $siteUrl . $page;
            }
        }

        return $ecommercePages;
    }

    /**
     * Detects membership and user account plugin pages that should not be indexed.
     *
     * @param string $siteUrl The site URL
     * @return array Array of membership plugin page URLs
     */
    private function detectMembershipPluginPages(string $siteUrl): array
    {
        $membershipPages = [];

        // MemberPress
        if ($this->isPluginActive('memberpress/memberpress.php')) {
            $memberPressPages = [
                '/login/',
                '/register/',
                '/account/',
                '/members/',
                '/member/',
                '/membership/',
                '/memberships/',
                '/thank-you/',
                '/protected-content/',
            ];

            foreach ($memberPressPages as $page) {
                $membershipPages[] = $siteUrl . $page;
            }
        }

        // LearnDash
        if ($this->isPluginActive('sfwd-lms/sfwd_lms.php')) {
            $learnDashPages = [
                '/courses/my-courses/',
                '/lessons/my-lessons/',
                '/quizzes/my-quizzes/',
                '/profile/',
                '/course-registration/',
            ];

            foreach ($learnDashPages as $page) {
                $membershipPages[] = $siteUrl . $page;
            }
        }

        // BuddyPress
        if ($this->isPluginActive('buddypress/bp-loader.php')) {
            $buddyPressPages = [
                '/members/',
                '/activity/',
                '/groups/',
                '/register/',
                '/activate/',
            ];

            foreach ($buddyPressPages as $page) {
                $membershipPages[] = $siteUrl . $page;
            }
        }

        return $membershipPages;
    }

    /**
     * Detects form and utility plugin pages that should not be indexed.
     *
     * @param string $siteUrl The site URL
     * @return array Array of form and utility plugin page URLs
     */
    private function detectFormAndUtilityPages(string $siteUrl): array
    {
        $utilityPages = [];

        // Contact Form 7
        if ($this->isPluginActive('contact-form-7/wp-contact-form-7.php')) {
            $utilityPages[] = $siteUrl . '/contact-form-7-submission/';
        }

        // Gravity Forms
        if ($this->isPluginActive('gravityforms/gravityforms.php')) {
            $utilityPages[] = $siteUrl . '/gravity-forms-submission/';
            $utilityPages[] = $siteUrl . '/gf_page/';
        }

        // WP Forms
        if ($this->isPluginActive('wpforms-lite/wpforms.php') || $this->isPluginActive('wpforms/wpforms.php')) {
            $utilityPages[] = $siteUrl . '/wpforms-submission/';
        }

        // Print pages and other utility pages
        $utilityPages[] = $siteUrl . '/print/';
        $utilityPages[] = $siteUrl . '/pdf/';
        $utilityPages[] = $siteUrl . '/amp/';

        return $utilityPages;
    }

    /**
     * Detects custom post type admin pages that should not be indexed.
     *
     * @param string $siteUrl The site URL
     * @return array Array of custom post type admin page URLs
     */
    private function detectCustomPostTypeAdminPages(string $siteUrl): array
    {
        $customPostTypePages = [];

        // Get all registered post types
        $postTypes = get_post_types([
            'public' => true,
            '_builtin' => false,
        ], 'objects');

        foreach ($postTypes as $postType) {
            // Skip common content post types that should be indexed
            if (in_array($postType->name, ['product', 'download', 'course', 'lesson', 'quiz'])) {
                continue;
            }

            // Check if this post type has admin/edit capabilities
            if (isset($postType->cap->edit_posts) && current_user_can($postType->cap->edit_posts)) {
                // Add edit pages for this post type
                $customPostTypePages[] = admin_url('edit.php?post_type=' . $postType->name);
                $customPostTypePages[] = admin_url('post-new.php?post_type=' . $postType->name);
            }
        }

        return $customPostTypePages;
    }

    /**
     * Detects duplicate content for a specific post.
     * In a real implementation, this would make an API call to an external service.
     *
     * @param int $postId The post ID to analyze
     * @param string $postUrl The URL of the post
     * @return array Duplicate content analysis results
     */
    public function detectDuplicateContentOnLocal(int $postId, string $postUrl): array
    {
        // Use a helper to get the post-object safely
        $currentPost = $this->getValidatedPost($postId);
        if (!$currentPost) {
            return [];
        }

        // Get the content hash (requires this to be stored on post save/update)
        $postContentHash = get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_CONTENT_HASH, true);

        $duplicatePages = [];

        // Query for other posts with the same content hash (if hash exists)
        if (!empty($postContentHash)) {
            // Use get_posts directly or fetchPosts helper if it's efficient
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            $otherPostsWithSameHash = get_posts([
                'post_type' => ['post', 'page'],
                //'post_status' => 'publish',
                'posts_per_page' => -1,
                //'post__not_in' => [$postId],
                'meta_key' => BaseConstants::OPTION_ANALYSIS_CONTENT_HASH,
                'meta_value' => $postContentHash,
                'fields' => 'ids',
                'no_found_rows' => true,
            ]);
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value

            foreach ($otherPostsWithSameHash as $otherPostId) {

                // Skip current post explicitly (VIP-safe)
                if ((int) $otherPostId === $postId) {
                    continue;
                }

                $duplicatePost = $this->getValidatedPost($otherPostId);
                if (!$duplicatePost) continue;

                $duplicateUrl = get_permalink($otherPostId);

                // Fetch HTML to check canonical (now using the internal fetch helper)
                $duplicateSeoMetaHash = $this->getRankingCoachSeoMetaHash($otherPostId);
                $duplicateHtmlResult = $this->fetchInternalUrlContent($duplicateUrl, [
                    'seo_meta_hash' => $duplicateSeoMetaHash
                ]);
                $duplicateCanonicalUrl = null;

                if (!is_wp_error($duplicateHtmlResult)) {
                    // Assumes extractCanonicalUrl is defined elsewhere
                    $duplicateCanonicalUrl = $this->extractCanonicalUrl($duplicateHtmlResult, $otherPostId);
                } else {
                    // Log error about failing to fetch duplicate URL
                    $this->log("Failed to fetch duplicate URL $duplicateUrl for canonical check. Error: " . $duplicateHtmlResult->get_error_message(), 'ERROR');
                }

                $duplicatePages[] = [
                    'id' => $otherPostId,
                    'url' => $duplicateUrl,
                    'similarity' => 1.0,
                    'title' => $duplicatePost->post_title,
                    'has_canonical' => !empty($duplicateCanonicalUrl),
                    'canonical_url' => $duplicateCanonicalUrl,
                    'canonical_points_to_original' => $duplicateCanonicalUrl !== null && $this->normalizeUrl($duplicateCanonicalUrl) === $this->normalizeUrl($postUrl),
                ];
            }
        }

        // Analyze the current URL for parameter/path patterns (Assumes these methods are defined elsewhere)
        $parameterBasedDuplicates = $this->analyzeUrlForParameterDuplicates($postUrl);
        $pathBasedDuplicates = $this->analyzeUrlForPathDuplicates($postUrl);

        // Combine findings (Note: Parameter/path-based are potential issues, not confirmed duplicates)
        $allIssuesDetected = array_merge($duplicatePages, $parameterBasedDuplicates, $pathBasedDuplicates);

        // Build recommendations based on the findings
        $recommendations = [];
        if (!empty($allIssuesDetected)) {
            $recommendations[] = 'Review the pages listed as duplicates or potential duplicates.';
        }

        if (!empty($duplicatePages)) {
            $recommendations[] = 'For identical content (' . count($duplicatePages) . ' page(s) found), either remove the duplicate page, consolidate the content, or ensure the duplicate page has a canonical tag pointing to the preferred version (' . $postUrl . ').';
        }

        if (!empty($parameterBasedDuplicates)) {
            $recommendations[] = 'The URL contains parameters (' . implode(', ', array_column($parameterBasedDuplicates, 'parameter')) . ') that could cause duplicate content issues if not handled. Ensure canonical tags are correct or use robots.txt/Search Console to handle these parameters.';
        }

        if (!empty($pathBasedDuplicates)) {
            $recommendations[] = 'The URL structure or related paths (' . implode(', ', array_column($pathBasedDuplicates, 'path_pattern')) . ') might lead to duplicate content. Ensure canonical tags are correct or review site structure/configuration (e.g., pagination, archives, print versions).';
        }

        return [
            'has_issues' => !empty($allIssuesDetected),
            'identical_duplicate_count' => count($duplicatePages),
            'url_pattern_issue_count' => count($parameterBasedDuplicates) + count($pathBasedDuplicates),
            'identical_duplicate_pages' => $duplicatePages,
            'similar_pages' => [],
            'parameter_based_issues' => $parameterBasedDuplicates,
            'path_based_issues' => $pathBasedDuplicates,
            'recommendations' => array_unique($recommendations)
        ];
    }

    /**
     * Get post-content using a primary strategy with fallbacks.
     *
     * Enhanced with SEO meta hash for cache invalidation when
     * postmeta changes without affecting post_modified_gmt.
     *
     * @param int $postId The post-ID.
     * @param string $extractionMethod Preferred method ('rendered_html', 'enhanced_internal', 'filtered_db', 'raw_db').
     * @param bool $bypassPostStatus Whether to bypass post status check for non-published posts
     * @return string The extracted content (raw HTML or text depending on internal steps and cleanContent usage).
     */
    public function getContentAggregated(int $postId, string $extractionMethod = 'rendered_html', bool $bypassPostStatus = true): string
    {
        $post = $this->getValidatedPost($postId);
        if (!$post) {
            $this->log("getContentAggregated failed: Post ID $postId not found.", 'WARNING');
            return '';
        }

        // Used for later cache invalidation
        $latestPostModified = $post->post_modified_gmt;
        $seoMetaHash = $this->getRankingCoachSeoMetaHash($postId);

        $content = '';

        // Define the ordered list of methods to try based on the preferred method
        $methods_to_try = match ($extractionMethod) {
            'enhanced_internal' => ['enhanced_internal', 'filtered_db', 'raw_db', 'rendered_html'], // Rendered as fallback
            'filtered_db'       => ['filtered_db', 'enhanced_internal', 'raw_db', 'rendered_html'], // Enhanced/Rendered as fallbacks
            'raw_db'            => ['raw_db', 'filtered_db', 'enhanced_internal', 'rendered_html'], // All others as fallbacks
            default             => ['rendered_html', 'enhanced_internal', 'filtered_db', 'raw_db'], // Default to a rendered_html chain
        };

        foreach ($methods_to_try as $method) {
            switch ($method) {
                case 'rendered_html':
                    $url = get_permalink($postId);
                    if ($url) {
                        $htmlResult = $this->executeWithTemporaryPublishStatus($postId, function() use ($url, $postId, $latestPostModified, $seoMetaHash) {
                            return $this->fetchInternalUrlContent($url, [
                                'post_id' => $postId,
                                'post_modified_gmt' => $latestPostModified,
                                'seo_meta_hash' => $seoMetaHash,
                            ]);
                        }, $bypassPostStatus);

                        if (!is_wp_error($htmlResult)) {
                            $content = $htmlResult;
                            //$mainContentHtml = $this->extractMainContentFromHtml($htmlResult);
                            if (empty($content)) {
                                $this->log("getContentAggregated: Extraction from rendered HTML failed for post ID $postId. Trying next method.", 'DEBUG');
                            }
                        } else {
                            $this->log("getContentAggregated: Failed to fetch rendered HTML for post ID $postId. Error: " . $htmlResult->get_error_message(), 'DEBUG');
                        }
                    }
                    break;

                case 'enhanced_internal':
                    $content = $this->getEnhancedAndProtectedContentInternal($postId, $bypassPostStatus);
                    if (empty($content)) {
                        $this->log("getContentAggregated: Enhanced internal extraction failed for post ID $postId. Trying next method.", 'DEBUG');
                    }
                    break;

                case 'filtered_db':
                    $content = $this->getFilteredPostContent($postId);
                    if (!empty($content)) {
                        $content = $this->cleanContent($content); // Clean the filtered content
                    } else {
                        $this->log("getContentAggregated: Filtered DB extraction failed for post ID $postId. Trying next method.", 'DEBUG');
                    }
                    break;

                case 'raw_db':
                    $content = $this->getRawPostContent($postId);
                    if (!empty($content)) {
                        $content = $this->cleanContent($content); // Clean the raw content
                    } else {
                        $this->log("getContentAggregated: Raw DB extraction failed for post ID $postId. Trying next method.", 'DEBUG');
                    }
                    break;
            }

            if (!empty($content)) {
                return $content;
            }
        }

        // If all methods fail
        $this->log("getContentAggregated failed: Could not extract content for post ID $postId using any method.", 'ERROR');
        return '';
    }

    /**
     * Combines getEnhancedPageContent and getProtectedContent logic internally.
     * Returns cleaned content.
     *
     * @param int $postId
     * @param bool $bypassPostStatus Whether to bypass post status check for non-published posts
     * @return string
     */
    protected function getEnhancedAndProtectedContentInternal(int $postId, bool $bypassPostStatus = false): string
    {
        $post = $this->getValidatedPost($postId);
        if (!$post) {
            return '';
        }

        return $this->executeWithTemporaryPublishStatus($postId, function() use ($postId) {
            // Step 1: Try to bypass protection filters
            $cleanup_filters = $this->applyProtectionBypassFilters();

            try {
                $content = '';

                // Step 2: Try page-builder-specific extraction first *after* protection bypass
                $content = $this->extractPageBuilderContent($postId);

                // Fallback if no specific builder content was found
                if (empty($content)) {
                    $content = $this->getFilteredPostContent($postId);
                }

                // Clean the obtained content
                return $content;

            } finally {
                // Step 3: Ensure protection bypass filters are removed
                if (is_callable($cleanup_filters)) {
                    $cleanup_filters();
                }
            }
        }, $bypassPostStatus);
    }

    /**
     * Get content for non-published posts by simulating published state
     * Uses a multi-strategy approach that works across different themes and page builders
     *
     * @param int $postId The post ID
     * @return string The extracted content
     */
    public function getFullRenderedPageRegardlessOfStatus(int $postId): string
    {
        $post = $this->getValidatedPost($postId);
        if (!$post) {
            $this->log("getFullRenderedPageRegardlessOfStatus failed: Post ID $postId not found.", 'WARNING');
            return '';
        }

        $seoMetaHash = $this->getRankingCoachSeoMetaHash($postId);

        // Strategy 1: Temporarily publish the post and use standard content extraction
        $content = $this->executeWithTemporaryPublishStatus($postId, function() use ($postId, $seoMetaHash) {
            // Strategy 1a: Try page builder specific extraction
            $content = $this->extractPageBuilderContent($postId);

            // Strategy 1b: If no page builder content, use enhanced filtered content
            if (empty($content)) {
                $content = $this->getEnhancedFilteredContent($postId);
            }

            // Strategy 1c: Fallback to internal URL fetch if available
            if (empty($content)) {
                $url = get_permalink($postId);
                if ($url && !is_wp_error($url)) {
                    $htmlResult = $this->fetchInternalUrlContent($url, [
                        'timeout' => 10,
                        'headers' => [
                            'X-RankingCoach-Preview' => '1'
                        ],
                        'seo_meta_hash' => $seoMetaHash
                    ]);

                    if (!is_wp_error($htmlResult)) {
                        $content = $this->extractMainContentFromHtml($htmlResult);
                    }
                }
            }

            return $content;
        }, true);

        if (empty($content)) {
            $this->log("getFullRenderedPageRegardlessOfStatus: All strategies failed for post ID $postId", 'WARNING');
        }

        return $content;
    }

    /**
     * Extract content from page builders with enhanced detection
     *
     * @param int $postId The post ID
     * @return string The extracted content
     */
    private function extractPageBuilderContent(int $postId): string
    {
        $content = '';

        // Elementor
        if (class_exists('Elementor\Plugin')) {
            try {
                $content = WordpressHelpers::render_elementor_content($postId);
            } catch (Exception $e) {
                $this->log("Elementor content extraction failed for post $postId: " . $e->getMessage(), 'DEBUG');
            }
        }

        // Divi Builder
        if (empty($content) && function_exists('et_pb_is_pagebuilder_used') && et_pb_is_pagebuilder_used($postId)) {
            $raw_content = $this->getRawPostContent($postId);
            if (!empty($raw_content)) {
                // Divi uses shortcodes, process them
                $content = do_shortcode($raw_content);
            }
        }

        // WPBakery Page Builder
        if (empty($content) && defined('WPB_VC_VERSION')) {
            $raw_content = $this->getRawPostContent($postId);
            if (!empty($raw_content) && strpos($raw_content, '[vc_') !== false) {
                $content = do_shortcode($raw_content);
            }
        }

        // Beaver Builder
        if (empty($content) && class_exists('FLBuilder') && \FLBuilderModel::is_builder_enabled($postId)) {
            $content = \FLBuilder::render_content_by_id($postId);
        }

        // Gutenberg Blocks (enhanced processing)
        if (empty($content) && function_exists('parse_blocks')) {
            $raw_content = $this->getRawPostContent($postId);
            if (!empty($raw_content) && has_blocks($raw_content)) {
                $content = do_blocks($raw_content);
            }
        }

        return $content;
    }

    /**
     * Enhanced filtered content extraction with proper context setup
     *
     * @param int $postId The post ID
     * @return string The filtered content
     */
    private function getEnhancedFilteredContent(int $postId): string
    {
        global $post, $wp_query;

        $target_post = $this->getValidatedPost($postId);
        if (!$target_post) {
            return '';
        }

        // Backup current state
        $original_post = $post;
        $original_query = $wp_query;

        try {
            // Set up proper context for content filters
            $post = $target_post;
            $wp_query = new WP_Query([
                'p' => $postId,
                'post_type' => $target_post->post_type,
                'post_status' => 'publish'
            ]);

            setup_postdata($post);

            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Applying WordPress core 'the_content' filter intentionally for proper content processing
            $content = apply_filters('the_content', $target_post->post_content);

            // Process shortcodes if not already processed
            if (!empty($content) && has_shortcode($content, '')) {
                $content = do_shortcode($content);
            }

            return (string)$content;

        } finally {
            // Restore original state
            $post = $original_post;
            $wp_query = $original_query;
            wp_reset_postdata();
        }
    }

    /**
     * Extract main content from full HTML page
     *
     * @param string $html The full HTML content
     * @return string The extracted main content
     */
    private function extractMainContentFromHtml(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Use DOMDocument for reliable HTML parsing
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);

        // Try common content selectors in order of specificity
        $selectors = [
            'main[role="main"]',
            'main',
            '.entry-content',
            '.post-content',
            '.content',
            'article',
            '.single-content',
            '.page-content',
            '#content',
            '#main-content'
        ];

        $xpath = new \DOMXPath($dom);

        foreach ($selectors as $selector) {
            $cssSelector = $this->convertCssToXpath($selector);
            $nodes = $xpath->query($cssSelector);

            if ($nodes->length > 0) {
                $content = '';
                foreach ($nodes as $node) {
                    $content .= $dom->saveHTML($node);
                }

                if (!empty(trim(wp_strip_all_tags($content)))) {
                    return $content;
                }
            }
        }

        // Fallback: return body content minus header/footer
        $body = $xpath->query('//body');
        if ($body->length > 0) {
            return $dom->saveHTML($body->item(0));
        }

        return $html;
    }

    /**
     * Convert CSS selector to XPath
     *
     * @param string $css CSS selector
     * @return string XPath expression
     */
    private function convertCssToXpath(string $css): string
    {
        // Basic CSS to XPath conversion
        $xpath = $css;
        $xpath = preg_replace('/^([a-zA-Z][a-zA-Z0-9]*)/', '//$1', $xpath); // tag
        $xpath = preg_replace('/\.([a-zA-Z][a-zA-Z0-9_-]*)/', '[contains(@class,"$1")]', $xpath); // class
        $xpath = preg_replace('/#([a-zA-Z][a-zA-Z0-9_-]*)/', '[@id="$1"]', $xpath); // id
        $xpath = preg_replace('/\[([^=]+)="([^"]+)"\]/', '[@$1="$2"]', $xpath); // attributes

        return $xpath;
    }

    /**
     * Get post-content with shortcodes processed
     *
     * @param int $postId
     * @param bool $returnDBContent
     * @param bool $bypassPostStatus Whether to bypass post status check for non-published posts
     * @return string
     */
    public function getContent(int $postId, bool $returnDBContent = false, bool $bypassPostStatus = true): string
    {
        // Get the post object
        $post = $this->getValidatedPost($postId);
        if (!$post) {
            $this->log("getContent failed: Post ID $postId not found.", 'WARNING');
            return '';
        }

        // Handle non-published posts when bypass is enabled
        if ($bypassPostStatus && $post->post_status !== 'publish') {
            // Use the enhanced method for non-published posts
            return $this->getFullRenderedPageRegardlessOfStatus($postId);
        }

        // Handle published posts or when bypass is disabled for published posts
        if ($post->post_status === 'publish') {
            if ($returnDBContent) {
                return $this->getContentAggregated($postId, 'enhanced_internal', $bypassPostStatus);
            }
            return $this->getContentAggregated($postId, 'rendered_html', $bypassPostStatus);
        }

        // Non-published post with bypass disabled - return empty
        return '';
    }

    /**
     * Get content from a URL
     *
     * @param string $url The URL to fetch content from
     * @return string The raw HTML content or an empty string on failure
     */
    public function getContentByUrl(string $url): string
    {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        if (empty($url)) {
            // Log error
            $this->log('getContentByUrl failed: Invalid or empty URL provided.', 'ERROR');
            return '';
        }

        try {
            // Use the external service's fetch method
            // This service likely handles its own caching, Guzzle requests, etc.
            $fullContentInfo = WPSeoOptimiserService::fetchContent($url, false);
            if (empty($fullContentInfo) || !isset($fullContentInfo['raw_html'])) {
                // Log error
                $this->log("getContentByUrl failed: WPSeoOptimiserService::fetchContent returned unexpected data for URL: $url.", 'ERROR');
                return '';
            }

            // The original method returned raw HTML after decoding entities.
            // WPSeoOptimiserService::fetchContent provides 'raw_html'.
            return html_entity_decode($fullContentInfo['raw_html'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        } catch (RuntimeException $e) {
            $this->log("getContentByUrl failed: RuntimeException from service for URL $url. Error: " . $e->getMessage(), 'ERROR');
        } catch (Exception $e) {
            // Log it
            $this->log("getContentByUrl failed: Unexpected Exception for URL $url. Error: " . $e->getMessage(), 'ERROR');
        }

        return '';
    }

    /**
     * Extract the first paragraph from HTML content using DOMDocument.
     *
     * @param string $content The HTML content
     * @return string The first paragraph or empty string if none found
     */
    public function extractFirstParagraphWithDOM(string $content): string
    {
        // Create a single DOMXPath object for all operations
        $xpath = $this->loadHTMLInDomXPath($content);
        if (!$xpath) {
            return '';
        }

        $firstParagraphNode = $this->getFirstParagraphWithXPath($xpath);
        if ($firstParagraphNode) {
            // Return the text content of the first paragraph
            return trim($firstParagraphNode->textContent);
        }

        // If no paragraph found, return an empty string
        return '';
    }
}
