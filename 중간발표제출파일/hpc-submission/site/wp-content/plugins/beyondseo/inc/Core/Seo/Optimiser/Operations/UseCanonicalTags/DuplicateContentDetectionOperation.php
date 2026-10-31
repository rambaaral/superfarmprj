<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\UseCanonicalTags;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class DuplicateContentDetectionOperation
 *
 * This operation identifies duplicate content across the website and analyzes canonical tag implementation.
 * Since this is a resource-intensive operation, it's designed to be run externally through an API.
 */
#[SeoMeta(
    name: 'Duplicate Content Detection',
    weight: WeightConfiguration::WEIGHT_DUPLICATE_CONTENT_DETECTION_OPERATION,
    description: 'Detects duplicate pages within the site and reviews canonical tags to ensure proper consolidation. Uses simulated API results to minimize load and caches findings, guiding resolution of content redundancy.',
)]
class DuplicateContentDetectionOperation extends Operation implements OperationInterface
{
    /**
     * Performs duplicate content detection and canonical tag analysis.
     * Since this is designed as an external operation, it simulates API results
     * and caches them for a week to minimize resource usage.
     *
     * @return array|null Analysis results
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get the post-URL and content
        $postUrl = $this->contentProvider->getPostUrl($postId);

        // In a real implementation, this would be an API call to an external duplicate content service
        // For demonstration; we simulate the API call

        // First, check if canonical tags exist on the page
        $canonicalInfo = $this->analyzeCanonicalTag($postId);

        // Then, analyze for duplicate content
        $duplicateContent = $this->contentProvider->detectDuplicateContentOnLocal($postId, $postUrl);

        // Combine the results
        return [
            'success' => true,
            'message' => __('Duplicate content analysis completed', 'beyondseo'),
            'canonical_tag' => $canonicalInfo,
            'duplicate_content' => $duplicateContent,
            'analysis_date' => current_time('mysql'),
        ];
    }

    /**
     * Analyzes the canonical tag implementation for a specific post.
     * In a real implementation, this would parse the actual HTML to find canonical tags.
     *
     * @param int $postId The post ID to analyze
     * @return array Canonical tag analysis results
     */
    private function analyzeCanonicalTag(int $postId): array
    {
        // Get the post-URL
        $expectedUrl = $this->contentProvider->getPostUrl($postId);

        // In a real implementation, we'd fetch the HTML and check for canonical tags
        // For now, extract from the content
        $content = $this->contentProvider->getContent($postId);

        // Try to extract canonical URL from content
        $canonicalUrl = $this->contentProvider->extractCanonicalUrl($content, $postId);

        // Build the analysis results
        $hasCanonical = !empty($canonicalUrl);
        $isSelfReferencing = $hasCanonical && ($canonicalUrl == $expectedUrl);

        $results = [
            'has_canonical' => $hasCanonical,
            'is_self_referencing' => $isSelfReferencing,
            'canonical_url' => $canonicalUrl,
            'expected_url' => $expectedUrl,
            'issues' => []
        ];

        // Identify issues
        if (!$hasCanonical) {
            $results['issues'][] = [
                'type' => 'missing_canonical',
                'message' => __('No canonical tag found on this page', 'beyondseo'),
                'recommendation' => __('Add a self-referencing canonical tag to help prevent duplicate content issues.', 'beyondseo')
            ];
        } elseif (!$isSelfReferencing) {
            $results['issues'][] = [
                'type' => 'non_self_canonical',
                'message' => __('Canonical tag points to a different URL', 'beyondseo'),
                'canonical_url' => $canonicalUrl,
                'expected_url' => $expectedUrl,
                'recommendation' => __('This may be intentional if this page is a duplicate of another page. If not, update the canonical tag to point to this URL.', 'beyondseo')
            ];
        }

        return $results;
    }

    /**
     * Evaluates the operation value based on duplicate content analysis.
     * This function assigns a score between 0 and 1 based on the severity of
     * duplicate content issues and canonical tag implementation.
     *
     * @return float A score based on the duplicate content analysis (0-1)
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // Get the analysis data
        $canonicalInfo = $factorData['canonical_tag'] ?? [];
        $duplicateContent = $factorData['duplicate_content'] ?? [];

        // 1. Evaluate canonical tag implementation (40% of the score)
        $canonicalScore = 1.0;

        if (empty($canonicalInfo) || !isset($canonicalInfo['has_canonical'])) {
            // If canonical analysis data is missing or indicates fetch failure
            if (isset($canonicalInfo['issues'])) {
                foreach($canonicalInfo['issues'] as $issue) {
                    if ($issue['type'] === 'fetch_failed' || $issue['type'] === 'url_resolution_failed') {
                        // Cannot assess canonical properly if fetch failed
                        $canonicalScore = 0.1; // Very low score as a critical issue
                        break;
                    }
                }
            }
            // If data is just empty for some other reason, assign a default penalty
            if ($canonicalScore === 1.0) {
                $canonicalScore = 0.5; // Default to mid-score if data is missing but no explicit fetch error
            }

        } else {
            if (!$canonicalInfo['has_canonical']) {
                // Missing canonical tag is a significant issue
                $canonicalScore = 0.3;
            } elseif (!$canonicalInfo['is_self_referencing']) {
                // Non-self-referencing canonical might be intentional, deduct less
                $canonicalScore = 0.7;
            }

            // Deduct more for canonical issues found during the analysis itself
            foreach($canonicalInfo['issues'] ?? [] as $issue) {
                if ($issue['type'] === 'missing_canonical') {
                    $canonicalScore -= 0.3; // Significant deduction
                } elseif ($issue['type'] === 'non_self_canonical') {
                    $canonicalScore -= 0.15; // Moderate deduction
                }
                // Other issue types (like fetch_failed) are handled above
            }
        }

        // Ensure canonical score doesn't go below 0
        $canonicalScore = max(0, $canonicalScore);

        // 2. Evaluate duplicate content issues (60% of the score)
        $duplicateScore = 1.0;

        if (!empty($duplicateContent) && isset($duplicateContent['has_duplicates'])) {
            if ($duplicateContent['has_duplicates']) {

                // Penalty for identical duplicates found
                $identicalDuplicates = $duplicateContent['duplicate_pages'] ?? [];
                $identicalCount = count($identicalDuplicates);
                if ($identicalCount > 0) {
                    $duplicateScore -= min(0.6, $identicalCount * 0.2); // Significant penalty for identical matches

                    // Further penalty if identical duplicates *don't* have canonicals pointing back
                    $duplicatesWithoutProperCanonical = 0;
                    foreach ($identicalDuplicates as $duplicate) {
                        if (!isset($duplicate['canonical_points_to_original']) || !$duplicate['canonical_points_to_original']) {
                            $duplicatesWithoutProperCanonical++;
                        }
                    }
                    $duplicateScore -= min(0.3, $duplicatesWithoutProperCanonical * 0.15); // Penalty for uncanonicalized identical duplicates
                }

                // Penalty for parameter/path pattern issues detected in the URL
                $parameterPatternIssues = $duplicateContent['parameter_based_duplicates'] ?? [];
                $pathPatternIssues = $duplicateContent['path_based_duplicates'] ?? [];
                $patternIssueCount = count($parameterPatternIssues) + count($pathPatternIssues);

                if ($patternIssueCount > 0) {
                    // These are potential issues based on URL structure, less severe than confirmed identical duplicates
                    $duplicateScore -= min(0.3, $patternIssueCount * 0.1);
                }

                // Note: highest_similarity will be 1.0 if any identical duplicates are found.
                // We already penalize based on count and canonical status of identical,
                // so directly using highest_similarity (which is 1.0 or 0) isn't the best granularity here.
            }
        }


        // Ensure the duplicate score doesn't go below 0
        $duplicateScore = max(0, $duplicateScore);

        // Calculate the final weighted score
        $score = ($canonicalScore * 0.4) + ($duplicateScore * 0.6);

        return max(0, min(1, $score)); // Ensure the score is between 0 and 1
    }

    /**
     * Provides suggestions for improving duplicate content and canonical tag implementation.
     * These suggestions are based on issues found during the analysis.
     *
     * @return array An array of suggestion issue types from the Suggestion enum
     */
    public function suggestions(): array
    {
        $suggestions = [];

        $factorData = $this->value;
        if (empty($factorData) || !isset($factorData['success']) || $factorData['success'] === false) {
            // If analysis failed, suggest checking accessibility/setup
            return [Suggestion::TECHNICAL_SEO_ISSUES];
        }

        $canonicalInfo = $factorData['canonical_tag'] ?? [];
        $duplicateContent = $factorData['duplicate_content'] ?? [];

        // 1. Canonical tag issues
        if (!empty($canonicalInfo)) {
            // Check for issues reported by the canonical analysis
            $canonicalIssues = $canonicalInfo['issues'] ?? [];
            foreach ($canonicalIssues as $issue) {
                if ($issue['type'] === 'fetch_failed' || $issue['type'] === 'url_resolution_failed') {
                    $suggestions[] = Suggestion::TECHNICAL_SEO_ISSUES; // Cannot fetch/analyze
                } elseif ($issue['type'] === 'missing_canonical') {
                    $suggestions[] = Suggestion::MISSING_CANONICAL_TAG; // Specific suggestion for missing
                } elseif ($issue['type'] === 'non_self_canonical') {
                    // This might be intentional, but often indicates a configuration issue
                    $suggestions[] = Suggestion::INCORRECT_CANONICAL_TAG; // Specific suggestion for incorrect
                }
            }
        } else {
            // Canonical analysis data missing
            $suggestions[] = Suggestion::TECHNICAL_SEO_ISSUES; // Indicate analysis problem
        }


        // 2. Duplicate content issues
        if (!empty($duplicateContent) && isset($duplicateContent['has_duplicates']) && $duplicateContent['has_duplicates']) {

            // Issue: Identical content found on other pages
            $identicalDuplicates = $duplicateContent['duplicate_pages'] ?? [];
            if (!empty($identicalDuplicates)) {
                $suggestions[] = Suggestion::DUPLICATE_CONTENT_IDENTICAL; // Specific suggestion for identical
                // Check if any identical duplicates lack proper canonicals
                foreach ($identicalDuplicates as $duplicate) {
                    if (!isset($duplicate['canonical_points_to_original']) || !$duplicate['canonical_points_to_original']) {
                        $suggestions[] = Suggestion::DUPLICATE_CONTENT_UNCANONICALIZED; // Specific suggestion for uncanonicalized duplicates
                        break; // Add suggestion once
                    }
                }
            }

            // Issue: Parameter-based duplicate patterns detected in the URL
            if (!empty($duplicateContent['parameter_based_duplicates'])) {
                $suggestions[] = Suggestion::DUPLICATE_CONTENT_PARAMETERS; // Specific suggestion for parameters
            }

            // Issue: Path-based duplicate patterns detected in the URL
            if (!empty($duplicateContent['path_based_duplicates'])) {
                $suggestions[] = Suggestion::DUPLICATE_CONTENT_PATHS; // Specific suggestion for paths
            }

            // Add a general suggestion if any duplicates/patterns were found
            if (!empty($identicalDuplicates) || !empty($duplicateContent['parameter_based_duplicates']) || !empty($duplicateContent['path_based_duplicates'])) {
                $suggestions[] = Suggestion::REVIEW_DUPLICATE_CONTENT; // General suggestion to review
            }
        }

        $finalSuggestions = [];
        foreach($suggestions as $sugg) {
            // Map the specific suggestions back to the potentially broader original enum values
            // This is a placeholder mapping based on the *original* Suggestion enum names
            // You should adjust this based on your *actual* Suggestion enum values.
            if ($sugg === Suggestion::MISSING_CANONICAL_TAG || $sugg === Suggestion::DUPLICATE_CONTENT_IDENTICAL || $sugg === Suggestion::DUPLICATE_CONTENT_PARAMETERS || $sugg === Suggestion::DUPLICATE_CONTENT_PATHS) {
                $finalSuggestions[] = Suggestion::KEYWORD_CANNIBALIZATION; // Broadly related to content issues
            } elseif ($sugg === Suggestion::INCORRECT_CANONICAL_TAG || $sugg === Suggestion::DUPLICATE_CONTENT_UNCANONICALIZED) {
                $finalSuggestions[] = Suggestion::UNREADABLE_URL_STRUCTURE; // Related to URL/canonical issues
            } elseif ($sugg === Suggestion::TECHNICAL_SEO_ISSUES) {
                $finalSuggestions[] = Suggestion::TECHNICAL_SEO_ISSUES; // Keep technical issues
            } elseif ($sugg === Suggestion::REVIEW_DUPLICATE_CONTENT) {
                // Maybe map to a general 'Content Quality' suggestion if available, or Keyword Cannibalization
                $finalSuggestions[] = Suggestion::KEYWORD_CANNIBALIZATION;
            } else {
                // Fallback or log unknown suggestion type
                $finalSuggestions[] = $sugg; // Keep if it's already a valid enum value
            }
        }

        return $finalSuggestions;
    }
}