<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Breadcrumbs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use WP_Post;
use WP_Term;

/**
 * Class BreadcrumbManager
 */
class BreadcrumbsManager
{

    /**
     * @var object
     */
    private object $settings;

    public function __construct()
    {
        // Stop if the breadcrumbs are disabled.
        if (!SettingsManager::instance()->enable_breadcrumbs) {
            return;
        }

        $this->settings = (object)SettingsManager::instance()->breadcrumb_settings ?? [];
    }

    /**
     * Returns the breadcrumb trail for the homepage.
     *
     * @return array The breadcrumb trail.
     */
    public function home(): array
    {
        // Since we just need the root breadcrumb (homepage), we can call this immediately without passing any breadcrumbs.
        return $this->setPositions();
    }

    /**
     * Returns the breadcrumb trail for the requested post.
     *
     * @param WP_Post $post The post object.
     *
     * @return array          The breadcrumb trail.
     */
    public function post(WP_Post $post): array
    {
        // Check if page is the static homepage.
        if (WordpressHelpers::is_static_page($post)) {
            return $this->home();
        }

        if (is_post_type_hierarchical($post->post_type)) {
            return $this->setPositions($this->postHierarchical($post));
        }
        return $this->setPositions($this->postNonHierarchical($post));
    }

    /**
     * Returns the breadcrumb trail for a hierarchical post.
     *
     * @param WP_Post $post The post object.
     * @return array The breadcrumb trail.
     */
    private function postHierarchical(WP_Post $post): array
    {
        $breadcrumbs = [];
        do {
            array_unshift(
                $breadcrumbs,
                [
                    'name' => $post->post_title,
                    'description' => WordpressHelpers::retrieve_description($post),
                    'url' => get_permalink($post),
                    'type' => is_home() ? 'CollectionPage' : $this->getPostWebPageGraph()
                ]
            );

            if ($post->post_parent) {
                $post = get_post($post->post_parent);
            } else {
                $post = false;
            }
        } while ($post);

        return $breadcrumbs;
    }

    /**
     * Returns the breadcrumb trail for a non-hierarchical post.
     *
     * In this case we need to compare the permalink structure with the permalink of the requested post and loop through all objects we're able to find.
     *
     * @param WP_Post $post The post object.
     * @return array The breadcrumb trail.
     */
    private function postNonHierarchical(WP_Post $post): array
    {
        global $wp_query;
        $homeUrl = CoreHelper::escape_pattern_regex(home_url());
        $permalink = get_permalink($post);
        $slug = preg_replace("/$homeUrl/", '', (string)$permalink);
        $permalinkStructure = get_option('permalink_structure');
        $tags = array_values(array_filter(explode('/', $permalinkStructure)));
        $objects = array_values(array_filter(explode('/', $slug)));
        $postGraph = $this->getPostWebPageGraph();

        // Enhanced validation: check if we have a valid permalink structure
        if (empty($permalinkStructure) || empty($tags)) {
            return [[
                'name' => $post->post_title,
                'description' => WordpressHelpers::retrieve_description($post),
                'url' => $permalink,
                'type' => $postGraph
            ]];
        }

        // More flexible count validation - allow for slight differences
        if (abs(count($tags) - count($objects)) > 1) {
            return [[
                'name' => $post->post_title,
                'description' => WordpressHelpers::retrieve_description($post),
                'url' => $permalink,
                'type' => $postGraph
            ]];
        }

        // Create proper mapping between tags and objects
        $pairs = $this->createPermalinkMapping($tags, $objects, $permalink);
        
        $breadcrumbs = [];
        $dateName = null;
        $timestamp = strtotime($post->post_date);
        
        foreach ($pairs as $tag => $object) {
            if (empty($object)) {
                continue;
            }

            $breadcrumb = $this->createBreadcrumbForTag($tag, $object, $post, $timestamp, $dateName);
            
            if ($breadcrumb) {
                // For categories, add hierarchical parents
                if ($tag === '%category%' && isset($breadcrumb['term'])) {
                    $categoryBreadcrumbs = $this->buildCategoryHierarchy($breadcrumb['term']);
                    $breadcrumbs = array_merge($breadcrumbs, $categoryBreadcrumbs);
                } else {
                    $breadcrumbs[] = $breadcrumb;
                }
            }
        }

        return $breadcrumbs;
    }

    /**
     * Create proper mapping between permalink tags and URL objects
     */
    private function createPermalinkMapping(array $tags, array $objects, string $permalink): array
    {
        $pairs = [];
        $objectIndex = 0;

        foreach ($tags as $tag) {
            if ($objectIndex >= count($objects)) {
                break;
            }
            
            $object = $objects[$objectIndex];
            
            // Validate that the object appears in the permalink at the expected position
            $escObject = CoreHelper::escape_pattern_regex($object);
            if (preg_match("/.*{$escObject}[\/]?/", $permalink)) {
                $pairs[$tag] = $object;
            }
            
            $objectIndex++;
        }
        
        return $pairs;
    }

    /**
     * Create breadcrumb for specific permalink tag
     */
    private function createBreadcrumbForTag(string $tag, string $object, WP_Post $post, int $timestamp, ?string &$dateName): ?array
    {
        global $wp_query;
        
        switch ($tag) {
            case '%category%':
                return $this->createCategoryBreadcrumb($object, $post, $wp_query);
                
            case '%author%':
                return $this->createAuthorBreadcrumb($object, $post);
                
            case '%postid%':
            case '%postname%':
                return $this->createPostBreadcrumb($post);
                
            case '%year%':
                $dateName = gmdate('Y', $timestamp);
                return $this->createDateBreadcrumb($dateName, $object);
                
            case '%monthnum%':
                if (!$dateName) {
                    $dateName = gmdate('F', $timestamp);
                }
                return $this->createDateBreadcrumb($dateName, $object);
                
            case '%day%':
                if (!$dateName) {
                    $dateName = gmdate('j', $timestamp);
                }
                $breadcrumb = $this->createDateBreadcrumb($dateName, $object);
                $dateName = null; // Reset for next iteration
                return $breadcrumb;
                
            default:
                return null;
        }
    }

    /**
     * Create category breadcrumb with proper term retrieval
     */
    private function createCategoryBreadcrumb(string $categorySlug, WP_Post $post, $wp_query): ?array
    {
        // First try to get the primary category for this post
        $categories = get_the_category($post->ID);
        $term = null;
        
        // Look for category matching the slug
        foreach ($categories as $category) {
            if ($category->slug === $categorySlug) {
                $term = $category;
                break;
            }
        }
        
        // Fallback to slug lookup if not found in post categories
        if (!$term) {
            $term = get_category_by_slug($categorySlug);
        }
        
        if (!$term || is_wp_error($term)) {
            return null;
        }
        
        // Temporarily modify query for description retrieval
        $oldQueriedObject = $wp_query->queried_object ?? null;
        $oldIsCategory = $wp_query->is_category ?? false;
        
        $wp_query->queried_object = $term;
        $wp_query->is_category = true;
        
        $breadcrumb = [
            'name' => $term->name,
            'description' => WordpressHelpers::retrieve_description(),
            'url' => get_term_link($term),
            'type' => 'CollectionPage',
            'term' => $term // Store for hierarchy building
        ];
        
        // Restore query state
        $wp_query->queried_object = $oldQueriedObject;
        $wp_query->is_category = $oldIsCategory;
        
        return $breadcrumb;
    }

    /**
     * Build category hierarchy breadcrumbs
     */
    private function buildCategoryHierarchy(WP_Term $term): array
    {
        $breadcrumbs = [];
        $current = $term;
        
        // Build hierarchy from current term to root
        while ($current && !is_wp_error($current)) {
            $breadcrumbs[] = [
                'name' => $current->name,
                'description' => $current->description ?: sprintf(
                    /* translators: %s is the category name */
                    esc_html__('Posts in %s category', 'beyondseo'),
                    $current->name
                ),
                'url' => get_term_link($current),
                'type' => 'CollectionPage'
            ];
            
            if ($current->parent) {
                $current = get_term($current->parent, $current->taxonomy);
            } else {
                break;
            }
        }
        
        return array_reverse($breadcrumbs);
    }

    /**
     * Create author breadcrumb
     */
    private function createAuthorBreadcrumb(string $authorSlug, WP_Post $post): array
    {
        return [
            'name' => get_the_author_meta('display_name', $post->post_author),
            'description' => CoreHelper::prepare_string(
                get_the_author_meta('description', $post->post_author) ?:
                /* translators: %s is the author's display name */
                sprintf(esc_html__('Posts by %s', 'beyondseo'), get_the_author_meta('display_name', $post->post_author))
            ),
            'url' => get_author_posts_url($post->post_author),
            'type' => 'ProfilePage'
        ];
    }

    /**
     * Create post breadcrumb
     */
    private function createPostBreadcrumb(WP_Post $post): array
    {
        return [
            'name' => $post->post_title,
            'description' => WordpressHelpers::retrieve_description($post),
            'url' => get_permalink($post),
            'type' => $this->getPostWebPageGraph()
        ];
    }

    /**
     * Create date archive breadcrumb
     */
    private function createDateBreadcrumb(string $dateName, string $object): array
    {
        return [
            'name' => $dateName,
            'description' => CoreHelper::prepare_string(sprintf(
                /* translators: %s is the date name (e.g., January 2023) */
                esc_html__('Archive for %s', 'beyondseo'),
                esc_html($dateName)
            )),
            'url' => home_url('/' . $object . '/'),
            'type' => 'CollectionPage'
        ];
    }

    /**
     * Returns the breadcrumb trail for the requested term.
     *
     * @param WP_Term $term The term object.
     * @return array The breadcrumb trail.
     */
    public function term(WP_Term $term): array
    {
        $breadcrumbs = [];
        do {
            array_unshift(
                $breadcrumbs,
                [
                    'name' => $term->name,
                    'description' => WordpressHelpers::retrieve_description(),
                    'url' => get_term_link($term, $term->taxonomy),
                    'type' => 'CollectionPage'
                ]
            );

            if ($term->parent) {
                $term = get_term($term->parent, $term->taxonomy);
            } else {
                $term = false;
            }
        } while ($term);

        return $this->setPositions($breadcrumbs);
    }

    /**
     * Returns the breadcrumb trail for the requested date archive.
     * @return array The breadcrumb trail.
     */
    public function date(): array
    {
        global $wp_query;

        $oldYear = $wp_query->is_year;
        $oldMonth = $wp_query->is_month;
        $oldDay = $wp_query->is_day;
        $wp_query->is_year = true;
        $wp_query->is_month = false;
        $wp_query->is_day = false;

        $breadcrumbs = [
            [
                'name' => get_the_date('Y'),
                'description' => WordpressHelpers::retrieve_description(),
                'url' => trailingslashit(get_year_link($wp_query->query_vars['year'])),
                'type' => 'CollectionPage'
            ]
        ];

        $wp_query->is_year = $oldYear;

        // Fall through if data archive is more specific than the year.
        if (is_year()) {
            return $this->setPositions($breadcrumbs);
        }

        $wp_query->is_month = true;

        $breadcrumbs[] = [
            'name' => get_the_date('F, Y'),
            'description' => WordpressHelpers::retrieve_description(),
            'url' => trailingslashit(get_month_link(
                $wp_query->query_vars['year'],
                $wp_query->query_vars['monthnum']
            )),
            'type' => 'CollectionPage'
        ];

        $wp_query->is_month = $oldMonth;

        // Fall through if data archive is more specific than the year & month.
        if (is_month()) {
            return $this->setPositions($breadcrumbs);
        }

        $wp_query->is_day = $oldDay;

        $breadcrumbs[] = [
            'name' => get_the_date(),
            'description' => WordpressHelpers::retrieve_description(),
            'url' => trailingslashit(get_day_link(
                $wp_query->query_vars['year'],
                $wp_query->query_vars['monthnum'],
                $wp_query->query_vars['day']
            )),
            'type' => 'CollectionPage'
        ];

        return $this->setPositions($breadcrumbs);
    }

    /**
     * Returns the breadcrumb trail for search results.
     * 
     * @param string $searchQuery The search query, if not provided will use get_search_query()
     * @return array The breadcrumb trail.
     */
    public function search(string $searchQuery = ''): array
    {
        if (empty($searchQuery)) {
            $searchQuery = get_search_query();
        }
        
        $searchPrefix = $this->settings->suffixes->search ?? __('Search results for', 'beyondseo');
        
        $breadcrumbs = [
            [
                'name' => $searchPrefix . ' "' . esc_html($searchQuery) . '"',
                'description' => $searchPrefix . ' "' . esc_html($searchQuery) . '"',
                'url' => get_search_link($searchQuery),
                'type' => 'SearchResultsPage'
            ]
        ];

        return $this->setPositions($breadcrumbs);
    }

    /**
     * Returns the breadcrumb trail for 404 error pages.
     * 
     * @return array The breadcrumb trail.
     */
    public function error404(): array
    {
        $error404Text = $this->settings->suffixes->{404} ?? __('Error 404: Page not found', 'beyondseo');
        
        $breadcrumbs = [
            [
                'name' => $error404Text,
                'description' => $error404Text,
                'url' => '',
                'type' => 'WebPage'
            ]
        ];

        return $this->setPositions($breadcrumbs);
    }

    /**
     * Returns the breadcrumb trail for archive pages with custom context.
     * 
     * @param string $archiveTitle The archive title
     * @param string $archiveUrl The archive URL
     * @param string $archiveType The archive type (for schema)
     * @return array The breadcrumb trail.
     */
    public function archive(string $archiveTitle = '', string $archiveUrl = '', string $archiveType = 'CollectionPage'): array
    {
        if (empty($archiveTitle)) {
            $archiveTitle = __('Archives', 'beyondseo');
        }
        
        $archivePrefix = $this->settings->suffixes->archive ?? __('Archives for', 'beyondseo');
        
        $breadcrumbs = [
            [
                'name' => $archiveTitle,
                'description' => $archivePrefix . ' ' . $archiveTitle,
                'url' => $archiveUrl,
                'type' => $archiveType
            ]
        ];

        return $this->setPositions($breadcrumbs);
    }

    /**
     * Sets the position for each breadcrumb after adding the root breadcrumb first.
     *
     * If no breadcrumbs are passed, then we assume we're on the homepage and just need the root breadcrumb.
     *
     * @param array $breadcrumbs The breadcrumb trail.
     * @return array The modified breadcrumb trail.
     */
    public function setPositions(array $breadcrumbs = []): array
    {
        // If the array isn't two-dimensional, then we need to wrap it in another array before continuing.
        if (
            count($breadcrumbs) &&
            count($breadcrumbs) === count($breadcrumbs, COUNT_RECURSIVE)
        ) {
            $breadcrumbs = [$breadcrumbs];
        }

        // The homepage needs to be root item of all trails.
        $homepage = [
            // Translators: This refers to the homepage of the site.
            'name' => __('Home', 'beyondseo'),
            'description' => WordpressHelpers::retrieve_home_page_description(),
            'url' => trailingslashit(home_url()),
            'type' => 'posts' === get_option('show_on_front') ? 'CollectionPage' : 'WebPage'
        ];
        array_unshift($breadcrumbs, $homepage);

        $breadcrumbs = array_filter($breadcrumbs);
        foreach ($breadcrumbs as $index => &$breadcrumb) {
            $breadcrumb['position'] = $index + 1;
        }

        // Ensure that the breadcrumbs support filtering.
        if ($this->settings->allow_filters) {
            $breadcrumbs = apply_filters('rankingcoach_breadcrumbs', $breadcrumbs);
        }

        return $breadcrumbs;
    }

    /**
     * Returns the most relevant WebPage graph for the post.
     *
     * @return string The graph name.
     */
    private function getPostWebPageGraph(): string
    {

        // Return the default if no WebPage graph was found.
        return 'WebPage';
    }

    /**
     * Renders the breadcrumb trail as HTML.
     *
     * @param array $items The breadcrumb items, each item should have 'name' and optionally 'url'.
     * @param string $separator The separator between breadcrumb items.
     * @return string The rendered HTML for the breadcrumb trail.
     */
    public static function renderBreadcrumbTrail(array $items, string $separator = ' › '): string
    {
        if (empty($items)) {
            return '';
        }

        $html = '<nav class="breadcrumb-trail">';
        foreach ($items as $index => $item) {
            $isLast = $index === array_key_last($items);
            $name = esc_html($item['name']);
            $url = esc_url($item['url'] ?? '');

            if (!empty($url) && !$isLast) {
                $html .= "<a href=\"{$url}\">{$name}</a>";
            } else {
                $html .= "<strong>{$name}</strong>";
            }

            if (!$isLast) {
                $html .= "<span class=\"separator\">{$separator}</span>";
            }
        }
        $html .= '</nav>';

        return $html;
    }
}
