<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Sitemap;

if ( !defined('ABSPATH') ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;
use WP_Post;
use WP_Term;
use WP_User;

/**
 * EntryBuilder class to handle sitemap entries generation
 * with permalink-aware URL generation, memory management,
 * and comprehensive permalink structure support.
 */
class EntryBuilder
{
    use RcLoggerTrait;

    private const DAY_IN_SECONDS = 86400;
    private const MAX_POSTS_PER_BATCH = 1000;
    private const CACHE_DURATION = 3600; // 1 hour

    /** @var string[] Post types included in the sitemap. */
    public const POST_TYPES = ['post', 'page'];

    private const TRANSIENT_POST_ENTRIES = 'rankingcoach_sitemap_post_entries';
    private const TRANSIENT_TAXONOMY_ENTRIES = 'rankingcoach_sitemap_taxonomy_entries';

    /**
     * Get entries based on type with error handling and caching
     *
     * @param string $type The type of entries to retrieve (general, posts, taxonomies)
     * @return array
     */
    public function getEntries(string $type): array {
        $entries = [];
        $urlsIndex = []; // Prevent duplicate URLs

        // Posts and Pages
        if (in_array($type, ['general', 'posts'])) {
            $entries = array_merge($entries, $this->getPostEntries($urlsIndex));
        }

        // Categories and Terms
        if (in_array($type, ['general', 'taxonomies'])) {
            $entries = array_merge($entries, $this->getTaxonomyEntries($urlsIndex));
        }

        // Homepage
        if ($type === 'general') {
            $entries = array_merge($entries, $this->getHomepageEntry($urlsIndex));
        }

        return $entries;
    }

    /**
     * Get post and page entries with permalink-aware URL generation and memory management
     */
    private function getPostEntries(array &$urlsIndex): array
    {
        // Check cache first
        $cached_entries = get_transient(self::TRANSIENT_POST_ENTRIES);
        if ($cached_entries !== false) {
            foreach ($cached_entries as $entry) {
                $urlsIndex[$entry['loc']] = true;
            }
            return $cached_entries;
        }

        $entries = [];
        $permalinkStructure = get_option('permalink_structure');
        
        // Get total post count first to manage memory
        $total_posts = wp_count_posts();
        $total_pages = wp_count_posts('page');
        $total_count = ($total_posts->publish ?? 0) + ($total_pages->publish ?? 0);
        
        // Use pagination for large sites
        $posts_per_batch = min(self::MAX_POSTS_PER_BATCH, $total_count);
        $offset = 0;
        
        do {
            try {
                $posts = get_posts([
                    'post_type' => self::POST_TYPES,
                    'post_status' => 'publish',
                    'numberposts' => $posts_per_batch,
                    'offset' => $offset,
                    'orderby' => 'modified',
                    'order' => 'DESC'
                ]);

                foreach ($posts as $post) {
                    try {
                        // Skip posts marked noindex or explicitly excluded from the sitemap
                        if ($this->isPostExcluded($post)) {
                            continue;
                        }

                        // Generate permalink respecting the current structure
                        $permalink = $this->generatePermalinkAwareUrl($post, $permalinkStructure);
                        
                        // Skip duplicates
                        if (isset($urlsIndex[$permalink])) {
                            continue;
                        }
                        $urlsIndex[$permalink] = true;

                        $entry = [
                            'loc' => $permalink,
                            'lastmod' => get_post_modified_time('c', true, $post),
                            'priority' => $this->calculatePostPriority($post),
                            'changefreq' => $this->getPostChangeFrequency($post)
                        ];

                        // Featured image with error handling
                        $thumbnail_id = get_post_thumbnail_id($post);
                        if ($thumbnail_id) {
                            $image_url = wp_get_attachment_url($thumbnail_id);
                            if ($image_url && filter_var($image_url, FILTER_VALIDATE_URL)) {
                                $entry['image'] = $image_url;
                            }
                        }

                        $entries[] = $entry;
                        
                    } catch (Exception $e) {
                        $this->log("Sitemap: Error processing post $post->ID: " . $e->getMessage());
                        continue;
                    }
                }
                
                $offset += $posts_per_batch;
                
                // Memory management: free up memory if we're processing in batches
                if ($total_count > self::MAX_POSTS_PER_BATCH) {
                    wp_cache_flush();
                }
                
            } catch (Exception $e) {
                $this->log('Sitemap: Error in post batch processing: ' . $e->getMessage());
                break;
            }
            
        } while (count($posts) === $posts_per_batch && $offset < $total_count);

        // Cache the results
        set_transient(self::TRANSIENT_POST_ENTRIES, $entries, self::CACHE_DURATION);

        return $entries;
    }

    /**
     * Checks whether a post must be left out of the sitemap.
     *
     * A post is excluded when it is marked noindex (the sitemap must never
     * list URLs the rendered robots meta forbids indexing for) or when it is
     * explicitly excluded from the sitemap via its advanced settings.
     */
    private function isPostExcluded(WP_Post $post): bool
    {
        return get_post_meta($post->ID, MetaTags::META_NOINDEX_FOR_PAGE, true)
            || get_post_meta($post->ID, MetaTags::META_EXCLUDE_SITEMAP_FOR_PAGE, true);
    }

    /**
     * Generate permalink-aware URL for posts
     */
    private function generatePermalinkAwareUrl(WP_Post $post, string $permalinkStructure): string
    {
        // Use WordPress native permalink generation which respects structure
        $permalink = get_permalink($post);
        
        // Validate the generated permalink matches expected structure
        if (!empty($permalinkStructure) && $this->shouldValidatePermalink($post)) {
            $validatedPermalink = $this->validatePermalinkStructure($post, $permalink, $permalinkStructure);
            if ($validatedPermalink !== $permalink) {
                // Log potential permalink inconsistency for debugging
                $this->log("Sitemap: Permalink structure mismatch for post $post->ID. Expected structure: $permalinkStructure");
            }
            return $validatedPermalink;
        }
        
        return $permalink;
    }

    /**
     * Validate permalink against expected structure with comprehensive structure support
     */
    private function validatePermalinkStructure(WP_Post $post, string $permalink, string $structure): string
    {
        try {
            // Category-based permalinks
            if (str_contains($structure, '%category%')) {
                return $this->validateCategoryPermalink($post, $permalink, $structure);
            }
            
            // Author-based permalinks
            if (str_contains($structure, '%author%')) {
                return $this->validateAuthorPermalink($post, $permalink, $structure);
            }
            
            // Date-based permalinks
            if ($this->isDateBasedStructure($structure)) {
                return $this->validateDatePermalink($post, $permalink, $structure);
            }
            
            // Post ID-based permalinks
            if (str_contains($structure, '%post_id%')) {
                return $this->validatePostIdPermalink($post, $permalink, $structure);
            }
            
            // Custom taxonomy permalinks
            $customTaxonomy = $this->detectCustomTaxonomyInStructure($structure);
            if ($customTaxonomy) {
                return $this->validateCustomTaxonomyPermalink($post, $permalink, $structure, $customTaxonomy);
            }
            
            // Tag-based permalinks
            if (str_contains($structure, '%tag%')) {
                return $this->validateTagPermalink($post, $permalink, $structure);
            }
            
        } catch (Exception $e) {
            $this->log("Sitemap: Error in permalink validation for post $post->ID: " . $e->getMessage());
        }
        
        return $permalink;
    }

    /**
     * Validate category-based permalink structure
     */
    private function validateCategoryPermalink(WP_Post $post, string $permalink, string $structure): string
    {
        $categories = get_the_category($post->ID);
        if (!empty($categories)) {
            $primaryCategory = $categories[0];
            $expectedCategorySlug = $primaryCategory->slug;
            
            // Check if category slug is present in permalink
            if (!str_contains($permalink, $expectedCategorySlug)) {
                // Reconstruct permalink with proper category
                return $this->reconstructCategoryBasedPermalink($post, $primaryCategory, $structure);
            }
        }
        return $permalink;
    }

    /**
     * Validate author-based permalink structure
     */
    private function validateAuthorPermalink(WP_Post $post, string $permalink, string $structure): string
    {
        $author = get_userdata($post->post_author);
        if ($author) {
            $authorSlug = $author->user_nicename;
            
            // Check if author slug is present in permalink
            if (!str_contains($permalink, $authorSlug)) {
                return $this->reconstructAuthorBasedPermalink($post, $author, $structure);
            }
        }
        return $permalink;
    }

    /**
     * Validate date-based permalink structure
     */
    private function validateDatePermalink(WP_Post $post, string $permalink, string $structure): string
    {
        $postDate = get_the_date('Y-m-d H:i:s', $post);
        $dateComponents = $this->extractDateComponents($postDate);
        
        // Check each date component in the structure
        $expectedComponents = [];
        if (str_contains($structure, '%year%')) {
            $expectedComponents['year'] = $dateComponents['year'];
        }
        if (str_contains($structure, '%monthnum%')) {
            $expectedComponents['month'] = $dateComponents['month'];
        }
        if (str_contains($structure, '%day%')) {
            $expectedComponents['day'] = $dateComponents['day'];
        }
        
        // Validate each component exists in permalink
        foreach ($expectedComponents as $value) {
            if (!str_contains($permalink, $value)) {
                return $this->reconstructDateBasedPermalink($post, $structure, $dateComponents);
            }
        }
        
        return $permalink;
    }

    /**
     * Validate post ID-based permalink structure
     */
    private function validatePostIdPermalink(WP_Post $post, string $permalink, string $structure): string
    {
        $postId = (string) $post->ID;
        
        // Check if post ID is present in permalink
        if (!str_contains($permalink, $postId)) {
            return $this->reconstructPostIdBasedPermalink($post, $structure);
        }
        
        return $permalink;
    }

    /**
     * Validate custom taxonomy permalink structure
     */
    private function validateCustomTaxonomyPermalink(WP_Post $post, string $permalink, string $structure, string $taxonomy): string
    {
        $terms = get_the_terms($post->ID, $taxonomy);
        if (!empty($terms) && !is_wp_error($terms)) {
            $primaryTerm = $terms[0];
            $expectedTermSlug = $primaryTerm->slug;
            
            // Check if term slug is present in permalink
            if (!str_contains($permalink, $expectedTermSlug)) {
                return $this->reconstructCustomTaxonomyPermalink($post, $primaryTerm, $structure, $taxonomy);
            }
        }
        return $permalink;
    }

    /**
     * Validate tag-based permalink structure
     */
    private function validateTagPermalink(WP_Post $post, string $permalink, string $structure): string
    {
        $tags = get_the_tags($post->ID);
        if (!empty($tags) && !is_wp_error($tags)) {
            $primaryTag = $tags[0];
            $expectedTagSlug = $primaryTag->slug;
            
            // Check if tag slug is present in permalink
            if (!str_contains($permalink, $expectedTagSlug)) {
                return $this->reconstructTagBasedPermalink($post, $primaryTag, $structure);
            }
        }
        return $permalink;
    }

    /**
     * Check if structure is date-based
     */
    private function isDateBasedStructure(string $structure): bool
    {
        $dateTokens = ['%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%'];
        foreach ($dateTokens as $token) {
            if (str_contains($structure, $token)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect custom taxonomy in permalink structure
     */
    private function detectCustomTaxonomyInStructure(string $structure): ?string
    {
        // Match patterns like %product_category%, %event_type%, etc.
        if (preg_match('/%([a-zA-Z_]+)%/', $structure, $matches)) {
            $possibleTaxonomy = $matches[1];
            
            // Exclude known WordPress tokens
            $knownTokens = [
                'year', 'monthnum', 'day', 'hour', 'minute', 'second',
                'postname', 'post_id', 'category', 'tag', 'author'
            ];
            
            if (!in_array($possibleTaxonomy, $knownTokens) && taxonomy_exists($possibleTaxonomy)) {
                return $possibleTaxonomy;
            }
        }
        return null;
    }

    /**
     * Extract date components from post date
     */
    private function extractDateComponents(string $date): array
    {
        $timestamp = strtotime($date);
        return [
            'year' => gmdate('Y', $timestamp),
            'month' => gmdate('m', $timestamp),
            'day' => gmdate('d', $timestamp),
            'hour' => gmdate('H', $timestamp),
            'minute' => gmdate('i', $timestamp),
            'second' => gmdate('s', $timestamp)
        ];
    }

    /**
     * Get supported permalink structure patterns
     */
    public function getSupportedPermalinkStructures(): array
    {
        return [
            'category' => [
                'pattern' => '/%category%/%postname%/',
                'description' => 'Category-based permalinks',
                'example' => '/news/my-post-title/'
            ],
            'author' => [
                'pattern' => '/%author%/%postname%/',
                'description' => 'Author-based permalinks',
                'example' => '/john-doe/my-post-title/'
            ],
            'date_full' => [
                'pattern' => '/%year%/%monthnum%/%day%/%postname%/',
                'description' => 'Full date-based permalinks',
                'example' => '/2024/01/15/my-post-title/'
            ],
            'date_month' => [
                'pattern' => '/%year%/%monthnum%/%postname%/',
                'description' => 'Year and month-based permalinks',
                'example' => '/2024/01/my-post-title/'
            ],
            'post_id' => [
                'pattern' => '/%post_id%/',
                'description' => 'Post ID-based permalinks',
                'example' => '/123/'
            ],
            'tag' => [
                'pattern' => '/%tag%/%postname%/',
                'description' => 'Tag-based permalinks',
                'example' => '/news-tag/my-post-title/'
            ],
            'custom_taxonomy' => [
                'pattern' => '/%product_category%/%postname%/',
                'description' => 'Custom taxonomy-based permalinks',
                'example' => '/electronics/my-product/'
            ]
        ];
    }

    /**
     * Debug method to analyze permalink structure issues
     */
    public function debugPermalinkStructure(WP_Post $post, string $structure): array
    {
        $debug_info = [
            'post_id' => $post->ID,
            'post_type' => $post->post_type,
            'permalink_structure' => $structure,
            'generated_permalink' => get_permalink($post),
            'should_validate' => $this->shouldValidatePermalink($post),
            'detected_patterns' => [],
            'validation_issues' => []
        ];

        // Detect patterns in structure
        $patterns = [
            '%category%' => 'Category-based',
            '%author%' => 'Author-based',
            '%year%' => 'Year component',
            '%monthnum%' => 'Month component',
            '%day%' => 'Day component',
            '%post_id%' => 'Post ID',
            '%tag%' => 'Tag-based',
            '%postname%' => 'Post slug'
        ];

        foreach ($patterns as $pattern => $description) {
            if (str_contains($structure, $pattern)) {
                $debug_info['detected_patterns'][$pattern] = $description;
            }
        }

        // Check for custom taxonomy patterns
        $customTaxonomy = $this->detectCustomTaxonomyInStructure($structure);
        if ($customTaxonomy) {
            $debug_info['detected_patterns']["%$customTaxonomy%"] = "Custom taxonomy: $customTaxonomy";
        }

        // Validate if should be validated
        if ($debug_info['should_validate']) {
            try {
                $validated_permalink = $this->validatePermalinkStructure($post, $debug_info['generated_permalink'], $structure);
                $debug_info['validated_permalink'] = $validated_permalink;
                $debug_info['permalink_changed'] = ($validated_permalink !== $debug_info['generated_permalink']);
            } catch (Exception $e) {
                $debug_info['validation_issues'][] = $e->getMessage();
            }
        }

        return $debug_info;
    }

    /**
     * Reconstruct category-based permalink
     */
    private function reconstructCategoryBasedPermalink(WP_Post $post, WP_Term $category, string $structure): string
    {
        $homeUrl = trailingslashit(home_url());
        $categoryPath = $this->buildCategoryPath($category);
        $postSlug = $post->post_name;
        
        // Build permalink based on structure
        $permalink = str_replace(
            ['%category%', '%postname%'],
            [$categoryPath, $postSlug],
            $structure
        );
        
        return $homeUrl . ltrim($permalink, '/');
    }

    /**
     * Reconstruct author-based permalink
     */
    private function reconstructAuthorBasedPermalink(WP_Post $post, WP_User $author, string $structure): string
    {
        $homeUrl = trailingslashit(home_url());
        $authorSlug = $author->user_nicename;
        $postSlug = $post->post_name;
        
        // Build permalink based on structure
        $permalink = str_replace(
            ['%author%', '%postname%'],
            [$authorSlug, $postSlug],
            $structure
        );
        
        return $homeUrl . ltrim($permalink, '/');
    }

    /**
     * Reconstruct date-based permalink
     */
    private function reconstructDateBasedPermalink(WP_Post $post, string $structure, array $dateComponents): string
    {
        $homeUrl = trailingslashit(home_url());
        $postSlug = $post->post_name;
        
        // Replace all date tokens
        $replacements = [
            '%year%' => $dateComponents['year'],
            '%monthnum%' => $dateComponents['month'],
            '%day%' => $dateComponents['day'],
            '%hour%' => $dateComponents['hour'],
            '%minute%' => $dateComponents['minute'],
            '%second%' => $dateComponents['second'],
            '%postname%' => $postSlug
        ];
        
        $permalink = str_replace(array_keys($replacements), array_values($replacements), $structure);
        
        return $homeUrl . ltrim($permalink, '/');
    }

    /**
     * Reconstruct post ID-based permalink
     */
    private function reconstructPostIdBasedPermalink(WP_Post $post, string $structure): string
    {
        $homeUrl = trailingslashit(home_url());
        $postId = $post->ID;
        $postSlug = $post->post_name;
        
        // Build permalink based on structure
        $permalink = str_replace(
            ['%post_id%', '%postname%'],
            [$postId, $postSlug],
            $structure
        );
        
        return $homeUrl . ltrim($permalink, '/');
    }

    /**
     * Reconstruct custom taxonomy-based permalink
     */
    private function reconstructCustomTaxonomyPermalink(WP_Post $post, WP_Term $term, string $structure, string $taxonomy): string
    {
        $homeUrl = trailingslashit(home_url());
        $termPath = $this->buildTaxonomyPath($term);
        $postSlug = $post->post_name;
        
        // Build permalink based on structure
        $permalink = str_replace(
            ["%$taxonomy%", '%postname%'],
            [$termPath, $postSlug],
            $structure
        );
        
        return $homeUrl . ltrim($permalink, '/');
    }

    /**
     * Reconstruct tag-based permalink
     */
    private function reconstructTagBasedPermalink(WP_Post $post, WP_Term $tag, string $structure): string
    {
        $homeUrl = trailingslashit(home_url());
        $tagSlug = $tag->slug;
        $postSlug = $post->post_name;
        
        // Build permalink based on structure
        $permalink = str_replace(
            ['%tag%', '%postname%'],
            [$tagSlug, $postSlug],
            $structure
        );
        
        return $homeUrl . ltrim($permalink, '/');
    }

    /**
     * Build hierarchical category path
     */
    private function buildCategoryPath(WP_Term $category): string
    {
        return $this->buildTaxonomyPath($category);
    }

    /**
     * Build hierarchical taxonomy path for any taxonomy
     */
    private function buildTaxonomyPath(WP_Term $term): string
    {
        $path = [];
        $current = $term;
        
        while ($current && !is_wp_error($current)) {
            array_unshift($path, $current->slug);
            if ($current->parent) {
                $current = get_term($current->parent, $current->taxonomy);
            } else {
                break;
            }
        }
        
        return implode('/', $path);
    }

    /**
     * Check if permalink should be validated
     */
    private function shouldValidatePermalink(WP_Post $post): bool
    {
        // Validate for posts and custom post types, but not for pages or hierarchical types
        if ($post->post_type === 'page' || is_post_type_hierarchical($post->post_type)) {
            return false;
        }
        
        // Always validate posts
        if ($post->post_type === 'post') {
            return true;
        }
        
        // Validate custom post types that have public URLs
        $post_type_object = get_post_type_object($post->post_type);
        return $post_type_object && $post_type_object->public && $post_type_object->publicly_queryable;
    }

    /**
     * Get taxonomy entries with error handling and caching
     */
    private function getTaxonomyEntries(array &$urlsIndex): array
    {
        // Check cache first
        $cached_entries = get_transient(self::TRANSIENT_TAXONOMY_ENTRIES);
        if ($cached_entries !== false) {
            foreach ($cached_entries as $entry) {
                $urlsIndex[$entry['loc']] = true;
            }
            return $cached_entries;
        }

        $entries = [];
        
        try {
            $taxonomies = get_taxonomies(['public' => true]);
            
            foreach ($taxonomies as $taxonomy) {
                try {
                    $terms = get_terms([
                        'taxonomy' => $taxonomy,
                        'hide_empty' => true,
                        'number' => 1000, // Limit to prevent memory issues
                        'fields' => 'all'
                    ]);

                    if (is_wp_error($terms)) {
                        $this->log("Sitemap: Error getting terms for taxonomy $taxonomy: " . $terms->get_error_message());
                        continue;
                    }

                    foreach ($terms as $term) {
                        try {
                            $termLink = get_term_link($term);
                            
                            if (is_wp_error($termLink)) {
                                $this->log("Sitemap: Error getting term link for term $term->term_id: " . $termLink->get_error_message());
                                continue;
                            }
                            
                            // Validate URL
                            if (!filter_var($termLink, FILTER_VALIDATE_URL)) {
                                $this->log("Sitemap: Invalid term URL for term $term->term_id: $termLink");
                                continue;
                            }
                            
                            // Skip duplicates
                            if (isset($urlsIndex[$termLink])) {
                                continue;
                            }
                            $urlsIndex[$termLink] = true;

                            $entries[] = [
                                'loc' => $termLink,
                                'lastmod' => $this->getTermLastModified($term),
                                'priority' => $this->calculateTermPriority($term),
                                'changefreq' => 'weekly'
                            ];
                            
                        } catch (Exception $e) {
                            $this->log("Sitemap: Error processing term $term->term_id: " . $e->getMessage());
                            continue;
                        }
                    }
                    
                } catch (Exception $e) {
                    $this->log("Sitemap: Error processing taxonomy $taxonomy: " . $e->getMessage());
                    continue;
                }
            }
            
        } catch (Exception $e) {
            $this->log('Sitemap: Critical error in taxonomy processing: ' . $e->getMessage());
        }

        // Cache the results
        set_transient(self::TRANSIENT_TAXONOMY_ENTRIES, $entries, self::CACHE_DURATION);

        return $entries;
    }

    /**
     * Get homepage entry
     */
    private function getHomepageEntry(array &$urlsIndex): array
    {
        $homeUrl = trailingslashit(home_url());

        if (isset($urlsIndex[$homeUrl])) {
            return [];
        }

        // A static front page marked noindex/excluded keeps the homepage out too
        $frontPageId = (int) get_option('page_on_front');
        if ($frontPageId) {
            $frontPage = get_post($frontPageId);
            if ($frontPage && $this->isPostExcluded($frontPage)) {
                return [];
            }
        }

        $urlsIndex[$homeUrl] = true;

        return [[
            'loc' => $homeUrl,
            'lastmod' => $this->getHomepageLastModified(),
            'priority' => '1.0',
            'changefreq' => 'daily'
        ]];
    }

    /**
     * Calculate post priority based on various factors
     */
    private function calculatePostPriority(WP_Post $post): string
    {
        // Homepage gets highest priority
        if ($post->ID == get_option('page_on_front')) {
            return '1.0';
        }
        
        // Important pages get higher priority
        if ($post->post_type === 'page') {
            $priority = 0.8;
        } else {
            // Recent posts get higher priority
            $daysSincePublication = (time() - strtotime($post->post_date)) / self::DAY_IN_SECONDS;
            if ($daysSincePublication < 30) {
                $priority = 0.8;
            } elseif ($daysSincePublication < 90) {
                $priority = 0.7;
            } else {
                $priority = 0.6;
            }
        }
        
        return number_format($priority, 1);
    }

    /**
     * Calculate term priority
     */
    private function calculateTermPriority(WP_Term $term): string
    {
        $priority = 0.6; // Base priority for terms
        
        // Terms with more posts get higher priority
        if ($term->count > 10) {
            $priority = 0.7;
        } elseif ($term->count > 5) {
            $priority = 0.65;
        }
        
        return number_format($priority, 1);
    }

    /**
     * Get post change frequency
     */
    private function getPostChangeFrequency(WP_Post $post): string
    {
        $daysSinceModified = (time() - strtotime($post->post_modified)) / self::DAY_IN_SECONDS;
        
        if ($daysSinceModified < 7) {
            return 'daily';
        } elseif ($daysSinceModified < 30) {
            return 'weekly';
        } elseif ($daysSinceModified < 90) {
            return 'monthly';
        } else {
            return 'yearly';
        }
    }

    /**
     * Get term last modified date (estimate based on latest post) with caching
     */
    private function getTermLastModified(WP_Term $term): string
    {
        // Check cache first
        $cache_key = "rankingcoach_term_lastmod_$term->term_id";
        $cached_date = get_transient($cache_key);
        if ($cached_date !== false)
            return $cached_date;

        try {
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $posts = get_posts([
                'post_type' => 'post',
                'post_status' => 'publish',
                'tax_query' => [
                    [
                        'taxonomy' => $term->taxonomy,
                        'field' => 'term_id',
                        'terms' => $term->term_id
                    ]
                ],
                'numberposts' => 1,
                'orderby' => 'modified',
                'order' => 'DESC'
            ]);
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            
            $lastmod = !empty($posts) ? 
                get_post_modified_time('c', true, $posts[0]) : 
                gmdate('c', time());
                
            // Cache for 1 hour
            set_transient($cache_key, $lastmod, 3600);
            
            return $lastmod;
            
        } catch (Exception $e) {
            $this->log("Sitemap: Error getting term lastmod for term $term->term_id: " . $e->getMessage());
            return gmdate('c', time());
        }
    }

    /**
     * Get homepage last modified date
     */
    private function getHomepageLastModified(): string
    {
        $frontPageId = get_option('page_on_front');
        
        if ($frontPageId) {
            $frontPage = get_post($frontPageId);
            if ($frontPage) {
                return get_post_modified_time('c', true, $frontPage);
            }
        }
        
        // Fallback to latest post
        $latestPost = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => 1,
            'orderby' => 'modified',
            'order' => 'DESC'
        ]);
        
        if (!empty($latestPost)) {
            return get_post_modified_time('c', true, $latestPost[0]);
        }
        
        return gmdate('c', time());
    }

    /**
     * Clear the cached sitemap entries so the next generation rebuilds them
     * from the current posts, terms and per-page settings.
     */
    public function clearCache(): void
    {
        delete_transient(self::TRANSIENT_POST_ENTRIES);
        delete_transient(self::TRANSIENT_TAXONOMY_ENTRIES);
    }
}
