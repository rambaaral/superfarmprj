<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use WP_Customize_Manager;
use WP_Upgrader;

/**
 * Schema Cache Manager class
 * Handles automatic cache invalidation and management
 */
class SchemaCacheManager {

    /**
     * Batch invalidation queue for secondary changes.
     *
     * @var array
     */
    private static array $batchQueue = [];

    /**
     * Flag to prevent recursive invalidations.
     *
     * @var bool
     */
    private static bool $processingBatch = false;

    /**
     * Initialize cache management hooks.
     */
    public static function init(): void
    {
        // TIER 1: Critical content changes - immediate invalidation
        self::initCriticalHooks();
        
        // TIER 2: Secondary changes - batched invalidation
        self::initSecondaryHooks();
        
        // TIER 3: System-level changes - comprehensive invalidation
        self::initSystemHooks();
        
        // Initialize batch processing
        self::initBatchProcessing();
    }

    /**
     * Initialize critical hooks for immediate cache invalidation.
     */
    private static function initCriticalHooks(): void
    {
        // Post lifecycle
        add_action('save_post', [self::class, 'clearPostSchemaCache'], 10, 1);
        add_action('post_updated', [self::class, 'clearPostSchemaCache'], 10, 1);
        add_action('delete_post', [self::class, 'clearPostSchemaCache'], 10, 1);
        add_action('wp_trash_post', [self::class, 'clearPostSchemaCache'], 10, 1);
        add_action('untrash_post', [self::class, 'clearPostSchemaCache'], 10, 1);
        
        // Post meta updates
        add_action('updated_post_meta', [self::class, 'handlePostMetaUpdate'], 10, 4);
        add_action('added_post_meta', [self::class, 'handlePostMetaUpdate'], 10, 4);
        add_action('deleted_post_meta', [self::class, 'handlePostMetaDelete'], 10, 4);
        
        // Term lifecycle
        add_action('edited_term', [self::class, 'clearTermSchemaCache'], 10, 3);
        add_action('created_term', [self::class, 'clearTermSchemaCache'], 10, 3);
        add_action('deleted_term', [self::class, 'clearTermSchemaCache'], 10, 3);
        add_action('set_object_terms', [self::class, 'handleObjectTermsUpdate'], 10, 6);
        
        // Critical options
        add_action('update_option', [self::class, 'handleOptionUpdate'], 10, 3);
        add_action('add_option', [self::class, 'handleOptionUpdate'], 10, 2);
        add_action('delete_option', [self::class, 'handleOptionDelete'], 10, 1);
    }

    /**
     * Initialize secondary hooks for batched cache invalidation.
     */
    private static function initSecondaryHooks(): void
    {
        // Comment system
        add_action('comment_post', [self::class, 'scheduleBatchInvalidation'], 10, 2);
        add_action('edit_comment', [self::class, 'scheduleBatchInvalidation'], 10, 2);
        add_action('delete_comment', [self::class, 'scheduleBatchInvalidation'], 10, 2);
        add_action('wp_set_comment_status', [self::class, 'scheduleBatchInvalidation'], 10, 2);
        add_action('comment_unapproved_to_approved', [self::class, 'scheduleBatchInvalidation'], 10, 1);
        add_action('comment_approved_to_unapproved', [self::class, 'scheduleBatchInvalidation'], 10, 1);
        
        // User management
        add_action('profile_update', [self::class, 'handleUserProfileUpdate'], 10, 2);
        add_action('user_register', [self::class, 'handleUserProfileUpdate'], 10, 1);
        add_action('delete_user', [self::class, 'handleUserProfileUpdate'], 10, 1);
        add_action('set_user_role', [self::class, 'handleUserRoleChange'], 10, 3);
        add_action('add_user_role', [self::class, 'handleUserRoleChange'], 10, 2);
        add_action('remove_user_role', [self::class, 'handleUserRoleChange'], 10, 2);
        
        // Media library
        add_action('attachment_updated', [self::class, 'handleAttachmentUpdate'], 10, 3);
        add_action('delete_attachment', [self::class, 'handleAttachmentUpdate'], 10, 1);
        add_action('wp_update_attachment_metadata', [self::class, 'handleAttachmentMetadataUpdate'], 10, 2);
        
        // Navigation menus
        add_action('wp_update_nav_menu', [self::class, 'handleMenuUpdate'], 10, 2);
        add_action('wp_delete_nav_menu', [self::class, 'handleMenuUpdate'], 10, 1);
        add_action('wp_update_nav_menu_item', [self::class, 'handleMenuItemUpdate'], 10, 3);
        
        // Widget updates
        add_action('sidebar_admin_setup', [self::class, 'scheduleBatchInvalidation']);
        add_action('widget_update_callback', [self::class, 'scheduleBatchInvalidation'], 10, 4);
    }

    /**
     * Initialize system-level hooks for comprehensive invalidation.
     */
    private static function initSystemHooks(): void
    {
        // Theme and customizer
        add_action('switch_theme', [self::class, 'clearAllSchemaCache']);
        add_action('customize_save_after', [self::class, 'handleCustomizerSave'], 10, 1);
        add_action('wp_update_custom_css_post', [self::class, 'clearAllSchemaCache'], 10, 1);
        
        // Plugin management
        add_action('activated_plugin', [self::class, 'clearAllSchemaCache']);
        add_action('deactivated_plugin', [self::class, 'clearAllSchemaCache']);
        add_action('upgrader_process_complete', [self::class, 'handlePluginUpgrade'], 10, 2);
        
        // Permalink structure
        add_action('permalink_structure_changed', [self::class, 'clearAllSchemaCache']);
        add_action('generate_rewrite_rules', [self::class, 'clearAllSchemaCache']);
        
        // Import/Export operations
        add_action('import_start', [self::class, 'clearAllSchemaCache']);
        add_action('import_end', [self::class, 'clearAllSchemaCache']);
        add_action('wp_import_insert_post', [self::class, 'clearPostSchemaCache'], 10, 4);
        
        // Multisite events
        if (is_multisite()) {
            add_action('wpmu_new_blog', [self::class, 'clearAllSchemaCache'], 10, 6);
            add_action('delete_blog', [self::class, 'clearAllSchemaCache'], 10, 2);
            add_action('activate_blog', [self::class, 'clearAllSchemaCache'], 10, 1);
            add_action('deactivate_blog', [self::class, 'clearAllSchemaCache'], 10, 1);
        }
    }

    /**
     * Initialize batch processing for secondary invalidations.
     *
     * Registers the cleanup callback only. Actual scheduling (wp_schedule_event)
     * is handled separately via scheduleCleanupCron()/unscheduleCleanupCron(), which
     * should only be called on plugin activation/deactivation to avoid a
     * wp_next_scheduled() check on every request.
     */
    private static function initBatchProcessing(): void
    {
        // Process batched invalidations on shutdown
        add_action('shutdown', [self::class, 'processBatchedInvalidations'], 5);

        add_action('rankingcoach_schema_cache_cleanup', [self::class, 'cleanupExpiredCache']);
    }

    /**
     * Schedule the schema cache cleanup cron event.
     * Should only be called from plugin activation, not on every request.
     */
    public static function scheduleCleanupCron(): void
    {
        if (!wp_next_scheduled('rankingcoach_schema_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'rankingcoach_schema_cache_cleanup');
        }
    }

    /**
     * Unschedule the schema cache cleanup cron event.
     * Should only be called from plugin deactivation.
     */
    public static function unscheduleCleanupCron(): void
    {
        $timestamp = wp_next_scheduled('rankingcoach_schema_cache_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'rankingcoach_schema_cache_cleanup');
        }
    }

    /**
     * Handle object terms update (post category/tag assignments).
     *
     * @param int $objectId Object ID
     * @param array $terms Terms
     * @param array $ttIds Term taxonomy IDs
     * @param string $taxonomy Taxonomy
     * @param bool $append Whether to append terms
     * @param array $oldTtIds Old term taxonomy IDs
     */
    public static function handleObjectTermsUpdate(int $objectId, array $terms, array $ttIds, string $taxonomy, bool $append, array $oldTtIds): void
    {
        if (get_post_type($objectId)) {
            self::clearPostSchemaCache($objectId);
        }
    }

    /**
     * Handle option deletion.
     *
     * @param string $optionName Option name
     */
    public static function handleOptionDelete(string $optionName): void
    {
        self::handleOptionUpdate($optionName, null, null);
    }

    /**
     * Schedule batch invalidation for secondary changes.
     *
     * @param mixed ...$args Variable arguments from hook
     */
    public static function scheduleBatchInvalidation(...$args): void
    {
        if (self::$processingBatch) {
            return;
        }

        $context = current_action();
        $key = md5($context . serialize($args));
        
        self::$batchQueue[$key] = [
            'context' => $context,
            'args' => $args,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Handle user profile updates.
     *
     * @param int $userId User ID
     * @param array|null $oldUserData Old user data (for profile_update)
     */
    public static function handleUserProfileUpdate(int $userId, ?array $oldUserData = null): void
    {
        // Clear cache for posts authored by this user
        $userPosts = get_posts([
            'author' => $userId,
            'post_type' => 'any',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids'
        ]);

        foreach ($userPosts as $postId) {
            self::scheduleBatchInvalidation('user_profile_update', $postId);
        }

        // Clear site cache for organization schema
        self::clearSiteSchemaCache();
    }

    /**
     * Handle user role changes.
     *
     * @param int $userId User ID
     * @param string $role Role name
     * @param array|null $oldRoles Old roles (for set_user_role)
     */
    public static function handleUserRoleChange(int $userId, string $role, ?array $oldRoles = null): void
    {
        self::handleUserProfileUpdate($userId);
    }

    /**
     * Handle attachment updates.
     *
     * @param int $attachmentId Attachment ID
     * @param array|null $data Attachment data
     * @param array|null $oldData Old attachment data
     */
    public static function handleAttachmentUpdate(int $attachmentId, ?array $data = null, ?array $oldData = null): void
    {
        // Find posts using this attachment as featured image
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        $posts = get_posts([
            'meta_key' => '_thumbnail_id',
            'meta_value' => $attachmentId,
            'post_type' => 'any',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids'
        ]);
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value

        foreach ($posts as $postId) {
            self::clearPostSchemaCache($postId);
        }

        // Clear site cache if this is a site logo or organization image
        self::clearSiteSchemaCache();
    }

    /**
     * Handle attachment metadata updates.
     *
     * @param array $data Attachment metadata
     * @param int $attachmentId Attachment ID
     */
    public static function handleAttachmentMetadataUpdate(array $data, int $attachmentId): void
    {
        self::handleAttachmentUpdate($attachmentId, $data);
    }

    /**
     * Handle navigation menu updates.
     *
     * @param int $menuId Menu ID
     * @param array|null $menuData Menu data
     */
    public static function handleMenuUpdate(int $menuId, ?array $menuData = null): void
    {
        // Menu changes affect breadcrumb schema site-wide
        self::clearAllSchemaCache();
    }

    /**
     * Handle menu item updates.
     *
     * @param int $menuId Menu ID
     * @param int $menuItemDbId Menu item database ID
     * @param array $args Menu item arguments
     */
    public static function handleMenuItemUpdate(int $menuId, int $menuItemDbId, array $args): void
    {
        self::handleMenuUpdate($menuId);
    }

    /**
     * Handle customizer save events.
     *
     * @param WP_Customize_Manager $manager Customizer manager
     */
    public static function handleCustomizerSave(WP_Customize_Manager $manager): void
    {
        // Customizer changes can affect organization schema, logos, etc.
        self::clearAllSchemaCache();
    }

    /**
     * Handle plugin upgrade completion.
     *
     * @param WP_Upgrader $upgrader Upgrader instance
     * @param array $hookExtra Hook extra data
     */
    public static function handlePluginUpgrade(WP_Upgrader $upgrader, array $hookExtra = []): void
    {
        // Plugin upgrades might affect schema generation
        if (isset($hookExtra['type']) && $hookExtra['type'] === 'plugin') {
            self::clearAllSchemaCache();
        }
    }

    /**
     * Process batched invalidations.
     */
    public static function processBatchedInvalidations(): void
    {
        if (empty(self::$batchQueue) || self::$processingBatch) {
            return;
        }

        self::$processingBatch = true;

        $postIds = [];
        $siteInvalidation = false;

        foreach (self::$batchQueue as $item) {
            $context = $item['context'];
            $args = $item['args'];

            switch ($context) {
                case 'comment_post':
                case 'edit_comment':
                case 'delete_comment':
                case 'wp_set_comment_status':
                case 'comment_unapproved_to_approved':
                case 'comment_approved_to_unapproved':
                    if (isset($args[0])) {
                        $comment = get_comment($args[0]);
                        if ($comment && $comment->comment_post_ID) {
                            $postIds[] = $comment->comment_post_ID;
                        }
                    }
                    break;

                case 'user_profile_update':
                    if (isset($args[0])) {
                        $postIds[] = $args[0];
                    }
                    break;

                case 'sidebar_admin_setup':
                case 'widget_update_callback':
                    $siteInvalidation = true;
                    break;
            }
        }

        // Process unique post invalidations
        $uniquePostIds = array_unique($postIds);
        foreach ($uniquePostIds as $postId) {
            delete_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE_HASH);
            delete_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE);
            delete_post_meta($postId, BaseConstants::OPTION_SCHEMA_VALIDATION_RESULTS);
        }

        // Process site-level invalidation if needed
        if ($siteInvalidation || !empty($uniquePostIds)) {
            self::clearSiteSchemaCache();
        }

        // Clear the queue
        self::$batchQueue = [];
        self::$processingBatch = false;

        if (!empty($uniquePostIds) || $siteInvalidation) {
            do_action('rankingcoach_schema_cache_cleared', 'batch', count($uniquePostIds));
        }
    }

    /**
     * Clear schema cache for a specific post.
     *
     * @param int $postId Post ID
     */
    public static function clearPostSchemaCache(int $postId): void
    {
        if (!$postId || wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        
        // Clear post-specific cache
        delete_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE_HASH);
        delete_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE);
        delete_post_meta($postId, BaseConstants::OPTION_SCHEMA_VALIDATION_RESULTS);
        
        // Clear site-level cache as well (homepage might be affected)
        self::clearSiteSchemaCache();
        
        do_action('rankingcoach_schema_cache_cleared', 'post', $postId);
    }

    /**
     * Handle post meta updates that affect schema generation.
     *
     * @param int $metaId Meta ID
     * @param int $postId Post ID
     * @param string $metaKey Meta key
     * @param mixed $metaValue Meta value
     */
    public static function handlePostMetaUpdate(int $metaId, int $postId, string $metaKey, mixed $metaValue): void
    {
        if (self::isSchemaRelatedMetaKey($metaKey)) {
            self::clearPostSchemaCache($postId);
        }
    }

    /**
     * Handle post meta deletions that affect schema generation.
     *
     * @param array $metaIds Array of deleted meta IDs
     * @param int $postId Post ID
     * @param string $metaKey Meta key
     * @param mixed $metaValue Meta value that was deleted
     */
    public static function handlePostMetaDelete(array $metaIds, int $postId, string $metaKey, mixed $metaValue): void
    {
        if (self::isSchemaRelatedMetaKey($metaKey)) {
            self::clearPostSchemaCache($postId);
        }
    }

    /**
     * Check if a meta key is related to schema generation.
     *
     * @param string $metaKey Meta key to check
     * @return bool True if the meta key affects schema generation
     */
    private static function isSchemaRelatedMetaKey(string $metaKey): bool
    {
        $internalCacheKeys = [
            BaseConstants::OPTION_SCHEMA_CACHE_HASH,
            BaseConstants::OPTION_SCHEMA_CACHE,
            BaseConstants::OPTION_SCHEMA_VALIDATION_RESULTS,
        ];

        if (in_array($metaKey, $internalCacheKeys, true)) {
            return false;
        }

        // Expanded schema-related meta keys
        $schemaRelatedKeys = [
            // RankingCoach specific
            BaseConstants::OPTION_SCHEMA_TYPE,
            
            // WordPress core
            '_thumbnail_id',
            '_wp_page_template',
            '_wp_attachment_image_alt',
            '_wp_attachment_metadata',
            
            // SEO plugins
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
            '_yoast_wpseo_canonical',
            '_yoast_wpseo_opengraph-title',
            '_yoast_wpseo_opengraph-description',
            '_yoast_wpseo_opengraph-image',
            '_yoast_wpseo_twitter-title',
            '_yoast_wpseo_twitter-description',
            '_yoast_wpseo_twitter-image',
            '_yoast_wpseo_focuskw',
            '_yoast_wpseo_schema_page_type',
            '_yoast_wpseo_schema_article_type',
            
            // RankMath
            'rank_math_title',
            'rank_math_description',
            'rank_math_canonical_url',
            'rank_math_focus_keyword',
            'rank_math_schema_PageType',
            'rank_math_schema_ArticleType',
            
            // All in One SEO
            '_aioseop_title',
            '_aioseop_description',
            '_aioseop_canonical_url',
            '_aioseop_keywords',
            
            // Custom fields that might affect schema
            'price',
            'rating',
            'review_count',
            'availability',
            'brand',
            'model',
            'sku',
            'gtin',
            'mpn',
            'author_bio',
            'event_date',
            'event_location',
            'recipe_ingredients',
            'recipe_instructions',
            'video_duration',
            'video_upload_date'
        ];
        
        // Check for exact matches first
        if (in_array($metaKey, $schemaRelatedKeys, true)) {
            return true;
        }
        
        // Check for any meta key starting with schema-related prefixes
        $schemaRelatedPrefixes = [
            'rankingcoach_',
            '_yoast_wpseo_',
            'rank_math_',
            '_aioseop_',
            'schema_',
            '_schema_',
            'structured_data_'
        ];
        
        foreach ($schemaRelatedPrefixes as $prefix) {
            if (strpos($metaKey, $prefix) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Clear schema cache for term-related pages.
     *
     * @param int $termId Term ID
     * @param int $ttId Term taxonomy ID
     * @param string $taxonomy Taxonomy name
     */
    public static function clearTermSchemaCache(int $termId, int $ttId, string $taxonomy): void
    {
        // Clear site-level cache for term archives
        self::clearSiteSchemaCache();
        
        do_action('rankingcoach_schema_cache_cleared', 'term', $termId);
    }

    /**
     * Handle option updates that affect schema generation.
     *
     * @param string $optionName Option name
     * @param mixed $oldValue Old value
     * @param mixed $newValue New value
     */
    public static function handleOptionUpdate(string $optionName, mixed $oldValue, mixed $newValue = null): void
    {
        // Core WordPress options affecting schema
        $coreSchemaOptions = [
            'blogname',
            'blogdescription',
            'admin_email',
            'show_on_front',
            'page_on_front',
            'page_for_posts',
            'date_format',
            'time_format',
            'start_of_week',
            'timezone_string',
            'gmt_offset',
            'site_logo',
            'custom_logo',
            'site_icon',
            'permalink_structure',
            'category_base',
            'tag_base',
            'default_category',
            'default_post_format',
            'users_can_register',
            'default_role',
            'comment_registration',
            'close_comments_for_old_posts',
            'thread_comments',
            'page_comments',
            'comments_per_page',
            'default_comments_page',
            'comment_order',
            'comments_notify',
            'moderation_notify',
            'comment_moderation',
            'require_name_email',
            'comment_whitelist',
            'comment_max_links',
            'moderation_keys',
            'blacklist_keys',
            'show_avatars',
            'avatar_rating',
            'avatar_default',
            'thumbnail_size_w',
            'thumbnail_size_h',
            'medium_size_w',
            'medium_size_h',
            'large_size_w',
            'large_size_h',
            'image_default_align',
            'image_default_link_type',
            'image_default_size'
        ];

        // Theme-related options
        $themeSchemaOptions = [
            'stylesheet',
            'template',
            'current_theme',
            'theme_mods_' . get_option('stylesheet'),
            'nav_menu_locations',
            'widget_text',
            'widget_recent-posts',
            'widget_recent-comments',
            'widget_archives',
            'widget_categories',
            'widget_meta',
            'widget_search',
            'widget_pages',
            'widget_calendar',
            'widget_tag_cloud',
            'widget_nav_menu',
            'widget_custom_html',
            'sidebars_widgets'
        ];

        // SEO plugin options
        $seoPluginPrefixes = [
            'wpseo',
            '_yoast_wpseo_',
            'rank_math_',
            'aioseop_',
            'seopress_',
            'squirrly_',
            'smartcrawl_',
            'rankingcoach_'
        ];

        // E-commerce options
        $ecommerceOptions = [
            'woocommerce_store_address',
            'woocommerce_store_address_2',
            'woocommerce_store_city',
            'woocommerce_default_country',
            'woocommerce_store_postcode',
            'woocommerce_currency',
            'woocommerce_price_display_suffix',
            'woocommerce_tax_display_shop',
            'woocommerce_tax_display_cart',
            'woocommerce_enable_reviews',
            'woocommerce_review_rating_verification_label',
            'woocommerce_review_rating_verification_required'
        ];

        // Check for plugin-specific prefixes first
        foreach ($seoPluginPrefixes as $prefix) {
            if (strpos($optionName, $prefix) === 0) {
                self::clearAllSchemaCache();
                return;
            }
        }

        // Check core options
        if (in_array($optionName, $coreSchemaOptions, true)) {
            self::clearAllSchemaCache();
            return;
        }

        // Check theme options
        if (in_array($optionName, $themeSchemaOptions, true)) {
            self::clearAllSchemaCache();
            return;
        }

        // Check e-commerce options
        if (in_array($optionName, $ecommerceOptions, true)) {
            self::clearAllSchemaCache();
            return;
        }

        // Check for dynamic theme mod options
        if (strpos($optionName, 'theme_mods_') === 0) {
            self::clearAllSchemaCache();
            return;
        }

        // Check for widget options
        if (strpos($optionName, 'widget_') === 0) {
            self::clearAllSchemaCache();
            return;
        }

        // Check for menu options
        if (strpos($optionName, 'nav_menu_') === 0) {
            self::clearAllSchemaCache();
            return;
        }
    }

    /**
     * Clear site-level schema cache.
     */
    public static function clearSiteSchemaCache(): void
    {
        delete_option(BaseConstants::OPTION_SCHEMA_CACHE_HASH . '_site');
        delete_option(BaseConstants::OPTION_SCHEMA_CACHE . '_site');
        
        do_action('rankingcoach_schema_cache_cleared', 'site', 0);
    }

    /**
     * Clear all schema cache (both post-level and site-level).
     */
    public static function clearAllSchemaCache(): void
    {
        $dbManager = DatabaseManager::getInstance();
        
        // Clear all post-level cache
        $dbManager->db()->table('postmeta')->delete()->where('meta_key', BaseConstants::OPTION_SCHEMA_CACHE_HASH)->get();
        $dbManager->db()->table('postmeta')->delete()->where('meta_key', BaseConstants::OPTION_SCHEMA_CACHE)->get();
        $dbManager->db()->table('postmeta')->delete()->where('meta_key', BaseConstants::OPTION_SCHEMA_VALIDATION_RESULTS)->get();
        
        // Clear site-level cache
        self::clearSiteSchemaCache();
        
        do_action('rankingcoach_schema_cache_cleared', 'all', 0);
    }

    /**
     * Clean up expired or invalid cache entries.
     *
     * @param int $maxAge Maximum age in seconds (default: 7 days)
     */
    public static function cleanupExpiredCache(int $maxAge = 604800): void
    {
        $dbManager = DatabaseManager::getInstance();
        
        $cutoffTime = current_time('timestamp') - $maxAge;
        
        // Get posts that haven't been modified recently
        $postsTable = $dbManager->db()->prefixTable('posts');
        $postmetaTable = $dbManager->db()->prefixTable('postmeta');
        $sql = "SELECT p.ID 
            FROM {$postsTable} p
            INNER JOIN {$postmetaTable} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = " . $dbManager->db()->escapeValue(BaseConstants::OPTION_SCHEMA_CACHE) . "
            AND UNIX_TIMESTAMP(p.post_modified) < " . (int)$cutoffTime;
        
        $results = $dbManager->db()->queryRaw($sql, 'ARRAY_N');
        
        $expiredPosts = [];
        if (is_array($results)) {
            foreach ($results as $row) {
                if (isset($row[0])) {
                    $expiredPosts[] = (int)$row[0];
                }
            }
        }
        
        foreach ($expiredPosts as $postId) {
            self::clearPostSchemaCache($postId);
        }
        
        do_action('rankingcoach_schema_cache_cleanup', count($expiredPosts));
    }
}
