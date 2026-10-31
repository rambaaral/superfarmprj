<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\FactorSuggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;

/**
 * Class Suggestion
 *
 * This class defines various issue types that can be encountered during SEO analysis.
 */
final class Suggestion
{
    public const COMPETITIVE_ADVANTAGE = 'competitive_advantage';
    public const CONTENT_EXPANSION = 'content_expansion';
    public const HIGH_KEYWORD_DIFFICULTY = 'high_keyword_difficulty';
    public const IMPORTANT_RELATED_TERMS_MISSING = 'important_related_terms_missing';
    public const IMPROVE_KEYWORD_DISTRIBUTION = 'improve_keyword_distribution';
    public const INDUSTRY_CHANGES = 'industry_changes';
    public const INSUFFICIENT_SECONDARY_KEYWORDS = 'insufficient_secondary_keywords';
    public const INSUFFICIENT_SEMANTIC_CONTEXT = 'insufficient_semantic_context';
    public const KEYWORDS_MISSING_IN_HEADINGS = 'keywords_missing_in_headings';
    public const KEYWORD_CANNIBALIZATION = 'keyword_cannibalization';
    public const KEYWORD_MISSING_IN_FIRST_PARAGRAPH = 'keyword_missing_in_first_paragraph';
    public const KEYWORD_MISSING_IN_POST_TITLE = 'keyword_missing_in_post_title';
    public const KEYWORD_MISSING_IN_META_TITLE = 'keyword_missing_in_meta_title';
    public const SECONDARY_KEYWORD_MISSING_IN_META_TITLE = 'secondary_keyword_missing_in_meta_title';
    public const KEYWORD_OVERUSE = 'keyword_overuse';
    public const KEYWORD_OVERUSE_IN_ALT_TEXT = 'keyword_overuse_in_alt_text';
    public const KEYWORD_STUFFING_IN_FIRST_PARAGRAPH = 'keyword_stuffing_in_first_paragraph';
    public const KEYWORD_REPETITION_IN_META_DESCRIPTION = 'keyword_repetition_in_meta_description';
    public const TOO_MANY_SECONDARY_KEYWORDS = 'too_many_secondary_keywords';
    public const KEYWORD_STUFFING_DETECTED = 'keyword_stuffing_detected';
    public const LOW_COMMERCIAL_INTENT = 'low_commercial_intent';
    public const MISSING_KEYWORD_COVERAGE = 'missing_keyword_coverage';
    public const MISSING_KEYWORD_MAP = 'keyword_map';
    public const MISSING_LONG_TAIL_KEYWORDS = 'missing_long_tail_keywords';
    public const MISSING_PRIMARY_KEYWORD = 'missing_primary_keyword';
    public const MISSING_RELATED_KEYWORDS = 'missing_related_keywords';
    public const MISSING_SECONDARY_KEYWORDS = 'missing_secondary_keywords';
    public const NARROW_KEYWORD_PORTFOLIO = 'narrow_keyword_portfolio';
    public const OPTIMISE_PRIMARY_KEYWORD = 'optimise_primary_keyword';
    public const OPTIMISE_PRIMARY_KEYWORD_USAGE = 'optimise_primary_keyword_usage';
    public const OPTIMISE_SECONDARY_KEYWORD = 'optimise_secondary_keyword';
    public const OPTIMISE_SECONDARY_KEYWORDS_USAGE = 'optimise_secondary_keywords_usage';
    public const OUTDATED_CONTENT = 'outdated_content';
    public const POOR_KEYWORD_DISTRIBUTION = 'poor_keyword_distribution';
    public const POOR_KEYWORD_PLACEMENT = 'poor_keyword_placement';
    public const POOR_RELATED_KEYWORDS_DISTRIBUTION = 'poor_related_keywords_distribution';
    public const POOR_VOLUME_COMPETITION_BALANCE = 'poor_volume_competition_balance';
    public const PRIMARY_KEYWORD_UNDERUSED = 'primary_keyword_underused';
    public const PRUNING_CANDIDATE = 'pruning_candidate';
    public const SUBOPTIMAL_KEYWORD_DENSITY = 'suboptimal_keyword_density';
    public const MISSING_IMAGE_ALT_TEXT_KEYWORD_USAGE = 'missing_image_alt_text_keyword_usage';
    public const SUBOPTIMAL_PRIMARY_KEYWORD_DENSITY = 'suboptimal_primary_keyword_density';
    public const UNBALANCED_KEYWORD_DISTRIBUTION = 'unbalanced_keyword_distribution';
    public const WEAK_TOPICAL_AUTHORITY = 'weak_topical_authority';
    public const LACKS_CONTENT_DEPTH = 'lacks_content_depth';
    public const INSUFFICIENT_HEADINGS = 'insufficient_headings';
    public const PARAGRAPHS_TOO_LONG = 'paragraphs_too_long';
    public const PARAGRAPHS_TOO_SHORT = 'paragraphs_too_short';
    public const CONTENT_TOO_SHORT = 'content_too_short';
    public const CONTENT_TOO_LONG = 'content_too_long';
    public const MISSING_MULTIMEDIA_IN_INTRO_SECTION = 'missing_multimedia_in_intro_section';
    public const INTENT_NOT_SATISFIED = 'intent_not_satisfied';
    public const META_TITLE_TOO_SHORT = 'meta_title_too_short';
    public const META_TITLE_TOO_LONG = 'meta_title_too_long';
    public const META_TITLE_MISSING = 'meta_title_missing';
    public const META_TITLE_CLICKBAIT = 'meta_title_clickbait';
    public const META_TITLE_MISSING_BRAND_SEPARATOR = 'meta_title_missing_brand_separator';
    public const KEYWORD_NOT_AT_TITLE_START = 'keyword_not_at_title_start';
    public const META_DESCRIPTION_TOO_SHORT = 'meta_description_too_short';
    public const META_DESCRIPTION_TOO_LONG = 'meta_description_too_long';
    public const META_DESCRIPTION_MISSING = 'meta_description_missing';
    public const META_DESCRIPTION_WEAK_CALL_TO_ACTION = 'meta_description_weak_call_to_action';
    public const META_DESCRIPTION_INTENT_NOT_SATISFIED = 'meta_description_intent_not_satisfied';
    public const USE_HYPHENS_IN_URL = 'use_hyphens_in_url';
    public const MISSING_PRIMARY_KEYWORD_IN_URL = 'missing_primary_keyword_in_url';
    public const PRIMARY_KEYWORD_TOO_DEEP_IN_URL = 'primary_keyword_too_deep_in_url';
    public const UNREADABLE_URL_STRUCTURE = 'unreadable_url_structure';
    public const KEYWORD_NOT_IN_URL_SLUG = 'keyword_not_in_url_slug';
    public const NON_SEO_FRIENDLY_URL_STRUCTURE = 'non_seo_friendly_url_structure';
    public const URL_TOO_LONG = 'url_too_long';
    public const MISSING_CANONICAL_TAG = 'missing_canonical_tag';
    public const INVALID_CANONICAL_TAG = 'invalid_canonical_tag';
    public const INCORRECT_CANONICAL_TARGET = 'incorrect_canonical_target';
    public const MULTIPLE_CANONICAL_TAGS_FOUND = 'multiple_canonical_tags_found';
    public const TECHNICAL_SEO_ISSUES = 'technical_seo_issues';
    public const INCORRECT_CANONICAL_TAG = 'incorrect_canonical_tag';
    public const DUPLICATE_CONTENT_IDENTICAL = 'duplicate_content_identical';
    public const DUPLICATE_CONTENT_PARAMETERS = 'duplicate_content_parameters';
    public const DUPLICATE_CONTENT_PATHS = 'duplicate_content_paths';
    public const DUPLICATE_CONTENT_UNCANONICALIZED = 'duplicate_content_uncanonicalized';
    public const REVIEW_DUPLICATE_CONTENT = 'review_duplicate_content';
    public const MISSING_SCHEMA_MARKUP = 'missing_schema_markup';
    public const SCHEMA_MARKUP_VALIDATION_FAILED = 'schema_markup_validation_failed';
    public const IMPROPER_SCHEMA_TYPE_USED = 'improper_schema_type_used';
    public const INVALID_LOCALBUSINESS_SCHEMA = 'invalid_localbusiness_schema';
    public const ROBOTS_TXT_MISSING = 'robots_txt_missing';
    public const ALL_PAGES_BLOCKED = 'all_pages_blocked';
    public const CRITICAL_PAGES_BLOCKED = 'critical_pages_blocked';
    public const ADMIN_SECTIONS_NOT_BLOCKED = 'admin_sections_not_blocked';
    public const OPTIMIZE_ROBOTS_TXT_STRUCTURE = 'optimize_robots_txt_structure';
    public const MISSING_SITEMAP_IN_ROBOTS_TXT = 'missing_sitemap_in_robots_txt';
    public const UNOPTIMIZED_IMAGES = 'unoptimized_images';
    public const MISSING_IMAGE_ALT_TEXT = 'missing_image_alt_text';
    public const MISSING_IMAGE_DESCRIPTIVE_ALT_TEXT = 'missing_image_descriptive_alt_text';
    public const LEGACY_IMAGE_FORMATS = 'legacy_image_formats';
    public const MISSING_WEBP_SUPPORT = 'missing_webp_support';
    public const MISSING_RESPONSIVE_IMAGES = 'missing_responsive_images';
    public const INCOMPLETE_RESPONSIVE_IMAGES = 'incomplete_responsive_images';
    public const MULTIPLE_H1_TAGS_FOUND = 'multiple_h1_tags_found';
    public const MISSING_H1_TAG = 'missing_h1_tag';
    public const IMPROPER_HEADING_NESTING = 'improper_heading_nesting';
    public const EMPTY_OR_SHORT_HEADINGS = 'empty_or_short_headings';
    public const PRIMARY_KEYWORD_MISSING_IN_HEADINGS = 'primary_keyword_missing_in_headings';
    public const FIRST_HEADING_NOT_H1 = 'first_heading_not_h1';
    public const KEYWORD_NOT_USED_NATURALLY_IN_ALT = 'keyword_not_used_naturally_in_alt';
    public const LACKS_EMOTIONAL_APPEAL = 'lacks_emotional_appeal';
    public const LACKS_CURIOSITY_ELEMENTS = 'lacks_curiosity_elements';
    public const LACKS_READER_CONNECTION = 'lacks_reader_connection';
    public const PAGE_NOT_INDEXED = 'page_not_indexed';
    public const SAFE_BROWSING_ISSUE = 'safe_browsing_issue';
    public const PAGE_SPEED_OVERALL_SLOW = 'page_speed_overall_slow';
    public const PAGE_SPEED_SLOW_LOADING = 'page_speed_slow_loading';
    public const PAGE_SPEED_SLOW_FCP = 'page_speed_slow_fcp';
    public const PAGE_SPEED_SLOW_LCP = 'page_speed_slow_lcp';
    public const PAGE_SPEED_HIGH_BLOCKING_TIME = 'page_speed_high_blocking_time';
    public const PAGE_SPEED_HIGH_CLS = 'page_speed_high_cls';
    public const PAGE_SPEED_LARGE_IMAGES = 'page_speed_large_images';
    public const PAGE_SPEED_UNOPTIMIZED_IMAGES = 'page_speed_unoptimized_images';
    public const PAGE_SPEED_RENDER_BLOCKING = 'page_speed_render_blocking';
    public const PAGE_SPEED_UNMINIFIED_RESOURCES = 'page_speed_unminified_resources';
    public const PAGE_SPEED_BROWSER_CACHING = 'page_speed_browser_caching';
    public const INSUFFICIENT_REFERRING_DOMAINS = 'insufficient_referring_domains';
    public const INCREASE_REFERRING_DOMAINS = 'increase_referring_domains';
    public const LOW_QUALITY_BACKLINKS = 'low_quality_backlinks';
    public const UNNATURAL_BACKLINK_PROFILE = 'unnatural_backlink_profile';
    public const LOW_RELEVANCE_BACKLINKS = 'low_relevance_backlinks';
    public const MISSING_HIGH_AUTHORITY_BACKLINKS = 'missing_high_authority_backlinks';
    public const POOR_BACKLINK_DIVERSITY = 'poor_backlink_diversity';
    public const DECLINING_BACKLINK_PROFILE = 'declining_backlink_profile';
    public const POOR_ANCHOR_TEXT_DISTRIBUTION = 'poor_anchor_text_distribution';
    public const OVER_OPTIMIZED_ANCHOR_TEXT = 'over_optimized_anchor_text';
    public const GENERIC_ANCHOR_TEXT = 'generic_anchor_text';
    public const IRRELEVANT_ANCHOR_TEXT = 'irrelevant_anchor_text';
    public const MISSING_BRANDED_ANCHOR_TEXT = 'missing_branded_anchor_text';
    public const MISSING_KEYWORD_RICH_ANCHOR_TEXT = 'missing_keyword_rich_anchor_text';
    public const NOFOLLOW_BACKLINKS_OVERUSE = 'nofollow_backlinks_overuse';
    public const MISSING_CONTEXTUAL_BACKLINKS = 'missing_contextual_backlinks';
    public const META_ROBOTS_ISSUES = 'meta_robots_issues';
    public const X_ROBOTS_TAG_ISSUES = 'x_robots_tag_issues';
    public const INSUFFICIENT_TRANSITION_WORDS = 'insufficient_transition_words';
    public const POOR_KEYWORD_PLACEMENT_IN_ALT_TEXT = 'poor_keyword_placement_in_alt_text';
    public const POOR_KEYWORD_ASSIGNMENT = 'poor_keyword_assignment';
    public const LATE_KEYWORD_PLACEMENT_IN_FIRST_PARAGRAPH = 'late_keyword_placement_in_first_paragraph';
    public const POOR_KEYWORD_ENGAGEMENT_IN_OPENING = 'poor_keyword_engagement_in_opening';
    public const POOR_KEYWORD_PLACEMENT_IN_META_TITLE = 'poor_keyword_placement_in_meta_title';
    public const META_TITLE_POOR_CAPITALIZATION = 'meta_title_poor_capitalization';
    public const EXCESSIVE_HEADINGS = 'excessive_headings';
    public const META_DESCRIPTION_SLIGHTLY_SHORT = 'slightly_short_meta_description';
    public const META_TITLE_OVER_OPTIMAL_WORD_COUNT = 'meta_title_over_optimal_word_count';

    /**
     * Get all available suggestions
     *
     * @return array<string>
     */
    public static function getAll(): array
    {
        return [
            self::COMPETITIVE_ADVANTAGE,
            self::CONTENT_EXPANSION,
            self::HIGH_KEYWORD_DIFFICULTY,
            self::IMPORTANT_RELATED_TERMS_MISSING,
            self::IMPROVE_KEYWORD_DISTRIBUTION,
            self::INDUSTRY_CHANGES,
            self::INSUFFICIENT_SECONDARY_KEYWORDS,
            self::INSUFFICIENT_SEMANTIC_CONTEXT,
            self::KEYWORDS_MISSING_IN_HEADINGS,
            self::KEYWORD_CANNIBALIZATION,
            self::KEYWORD_MISSING_IN_FIRST_PARAGRAPH,
            self::KEYWORD_MISSING_IN_POST_TITLE,
            self::KEYWORD_MISSING_IN_META_TITLE,
            self::SECONDARY_KEYWORD_MISSING_IN_META_TITLE,
            self::KEYWORD_OVERUSE,
            self::KEYWORD_OVERUSE_IN_ALT_TEXT,
            self::KEYWORD_STUFFING_IN_FIRST_PARAGRAPH,
            self::KEYWORD_REPETITION_IN_META_DESCRIPTION,
            self::TOO_MANY_SECONDARY_KEYWORDS,
            self::KEYWORD_STUFFING_DETECTED,
            self::LOW_COMMERCIAL_INTENT,
            self::MISSING_KEYWORD_COVERAGE,
            self::MISSING_KEYWORD_MAP,
            self::MISSING_LONG_TAIL_KEYWORDS,
            self::MISSING_PRIMARY_KEYWORD,
            self::MISSING_RELATED_KEYWORDS,
            self::MISSING_SECONDARY_KEYWORDS,
            self::NARROW_KEYWORD_PORTFOLIO,
            self::OPTIMISE_PRIMARY_KEYWORD,
            self::OPTIMISE_PRIMARY_KEYWORD_USAGE,
            self::OPTIMISE_SECONDARY_KEYWORD,
            self::OPTIMISE_SECONDARY_KEYWORDS_USAGE,
            self::OUTDATED_CONTENT,
            self::POOR_KEYWORD_DISTRIBUTION,
            self::POOR_KEYWORD_PLACEMENT,
            self::POOR_RELATED_KEYWORDS_DISTRIBUTION,
            self::POOR_VOLUME_COMPETITION_BALANCE,
            self::PRIMARY_KEYWORD_UNDERUSED,
            self::PRUNING_CANDIDATE,
            self::SUBOPTIMAL_KEYWORD_DENSITY,
            self::MISSING_IMAGE_ALT_TEXT_KEYWORD_USAGE,
            self::SUBOPTIMAL_PRIMARY_KEYWORD_DENSITY,
            self::UNBALANCED_KEYWORD_DISTRIBUTION,
            self::WEAK_TOPICAL_AUTHORITY,
            self::LACKS_CONTENT_DEPTH,
            self::INSUFFICIENT_HEADINGS,
            self::PARAGRAPHS_TOO_LONG,
            self::PARAGRAPHS_TOO_SHORT,
            self::CONTENT_TOO_SHORT,
            self::CONTENT_TOO_LONG,
            self::MISSING_MULTIMEDIA_IN_INTRO_SECTION,
            self::INTENT_NOT_SATISFIED,
            self::META_TITLE_TOO_SHORT,
            self::META_TITLE_TOO_LONG,
            self::META_TITLE_MISSING,
            self::META_TITLE_CLICKBAIT,
            self::META_TITLE_MISSING_BRAND_SEPARATOR,
            self::KEYWORD_NOT_AT_TITLE_START,
            self::META_DESCRIPTION_TOO_SHORT,
            self::META_DESCRIPTION_TOO_LONG,
            self::META_DESCRIPTION_MISSING,
            self::META_DESCRIPTION_WEAK_CALL_TO_ACTION,
            self::META_DESCRIPTION_INTENT_NOT_SATISFIED,
            self::USE_HYPHENS_IN_URL,
            self::MISSING_PRIMARY_KEYWORD_IN_URL,
            self::PRIMARY_KEYWORD_TOO_DEEP_IN_URL,
            self::UNREADABLE_URL_STRUCTURE,
            self::KEYWORD_NOT_IN_URL_SLUG,
            self::NON_SEO_FRIENDLY_URL_STRUCTURE,
            self::URL_TOO_LONG,
            self::MISSING_CANONICAL_TAG,
            self::INVALID_CANONICAL_TAG,
            self::INCORRECT_CANONICAL_TARGET,
            self::MULTIPLE_CANONICAL_TAGS_FOUND,
            self::TECHNICAL_SEO_ISSUES,
            self::INCORRECT_CANONICAL_TAG,
            self::DUPLICATE_CONTENT_IDENTICAL,
            self::DUPLICATE_CONTENT_PARAMETERS,
            self::DUPLICATE_CONTENT_PATHS,
            self::DUPLICATE_CONTENT_UNCANONICALIZED,
            self::REVIEW_DUPLICATE_CONTENT,
            self::MISSING_SCHEMA_MARKUP,
            self::SCHEMA_MARKUP_VALIDATION_FAILED,
            self::IMPROPER_SCHEMA_TYPE_USED,
            self::INVALID_LOCALBUSINESS_SCHEMA,
            self::ROBOTS_TXT_MISSING,
            self::ALL_PAGES_BLOCKED,
            self::CRITICAL_PAGES_BLOCKED,
            self::ADMIN_SECTIONS_NOT_BLOCKED,
            self::OPTIMIZE_ROBOTS_TXT_STRUCTURE,
            self::MISSING_SITEMAP_IN_ROBOTS_TXT,
            self::UNOPTIMIZED_IMAGES,
            self::MISSING_IMAGE_ALT_TEXT,
            self::MISSING_IMAGE_DESCRIPTIVE_ALT_TEXT,
            self::LEGACY_IMAGE_FORMATS,
            self::MISSING_WEBP_SUPPORT,
            self::MISSING_RESPONSIVE_IMAGES,
            self::INCOMPLETE_RESPONSIVE_IMAGES,
            self::MULTIPLE_H1_TAGS_FOUND,
            self::MISSING_H1_TAG,
            self::IMPROPER_HEADING_NESTING,
            self::EMPTY_OR_SHORT_HEADINGS,
            self::PRIMARY_KEYWORD_MISSING_IN_HEADINGS,
            self::FIRST_HEADING_NOT_H1,
            self::KEYWORD_NOT_USED_NATURALLY_IN_ALT,
            self::LACKS_EMOTIONAL_APPEAL,
            self::LACKS_CURIOSITY_ELEMENTS,
            self::LACKS_READER_CONNECTION,
            self::PAGE_NOT_INDEXED,
            self::SAFE_BROWSING_ISSUE,
            self::PAGE_SPEED_OVERALL_SLOW,
            self::PAGE_SPEED_SLOW_LOADING,
            self::PAGE_SPEED_SLOW_FCP,
            self::PAGE_SPEED_SLOW_LCP,
            self::PAGE_SPEED_HIGH_BLOCKING_TIME,
            self::PAGE_SPEED_HIGH_CLS,
            self::PAGE_SPEED_LARGE_IMAGES,
            self::PAGE_SPEED_UNOPTIMIZED_IMAGES,
            self::PAGE_SPEED_RENDER_BLOCKING,
            self::PAGE_SPEED_UNMINIFIED_RESOURCES,
            self::PAGE_SPEED_BROWSER_CACHING,
            self::INSUFFICIENT_REFERRING_DOMAINS,
            self::INCREASE_REFERRING_DOMAINS,
            self::LOW_QUALITY_BACKLINKS,
            self::UNNATURAL_BACKLINK_PROFILE,
            self::LOW_RELEVANCE_BACKLINKS,
            self::MISSING_HIGH_AUTHORITY_BACKLINKS,
            self::POOR_BACKLINK_DIVERSITY,
            self::DECLINING_BACKLINK_PROFILE,
            self::POOR_ANCHOR_TEXT_DISTRIBUTION,
            self::OVER_OPTIMIZED_ANCHOR_TEXT,
            self::GENERIC_ANCHOR_TEXT,
            self::IRRELEVANT_ANCHOR_TEXT,
            self::MISSING_BRANDED_ANCHOR_TEXT,
            self::MISSING_KEYWORD_RICH_ANCHOR_TEXT,
            self::NOFOLLOW_BACKLINKS_OVERUSE,
            self::MISSING_CONTEXTUAL_BACKLINKS,
            self::META_ROBOTS_ISSUES,
            self::X_ROBOTS_TAG_ISSUES,
            self::INSUFFICIENT_TRANSITION_WORDS,
            self::POOR_KEYWORD_PLACEMENT_IN_ALT_TEXT,
            self::POOR_KEYWORD_ASSIGNMENT,
            self::LATE_KEYWORD_PLACEMENT_IN_FIRST_PARAGRAPH,
            self::POOR_KEYWORD_ENGAGEMENT_IN_OPENING,
            self::POOR_KEYWORD_PLACEMENT_IN_META_TITLE,
            self::META_TITLE_POOR_CAPITALIZATION,
            self::EXCESSIVE_HEADINGS,
            self::META_DESCRIPTION_SLIGHTLY_SHORT,
            self::META_TITLE_OVER_OPTIMAL_WORD_COUNT,
        ];
    }

    /**
     * Check if the given value is a valid suggestion
     *
     * @param string $value
     * @return bool
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::getAll(), true);
    }

    /**
     * Get the description of the suggestion.
     *
     * @param string $suggestion
     * @return array
     */
    public static function getDescription(string $suggestion): array
    {
        return match ($suggestion) {
            self::META_TITLE_OVER_OPTIMAL_WORD_COUNT => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your meta title contains too many words. Please aim to keep it below 6 words for optimal SEO performance.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Meta Title Contains Too Many Words', 'beyondseo'),
            ],
            self::META_DESCRIPTION_SLIGHTLY_SHORT => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                /* translators: %1$d is the minimum character length, %2$d is the maximum character length for meta descriptions */
                'description' => sprintf(__('Your meta description is within the acceptable range but could be improved. Aim for a length between %1$d and %2$d characters to enhance click-through rates.', 'beyondseo'), SeoOptimiserConfig::META_DESCRIPTION_MIN_OPTIMAL_LENGTH, SeoOptimiserConfig::META_DESCRIPTION_MAX_OPTIMAL_LENGTH),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Slightly Short Meta Description', 'beyondseo'),
            ],
            self::EXCESSIVE_HEADINGS => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                /* translators: %d is the maximum number of headings per 1000 words */
                'description' => sprintf(__('This page has too many headings. For better readability and SEO, use no more than %d headings per 1000 words.', 'beyondseo'), SeoOptimiserConfig::CONTENT_MAX_RECOMMENDED_HEADINGS_PER_1000_WORDS),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Reduce Excessive Headings', 'beyondseo'),
            ],
            self::META_TITLE_POOR_CAPITALIZATION => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your meta title has inconsistent capitalization. Use proper capitalization rules to improve readability and professionalism. Capitalize all words longer than 3 letters, and always capitalize the first and last word of the title.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Improve Meta Title Capitalization', 'beyondseo'),
            ],
            self::INSUFFICIENT_TRANSITION_WORDS => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Transition words connect ideas smoothly. Use them to guide readers through your content logically. Add transition words like "however," "furthermore," or "consequently" to improve flow.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Use more transition words', 'beyondseo'),
            ],
            self::INSUFFICIENT_REFERRING_DOMAINS => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your page has very few websites linking to it. Getting links from at least 10 different websites will help search engines trust your site more and improve your rankings.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Build More Backlinks', 'beyondseo'),
            ],
            self::INCREASE_REFERRING_DOMAINS => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('You have some websites linking to you, but getting more links (aim for 50+) will significantly boost your search rankings and make your site more authoritative.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Expand Your Backlink Profile', 'beyondseo'),
            ],
            self::LOW_QUALITY_BACKLINKS => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('The websites linking to you are not very trustworthy. Focus on getting links from respected, authoritative websites in your industry instead of low-quality sites.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Improve Backlink Quality', 'beyondseo'),
            ],
            self::UNNATURAL_BACKLINK_PROFILE => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Your links look artificial to search engines. Focus on earning links naturally from relevant websites that would genuinely reference your content, rather than using manipulative tactics.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Balance Your Backlink Profile', 'beyondseo'),
            ],
            self::LOW_RELEVANCE_BACKLINKS => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Many websites linking to you are not related to your industry. Focus on getting links from websites in your specific field, as these are more valuable for SEO.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Increase Topical Relevance of Backlinks', 'beyondseo'),
            ],
            self::MISSING_HIGH_AUTHORITY_BACKLINKS => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('You do not have links from highly respected websites. Even just a few links from trusted, popular sites can significantly boost your search rankings.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Acquire High-Authority Backlinks', 'beyondseo'),
            ],
            self::POOR_BACKLINK_DIVERSITY => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your links come from too few types of websites or regions. Getting links from diverse sources looks more natural to search engines and improves rankings.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Diversify Your Backlink Sources', 'beyondseo'),
            ],
            self::DECLINING_BACKLINK_PROFILE => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('You are losing more links than you are gaining. This signals declining relevance to search engines. Create a consistent strategy to earn new links regularly.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Address Declining Backlink Trend', 'beyondseo'),
            ],
            self::POOR_ANCHOR_TEXT_DISTRIBUTION => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('The clickable text in your backlinks is not varied enough. Aim for a natural mix of branded text, keywords, and generic phrases like "click here".', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Improve Anchor Text Distribution', 'beyondseo'),
            ],
            self::OVER_OPTIMIZED_ANCHOR_TEXT => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Too many of your backlinks use exact keyword phrases as clickable text. This looks manipulative to search engines. Aim for more natural, varied link text.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Reduce Over-Optimized Anchor Text', 'beyondseo'),
            ],
            self::GENERIC_ANCHOR_TEXT => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Many of your backlinks use vague text like "click here" or "read more". These provide less SEO value than descriptive text that includes your brand or keywords.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Reduce Generic Anchor Text Usage', 'beyondseo'),
            ],
            self::IRRELEVANT_ANCHOR_TEXT => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Some of your backlinks use text that is unrelated to your business or content. Try to get links with text that actually relates to what you offer.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Address Irrelevant Anchor Text', 'beyondseo'),
            ],
            self::MISSING_BRANDED_ANCHOR_TEXT => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Not enough links use your brand name as the clickable text. Brand-name links help establish your authority and create a natural-looking link profile.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Increase Branded Anchor Text', 'beyondseo'),
            ],
            self::MISSING_KEYWORD_RICH_ANCHOR_TEXT => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Few of your backlinks use relevant keywords as clickable text. Having some (but not too many) keyword-rich links helps search engines understand your content\'s topic.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.7,
                'title' => __('Add Relevant Keyword Anchors', 'beyondseo'),
            ],
            self::NOFOLLOW_BACKLINKS_OVERUSE => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Too many of your links are marked "nofollow," which limits their SEO value. Focus on getting more standard "dofollow" links from reputable websites.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Balance Nofollow/Dofollow Ratio', 'beyondseo'),
            ],
            self::MISSING_CONTEXTUAL_BACKLINKS => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your backlink profile lacks contextual links (links embedded within relevant content). These are more valuable than links in footers, sidebars, or author bios.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Acquire More Contextual Backlinks', 'beyondseo'),
            ],
            self::HIGH_KEYWORD_DIFFICULTY => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your selected keywords face intense competition. Pivot toward alternative terms with moderate competition to enhance visibility and achieve better ranking positions.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Target Lower Competition Keywords', 'beyondseo'),
            ],
            self::POOR_VOLUME_COMPETITION_BALANCE => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Look for keywords with a good balance between search volume and competition. Ideal keywords have moderate search volume with relatively low competition.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Balance Search Volume and Competition', 'beyondseo'),
            ],
            self::LOW_COMMERCIAL_INTENT => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Keywords with higher CPC (Cost Per Click) often indicate higher commercial intent. For conversion-focused pages, prioritize keywords with good CPC values.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.7,
                'title' => __('Consider Commercial Intent', 'beyondseo'),
            ],
            self::NARROW_KEYWORD_PORTFOLIO => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your keyword strategy relies on too few terms. Diversify with a blend of competitive and niche keywords to capture broader traffic and achieve sustainable ranking growth.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.8,
                'title' => __('Expand Your Keyword Portfolio', 'beyondseo'),
            ],
            self::MISSING_LONG_TAIL_KEYWORDS => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your content omits specific multi-word phrases that attract highly targeted visitors. Incorporate these longer, conversational keyword variations to capture users with precise search intent.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Target Long Tail Variations', 'beyondseo'),
            ],
            self::MISSING_KEYWORD_MAP => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your site needs a structured keyword mapping strategy. Create a comprehensive plan assigning specific terms to each page, preventing content overlap and maximizing topical authority.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Create a Keyword Map', 'beyondseo'),
            ],
            self::KEYWORD_CANNIBALIZATION => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('You have multiple pages targeting the same keywords, which confuses search engines about which page to rank. Either combine these pages into one stronger page or make each page focus on different keywords to avoid competing with yourself.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Fix Keyword Cannibalization', 'beyondseo'),
            ],
            self::IMPROVE_KEYWORD_DISTRIBUTION => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your keyword distribution is uneven. Some topics have too many pages while others have too few. Balance your keyword targeting across content.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Improve Keyword Distribution', 'beyondseo'),
            ],
            self::MISSING_KEYWORD_COVERAGE => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your site needs content addressing several industry-relevant keywords. Develop strategic pages targeting these untapped opportunities to capture additional search traffic and audience segments.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.7,
                'title' => __('Expand Keyword Coverage', 'beyondseo'),
            ],
            self::WEAK_TOPICAL_AUTHORITY => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your website isn\'t seen as an authority on this topic. Create a main page (pillar page) about your core topic, then link to related pages that cover specific aspects in more detail. This connected structure shows search engines you\'re an expert on the subject.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_LOW,
                'threshold' => 0.8,
                'title' => __('Strengthen Topical Authority', 'beyondseo'),
            ],
            self::MISSING_PRIMARY_KEYWORD => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your content doesn\'t have a main keyword to target. Choose one specific phrase that best represents what your page is about. This helps search engines understand your content and show it to the right people.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Set Primary Keyword', 'beyondseo'),
            ],
            self::INSUFFICIENT_SECONDARY_KEYWORDS => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your content focuses too much on just one keyword. Add 2-3 related phrases (secondary keywords) that people might also search for. This helps your page appear in more search results and attract more visitors.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Add Secondary Keywords', 'beyondseo'),
            ],
            self::POOR_KEYWORD_PLACEMENT => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your main keyword isn\'t placed in the most important parts of your content. Make sure to include it in your title, first paragraph, at least one heading, and meta description. These locations have the biggest impact on search rankings.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.7,
                'title' => __('Place Keywords in Strategic Locations', 'beyondseo'),
            ],
            self::SUBOPTIMAL_KEYWORD_DENSITY => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your main keyword appears either too frequently or not often enough in your content. Try to use it in about 1-2% of your text (1-2 times per 100 words). This makes your content relevant without looking like you\'re trying to manipulate search rankings.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.8,
                'title' => __('Maintain Optimal Keyword Density', 'beyondseo'),
            ],
            self::OPTIMISE_PRIMARY_KEYWORD_USAGE => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your main keyword isn\'t being used effectively throughout your content. Make sure to include it naturally in important places like your title, headings, and throughout your text. This helps search engines understand what your page is about.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Optimize Primary Keyword Usage', 'beyondseo'),
            ],
            self::KEYWORD_OVERUSE => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your content contains excessive keyword repetition. Diversify your language with synonyms and related terms to maintain natural flow while preserving search relevance.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.5,
                'title' => __('Keyword Overuse', 'beyondseo'),
            ],
            self::KEYWORD_OVERUSE_IN_ALT_TEXT => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Too many images contain your keyword in their alt text. This appears unnatural to search engines. Use your keyword in alt text for only the most relevant images (aim for 30-50% of images).', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Reduce Keyword Usage in Image Alt Text', 'beyondseo'),
            ],
            self::KEYWORD_STUFFING_IN_FIRST_PARAGRAPH => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Your first paragraph contains excessive keyword repetition. This creates poor readability and may trigger search engine penalties. Rewrite using pronouns, synonyms, and natural language flow.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Fix Keyword Stuffing in Opening Paragraph', 'beyondseo'),
            ],
            self::KEYWORD_REPETITION_IN_META_DESCRIPTION => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your meta description repeats keywords too frequently. Write a compelling, natural description that mentions your keyword once while focusing on user engagement and click-through appeal.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Reduce Keyword Repetition in Meta Description', 'beyondseo'),
            ],
            self::TOO_MANY_SECONDARY_KEYWORDS => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('You have defined too many secondary keywords. Focus on 3-5 highly relevant secondary keywords that complement your primary keyword rather than trying to target too many terms.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Reduce Number of Secondary Keywords', 'beyondseo'),
            ],
            self::OPTIMISE_PRIMARY_KEYWORD => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Refine your primary keyword phrase to capture optimal search intent. Select a specific 2-4 word phrase that balances search volume with competition.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_LOW,
                'threshold' => 0.3,
                'title' => __('Primary Keyword Optimization', 'beyondseo'),
            ],
            self::OPTIMISE_SECONDARY_KEYWORDS_USAGE => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Secondary keywords are overused in your meta description, diluting its effectiveness. Strategically incorporate 1-2 secondary terms to enhance relevance without compromising readability.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_LOW,
                'threshold' => 0.3,
                'title' => __('Optimize Meta Description Keywords', 'beyondseo'),
            ],
            self::OUTDATED_CONTENT => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your content contains obsolete information. Refresh with current data, recent developments, and emerging industry trends to maintain relevance and authority.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Update Content Freshness', 'beyondseo'),
            ],
            self::INDUSTRY_CHANGES => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your content fails to incorporate recent industry developments and trends. Update with current information to maintain authority and improve search visibility.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Adapt to Industry Changes', 'beyondseo'),
            ],
            self::CONTENT_EXPANSION => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Enhance your content by incorporating additional dimensions of the topic. This enriches contextual relevance, improves user engagement, and boosts search engine visibility.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Expand Content Depth', 'beyondseo'),
            ],
            self::COMPETITIVE_ADVANTAGE => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your competitors dominate search rankings with superior content depth and keyword optimization. Enhance your material to surpass their quality and establish market authority.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.5,
                'title' => __('Improve Competitive Advantage', 'beyondseo'),
            ],
            self::PRUNING_CANDIDATE => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('This content underperforms and dilutes site authority. Consider removing or merging it with stronger pages to enhance overall domain quality and search visibility.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_LOW,
                'threshold' => 0.3,
                'title' => __('Consider Content Pruning', 'beyondseo'),
            ],
            self::SUBOPTIMAL_PRIMARY_KEYWORD_DENSITY => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your primary keyword appears either too frequently or insufficiently in your content. Aim for 0.5% to 3% density to enhance search engine recognition and relevance.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Optimize Keyword Density', 'beyondseo'),
            ],
            self::KEYWORD_STUFFING_DETECTED => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Your content contains excessive keyword repetition, which may trigger search engine penalties. Create more natural, reader-friendly text with balanced keyword distribution.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Reduce Keyword Overuse', 'beyondseo'),
            ],
            self::PRIMARY_KEYWORD_UNDERUSED => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your content contains insufficient mentions of the primary keyword, diminishing topical relevance. Strategically integrate more instances while maintaining natural readability.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Increase Primary Keyword Usage', 'beyondseo'),
            ],
            self::POOR_KEYWORD_DISTRIBUTION => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your keywords concentrate in isolated sections rather than flowing naturally throughout the content. Spread them evenly across headings, paragraphs, and conclusion for better SEO impact.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.7,
                'title' => __('Improve Keyword Distribution', 'beyondseo'),
            ],
            self::KEYWORD_MISSING_IN_POST_TITLE => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your primary keyword is absent from the post/page title, significantly reducing search visibility and click-through rates. Incorporate it naturally to boost rankings and relevance.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.8,
                'title' => __('Add Primary Keyword to Post Title', 'beyondseo'),
            ],
            self::KEYWORD_MISSING_IN_META_TITLE => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your primary keyword is absent from the meta title tag, significantly reducing search visibility and click-through rates. Incorporate it naturally to boost rankings and relevance.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.8,
                'title' => __('Add Primary Keyword to Meta Title', 'beyondseo'),
            ],
            self::SECONDARY_KEYWORD_MISSING_IN_META_TITLE => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your meta title lacks secondary keywords that could expand your search visibility. Incorporate them naturally to capture additional relevant traffic and ranking opportunities.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.8,
                'title' => __('Add a Secondary Keyword to Meta Title', 'beyondseo'),
            ],
            self::KEYWORDS_MISSING_IN_HEADINGS => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your headings fail to incorporate primary and secondary keywords, reducing topical relevance. Strategically integrate them to enhance search visibility and user engagement.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.5,
                'title' => __('Add Keywords to Headings', 'beyondseo'),
            ],
            self::KEYWORD_MISSING_IN_FIRST_PARAGRAPH => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your opening paragraph omits the primary keyword, reducing immediate relevance signals. Incorporate it naturally to enhance topical clarity and search engine recognition.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.7,
                'title' => __('Add Primary Keyword to First Paragraph', 'beyondseo'),
            ],
            self::UNBALANCED_KEYWORD_DISTRIBUTION => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your content shows irregular keyword placement with clusters in some sections and sparse usage in others. Distribute keywords evenly for improved SEO performance.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_LOW,
                'threshold' => 0.5,
                'title' => __('Balance Keyword Usage in Content Sections', 'beyondseo'),
            ],
            self::MISSING_RELATED_KEYWORDS => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your content omits crucial semantically-related terms that search engines expect to find. Incorporate these contextual keywords to strengthen topical relevance and authority.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Include Missing Related Keywords', 'beyondseo'),
            ],
            self::INSUFFICIENT_SEMANTIC_CONTEXT => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your content mentions related keywords but doesn\'t clearly explain how they connect to your main topic. Try using these keywords in complete sentences that show their relationship to your main subject. This helps search engines better understand what your content is about.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Enhance Semantic Context', 'beyondseo'),
            ],
            self::IMPORTANT_RELATED_TERMS_MISSING => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your content omits essential semantic terms that search engines associate with your topic, diminishing your authority and topical relevance.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Add Key Missing Related Terms', 'beyondseo'),
            ],
            self::MISSING_SECONDARY_KEYWORDS => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your content lacks supporting keywords that expand your main topical coverage. Add 2-5 relevant secondary keywords to improve search visibility and semantic context.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.3,
                'title' => __('Add Secondary Keywords', 'beyondseo'),
            ],
            self::OPTIMISE_SECONDARY_KEYWORD => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your secondary keywords are too complex. Simplify them to 5 words or fewer to improve readability and search engine recognition in meta titles and description.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_LOW,
                'threshold' => 0.3,
                'title' => __('Simplify Secondary Keywords', 'beyondseo'),
            ],
            self::POOR_RELATED_KEYWORDS_DISTRIBUTION => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your related keywords appear concentrated in isolated sections rather than flowing naturally throughout the content. Redistribute them strategically to enhance topical relevance and improve search visibility.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.7,
                'title' => __('Improve Keyword Distribution', 'beyondseo'),
            ],
            self::LACKS_CONTENT_DEPTH => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your content provides only surface-level information without comprehensive analysis or detailed explanations. Incorporate expert insights, relevant statistics, and practical examples to establish topical authority and satisfy user intent.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Simplify Complex Language To Avoid Lack of Depth', 'beyondseo'),
            ],
            self::INSUFFICIENT_HEADINGS => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Content lacks a clear heading hierarchy. Use strategic H2–H4 tags to improve readability, user experience, and search engine understanding.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Add More Headings', 'beyondseo'),
            ],
            self::PARAGRAPHS_TOO_LONG => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                /* translators: %d is the maximum word count for paragraphs */
                'description' => sprintf(__('Your paragraphs exceed optimal length. It should contain less than %d words. Divide them into smaller, focused sections to enhance readability, reduce cognitive load, and improve visitor engagement.', 'beyondseo'), SeoOptimiserConfig::PARAGRAPH_WORD_COUNT_THRESHOLD['long']),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Shorten Paragraphs', 'beyondseo'),
            ],
            self::PARAGRAPHS_TOO_SHORT => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                /* translators: %d is the minimum word count for paragraphs */
                'description' => sprintf(__('Your paragraphs are too short, making the content feel fragmented. Aim for a minimum of %d words per paragraph to improve flow and coherence.', 'beyondseo'), SeoOptimiserConfig::PARAGRAPH_WORD_COUNT_THRESHOLD['short']),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Lengthen Paragraphs', 'beyondseo'),
            ],
            self::CONTENT_TOO_SHORT => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your content lacks sufficient depth for search engines to recognize topical authority. Expand to at least 500 words with comprehensive, valuable information for readers.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Expand Content Length', 'beyondseo'),
            ],
            self::CONTENT_TOO_LONG => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your content exceeds optimal length. Trim unnecessary sections and focus on core messages to enhance reader engagement and reduce bounce rates.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Shorten Content Length', 'beyondseo'),
            ],
            self::MISSING_MULTIMEDIA_IN_INTRO_SECTION => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your introduction section is missing multimedia elements. Consider adding images, videos, or infographics to enhance engagement.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Add Multimedia to Introduction', 'beyondseo'),
            ],
            self::INTENT_NOT_SATISFIED => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your content does not satisfy the user intent behind the target keywords. Ensure your content aligns with what users are searching for.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Align Content with User Intent', 'beyondseo'),
            ],
            self::META_TITLE_TOO_SHORT => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your meta title needs expansion with relevant keywords. Longer titles capture more search intent and enhance click-through rates from search results.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Expand Meta Title', 'beyondseo'),
            ],
            self::META_TITLE_TOO_LONG => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your meta title exceeds recommended character limits. Truncated titles diminish click-through rates and weaken keyword relevance. Aim for 50-60 characters for optimal search visibility.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Shorten Meta Title', 'beyondseo'),
            ],
            self::META_TITLE_MISSING => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your page requires a meta title tag. Implementing a concise, keyword-rich title enhances click-through rates and establishes crucial context for search engines.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.1,
                'title' => __('Add Meta Title', 'beyondseo'),
            ],
            self::META_TITLE_CLICKBAIT => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your meta title contains clickbait patterns. Consider using a more professional and straightforward title that accurately represents your content.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.5,
                'title' => __('Avoid Clickbait in Meta Title', 'beyondseo'),
            ],
            self::META_TITLE_MISSING_BRAND_SEPARATOR => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your meta title lacks a brand separator (-, |, :). Adding a separator between content and brand name improves readability and professional appearance in search results.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Add Brand Separator to Meta Title', 'beyondseo'),
            ],
            self::KEYWORD_NOT_AT_TITLE_START => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('The primary keyword isn\'t placed at the beginning of the meta title. For stronger SEO signals, move it closer to the start to emphasize relevance.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Move Keyword to Beginning of Meta Title', 'beyondseo'),
            ],
            self::META_DESCRIPTION_TOO_SHORT => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your meta description requires expansion to at least 150 characters. Incorporate compelling details and relevant keywords to improve click-through rates from search results.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Expand Meta Description', 'beyondseo'),
            ],
            self::META_DESCRIPTION_TOO_LONG => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your meta description exceeds optimal length and will be truncated in search results, diminishing click-through rates. Condense it to 155-160 characters for maximum visibility and impact.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Shorten Meta Description', 'beyondseo'),
            ],
            self::META_DESCRIPTION_MISSING => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your page needs a meta description. Creating a compelling summary improves SERP appearance, drives qualified traffic, and communicates page value to search engines.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.1,
                'title' => __('Add Meta Description', 'beyondseo'),
            ],
            self::META_DESCRIPTION_WEAK_CALL_TO_ACTION => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your meta description includes a weak or vague call to action. Make it more specific and persuasive to increase clicks.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Strengthen Your CTA', 'beyondseo'),
            ],
            self::META_DESCRIPTION_INTENT_NOT_SATISFIED => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your meta description does not satisfy the user intent behind the target keywords. Ensure your meta description aligns with what users are searching for.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Align Meta Description with User Intent', 'beyondseo'),
            ],
            self::USE_HYPHENS_IN_URL => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Replace underscores with hyphens in your URLs to enhance search visibility. Search engines recognize hyphens as word separators, improving crawling efficiency and keyword recognition.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Use Hyphens in URLs', 'beyondseo'),
            ],
            self::MISSING_PRIMARY_KEYWORD_IN_URL => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your URL omits the primary keyword, reducing search visibility. Incorporating it enhances ranking potential, signals content relevance, and improves user experience.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Add Primary Keyword to URL', 'beyondseo'),
            ],
            self::PRIMARY_KEYWORD_TOO_DEEP_IN_URL => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('The primary keyword is in the URL, but located deep in the structure. Move it closer to the root for stronger SEO signals.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.7,
                'title' => __('Move Primary Keyword Closer to Root in URL', 'beyondseo'),
            ],
            self::UNREADABLE_URL_STRUCTURE => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('The URL contains complex structures, query parameters, symbols that reduce readability or a long path. Simplify the structure and remove unnecessary characters.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Simplify the URL Structure', 'beyondseo'),
            ],
            self::KEYWORD_NOT_IN_URL_SLUG => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('The primary keyword is not reflected in the URL slug, reducing relevance. Include it in a natural and concise way.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.5,
                'title' => __('Include Primary Keyword in URL', 'beyondseo'),
            ],
            self::NON_SEO_FRIENDLY_URL_STRUCTURE => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your permalink structure is not optimized for SEO. Consider enabling a custom or post-name-based structure for better visibility.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.4,
                'title' => __('Use SEO-Friendly Permalink Structure', 'beyondseo'),
            ],
            self::URL_TOO_LONG => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your URL exceeds optimal length, reducing user experience and SEO effectiveness. Trim unnecessary segments and parameters for improved performance.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.5,
                'title' => __('Shorten the URL', 'beyondseo'),
            ],
            self::MISSING_CANONICAL_TAG => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your page lacks a canonical tag, risking search ranking dilution through duplicate content. Implement this tag to consolidate page authority and clarify preferred URL versions.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Add a Canonical Tag', 'beyondseo'),
            ],
            self::INVALID_CANONICAL_TAG => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your canonical tag contains a malformed URL that search engines cannot interpret. Fix this to prevent indexing issues and ensure proper attribution of page authority.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Fix Canonical Tag URL', 'beyondseo'),
            ],
            self::INCORRECT_CANONICAL_TARGET => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your canonical tag points to an incorrect URL, potentially diverting search engines to index the wrong page and diluting your ranking potential.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Update Canonical to Self-Referential', 'beyondseo'),
            ],
            self::MULTIPLE_CANONICAL_TAGS_FOUND => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Your page contains several canonical tags, creating ambiguity for search engines and compromising their ability to properly index your content.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Use Only One Canonical Tag', 'beyondseo'),
            ],
            self::TECHNICAL_SEO_ISSUES => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Technical SEO issues can hinder how search engines crawl and index your website. These include broken links, incorrect meta tags, and slow load times.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.5,
                'title' => __('Resolve Technical SEO Issues', 'beyondseo'),
            ],
            self::ADMIN_SECTIONS_NOT_BLOCKED => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Important admin sections like /wp-admin/, /wp-includes/, and /wp-login.php are not blocked in your robots.txt file. Blocking these areas prevents search engines from crawling non-essential pages and improves crawl efficiency.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Block Admin Sections in Robots.txt', 'beyondseo'),
            ],
            self::OPTIMIZE_ROBOTS_TXT_STRUCTURE => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your robots.txt file structure could be optimized. Consider organizing directives by user-agent and ensuring consistent formatting for better readability and effectiveness.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Optimize Robots.txt Structure', 'beyondseo'),
            ],
            self::MISSING_SITEMAP_IN_ROBOTS_TXT => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your robots.txt file doesn\'t reference your XML sitemap. Adding a Sitemap directive helps search engines discover and crawl your content more efficiently.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Add Sitemap Reference to Robots.txt', 'beyondseo'),
            ],
            self::INCORRECT_CANONICAL_TAG => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Your canonical tag references an inappropriate or erroneous URL, causing search engines to misinterpret page relationships and potentially diluting ranking signals.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Correct the Canonical Tag', 'beyondseo'),
            ],
            self::DUPLICATE_CONTENT_IDENTICAL => [
                'additionalInfo' => ['type' => SuggestionType::ERROR],
                'description' => __('Identical content has been detected on multiple URLs. This can severely dilute ranking signals and confuse search engines.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_CRITICAL,
                'threshold' => 0.8,
                'title' => __('Remove Identical Duplicate Content', 'beyondseo'),
            ],
            self::DUPLICATE_CONTENT_PARAMETERS => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('URLs with different query parameters are serving the same content. Search engines may treat these as duplicate pages unless properly canonicalized.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Consolidate Parameterized URLs', 'beyondseo'),
            ],
            self::DUPLICATE_CONTENT_PATHS => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Content is duplicated across different URL paths (e.g., /product and /product/index). This creates indexing inefficiencies.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Unify Duplicate URL Paths', 'beyondseo'),
            ],
            self::DUPLICATE_CONTENT_UNCANONICALIZED => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Multiple identical pages found without canonical tags, causing search engines to split ranking power instead of consolidating it to your preferred version.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.7,
                'title' => __('Add Canonical Tags to Duplicates', 'beyondseo'),
            ],
            self::REVIEW_DUPLICATE_CONTENT => [
                'additionalInfo' => ['type' => SuggestionType::NOTICE],
                'description' => __('Similar content exists across multiple pages, diluting SEO value. Evaluate these instances to determine if merging or implementing canonical references would benefit rankings.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_LOW,
                'threshold' => 0.3,
                'title' => __('Review Potential Duplicates', 'beyondseo'),
            ],
            self::MISSING_SCHEMA_MARKUP => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your page needs structured data implementation. Adding appropriate schema markup enhances search engine comprehension and boosts visibility in rich search results.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.1,
                'title' => __('Add Schema Markup to Page', 'beyondseo'),
            ],
            self::SCHEMA_MARKUP_VALIDATION_FAILED => [
                'additionalInfo' => ['type' => SuggestionType::ERROR],
                'description' => __('Your structured data contains validation errors, preventing search engines from properly interpreting it. Resolve these issues to ensure effective indexing and rich result display.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_CRITICAL,
                'threshold' => 0.1,
                'title' => __('Fix Schema Markup Validation Errors', 'beyondseo'),
            ],
            self::IMPROPER_SCHEMA_TYPE_USED => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('The existing structured data does not match the expected type for this content. Update it to a more suitable schema type such as Article, Product, LocalBusiness, etc.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Update to Correct Schema Type', 'beyondseo'),
            ],
            self::INVALID_LOCALBUSINESS_SCHEMA => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('The LocalBusiness schema is present but missing required properties such as name, address, or telephone. Complete the schema to ensure proper validation.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.4,
                'title' => __('Fix Incomplete LocalBusiness Schema', 'beyondseo'),
            ],
            self::ROBOTS_TXT_MISSING => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your website requires a robots.txt file to guide search engine crawlers effectively. Implementing this essential file will enhance crawl efficiency and provide better indexation control.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.1,
                'title' => __('Create a Robots.txt File', 'beyondseo'),
            ],
            self::ALL_PAGES_BLOCKED => [
                'additionalInfo' => ['type' => SuggestionType::ERROR],
                'description' => __('Your robots.txt file is blocking all pages from being crawled (Disallow: /). This prevents search engines from indexing any content. Remove or revise the rule immediately.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_CRITICAL,
                'threshold' => 0.1,
                'title' => __('Fix Robots.txt Blocking All Pages', 'beyondseo'),
            ],
            self::CRITICAL_PAGES_BLOCKED => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Some critical pages (like homepage, contact, product pages) are blocked in your robots.txt file. This may harm visibility in search results. Allow search engines to access key pages.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.2,
                'title' => __('Allow Crawling of Important Pages', 'beyondseo'),
            ],
            self::UNOPTIMIZED_IMAGES => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your page contains unoptimized images that are too large (over 200KB). Large images slow down page loading, affecting user experience and SEO rankings. Compress these images without losing quality using image optimization tools.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Optimize Large Images', 'beyondseo'),
            ],
            self::LEGACY_IMAGE_FORMATS => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your page uses legacy image formats (JPEG, PNG, GIF) instead of modern formats like WebP or AVIF. Next-gen formats provide better compression and quality, reducing page load times by 25-35% while maintaining visual quality. Convert your images to WebP for better performance and SEO.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Use Next-Gen Image Formats', 'beyondseo'),
            ],
            self::MISSING_WEBP_SUPPORT => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your website does not serve WebP images or provide fallbacks for browsers that support this format. Implementing WebP with proper fallbacks can significantly improve page load times while ensuring compatibility across all browsers.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.4,
                'title' => __('Implement WebP Support', 'beyondseo'),
            ],
            self::MISSING_RESPONSIVE_IMAGES => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your page contains images without responsive attributes (srcset). Responsive images allow browsers to load appropriately sized images based on the device screen size, improving page load speed and mobile experience. Add srcset attributes to deliver optimized images for different devices.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Implement Responsive Images', 'beyondseo'),
            ],
            self::INCOMPLETE_RESPONSIVE_IMAGES => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your images have srcset attributes but are missing the sizes attribute. The sizes attribute helps browsers determine which image to download before layout occurs, improving performance. Add sizes attributes to your responsive images for optimal rendering.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_LOW,
                'threshold' => 0.7,
                'title' => __('Complete Responsive Image Implementation', 'beyondseo'),
            ],
            self::KEYWORD_NOT_USED_NATURALLY_IN_ALT => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('The primary keyword is present in some alt texts, but appears forced or unnatural. Revise the alt text to include the keyword in a more natural and descriptive way.', 'beyondseo'),
                'title' => __('Use Keywords Naturally in Alt Text', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
            ],
            self::MULTIPLE_H1_TAGS_FOUND => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your page contains multiple H1 tags. Use a single H1 tag to clearly define the main topic of the page for both users and search engines.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.2,
                'title' => __('Use Only One H1 Tag', 'beyondseo'),
            ],
            self::MISSING_H1_TAG => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your page omits the crucial H1 heading tag. Incorporate a descriptive, keyword-rich H1 element to establish page hierarchy and strengthen topical relevance for search engines.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.1,
                'title' => __('Add an H1 Heading', 'beyondseo'),
            ],
            self::IMPROPER_HEADING_NESTING => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Heading tags are not used in a proper hierarchical order (e.g., skipping from H1 to H4). Use correct nesting to improve semantic structure and SEO.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.4,
                'title' => __('Fix Heading Nesting Order', 'beyondseo'),
            ],
            self::EMPTY_OR_SHORT_HEADINGS => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('One or more heading tags are empty or too short to be meaningful. Provide concise and descriptive text to help both readers and search engines.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.3,
                'title' => __('Avoid Empty or Short Headings', 'beyondseo'),
            ],
            self::PRIMARY_KEYWORD_MISSING_IN_HEADINGS => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your primary keyword is missing from all heading tags. Include it in at least one heading to improve topic clarity and SEO.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Add Primary Keyword to Headings', 'beyondseo'),
            ],
            self::FIRST_HEADING_NOT_H1 => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('The first heading on the page is not an H1. Begin with an H1 to clearly define the page\'s topic for both users and search engines.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.6,
                'title' => __('Use H1 as the First Heading', 'beyondseo'),
            ],
            self::LACKS_EMOTIONAL_APPEAL => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your opening paragraph lacks emotional appeal. Include words that evoke emotions to create a stronger connection with readers and increase engagement.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Add Emotional Appeal to Opening', 'beyondseo'),
            ],
            self::LACKS_CURIOSITY_ELEMENTS => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your opening paragraph doesn\'t create curiosity or intrigue. Include elements that spark curiosity such as questions, teasers, or hints at valuable information to come.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Create Curiosity in Opening Paragraph', 'beyondseo'),
            ],
            self::LACKS_READER_CONNECTION => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your opening paragraph doesn\'t establish a personal connection with readers. Use direct address (you/your) or shared experiences to make readers feel the content is relevant to them personally.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Establish Reader Connection in Opening', 'beyondseo'),
            ],
            self::PAGE_NOT_INDEXED => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Your page is not indexed by search engines. This means it won\'t appear in search results, significantly limiting its visibility and traffic potential.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_CRITICAL,
                'threshold' => 0.1,
                'title' => __('Page Not Indexed by Search Engines', 'beyondseo'),
            ],
            self::SAFE_BROWSING_ISSUE => [
                'additionalInfo' => ['type' => SuggestionType::ERROR],
                'description' => __('Your page has been flagged by Google Safe Browsing. This indicates potential security issues such as malware, phishing, or harmful content that could harm visitors and damage your site reputation.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_CRITICAL,
                'threshold' => 0.1,
                'title' => __('Security Issue Detected by Safe Browsing', 'beyondseo'),
            ],
            self::PAGE_SPEED_OVERALL_SLOW => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your page has a low overall performance score. Slow loading pages lead to higher bounce rates and lower conversion rates. Implement the specific recommendations to improve page speed.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.3,
                'title' => __('Improve Overall Page Performance', 'beyondseo'),
            ],
            self::PAGE_SPEED_SLOW_LOADING => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your page takes too long to load completely. Pages that load in under 3 seconds have significantly higher engagement. Optimize resources to reduce total loading time.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Reduce Total Page Load Time', 'beyondseo'),
            ],
            self::PAGE_SPEED_SLOW_FCP => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your page takes too long to show the first piece of content to visitors. This is called First Contentful Paint (FCP) and it\'s an important part of user experience. Improve your server response time and remove anything that blocks the page from loading quickly.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Improve First Contentful Paint', 'beyondseo'),
            ],
            self::PAGE_SPEED_SLOW_LCP => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('The largest element on your page (like a hero image or banner) takes too long to appear. This is called Largest Contentful Paint (LCP) and it\'s a key factor in how Google ranks your site. Optimize your largest images and content elements to load faster.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Improve Largest Contentful Paint', 'beyondseo'),
            ],
            self::PAGE_SPEED_HIGH_BLOCKING_TIME => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Total Blocking Time (TBT) is too high. This indicates that your page has long tasks that block the main thread and delay interactivity. Optimize JavaScript execution and split long tasks.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Reduce Total Blocking Time', 'beyondseo'),
            ],
            self::PAGE_SPEED_HIGH_CLS => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Cumulative Layout Shift (CLS) is too high. This Core Web Vital measures visual stability. Elements that shift unexpectedly create a poor user experience. Set dimensions for images and ads, and avoid inserting content above existing content.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Improve Visual Stability', 'beyondseo'),
            ],
            self::PAGE_SPEED_LARGE_IMAGES => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your page contains images that are too large in file size. Large images significantly slow down page loading. Resize and compress images to appropriate dimensions and file sizes.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Optimize Large Images', 'beyondseo'),
            ],
            self::PAGE_SPEED_UNOPTIMIZED_IMAGES => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your page contains unoptimized images. Properly compressed images can reduce file size by 30-80% without visible quality loss. Use modern image compression techniques and appropriate formats.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Compress Images Properly', 'beyondseo'),
            ],
            self::PAGE_SPEED_RENDER_BLOCKING => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your page has render-blocking resources that delay page rendering. These resources (typically CSS and JavaScript) prevent the page from displaying quickly. Inline critical CSS, defer non-critical CSS, and use async/defer for scripts.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Eliminate Render-Blocking Resources', 'beyondseo'),
            ],
            self::PAGE_SPEED_UNMINIFIED_RESOURCES => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your page includes unminified CSS and JavaScript files. Minification removes unnecessary characters from code without changing functionality, reducing file size. Minify all CSS and JavaScript resources.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Minify CSS and JavaScript', 'beyondseo'),
            ],
            self::PAGE_SPEED_BROWSER_CACHING => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your page does not leverage browser caching effectively. Proper cache settings allow returning visitors to load your page faster by storing resources locally. Set appropriate cache headers for static resources.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Implement Efficient Browser Caching', 'beyondseo'),
            ],
            self::META_ROBOTS_ISSUES => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Some pages have problematic meta robots tags that may prevent proper indexing or crawling. Review and update these tags to ensure proper search engine visibility.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Fix Meta Robots Tag Issues', 'beyondseo'),
            ],
            self::X_ROBOTS_TAG_ISSUES => [
                'additionalInfo' => ['type' => SuggestionType::WARNING],
                'description' => __('Your site has X-Robots-Tag HTTP header issues that may affect search engine crawling and indexing. These HTTP headers need to be corrected to ensure proper visibility.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Fix X-Robots-Tag Header Issues', 'beyondseo'),
            ],
            self::POOR_KEYWORD_PLACEMENT_IN_ALT_TEXT => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your primary keyword is not effectively placed in image alt text. Include the keyword naturally in alt text for relevant images to improve accessibility and SEO relevance.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Optimize Keyword Placement in Alt Text', 'beyondseo'),
            ],
            self::POOR_KEYWORD_ASSIGNMENT => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Your primary and secondary keywords are not properly assigned or validated. Review and optimize your keyword selection to ensure they align with content intent and search behavior.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Improve Keyword Assignment Strategy', 'beyondseo'),
            ],
            self::LATE_KEYWORD_PLACEMENT_IN_FIRST_PARAGRAPH => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your primary keyword appears too late in the first paragraph. Place the keyword within the first 100 words to establish topic relevance early for both users and search engines.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.5,
                'title' => __('Move Keyword Earlier in First Paragraph', 'beyondseo'),
            ],
            self::POOR_KEYWORD_ENGAGEMENT_IN_OPENING => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your opening paragraph lacks engaging keyword integration. Incorporate the primary keyword naturally while maintaining compelling, reader-focused content that encourages continued reading.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.6,
                'title' => __('Enhance Keyword Engagement in Opening', 'beyondseo'),
            ],
            self::POOR_KEYWORD_PLACEMENT_IN_META_TITLE => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('Your primary keyword is not optimally positioned in the meta title. Place the keyword near the beginning of the title for maximum SEO impact and improved click-through rates.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.4,
                'title' => __('Optimize Keyword Position in Meta Title', 'beyondseo'),
            ],
            self::MISSING_IMAGE_ALT_TEXT => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Some images on your page are missing alt text. Alt text is crucial for accessibility and SEO, as it helps search engines understand the content of images.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_HIGH,
                'threshold' => 0.5,
                'title' => __('Add Alt Text to Images', 'beyondseo'),
            ],
            self::MISSING_IMAGE_DESCRIPTIVE_ALT_TEXT => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Some images have alt text that is too short or not descriptive enough. Use more detailed alt text to improve accessibility and provide better context for search engines.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.4,
                'title' => __('Enhance Image Alt Text Descriptiveness', 'beyondseo'),
            ],
            self::MISSING_IMAGE_ALT_TEXT_KEYWORD_USAGE => [
                'additionalInfo' => ['type' => SuggestionType::IMPLEMENTATION],
                'description' => __('Some images have alt text that does not include the primary keyword. Including the keyword in image alt text can improve relevance and help with SEO.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_MEDIUM,
                'threshold' => 0.5,
                'title' => __('Include Primary Keyword in Image Alt Text', 'beyondseo'),
            ],
            default => [
                'additionalInfo' => ['type' => SuggestionType::OPTIMIZATION],
                'description' => __('An unknown optimisation suggestion was generated. Please review the detected issue.', 'beyondseo'),
                'priority' => FactorSuggestion::PRIORITY_LOW,
                'threshold' => 0.0,
                'title' => __('Unknown Optimisation Suggestion', 'beyondseo'),
            ],
        };
    }
}
