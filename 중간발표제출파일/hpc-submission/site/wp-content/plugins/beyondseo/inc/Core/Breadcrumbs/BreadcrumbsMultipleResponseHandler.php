<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Breadcrumbs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Breadcrumbs\Dtos\BreadcrumbsResponseDto;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use WP_Term;

/**
 * Handles multiple breadcrumb type requests and generates appropriate responses
 */
class BreadcrumbsMultipleResponseHandler
{
    use RcLoggerTrait;

    private BreadcrumbsManager $breadcrumbsManager;
    private SettingsManager $settingsManager;

    public function __construct()
    {
        $this->breadcrumbsManager = new BreadcrumbsManager();
        $this->settingsManager = SettingsManager::instance();
    }

    /**
     * Process multiple breadcrumb types and return structured response
     *
     * @param array $types Array of requested breadcrumb types
     * @param array $context Context information for generation
     * @return BreadcrumbsResponseDto
     */
    public function processMultipleTypes(array $types, array $context = []): BreadcrumbsResponseDto
    {
        $response = new BreadcrumbsResponseDto();
        $settings = $this->settingsManager->breadcrumb_settings ?? [];
        $settings = json_decode(json_encode($settings), true);
        
        foreach ($types as $type) {
            try {
                $breadcrumbs = $this->generateBreadcrumbsForType($type, $context, $settings);
                $response->addBreadcrumbsForType($type, $breadcrumbs);
            } catch (\Exception $e) {
                // Log error but continue processing other types
                $this->log("Error generating breadcrumbs for type '$type': " . $e->getMessage(), 'ERROR');
                $response->addBreadcrumbsForType($type, []);
            }
        }

        $response->addMeta('settings_applied', $this->getAppliedSettings($settings))
                ->addMeta('context_used', $context)
                ->addMeta('requested_types', $types);

        return $response;
    }

    /**
     * Generate breadcrumbs for a specific type
     *
     * @param string $type The breadcrumb type
     * @param array $context Context information
     * @param array $settings Breadcrumb settings
     * @return array
     */
    private function generateBreadcrumbsForType(string $type, array $context, array $settings): array
    {
        // Check if this type is enabled in settings
        if (!$this->isTypeEnabled($type, $settings)) {
            return [];
        }

        switch ($type) {
            case 'home':
                return $this->breadcrumbsManager->home();

            case 'post':
                return $this->generatePostBreadcrumbs($context);

            case 'page':
                return $this->generatePageBreadcrumbs($context);

            case 'archive':
                return $this->generateArchiveBreadcrumbs($context, $settings);

            case 'search':
                return $this->generateSearchBreadcrumbs($context, $settings);

            case '404':
                return $this->generate404Breadcrumbs($settings);

            case 'category':
                return $this->generateCategoryBreadcrumbs($context);

            case 'tag':
                return $this->generateTagBreadcrumbs($context);

            case 'term':
                return $this->generateTermBreadcrumbs($context);

            case 'date':
                return $this->generateDateBreadcrumbs($context);

            default:
                return [];
        }
    }

    /**
     * Generate breadcrumbs for post type
     */
    private function generatePostBreadcrumbs(array $context): array
    {
        if (isset($context['post_id'])) {
            $post = get_post($context['post_id']);
            if ($post && $post->post_type === 'post') {
                return $this->breadcrumbsManager->post($post);
            }
        }

        // Fallback: generate for current post if in the loop
        global $post;
        if ($post && $post->post_type === 'post') {
            return $this->breadcrumbsManager->post($post);
        }

        return $this->generateGenericPostBreadcrumbs();
    }

    /**
     * Generate breadcrumbs for page type
     */
    private function generatePageBreadcrumbs(array $context): array
    {
        if (isset($context['post_id'])) {
            $post = get_post($context['post_id']);
            if ($post && $post->post_type === 'page') {
                return $this->breadcrumbsManager->post($post);
            }
        }

        // Fallback: generate for current page if available
        global $post;
        if ($post && $post->post_type === 'page') {
            return $this->breadcrumbsManager->post($post);
        }

        return $this->generateGenericPageBreadcrumbs();
    }

    /**
     * Generate breadcrumbs for archive pages
     */
    private function generateArchiveBreadcrumbs(array $context, array $settings): array
    {
        $archiveTitle = $context['archive_title'] ?? __('Archives', 'beyondseo');
        $archiveUrl = $context['archive_url'] ?? '';
        $archiveType = $context['archive_type'] ?? 'CollectionPage';
        
        return $this->breadcrumbsManager->archive($archiveTitle, $archiveUrl, $archiveType);
    }

    /**
     * Generate breadcrumbs for search results
     */
    private function generateSearchBreadcrumbs(array $context, array $settings): array
    {
        $searchQuery = $context['search_query'] ?? '';
        
        return $this->breadcrumbsManager->search($searchQuery);
    }

    /**
     * Generate breadcrumbs for 404 pages
     */
    private function generate404Breadcrumbs(array $settings): array
    {
        return $this->breadcrumbsManager->error404();
    }

    /**
     * Generate breadcrumbs for category pages
     */
    private function generateCategoryBreadcrumbs(array $context): array
    {
        if (isset($context['term_id'])) {
            $term = get_term($context['term_id'], 'category');
            if ($term && !is_wp_error($term)) {
                return $this->breadcrumbsManager->term($term);
            }
        }

        // Fallback for current category
        $term = get_queried_object();
        if ($term instanceof WP_Term && $term->taxonomy === 'category') {
            return $this->breadcrumbsManager->term($term);
        }

        return $this->breadcrumbsManager->setPositions();
    }

    /**
     * Generate breadcrumbs for tag pages
     */
    private function generateTagBreadcrumbs(array $context): array
    {
        if (isset($context['term_id'])) {
            $term = get_term($context['term_id'], 'post_tag');
            if ($term && !is_wp_error($term)) {
                return $this->breadcrumbsManager->term($term);
            }
        }

        // Fallback for current tag
        $term = get_queried_object();
        if ($term instanceof WP_Term && $term->taxonomy === 'post_tag') {
            return $this->breadcrumbsManager->term($term);
        }

        return $this->breadcrumbsManager->setPositions();
    }

    /**
     * Generate breadcrumbs for term pages
     */
    private function generateTermBreadcrumbs(array $context): array
    {
        if (isset($context['term_id']) && isset($context['taxonomy'])) {
            $term = get_term($context['term_id'], $context['taxonomy']);
            if ($term && !is_wp_error($term)) {
                return $this->breadcrumbsManager->term($term);
            }
        }

        // Fallback for current term
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            return $this->breadcrumbsManager->term($term);
        }

        return $this->breadcrumbsManager->setPositions();
    }

    /**
     * Generate breadcrumbs for date archives
     */
    private function generateDateBreadcrumbs(array $context): array
    {
        return $this->breadcrumbsManager->date();
    }

    /**
     * Generate generic post breadcrumbs when no specific post context is available
     */
    private function generateGenericPostBreadcrumbs(): array
    {
        $breadcrumbs = $this->breadcrumbsManager->setPositions();
        
        $breadcrumbs[] = [
            'name' => __('Posts', 'beyondseo'),
            'description' => __('Blog posts', 'beyondseo'),
            'url' => get_post_type_archive_link('post') ?: home_url('/blog/'),
            'type' => 'CollectionPage',
            'position' => count($breadcrumbs) + 1
        ];

        return $breadcrumbs;
    }

    /**
     * Generate generic page breadcrumbs when no specific page context is available
     */
    private function generateGenericPageBreadcrumbs(): array
    {
        $breadcrumbs = $this->breadcrumbsManager->setPositions();
        
        $breadcrumbs[] = [
            'name' => __('Pages', 'beyondseo'),
            'description' => __('Site pages', 'beyondseo'),
            'url' => home_url(),
            'type' => 'CollectionPage',
            'position' => count($breadcrumbs) + 1
        ];

        return $breadcrumbs;
    }

    /**
     * Check if a breadcrumb type is enabled in settings
     */
    private function isTypeEnabled(string $type, array $settings): bool
    {
        $settingKey = "show_on_{$type}";
        
        // Handle special cases
        switch ($type) {
            case 'post':
                return $settings['show_on_posts'] ?? true;
            case 'page':
                return $settings['show_on_pages'] ?? true;
            case 'archive':
                return $settings['show_on_archives'] ?? true;
            case 'search':
                return $settings['show_on_search'] ?? true;
            case '404':
                return $settings['show_on_404'] ?? true;
            case 'category':
                return $settings['show_on_categories'] ?? true;
            case 'tag':
                return $settings['show_on_tags'] ?? true;
            case 'term':
                return $settings['show_on_taxonomies'] ?? true;
            case 'home':
                return true; // Always enabled for home
            case 'date':
                return $settings['show_on_archives'] ?? true; // Date archives fall under archives
            default:
                return $settings[$settingKey] ?? true;
        }
    }

    /**
     * Get applied settings for meta information
     */
    private function getAppliedSettings(array $settings): array
    {
        return [
            'separator' => $settings['separator'] ?? ' » ',
            'max_depth' => $settings['max_depth'] ?? 4,
            'show_current_as_link' => $settings['show_current_as_link'] ?? false,
            'enable_schema_markup' => $settings['enable_schema_markup'] ?? true,
            'allow_filters' => $settings['allow_filters'] ?? true
        ];
    }
}
