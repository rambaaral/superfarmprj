<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\OptimizeUrlStructure;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class UrlReadabilityOperation
 *
 * This operation analyzes URL readability for WordPress posts and pages.
 * It validates that URLs are human-readable, short, and descriptive,
 * flagging issues like complex parameters, numbers, or unrelated characters.
 */
#[SeoMeta(
    name: 'Url Readability',
    weight: WeightConfiguration::WEIGHT_URL_READABILITY_OPERATION,
    description: 'Evaluates URL structure for clarity and simplicity, detecting excessive parameters, numeric strings, or confusing paths. Guides improvements to create short, readable URLs that clearly describe page content.',
)]
class UrlReadabilityOperation extends Operation implements OperationInterface
{
    /**
     * Performs URL readability analysis for the specified post.
     *
     * @return array|null Analysis results or null if invalid post-ID
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get URL and related information
        $pageUrl = $this->contentProvider->getPostUrl($postId);
        $pageTitle = $this->contentProvider->getPostTitle($postId);
        $postSlug = $this->contentProvider->getPostField('post_name', $postId);

        // Get permalink settings
        $permalinkStructure = get_option('permalink_structure');

        // Perform analysis
        $results = $this->contentProvider->analyzeUrlReadability($pageUrl, $pageTitle, $postSlug, $permalinkStructure);

        // Prepare analysis results
        return [
            'success' => true,
            'message' => __('URL readability analysis completed', 'beyondseo'),
            'url' => $pageUrl,
            'title' => $pageTitle,
            'slug' => $postSlug,
            'permalink_structure' => $permalinkStructure,
            'analysis' => $results,
        ];
    }

    /**
     * Calculates the overall URL readability score.
     *
     * @param int $urlLength URL length
     * @param bool $hasQueryParams Has query parameters
     * @param bool $hasExcessiveNumbers Has excessive numbers
     * @param bool $hasSpecialCharacters Has special characters
     * @param float $keywordRelevance Keyword relevance score
     * @param bool $hasExcessiveSegments Has too many segments
     * @param bool $isSeoFriendlyStructure Using SEO-friendly permalink structure
     * @return float Readability score (0-1)
     * @noinspection PhpTooManyParametersInspection
     */
    private function calculateUrlReadabilityScore(
        int $urlLength,
        bool $hasQueryParams,
        bool $hasExcessiveNumbers,
        bool $hasSpecialCharacters,
        float $keywordRelevance,
        bool $hasExcessiveSegments,
        bool $isSeoFriendlyStructure
    ): float {
        // Start with a perfect score
        $score = 1.0;

        // Deduct for URL length issues
        if ($urlLength > SeoOptimiserConfig::MAX_ACCEPTABLE_URL_LENGTH) {
            $score -= 0.3;
        } elseif ($urlLength > SeoOptimiserConfig::MAX_RECOMMENDED_URL_LENGTH) {
            $score -= 0.15;
        }

        // Deduct for query parameters
        if ($hasQueryParams) {
            $score -= 0.2;
        }

        // Deduct for excessive numbers
        if ($hasExcessiveNumbers) {
            $score -= 0.15;
        }

        // Deduct for special characters
        if ($hasSpecialCharacters) {
            $score -= 0.15;
        }

        // Adjust based on keyword relevance
        $score += ($keywordRelevance - 0.5) * 0.2;

        // Deduct for excessive segments
        if ($hasExcessiveSegments) {
            $score -= 0.2;
        }

        // Deduct for non-SEO-friendly permalink structure
        if (!$isSeoFriendlyStructure) {
            $score -= 0.25;
        }

        // Ensure the score is between 0 and 1
        return max(0, min(1, $score));
    }

    /**
     * Evaluates the URL readability score.
     *
     * @return float Score based on URL readability (0-1)
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        $analysis = $factorData['analysis'];

        $urlLength = $analysis['url_length']['length'] ?? 0;
        $hasQueryParams = $analysis['query_parameters']['has_query_params'] ?? false;
        $hasExcessiveNumbers = $analysis['slug_quality']['has_excessive_numbers'] ?? false;
        $hasSpecialCharacters = $analysis['slug_quality']['has_special_characters'] ?? false;
        $keywordRelevance = $analysis['keyword_relevance']['score'] ?? 0;
        $hasExcessiveSegments = $analysis['slug_quality']['has_excessive_segments'] ?? false;
        $isSeoFriendlyStructure = $analysis['permalink_structure']['is_seo_friendly'] ?? false;

        // Calculate overall readability score (0-1)
        return $this->calculateUrlReadabilityScore(
            $urlLength,
            $hasQueryParams,
            $hasExcessiveNumbers,
            $hasSpecialCharacters,
            $keywordRelevance,
            $hasExcessiveSegments,
            $isSeoFriendlyStructure
        );
    }

    /**
     * Provides suggestions for improving URL readability.
     *
     * @return array Suggestions for improvement
     */
    public function suggestions(): array
    {
        $factorData = $this->value;
        $suggestions = [];

        $analysis = $factorData['analysis'];

        // URL structure issues (query params, length, numbers, special chars)
        if ($analysis['query_parameters']['has_query_params'] ||
            $analysis['url_length']['is_too_long'] ||
            $analysis['slug_quality']['has_excessive_numbers'] ||
            $analysis['slug_quality']['has_special_characters'] ||
            $analysis['slug_quality']['has_excessive_segments']) {
            $suggestions[] = Suggestion::UNREADABLE_URL_STRUCTURE;
        }

        // Poor keyword relevance in URL
        if (!$analysis['keyword_relevance']['is_relevant']) {
            $suggestions[] = Suggestion::KEYWORD_NOT_IN_URL_SLUG;
        }

        // Non-SEO-friendly permalink structure
        if (!$analysis['permalink_structure']['is_seo_friendly']) {
            $suggestions[] = Suggestion::NON_SEO_FRIENDLY_URL_STRUCTURE;
        }

        return $suggestions;
    }
}