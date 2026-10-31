<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Seo\Adapters\WordPressProvider;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Seo\Services\SeoOptimiserService;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;
use RankingCoach\Inc\Modules\ModuleManager;
use RuntimeException;
use Throwable;
use WP_Post;

/**
 * Class PostEventsManager
 * 
 * Handles post save events and triggers keyword synchronization.
 */
class PostEventsManager {

    use RcLoggerTrait;

    /**
     * Singleton instance of PostEventsManager.
     *
     * @var PostEventsManager|null
     */
    private static ?PostEventsManager $instance = null;
    
    /**
     * Track processed posts in current request to prevent duplicates.
     * 
     * @var array<int, bool>
     */
    private static array $processedPosts = [];
    
    /**
     * Minimum time between synchronizations in seconds.
     * 
     * @var int
     */
    private const SYNC_THROTTLE_SECONDS = 30;

    /**
     * Returns the singleton instance of PostEventsManager.
     *
     * @return PostEventsManager
     */
    public static function instance(): PostEventsManager {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the manager by registering hooks.
     *
     * @return void
     */
    public function initialize(): void {
        // Use a higher priority (20) to ensure we run after most other save_post handlers
        add_action( 'save_post', [ $this, 'handlePostSave' ], 20, 3 );
        add_action( 'load-post.php', [ $this, 'handlePostLoad' ] );
        add_action( 'deleted_post', [ $this, 'handlePostDelete' ], 10, 2 );
    }

    /**
     * Drop the plugin's per-post rows (optimiser analysis, meta tag templates) once WordPress
     * has deleted a post. Post meta goes away with the post, but these rows live in plugin
     * tables and used to be left behind. That now also covers the auto-drafts WordPress purges
     * a week after an abandoned "Add New" visit, whose meta box can store SEO data since
     * WORDPRESS-986.
     *
     * @param int          $post_id The deleted post ID.
     * @param WP_Post|null $post    The deleted post object.
     *
     * @return void
     */
    public function handlePostDelete( int $post_id, ?WP_Post $post = null ): void {
        // Revisions and menu items never own plugin rows; skip the extra queries.
        if ( $post instanceof WP_Post && in_array( $post->post_type, [ 'revision', 'nav_menu_item' ], true ) ) {
            return;
        }

        try {
            SeoOptimiserService::getInstance()->deleteOptimiser( $post_id );
        } catch ( Throwable $e ) {
            $this->log( "Optimiser cleanup error for deleted post ID: $post_id - " . $e->getMessage(), 'WARNING' );
        }

        try {
            $metaTags = ModuleManager::instance()->get_module( MetaTags::MODULE_NAME );
            if ( $metaTags instanceof MetaTags ) {
                $metaTags->deleteAllTags( $post_id );
            }
        } catch ( Throwable $e ) {
            $this->log( "Meta tags cleanup error for deleted post ID: $post_id - " . $e->getMessage(), 'WARNING' );
        }
    }

    /**
     * Handle post load event and trigger SEO optimization if score doesn't exist.
     *
     * @return void
     */
    public function handlePostLoad(): void {

        $screen = get_current_screen();

        if ($screen && 'post' === $screen->base && !empty($screen->post_type)) {
            $post_id = get_the_ID();
        }

        if (empty($post_id)) {
            return;
        }

        // Verify this is a valid post/page
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, ['post', 'page'])) {
            return;
        }

        // Check if the SEO score already exists in post meta
        $seoScore = get_post_meta($post_id, BaseConstants::OPTION_ANALYSIS_SEO_SCORE, true);
        
        // If the SEO score does not exist, execute the SEO optimization
        if (empty($seoScore)) {
            self::executeSeoOptimisation($post_id);
        }
    }

    /**
     * Handle post save event and trigger keyword synchronization for existing posts/pages.
     *
     * @param int     $post_id The post ID.
     * @param WP_Post $post    The post object.
     * @param bool    $update  Whether this is an existing post being updated.
     * 
     * @return void
     */
    public function handlePostSave( int $post_id, WP_Post $post, bool $update ): void {
        // Skip if this is an autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // Skip if this is a revision
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Skip the empty auto-draft WordPress inserts on every "Add New" page view: there is
        // nothing to sync or analyze yet, and the first real save fires this hook again.
        if ( 'auto-draft' === $post->post_status ) {
            return;
        }

        // Skip if this is not a post or page
        if ( ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
            return;
        }
        
        // Skip if user doesn't have permission to edit this post
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Prevent duplicate processing in the same request
        if ( isset( self::$processedPosts[$post_id] ) ) {
            //$this->log( "Skipping duplicate processing for post ID: $post_id (already processed in this request)" );
            return;
        }
        
        // Mark as processed for this request
        self::$processedPosts[$post_id] = true;
        
        // Check for recent processing using single transient per post
        $transientKey = 'rc_post_processing_' . $post_id;
        if ( get_transient( $transientKey ) ) {
            //$this->log( "Skipping processing for post ID: $post_id (recently processed)" );
            return;
        }
        
        // Set transient to prevent duplicate processing across requests
        set_transient( $transientKey, time(), self::SYNC_THROTTLE_SECONDS );

        // Save page keywords data for external landing pages
        $this->saveUsePluginPageAndKeywordsData($post_id);

        // Process keywords score calculation if needed
        $canProcessSeoScoreCalculation = false;

        $setting = SettingsManager::instance();
        if((bool)$setting->get_option( 'allow_seo_optimiser_on_saved_posts', false ) === true) {
            $canProcessSeoScoreCalculation  = $this->shouldProcessSeoOptimization( $post_id, $post, $update );
        }

        // Clear URL content cache
        try {
            $provider = new WordPressProvider();
            $permalink = get_permalink($post_id);
            if ($permalink) {
                $provider->clearUrlContentCache($permalink);
            }
        } catch (Throwable $e) {
            $this->log("URL cache clear error for post ID: $post_id - " . $e->getMessage(), 'WARNING');
        }

        // Process SEO optimization if needed
        if ( $canProcessSeoScoreCalculation ) {
            try {
                self::executeSeoOptimisation($post_id);
            } catch ( Throwable $e ) {
                $this->log( "SEO-Optimization error for post ID: $post_id - " . $e->getMessage(), 'ERROR' );
            }
        }
    }
    
    /**
     * Determine if keywords synchronization should be processed.
     *
     * @param int     $post_id The post ID.
     * @param WP_Post $post    The post object.
     * @param bool    $update  Whether this is an existing post being updated.
     * 
     * @return bool
     */
    private function shouldProcessKeywords( int $post_id, WP_Post $post, bool $update ): bool {
        // Only process keywords for updates to existing posts
        if ( ! $update ) {
            return false;
        }
        
        // Skip if this is a meta-only update (AJAX requests typically indicate meta updates)
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return false;
        }
        
        // Check if we're in a valid context for processing
        return $this->isValidProcessingContext();
    }
    
    /**
     * Determine if SEO optimization should be processed.
     *
     * @param int     $post_id The post ID.
     * @param WP_Post $post    The post object.
     * @param bool    $update  Whether this is an existing post being updated.
     * 
     * @return bool
     */
    private function shouldProcessSeoOptimization( int $post_id, WP_Post $post, bool $update ): bool {
        // Process SEO optimization for both new posts and updates
        // Skip if this is a meta-only update
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return false;
        }
        
        return $this->isValidProcessingContext();
    }
    
    /**
     * Check if we're in a valid context for processing operations.
     *
     * @return bool
     */
    private function isValidProcessingContext(): bool {
        // Allow REST API requests (Gutenberg editor)
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return true;
        }
        
        // Allow if we're in admin context
        if ( is_admin() ) {
            return true;
        }
        
        // Allow programmatic saves
        return true;
    }

    /**
     * Execute SEO optimization for a given post ID.
     *
     * This method constructs the API URL for the SEO optimization endpoint,
     * sends a POST request with the post ID, and handles the response.
     * Will skip execution if SEO score already exists for the post.
     *
     * @param int $postId The ID of the post to optimize.
     */
    public static function executeSeoOptimisation(int $postId, bool $forceRecalculation = false): void
    {
        $self = self::instance();

        // Check if SEO score already exists for this post
        $existingSeoScore = get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_SEO_SCORE, true);
        if (!$forceRecalculation && !empty($existingSeoScore)) {
            return;
        }

        // Construct the URL
        $url = add_query_arg([
            'noCache' => 1,
            'debug'   => 1,
        ], home_url("/wp-json/rankingcoach/api/optimiser/{$postId}"));

        // Implement enhanced retry mechanism with more retries for reliability
        $maxRetries = 3; // Increase from 2 to 3 for better reliability
        $retryCount = 0;
        $success = false;


        while ($retryCount <= $maxRetries && !$success) {
            // Execute the POST request

            // Get current user if available
            $currentUser = wp_get_current_user();
            $username = $currentUser && $currentUser->exists() ? $currentUser->user_login : 'admin';
            // Retrieve the app password from usermeta
            $appPassword = get_user_meta($currentUser->ID, BaseConstants::OPTION_APPLICATION_PASSWORD, true);

            try {
                $host = wp_parse_url($url, PHP_URL_HOST);
            } catch (\Exception $e) {
                $host = null;
            }
            $isLocalhost = $host && (in_array($host, ['localhost', '127.0.0.1', '::1'])
                    || str_contains($host, 'localhost'));

            // Increase timeout to prevent cURL timeout errors
            $response = wp_remote_post($url, [
                'timeout' => 30, // Set timeout to 30 seconds to prevent timeouts
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                    'Referer'      => admin_url(), // Set the HTTP referer to the admin URL
                    'Authorization' => 'Basic ' . base64_encode($username . ':' . $appPassword), // Use basic auth with credentials
                ],
                'sslverify' => !$isLocalhost && wp_get_environment_type() === 'production', // Skip SSL verification in development
                'blocking'   => true, // Ensure the request is blocking to get the response
            ]);

            // Check for errors and handle them
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $error_code = $response->get_error_code();

                $self->log("SEO Optimisation API call failed: {$error_code} - {$error_message}", 'ERROR');

                $retryCount++;
                if ($retryCount <= $maxRetries) {
                    $self->log("Retry $retryCount for SEO Optimisation API", 'WARNING');

                    // Use longer backoff for timeout errors
                    sleep(2 ** ($retryCount - 1));
                }
            } else {
                $responseCode = wp_remote_retrieve_response_code($response);
                $responseBody = wp_remote_retrieve_body($response);

                if ($responseCode >= 200 && $responseCode < 300) {
                    $self->log("SEO Optimisation API successful (HTTP {$responseCode}): " . substr($responseBody, 0, 100) . (strlen($responseBody) > 100 ? '...' : ''));
                    $success = true;
                } else {
                    $self->log("SEO Optimisation API error: HTTP {$responseCode} - " . substr($responseBody, 0, 100) . (strlen($responseBody) > 100 ? '...' : ''), 'ERROR');

                    // For server errors (5xx), use longer backoff
                    $retryCount++;
                    if ($retryCount <= $maxRetries) {
                        $self->log("Retry $retryCount for SEO Optimisation API", 'WARNING');

                        sleep(2 ** ($retryCount - 1));
                    }
                }
            }
        }
        // Store the optimization status
        update_post_meta($postId, BaseConstants::OPTION_ANALYSIS_STATUS, $success ? 'completed' : 'failed');

        // Log the final outcome
        if ($success) {
            //$self->log("SEO Optimization completed successfully for post ID: {$postId} after " . ($retryCount + 1) . ' attempt(s)');
        } else {
            $self->log("SEO Optimization failed for post ID: {$postId} after exhausting all {$maxRetries} retries", 'ERROR');
        }
    }

    /**
     * Calculate the average SEO score from all posts that have been analyzed
     *
     * Queries all posts with the BaseConstants::OPTION_ANALYSIS_SEO_SCORE meta key
     * and calculates the arithmetic mean of their scores with enterprise-grade error handling.
     *
     * @param int $limit Maximum number of records to process (default: 10000)
     * @return array Returns an array with the average score, count of posts, and status
     * @throws RuntimeException On critical database errors
     */
    public static function calculateAverageScoreFromPostMeta(int $limit = 10000): array
    {
        $self = self::instance();

        try {
            $dbManager = DatabaseManager::getInstance();
            $db = $dbManager->db();
            
            // Validate database connection
            if (!$db || !$db->db) {
                throw new RuntimeException('Database connection unavailable');
            }

            $postmetaTable = $dbManager->prefixTable('postmeta');
            $postsTable = $dbManager->prefixTable('posts');
            $metaKey = BaseConstants::OPTION_ANALYSIS_SEO_SCORE;

            // Use optimized query with aggregation to reduce memory footprint
            $query = $db->db->prepare(/** @lang=MySQL */ "
                SELECT 
                    AVG(CAST(pm.meta_value AS DECIMAL(5,2))) as avg_score,
                    COUNT(*) as total_count,
                    MIN(CAST(pm.meta_value AS DECIMAL(5,2))) as min_score,
                    MAX(CAST(pm.meta_value AS DECIMAL(5,2))) as max_score
                FROM $postmetaTable pm
                INNER JOIN $postsTable p ON pm.post_id = p.ID
                WHERE pm.meta_key = %s 
                AND p.post_status = 'publish'
                AND p.post_type IN ('post', 'page')
                AND pm.meta_value IS NOT NULL 
                AND pm.meta_value != ''
                AND pm.meta_value REGEXP '^[0-9]+(\.[0-9]+)?$'
                AND CAST(pm.meta_value AS DECIMAL(5,2)) BETWEEN 0 AND 100
                LIMIT %d
            ", $metaKey, $limit);

            $result = $db->queryRaw($query, ARRAY_A);

            // Check for database errors
            if (empty($result)) {
                return [
                    'average_score' => 0.0,
                    'count' => 0,
                    'min_score' => null,
                    'max_score' => null
                ];
            }
            $resultRow = is_array($result) ? $result[0] : $result;

            // Handle empty result set
            if (!$resultRow || !$resultRow['total_count']) {
                //$self->log('No valid SEO scores found in database', 'INFO');
                return [
                    'average_score' => 0.0,
                    'count' => 0,
                    'min_score' => null,
                    'max_score' => null
                ];
            }

            // Validate aggregated results
            $avgScore = (float) $resultRow['avg_score'];
            $count = (int) $resultRow['total_count'];
            $minScore = (float) $resultRow['min_score'];
            $maxScore = (float) $resultRow['max_score'];

            // Sanity checks on aggregated data
            if ($avgScore < 0 || $avgScore > 100) {
                throw new RuntimeException("Invalid average score calculated: $avgScore");
            }

            if ($count <= 0) {
                throw new RuntimeException("Invalid count returned: $count");
            }

            // Check for potential data corruption
            if ($minScore > $maxScore) {
                //$self->log("Data integrity warning: min_score ($minScore) > max_score ($maxScore)", 'WARNING');
            }

            $finalScore = round($avgScore, 2);
            
            //$self->log("SEO score calculation completed: avg=$finalScore, count=$count", 'INFO');

            return [
                'average_score' => $finalScore,
                'count' => $count,
                'min_score' => $minScore,
                'max_score' => $maxScore
            ];

        } catch (Throwable $e) {
            $self->log('Critical error in calculateAverageScoreFromPostMeta: ' . $e->getMessage(), 'ERROR');
            
            // Return safe fallback values
            return [
                'average_score' => 0.0,
                'count' => 0,
                'min_score' => null,
                'max_score' => null
            ];
        }
    }

    /**
     * Save page URL and keywords data to wp_options for external landing pages.
     *
     * @param int $post_id The post ID.
     * @return void
     */
    private function saveUsePluginPageAndKeywordsData(int $post_id): void
    {
        $primaryKeyword = get_post_meta($post_id, BaseConstants::META_KEY_PRIMARY_KEYWORD, true);
        $secondaryKeywords = get_post_meta($post_id, BaseConstants::META_KEY_SECONDARY_KEYWORDS, true);

        // If no keywords are found, we don't save anything
        if (empty($primaryKeyword) && empty($secondaryKeywords)) {
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        // Get the real URL based on slug, even if it's set as the front page
        // get_permalink() would return the root domain if it's the front page
        $url = home_url(user_trailingslashit($post->post_name));

        if (!$url) {
            return;
        }

        // Prepare keywords list
        $keywords = [];
        if (!empty($primaryKeyword)) {
            $keywords[] = $primaryKeyword;
        }

        if (!empty($secondaryKeywords)) {
            if (is_array($secondaryKeywords)) {
                $keywords = array_merge($keywords, $secondaryKeywords);
            } else if (is_string($secondaryKeywords)) {
                // Check if it's a comma-separated string or JSON
                $decoded = json_decode($secondaryKeywords, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $keywords = array_merge($keywords, $decoded);
                } else {
                    // Try to split by comma if it's a string but not JSON
                    $splitKeywords = explode(',', $secondaryKeywords);
                    $keywords = array_merge($keywords, array_map('trim', $splitKeywords));
                }
            }
        }

        $keywords = array_unique(array_filter($keywords));

        if (empty($keywords)) {
            return;
        }

        // Get existing data from wp_options
        $optionKey = BaseConstants::OPTION_USE_PLUGIN_PAGE_KEYWORDS_DATA;
        $existingData = get_option($optionKey, []);
        if (!is_array($existingData)) {
            $existingData = [];
        }

        // Update or add the entry for this post_id
        $existingData[$post_id] = [
            'url' => $url,
            'keywords' => array_values($keywords),
            'last_updated' => time()
        ];

        update_option($optionKey, $existingData, false);
    }
}
