<?php
/** @noinspection PhpSillyAssignmentInspection */
/** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
/** @noinspection RegExpUnnecessaryNonCapturingGroup */
/** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Adapters;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\ContentProviderInterface;
use RankingCoach\Inc\Traits\SingletonTrait;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Seo\Libs\ContentFetcher;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Helpers\Traits\RcContentRetrieveTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcContentStructureAnalysisTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcKeywordsAnalysisTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcLocalSeoTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcMediaAnalysisTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcReadabilityAnalysisTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcSchemaAnalysisTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcTechnicalSeoTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcUserIntentAnalysisTrait;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Class WordPressProvider
 *
 * Provides methods to manage content for SEO in WordPress.
 */
class WordPressProvider implements ContentProviderInterface
{
    use SingletonTrait;

    // Logging trait
    use RcLoggerTrait;

    // WordPress specific traits
    use RcMediaAnalysisTrait;
    use RcSchemaAnalysisTrait;
    use RcKeywordsAnalysisTrait;
    use RcContentRetrieveTrait;
    use RcTechnicalSeoTrait;
    use RcUserIntentAnalysisTrait;
    use RcReadabilityAnalysisTrait;
    use RcLocalSeoTrait;
    use RcContentStructureAnalysisTrait;

    private const THROTTLE_PERIOD = 1; // seconds between analyses
    private const URL_CACHE_EXPIRATION = 0; //default 60, 1 minute cache for URL content

    /**
     * Static cache for URL content
     * @var array<string, mixed>
     */
    public static array $contentCache = [];

    /**
     * Flag to indicate if caching is enabled
     * @var bool
     */
    public bool $useCache = true;
    
    /**
     * Clears the URL content cache.
     * 
     * @param string|null $url Optional specific URL to clear from cache
     * @return bool True if cache was cleared, false otherwise
     */
    public function clearUrlContentCache(?string $url = null): bool
    {
        if ($url !== null) {
            // Clear specific URL from cache
            $url = filter_var($url, FILTER_SANITIZE_URL);
            if (empty($url)) {
                return false;
            }
            
            // Generate possible cache keys for this URL (with different args)
            $baseKey = md5($url);
            
            // Clear from memory cache
            foreach (self::$contentCache as $key => $value) {
                if (str_starts_with($key, $baseKey)) {
                    unset(self::$contentCache[$key]);
                }
            }
            ContentFetcher::removeUrl($url);

            // Clear specific transients using WordPress native approach
            $this->clearTransientsByPattern('rankingcoach_url_content_' . $baseKey);
            
            return true;
        }
        
        // Clear all URL cache
        self::$contentCache = [];
        ContentFetcher::clearUrlCache();
        
        // Clear all rankingcoach_url_content transients
        $this->clearTransientsByPattern('rankingcoach_url_content_');
        
        return true;
    }

    /**
     * Clears transients matching a pattern using WordPress-native methods.
     * 
     * @param string $pattern The transient key pattern to match
     * @return void
     */
    private function clearTransientsByPattern(string $pattern): void
    {
        // Get all transients with timeout values to identify existing transients
        $allOptions = wp_load_alloptions();
        
        foreach ($allOptions as $optionName => $optionValue) {
            // Match transient timeout keys to identify transients
            if (str_starts_with($optionName, '_transient_timeout_' . $pattern)) {
                $transientKey = str_replace('_transient_timeout_', '', $optionName);
                delete_transient($transientKey);
            }
        }
    }

    /**
     * Gets the throttle period for analysis.
     *
     * @return int The throttle period in seconds
     */
    public static function getThrottlePeriod(): int
    {
        return self::THROTTLE_PERIOD;
    }

    /**
     * Checks if analysis should be throttled.
     *
     * @param int $postId The post ID to check
     * @return bool True if analysis should be throttled, false otherwise
     */
    public static function shouldThrottleAnalysis(int $postId): bool
    {
        $last_run = get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_DATE_TIMESTAMP, true);
        $current_time = time();

        // If analysis was run recently, throttle it
        if (!empty($last_run) && ($current_time - intval($last_run) < self::getThrottlePeriod())) {
            return true;
        }

        return false;
    }

    /**
     * Performs a safe WordPress redirect with a custom header.
     *
     * @param string $targetUrl The URL to redirect to
     * @param int $httpStatusCode HTTP status code to use
     */
    #[NoReturn]
    public static function redirectTo(string $targetUrl, int $httpStatusCode = 302): void
    {
        $customHeader = str_replace('\\', '', RANKINGCOACH_NAMESPACE);
        wp_safe_redirect($targetUrl, $httpStatusCode, $customHeader);
        exit;
    }

    /**
     * Helper method to get post URL from post ID.
     *
     * @param int $postId The post ID
     * @return string The post URL or empty string if post not found
     */
    public function getPostUrl(int $postId): string
    {
        // Get the URL for the post
        $post = get_post($postId);
        if (!$post) {
            return '';
        }
        return get_permalink($post) ?: home_url("?p={$postId}");
    }

    /**
     * Gets a random post URL for testing.
     *
     * @return string URL of a random post or empty string if none found
     */
    public function getRandomPostUrl(): string
    {
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => 1,
            'orderby' => 'rand',
        ]);

        if (!empty($posts)) {
            return get_permalink($posts[0]) ?: home_url("?p={$posts[0]->ID}");
        }

        return '';
    }

    /**
     * Gets a random page URL for testing.
     *
     * @return string URL of a random page or empty string if none found
     */
    public function getRandomPageUrl(): string
    {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => 1,
            'orderby' => 'rand',
        ]);

        if (!empty($pages)) {
            return get_permalink($pages[0]) ?: home_url("?p={$pages[0]->ID}");
        }

        return '';
    }

    /**
     * Gets the author URL for a specific post.
     *
     * @param int|null $postId The post ID (optional, uses current post if not provided)
     * @return string URL of the post author or empty string if none found
     */
    public function getAuthorUrl(?int $postId = null): string
    {
        // If no postId provided and we have a class property, use that
        if ($postId === null && isset($this->postId)) {
            $postId = $this->postId;
        }

        // If we still don't have a postId, return empty
        if (empty($postId)) {
            return '';
        }

        $post = get_post($postId);

        if (!empty($post)) {
            return get_author_posts_url($post->post_author);
        }

        return '';
    }

    /**
     * Gets appropriate benchmark values based on content type.
     *
     * @param int $postId The post ID
     * @return array The benchmark values
     */
    public function getLengthBenchmarksForContentType(int $postId): array
    {
        $contentType = $this->getPostType($postId) ?? 'default';
        // Map content types to appropriate benchmarks
        if (in_array($contentType, ['post', 'article', 'blog'])) {
            return SeoOptimiserConfig::CONTENT_LENGTH_BENCHMARKS['post'];
        } elseif (in_array($contentType, ['page', 'landing-page'])) {
            return SeoOptimiserConfig::CONTENT_LENGTH_BENCHMARKS['page'];
        } elseif (in_array($contentType, ['product', 'download'])) {
            return SeoOptimiserConfig::CONTENT_LENGTH_BENCHMARKS['product'];
        }

        // Default benchmark for unknown content types
        return SeoOptimiserConfig::CONTENT_LENGTH_BENCHMARKS['default'];
    }

    /**
     * Gets the post type for a given post ID.
     *
     * @param int $postId The post ID
     * @return string The post type
     */
    public function getPostType(int $postId): string
    {
        return get_post_type($postId);
    }

    /**
     * Gets the site URL.
     *
     * @return string The site URL
     */
    public function getSiteUrl(): string
    {
        // Prefer the exact value stored in wp_options to preserve scheme (https)
        $optionSiteUrl = (string) get_option('siteurl');
        $optionHomeUrl = (string) get_option('home');
        $preferred = $optionSiteUrl ?: $optionHomeUrl;

        if (!empty($preferred) && filter_var($preferred, FILTER_VALIDATE_URL)) {
            return rtrim($preferred, '/');
        }

        // Fallback to WordPress helpers
        $url = get_site_url() ?: home_url();

        // If SSL is detected or options indicate https, force https on the fallback
        $https = WordpressHelpers::sanitize_input('SERVER', 'HTTPS');
        $xForwardedProto = WordpressHelpers::sanitize_input('SERVER', 'HTTP_X_FORWARDED_PROTO');
        $httpsDetected = (
            (function_exists('is_ssl') && is_ssl()) ||
            (!empty($optionSiteUrl) && str_starts_with($optionSiteUrl, 'https://')) ||
            (!empty($optionHomeUrl) && str_starts_with($optionHomeUrl, 'https://')) ||
            ($https === 'on' || $https === '1') ||
            ($xForwardedProto === 'https')
        );

        if ($httpsDetected && function_exists('set_url_scheme')) {
            $url = set_url_scheme($url, 'https');
        }

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $url = '/';
        }

        return rtrim($url, '/');
    }

    /**
     * Gets the language code from the locale.
     *
     * @param string|null $locale Optional locale to extract language from
     * @return string The language code (e.g., 'en' from 'en_US')
     */
    public function getLanguageFromLocale(?string $locale = null): string
    {
        $locale = $locale ?? $this->getLocale();
        return substr($locale, 0, 2);
    }

    /**
     * Gets the current locale.
     *
     * @return string The current locale
     */
    public function getLocale(): string
    {
        return get_locale();
    }

    /**
     * Gets the first category name for a post.
     *
     * @param int $postId The post ID
     * @return string The first category name or empty string if none
     */
    public function getFirstCategoryName(int $postId): string
    {
        $categories = $this->getPostCategories($postId);
        return !empty($categories) ? $categories[0]->name : '';
    }

    /**
     * Gets categories for a post.
     *
     * @param int $postId The post ID
     * @return array The post categories
     */
    public function getPostCategories(int $postId): array
    {
        return get_the_category($postId);
    }

    /**
     * Gets the title of a post.
     *
     * @param int $postId The post ID
     * @return string The post title
     */
    public function getPostTitle(int $postId): string
    {
        // Empty for an unsaved "Add New" post instead of WordPress' "Auto Draft" placeholder.
        return WordpressHelpers::get_editable_post_title($postId);
    }

    /**
     * Gets cached data with the given key.
     *
     * @param string $cacheKey The cache key
     * @return mixed The cached data or false if not found
     */
    public function getTransient(string $cacheKey): mixed
    {
        return get_transient($cacheKey);
    }

    /**
     * Sets data in a cache with the given key.
     *
     * @param string $cacheKey The cache key
     * @param mixed $data The data to cache
     * @param int $expiration The expiration time in seconds
     * @return bool True on success, false on failure
     */
    public function setTransient(string $cacheKey, mixed $data, int $expiration): bool
    {
        return set_transient($cacheKey, $data, $expiration);
    }

    /**
     * Removes a transient with the given key.
     *
     * @param string $cacheKey The cache key
     * @return bool True on success, false on failure
     */
    public function deleteTransient(string $cacheKey): bool
    {
        return delete_transient($cacheKey);
    }

    /**
     * Gets a specific field from a post.
     *
     * @param string $field The field name
     * @param int $postId The post ID
     * @return string|int|array The field value or null if not found
     */
    public function getPostField(string $field, int $postId): string|int|array
    {
        return get_post_field($field, $postId);
    }

    /**
     * Checks if a path matches a robots.txt pattern.
     *
     * @param string $path The path to check
     * @param string $pattern The robots.txt pattern
     * @return bool Whether the path matches the pattern
     */
    public function isPathMatched(string $path, string $pattern): bool
    {
        // Handle an empty path or pattern
        if (empty($pattern) || empty($path)) {
            return false;
        }

        // Normalize paths
        $path = '/' . ltrim($path, '/');
        $pattern = '/' . ltrim($pattern, '/');

        // Exact match
        if ($path === $pattern) {
            return true;
        }

        // Check if a pattern ends with * (wildcard)
        if (str_ends_with($pattern, '*')) {
            $patternPrefix = substr($pattern, 0, -1);
            return str_starts_with($path, $patternPrefix);
        }

        // Check if a path starts with a pattern and a pattern is a directory
        if (str_ends_with($pattern, '/')) {
            return str_starts_with($path, $pattern);
        }

        // Check if pattern contains wildcards in the middle (not standard robots.txt but sometimes used)
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace(['*', '/'], ['.*', '\/'], $pattern) . '$/';
            return (bool)preg_match($regex, $path);
        }

        return false;
    }

    /**
     * Formats bytes into a human-readable format.
     *
     * @param int $bytes The number of bytes
     * @param int $precision The number of decimal places
     * @return string The formatted size
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Checks if a string is valid JSON.
     *
     * @param string $string The string to check
     * @return bool Whether the string is valid JSON
     */
    public function isJson(string $string): bool
    {
        if (empty($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }


    /**
     * Creates a DOMXPath object from the provided HTML content.
     *
     * @param string $htmlContent The HTML content to parse
     * @param bool $returnDocument Whether to return the DOMDocument object
     * @return DOMDocument|DOMXPath|null The DOMXPath object if successful, or null if an error occurs
     */
    protected function loadHTMLInDomXPath(string $htmlContent, bool $returnDocument = false): DOMXPath|DOMDocument|null
    {
        $dom = new DOMDocument();
        $htmlContent = mb_encode_numericentity($htmlContent, [0x80, 0x10FFFF, 0, ~0], 'UTF-8');

        libxml_use_internal_errors(true);
        $success = $dom->loadHTML($htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if (!$success) {
            return null;
        }

        // If $returnDocument is true, return the DOMDocument object
        if ($returnDocument) {
            return $dom;
        }

        return new DOMXPath($dom);
    }

    /**
     * Helper method to extract the HTML content string from a DOMXPath object's associated DOMDocument.
     * This is the inverse operation of loading HTML into a DOM/XPath.
     *
     * @param DOMXPath $xpath The DOMXPath object
     * @return string|null The HTML content string on success, or null if the associated DOMDocument cannot be accessed
     */
    protected function loadHTMLFromDomXPath(DOMXPath $xpath): ?string
    {
        $dom = null;
        $htmlString = null;

        // Attempt to get the root element from the DOMXPath object
        $rootElement = $xpath->query('/*')->item(0);

        // Check if a root element was found and it's a DOMElement
        if ($rootElement instanceof DOMElement) {
            $dom = $rootElement->ownerDocument;
        }
        // Fallback: sometimes DOMXPath might have a document property, though not standard public API
        if ($dom === null && isset($xpath->document)) {
            $dom = $xpath->document;
        }

        // If we successfully got the DOMDocument
        if ($dom instanceof DOMDocument) {
            libxml_use_internal_errors(true);
            $htmlString = $dom->saveHTML();
            libxml_clear_errors();
            libxml_use_internal_errors(false);
        }

        return $htmlString;
    }

    /**
     * Gets all published posts and pages.
     *
     * @param array $filter Optional filter parameters
     * @return array Array of post objects
     */
    public function fetchPosts(array $filter = []): array
    {
        // This can generate a lot of data, so limit to 50 by default
        // and only get posts and pages
        $args = [
            'post_type' => $filter['post_type'] ?? ['post', 'page'],
            'post_status' => $filter['post_status'] ?? 'publish',
            'posts_per_page' => $filter['posts_per_page'] ?? 50,
            'orderby' => $filter['orderby'] ?? 'date',
            'order' => $filter['order'] ?? 'DESC',
            'no_found_rows' => true, // Improves performance when pagination not needed
        ];

        if (isset($filter['s'])) {
            $args['s'] = $filter['s'];
        }

        if (isset($filter['category'])) {
            $args['category_name'] = $filter['category'];
        }

        if (isset($filter['tag'])) {
            $args['tag'] = $filter['tag'];
        }

        try {
            $query = new WP_Query($args);
            if (is_wp_error($query) || !$query->have_posts()) {
                return [];
            }
            return $query->posts;
        } catch (Exception $e) {
            $this->log('Error fetching posts: ' . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Gets and validates a WordPress post object.
     *
     * @param int $postId The post ID
     * @return WP_Post|null Returns the WP_Post object or null if not found/invalid
     */
    protected function getValidatedPost(int $postId): ?WP_Post
    {
        $post = get_post($postId);
        if (!$post instanceof WP_Post) {
            return null;
        }
        return $post;
    }

    /**
     * Gets raw post content directly from the database.
     *
     * @param int $postId The post ID
     * @return string Returns the raw post_content, or empty string on failure
     */
    protected function getRawPostContent(int $postId): string
    {
        $post = $this->getValidatedPost($postId);
        return $post ? $post->post_content : '';
    }

    /**
     * Gets post content processed by the standard WordPress 'the_content' filter.
     * Handles standard shortcodes, basic blocks, etc.
     *
     * @param int $postId The post ID
     * @return string Returns the filtered content, or empty string on failure
     */
    protected function getFilteredPostContent(int $postId): string
    {
        global $post;
        $validatedPost = $this->getValidatedPost($postId);
        if (!$validatedPost) {
            return '';
        }

        $original_post = $post;
        $post = $validatedPost;
        setup_postdata($post);

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        $content = apply_filters('the_content', $post->post_content);

        wp_reset_postdata();
        $post = $original_post;

        return (string)$content;
    }

    /**
     * Fetches content (full HTML) from a URL using WordPress HTTP API with caching.
     * Preferred for fetching content from the *current* site's URL.
     * Implements multi-level caching to prevent redundant calls to the same URL.
     * 
     * @param string $url The URL to fetch content from
     * @param array $args Optional arguments for the request
     * @param bool $getHeader Whether to only get the headers
     * @return string|array|WP_Error The fetched content, response array, or WP_Error
     */
    public function fetchInternalUrlContent(string $url, array $args = [], bool $getHeader = false): string|array|WP_Error
    {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        if (empty($url)) {
            // Log error
            $this->log('Cannot fetch content from an empty or invalid URL: ' . $url, 'ERROR');
            return new WP_Error('invalid_url', __('Invalid URL provided.', 'beyondseo'));
        }
        
        // Generate a unique cache key based on URL and request parameters
        $cacheKey = md5($url . serialize($args) . ($getHeader ? '1' : '0'));
        
        // Check if we already have this URL in our cache
        if (isset(self::$contentCache[$cacheKey])) {
            // deprecated log registering
            //$this->log('Using cached content for URL: ' . $url, 'DEBUG');
            return self::$contentCache[$cacheKey];
        }
        
        // Check if we have this URL in WordPress transients for longer-term caching
        $transientKey = 'rankingcoach_url_content_' . $cacheKey;
        $cachedContent = $this->getTransient($transientKey);
        if ($cachedContent !== false) {
            // Store in memory cache for future requests in this session
            self::$contentCache[$cacheKey] = $cachedContent;
            ContentFetcher::registerUrl($url);
            
            $this->log('Using transient cached content for URL: ' . $url, 'DEBUG');
            return $cachedContent;
        }

        // Check the shared ContentFetcher cache — avoids duplicate HTTP requests when
        // ContentFetcher::fetchContent() already fetched this URL in the same request
        if (!$getHeader) {
            $cachedHtml = ContentFetcher::getCachedRawHtml($url);
            if ($cachedHtml !== null) {
                self::$contentCache[$cacheKey] = $cachedHtml;
                return $cachedHtml;
            }
        }

        $isLocalhost = in_array(wp_parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true);

        $args = array_merge([
            'timeout' => 4,
            'sslverify' => !$isLocalhost && wp_get_environment_type() === 'production',
            'headers' => [
                'X-RankingCoach-Bypass-Redirect' => '1'
            ],
        ], $args);
        
        // If headers are already set in $args, merge our custom header
        if (isset($args['headers']) && is_array($args['headers']) && !isset($args['headers']['X-RankingCoach-Bypass-Redirect'])) {
            $args['headers']['X-RankingCoach-Bypass-Redirect'] = '1';
        }

        // For header requests, we don't cache as they're typically used for status checks
        if ($getHeader) {
            return wp_remote_head($url, $args);
        }
        
        // Perform the actual request
        $fetchStart = microtime(true);
        $response = wp_remote_get($url, $args);
        $fetchDuration = round(microtime(true) - $fetchStart, 4);

        if (is_wp_error($response)) {
            // Log the error
            $this->log('Failed to fetch URL ' . $url . '. Error: ' . $response->get_error_message(), 'ERROR');
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            /* translators: %d is the HTTP status code */
            $errorMessage = sprintf(__('HTTP error fetching URL. Status code: %d', 'beyondseo'), $statusCode);
            // Log the error
            $this->log('Failed to fetch URL ' . $url . '. Error: ' . $errorMessage, 'ERROR');
            return new WP_Error('http_error', $errorMessage);
        }

        $result = wp_remote_retrieve_body($response);

        // Save the result in the memory cache
        self::$contentCache[$cacheKey] = $result;

        // Store full response in shared ContentFetcher cache (body + headers + DOM + timing)
        // so any subsequent fetchContent() or fetchInternalUrlContent() call skips the HTTP request
        ContentFetcher::storeFromWpResponse($url, $response, $fetchDuration);
        
        // Store in WordPress transients for persistence between requests
        if (self::URL_CACHE_EXPIRATION > 0) {
            $this->setTransient($transientKey, $result, self::URL_CACHE_EXPIRATION);
        }

        return $result;
    }

    /**
     * Returns HTTP response headers cached from a prior GET fetch, or null if not yet fetched.
     * Use this after fetchInternalUrlContent() to avoid a separate HEAD request.
     *
     * @param string $url The URL whose headers to retrieve
     * @return array|null Cached headers array, or null if the URL has not been fetched via GET yet
     */
    public function getCachedHeaders(string $url): ?array
    {
        return ContentFetcher::getCachedHeaders(filter_var($url, FILTER_SANITIZE_URL));
    }

    /**
     * Extracts the main content area from an HTML string using common selectors.
     *
     * @param string $html The full HTML content
     * @return string The extracted main content HTML, or empty string if not found
     */
    protected function extractMainContentFromHtml(string $html): string
    {
        $xpath = $this->loadHTMLInDomXPath($html);
        if (!$xpath) {
            return '';
        }

        // Define common content area selectors
        $content_selectors = [
            '//article',
            '//main',
            '//*[@id="main-content"]',
            '//*[@class="main-content"]',
            '//*[@id="primary"]',
            '//*[@class="site-content"]',
            '//*[@class="entry-content"]',
            '//*[@id="content"]',
            '//*[@class="content"]',
            '//body',
        ];

        foreach ($content_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                // Return the HTML of the first matching node
                return $xpath->document->saveHTML($nodes->item(0));
            }
        }

        // If no specific content area found, maybe return the whole body or empty
        $body_nodes = $xpath->query('//body');
        if ($body_nodes && $body_nodes->length > 0) {
            $body_html = $xpath->document->saveHTML($body_nodes->item(0));
            return preg_replace('/^<body[^>]*>(.*)<\/body>$/is', '$1', $body_html);
        }

        return '';
    }

    /**
     * Temporarily applies filters to bypass common membership/protection plugins.
     *
     * @return callable A function that can be called to remove the applied filters
     */
    protected function applyProtectionBypassFilters(): callable
    {
        $filters_to_remove = [];

        // MemberPress
        if (class_exists('MeprRule')) {
            add_filter('mepr-pre-run-rule-content', '__return_true', 99);
            $filters_to_remove[] = ['mepr-pre-run-rule-content', '__return_true', 99];
        }

        // WooCommerce Memberships
        if (class_exists('WC_Memberships')) {
            add_filter('wc_memberships_is_post_content_restricted', '__return_false', 999);
            $filters_to_remove[] = ['wc_memberships_is_post_content_restricted', '__return_false', 999];
        }

        // Return a closure that will remove these filters
        return function () use ($filters_to_remove) {
            foreach ($filters_to_remove as $filter_info) {
                remove_filter($filter_info[0], $filter_info[1], $filter_info[2]);
            }
        };
    }

    /**
     * Counts the number of words in a text.
     *
     * @param string $text The text to count words in
     * @return int The number of words
     */
    public function getWordCount(string $text): int
    {
        return str_word_count($text);
    }

    /**
     * Checks if a link is internal (same domain) or external.
     *
     * @param string $url The URL to check
     * @return bool True if internal, false if external
     */
    public function isInternalLink(string $url): bool
    {
        // Get the site domain
        $siteUrl = $this->getSiteUrl();
        $siteDomain = wp_parse_url($siteUrl, PHP_URL_HOST);

        // If the URL starts with a slash or hash, it's internal
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        // Check if the URL contains the site domain
        $urlDomain = wp_parse_url($url, PHP_URL_HOST);
        return $urlDomain === $siteDomain;
    }

    /**
     * Gets the site name.
     *
     * @return string The site name
     */
    public function getSiteName(): string
    {
        return get_bloginfo('name');
    }
    
    /**
     * Gets all the keywords for a post.
     *
     * @param int $postId The post ID
     * @return array The keywords array
     */
    public function getKeywords(int $postId): array
    {
        $primaryKeyword = $this->getPrimaryKeyword($postId) ?: '';
        $secondaryKeywords = $this->getSecondaryKeywords($postId) ?: [];

        if (empty($primaryKeyword) && empty($secondaryKeywords)) {
            return [];
        }

        $keywords = array_merge([$primaryKeyword], $secondaryKeywords);
        $keywords = array_filter($keywords);

        return array_values($keywords);
    }
}
