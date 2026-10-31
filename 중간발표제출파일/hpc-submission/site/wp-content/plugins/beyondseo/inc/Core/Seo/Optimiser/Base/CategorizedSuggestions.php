<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base;

if (!defined('ABSPATH')) { exit; }

use RankingCoach\Inc\Core\Seo\Optimiser\Base\FactorSuggestions;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\FactorSuggestion;

/**
 * Class CategorizedSuggestions
 * 
 * A collection class that manages categorized SEO optimization suggestions.
 * Provides organized access to suggestions grouped by type (keywords, content, technical, etc.).
 */
class CategorizedSuggestions
{
    /** @var FactorSuggestions */
    public FactorSuggestions $primaryKeyword;

    /** @var FactorSuggestions */
    public FactorSuggestions $additionalKeywords;

    /** @var FactorSuggestions */
    public FactorSuggestions $titleMeta;

    /** @var FactorSuggestions */
    public FactorSuggestions $content;

    /** @var FactorSuggestions */
    public FactorSuggestions $technical;

    /** @var FactorSuggestions */
    public FactorSuggestions $structure;

    /** @var FactorSuggestions */
    public FactorSuggestions $backlinks;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->primaryKeyword = new FactorSuggestions();
        $this->additionalKeywords = new FactorSuggestions();
        $this->titleMeta = new FactorSuggestions();
        $this->content = new FactorSuggestions();
        $this->technical = new FactorSuggestions();
        $this->structure = new FactorSuggestions();
        $this->backlinks = new FactorSuggestions();
    }

    /**
     * Get all categories as an associative array
     *
     * @return array<string, FactorSuggestions>
     */
    public function toArray(): array
    {
        return [
            'primary_keyword' => $this->primaryKeyword,
            'additional_keywords' => $this->additionalKeywords,
            'title_meta' => $this->titleMeta,
            'content' => $this->content,
            'technical' => $this->technical,
            'structure' => $this->structure,
            'backlinks' => $this->backlinks,
        ];
    }

    /**
     * Get a specific category by name
     *
     * @param string $categoryName
     * @return FactorSuggestions|null
     */
    public function getCategory(string $categoryName): ?FactorSuggestions
    {
        return match ($categoryName) {
            'primary_keyword' => $this->primaryKeyword,
            'additional_keywords' => $this->additionalKeywords,
            'title_meta' => $this->titleMeta,
            'content' => $this->content,
            'technical' => $this->technical,
            'structure' => $this->structure,
            'backlinks' => $this->backlinks,
            default => null,
        };
    }

    /**
     * Add a suggestion to the appropriate category
     *
     * @param FactorSuggestion $suggestion
     * @return void
     */
    public function addSuggestion(FactorSuggestion $suggestion): void
    {
        $category = $this->getCategoryForSuggestion($suggestion->issueType);
        $categoryObject = $this->getCategory($category);
        $categoryObject?->add($suggestion);
    }

    /**
     * Sort all categories by priority
     *
     * @return self
     */
    public function sortByPriority(): self
    {
        $this->primaryKeyword->orderBy('priority');
        $this->additionalKeywords->orderBy('priority');
        $this->titleMeta->orderBy('priority');
        $this->content->orderBy('priority');
        $this->technical->orderBy('priority');
        $this->structure->orderBy('priority');
        $this->backlinks->orderBy('priority');

        return $this;
    }

    /**
     * Get the total count of suggestions across all categories
     *
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->primaryKeyword->count() +
               $this->additionalKeywords->count() +
               $this->titleMeta->count() +
               $this->content->count() +
               $this->technical->count() +
               $this->structure->count() +
               $this->backlinks->count();
    }

    /**
     * Get the category for a specific suggestion type
     *
     * @param string $issueType The issue type from the suggestion
     * @return string The category name
     */
    private function getCategoryForSuggestion(string $issueType): string
    {
        // Primary Keyword category (18 types) - Specific pentru primary keyword
        $primaryKeywordTypes = [
            'missing_primary_keyword', 'optimise_primary_keyword_usage', 'optimise_primary_keyword',
            'primary_keyword_underused', 'suboptimal_primary_keyword_density', 'primary_keyword_missing_in_headings',
            'keyword_missing_in_meta_title', 'keyword_missing_in_first_paragraph', 'keyword_not_at_title_start',
            'missing_primary_keyword_in_url', 'primary_keyword_too_deep_in_url', 'keyword_not_in_url_slug',
            'high_keyword_difficulty', 'poor_volume_competition_balance', 'low_commercial_intent',
            'keyword_cannibalization', 'poor_keyword_placement', 'suboptimal_keyword_density'
        ];

        // Additional Keywords category (28 types) - Secondary, related, semantic keywords
        $additionalKeywordTypes = [
            'insufficient_secondary_keywords', 'missing_secondary_keywords', 'secondary_keyword_missing_in_meta_title',
            'insufficient_secondary_keyword_usage', 'optimise_secondary_keyword', 'optimise_secondary_keywords_usage',
            'missing_related_keywords', 'important_related_terms_missing', 'insufficient_semantic_context',
            'unbalanced_related_keyword_usage', 'poor_related_keywords_distribution', 'missing_long_tail_keywords',
            'narrow_keyword_portfolio', 'missing_keyword_coverage', 'keyword_map', 'missing_keyword_map',
            'improve_keyword_distribution', 'poor_keyword_distribution', 'unbalanced_keyword_distribution',
            'competitive_advantage', 'description_keyword_overuse', 'keyword_stuffing_detected', 'keywords_missing_in_headings'
        ];

        // Title & Meta category (11 types)
        $titleMetaTypes = [
            'meta_title_too_short', 'meta_title_too_long', 'meta_title_missing', 'meta_title_clickbait',
            'meta_title_missing_brand_separator', 'keyword_not_at_title_start', 'meta_description_too_short', 'meta_description_too_long',
            'meta_description_missing', 'meta_description_weak_call_to_action', 'meta_description_intent_not_satisfied'
        ];

        // Content category (14 types)
        $contentTypes = [
            'content_too_short', 'content_too_long', 'lacks_content_depth', 'insufficient_headings',
            'paragraphs_too_long', 'missing_multimedia_in_intro_section', 'intent_not_satisfied',
            'lacks_emotional_appeal', 'lacks_curiosity_elements', 'lacks_reader_connection',
            'content_expansion', 'outdated_content', 'industry_changes',
            'pruning_candidate', 'weak_topical_authority'
        ];

        // Technical category (37 types)
        $technicalTypes = [
            'missing_canonical_tag', 'invalid_canonical_tag', 'multiple_canonical_tags_found',
            'robots_txt_missing', 'all_pages_blocked', 'critical_pages_blocked',
            'missing_schema_markup', 'improper_schema_type_used', 'page_not_indexed',
            'safe_browsing_issue', 'meta_robots_issues', 'x_robots_tag_issues',
            'technical_seo_issues', 'incorrect_canonical_tag', 'incorrect_canonical_target',
            'duplicate_content_identical', 'duplicate_content_parameters', 'duplicate_content_paths', 
            'duplicate_content_uncanonicalized', 'review_duplicate_content', 'invalid_localbusiness_schema', 
            'admin_sections_not_blocked', 'optimize_robots_txt_structure', 'missing_sitemap_in_robots_txt', 
            'page_speed_overall_slow', 'page_speed_slow_loading', 'page_speed_slow_fcp', 'page_speed_slow_lcp',
            'page_speed_high_blocking_time', 'page_speed_high_cls', 'page_speed_large_images',
            'page_speed_unoptimized_images', 'page_speed_render_blocking', 'page_speed_unminified_resources',
            'page_speed_browser_caching'
        ];

        // Structure category (19 types)
        $structureTypes = [
            'multiple_h1_tags_found', 'missing_h1_tag', 'improper_heading_nesting',
            'empty_or_short_headings', 'first_heading_not_h1', 'primary_keyword_missing_in_headings',
            'use_hyphens_in_url', 'missing_primary_keyword_in_url', 'primary_keyword_too_deep_in_url',
            'unreadable_url_structure', 'keyword_not_in_url_slug', 'non_seo_friendly_url_structure',
            'url_too_long', 'unoptimized_images', 'legacy_image_formats', 'missing_webp_support',
            'missing_responsive_images', 'incomplete_responsive_images', 'keyword_not_used_naturally_in_alt'
        ];

        // Backlinks category (16 types)
        $backlinkTypes = [
            'insufficient_referring_domains', 'increase_referring_domains', 'low_quality_backlinks',
            'unnatural_backlink_profile', 'low_relevance_backlinks', 'missing_high_authority_backlinks',
            'poor_backlink_diversity', 'declining_backlink_profile', 'poor_anchor_text_distribution',
            'over_optimized_anchor_text', 'generic_anchor_text', 'irrelevant_anchor_text',
            'missing_branded_anchor_text', 'missing_keyword_rich_anchor_text', 'nofollow_backlinks_overuse',
            'missing_contextual_backlinks'
        ];

        if (in_array($issueType, $primaryKeywordTypes)) {
            return 'primary_keyword';
        } elseif (in_array($issueType, $additionalKeywordTypes)) {
            return 'additional_keywords';
        } elseif (in_array($issueType, $titleMetaTypes)) {
            return 'title_meta';
        } elseif (in_array($issueType, $contentTypes)) {
            return 'content';
        } elseif (in_array($issueType, $technicalTypes)) {
            return 'technical';
        } elseif (in_array($issueType, $structureTypes)) {
            return 'structure';
        } elseif (in_array($issueType, $backlinkTypes)) {
            return 'backlinks';
        }

        // Default fallback for unknown types
        return 'technical';
    }
}
