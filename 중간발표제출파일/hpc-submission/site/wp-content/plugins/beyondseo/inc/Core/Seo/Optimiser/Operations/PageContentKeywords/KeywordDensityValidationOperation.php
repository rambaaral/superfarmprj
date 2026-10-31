<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\PageContentKeywords;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;
use Throwable;

/**
 * Class KeywordDensityValidationOperation
 *
 * This class is responsible for analyzing keyword density in page content,
 * detecting overuse or underuse of keywords, and providing optimization suggestions.
 */
#[SeoMeta(
    name: 'Keyword Density Validation',
    weight: WeightConfiguration::WEIGHT_KEYWORD_DENSITY_VALIDATION_OPERATION,
    description: 'Calculates keyword density across the page to detect excessive or insufficient usage. Evaluates primary and secondary terms, suggesting adjustments to keep frequencies within recommended ranges for natural, SEO-friendly content.',
)]
class KeywordDensityValidationOperation extends Operation implements OperationInterface
{
    /**
     * Performs keyword density analysis on page content.
     *
     * @return array|null The analysis results
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get the primary and secondary keywords
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);
        $secondaryKeywords = $this->contentProvider->getSecondaryKeywords($postId);

        // Check if keywords are available
        if (empty($primaryKeyword) && empty($secondaryKeywords)) {
            return [
                'success' => false,
                'message' => __('No keywords found for analysis', 'beyondseo'),
                'density_analysis' => []
            ];
        }

        // Get the content
        try {
            // Try to get the rendered content first (as it would appear in browser)
            $content = $this->contentProvider->getContent($postId);

            // If that fails, fall back to database content
            if (empty($content)) {
                $content = $this->contentProvider->getContent($postId, true);
            }

            // Clean the content for text analysis
            $cleanContent = $this->contentProvider->cleanContent($content);

            // If still no content, return an error
            if (empty($cleanContent)) {
                return [
                    'success' => false,
                    'message' => __('No content found for analysis', 'beyondseo'),
                    'density_analysis' => []
                ];
            }
        } catch (Throwable $e) {
            return [
                'success' => false,
                /* translators: %s is the error message */
                'message' => sprintf(__('Error fetching content: %s', 'beyondseo'), $e->getMessage()),
                'density_analysis' => []
            ];
        }

        // Get total word count
        $totalWordCount = $this->contentProvider->getWordCount($cleanContent);

        // If the content is too short, adjust expectations
        $isShortContent = $totalWordCount < 300;

        // Analyze primary keyword density
        $primaryKeywordAnalysis = [];
        if (!empty($primaryKeyword)) {
            $primaryKeywordAnalysis = $this->contentProvider->analyzeKeywordDensity(
                $postId,
                $primaryKeyword,
                $content,
                $cleanContent,
                $totalWordCount,
                $isShortContent,
                true
            );
        }

        // Analyze secondary keywords density
        $secondaryKeywordsAnalysis = [];
        foreach ($secondaryKeywords as $secondaryKeyword) {
            $secondaryKeywordsAnalysis[$secondaryKeyword] = $this->contentProvider->analyzeKeywordDensity(
                $postId,
                $secondaryKeyword,
                $content,
                $cleanContent,
                $totalWordCount,
                $isShortContent
            );
        }

        // Calculate overall content balance
        $overallBalance = $this->contentProvider->calculateKeywordsDensityOverallBalance(
            $primaryKeywordAnalysis,
            $secondaryKeywordsAnalysis
        );

        // Prepare the results
        return [
            'success' => true,
            'message' => __('Keyword density analysis completed', 'beyondseo'),
            'density_analysis' => [
                'total_word_count' => $totalWordCount,
                'is_short_content' => $isShortContent,
                'primary_keyword' => [
                    'keyword' => $primaryKeyword,
                    'analysis' => $primaryKeywordAnalysis
                ],
                'secondary_keywords' => array_map(static function ($keyword, $analysis) {
                    return [
                        'keyword' => $keyword,
                        'analysis' => $analysis
                    ];
                }, array_keys($secondaryKeywordsAnalysis), array_values($secondaryKeywordsAnalysis)),
                'overall_balance' => $overallBalance
            ]
        ];
    }

    /**
     * Score the operation based on the analysis results.
     *
     * @return float The score from 0 to 1
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // Extract density analysis data
        $densityAnalysis = $factorData['density_analysis'] ?? [];

        // If no analysis data, return 0
        if (empty($densityAnalysis)) {
            return 0;
        }

        // Get primary keyword analysis
        $primaryAnalysis = $densityAnalysis['primary_keyword']['analysis'] ?? [];

        // If no primary keyword analysis, return a default middle score
        if (empty($primaryAnalysis)) {
            return 0.5;
        }

        // Get the density score for the primary keyword (0-1)
        $primaryDensityScore = $primaryAnalysis['score'] ?? 0;

        // Get secondary keywords analyses
        $secondaryKeywords = $densityAnalysis['secondary_keywords'] ?? [];

        // Calculate average score for secondary keywords
        $secondaryScores = [];
        foreach ($secondaryKeywords as $secondaryData) {
            $analysis = $secondaryData['analysis'] ?? [];
            if (!empty($analysis)) {
                $secondaryScores[] = $analysis['score'] ?? 0;
            }
        }

        $avgSecondaryScore = count($secondaryScores) > 0 ?
            array_sum($secondaryScores) / count($secondaryScores) : 0;

        // Get the overall balance score
        $balanceScore = $densityAnalysis['overall_balance']['score'] ?? 0.5;

        // Calculate the final score with appropriate weights
        // Primary keyword (50%), secondary keywords (25%), and balance (25%)
        $finalScore = ($primaryDensityScore * 0.5) +
            ($avgSecondaryScore * 0.25) +
            ($balanceScore * 0.25);

        // Ensure the score is between 0 and 1
        return max(0, min(1, $finalScore));
    }

    /**
     * Identifies potential SEO issues based on the keyword density analysis.
     *
     * @return array List of active suggestion types (issue types)
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    public function suggestions(): array
    {
        $activeSuggestions = []; // Will hold all identified issue types
        $customSuggestions = []; // Will hold customized suggestion details with keyword information

        // Extract the analysis results for this operation
        $factorData = $this->value;
        $postId = $this->postId;

        // Extract density analysis data
        $densityAnalysis = $factorData['density_analysis'] ?? [];
        if (empty($densityAnalysis)) {
            return $activeSuggestions;
        }

        // Extract primary keyword data
        $primaryKeywordData = $densityAnalysis['primary_keyword'] ?? [];
        $primaryKeyword = $primaryKeywordData['keyword'] ?? '';
        $primaryAnalysis = $primaryKeywordData['analysis'] ?? [];

        // Extract secondary keywords data
        $secondaryKeywordData = $densityAnalysis['secondary_keywords'] ?? [];

        // Extract overall balance data
        $overallBalance = $densityAnalysis['overall_balance'] ?? [];

        // 1. Check for suboptimal primary keyword density
        if (!empty($primaryAnalysis)) {
            $primaryDensity = $primaryAnalysis['density'] ?? 0;
            $optimalMin = $primaryAnalysis['optimal_range']['min'] ?? SeoOptimiserConfig::OPTIMAL_DENSITY_MIN;
            $optimalMax = $primaryAnalysis['optimal_range']['max'] ?? SeoOptimiserConfig::OPTIMAL_DENSITY_MAX;

            if ($primaryDensity < $optimalMin || $primaryDensity > $optimalMax) {
                $activeSuggestions[] = Suggestion::SUBOPTIMAL_PRIMARY_KEYWORD_DENSITY;

                // Add a custom suggestion with the actual keyword
                if (!empty($primaryKeyword)) {
                    $customSuggestions['suboptimal_primary_keyword_density'] = [
                        'keyword' => $primaryKeyword,
                        'current_density' => $primaryDensity,
                        'optimal_min' => $optimalMin,
                        'optimal_max' => $optimalMax
                    ];
                }
            }
        }

        // 2. Check for keyword stuffing (severe overuse)
        if (!empty($primaryAnalysis)) {
            $primaryDensity = $primaryAnalysis['density'] ?? 0;
            $primaryStatus = $primaryAnalysis['status'] ?? '';

            if ($primaryDensity > SeoOptimiserConfig::SEVERE_OVERUSE_THRESHOLD ||
                $primaryStatus === 'severely_overused') {
                $activeSuggestions[] = Suggestion::KEYWORD_STUFFING_DETECTED;

                // Add a custom suggestion with the actual keyword
                if (!empty($primaryKeyword)) {
                    $customSuggestions['keyword_stuffing_detected'] = [
                        'keyword' => $primaryKeyword,
                        'current_density' => $primaryDensity
                    ];
                }
            }
        }

        // 3. Check for underused primary keyword
        if (!empty($primaryAnalysis)) {
            $primaryDensity = $primaryAnalysis['density'] ?? 0;
            $primaryStatus = $primaryAnalysis['status'] ?? '';

            if ($primaryDensity < SeoOptimiserConfig::UNDERUSE_THRESHOLD ||
                $primaryStatus === 'severely_underused' ||
                $primaryStatus === 'underused') {
                $activeSuggestions[] = Suggestion::PRIMARY_KEYWORD_UNDERUSED;

                // Add a custom suggestion with the actual keyword
                if (!empty($primaryKeyword)) {
                    $customSuggestions['primary_keyword_underused'] = [
                        'keyword' => $primaryKeyword,
                        'current_density' => $primaryDensity
                    ];
                }
            }
        }

        // 4. Check for poor keyword distribution
        if (!empty($primaryAnalysis)) {
            $distributionScore = $primaryAnalysis['distribution'] ?? null;
            if ($distributionScore === null) {
                $distributionScore = $primaryAnalysis['distribution_score'] ?? null;
            }
            if ($distributionScore === null) {
                $distributionScore = $primaryAnalysis['detailed_analysis']['distribution_score'] ?? 1;
            }

            if ($distributionScore < 0.3) {
                $activeSuggestions[] = Suggestion::POOR_KEYWORD_DISTRIBUTION;

                // Add a custom suggestion with the actual keyword
                if (!empty($primaryKeyword)) {
                    $customSuggestions['poor_keyword_distribution'] = [
                        'keyword' => $primaryKeyword,
                        'distribution_score' => $distributionScore
                    ];
                }
            }
        }

        // 5. Check for insufficient secondary keyword usage
        $effectiveSecondaryKeywords = 0;
        $secondaryKeywordsList = [];

        foreach ($secondaryKeywordData as $secondaryData) {
            $keyword = $secondaryData['keyword'] ?? '';
            $analysis = $secondaryData['analysis'] ?? [];

            if (!empty($analysis) && !empty($keyword)) {
                $secondaryKeywordsList[] = $keyword;

                $density = $analysis['density'] ?? 0;
                $status = $analysis['status'] ?? '';
                $structuralUsage = $analysis['structural_usage'] ?? [];
                $isStrategicallyPlaced = ($structuralUsage['in_title'] ?? false) || ($structuralUsage['in_headings'] ?? false);

                // Consider a secondary keyword as effective if:
                // - It has sufficient density (at least 70% of an optimal minimum)
                // - Or it's used in strategic places (title, headings)
                if ($density >= SeoOptimiserConfig::OPTIMAL_DENSITY_MIN * 0.7 ||
                    $status === 'optimal' ||
                    $isStrategicallyPlaced) {
                    $effectiveSecondaryKeywords++;
                }
            }
        }

        // Check if there are enough effective secondary keywords
        if (count($secondaryKeywordData) < 2 || $effectiveSecondaryKeywords < 2) {
            $activeSuggestions[] = Suggestion::INSUFFICIENT_SECONDARY_KEYWORDS;

            // Add custom suggestion with primary and secondary keywords
            if (!empty($primaryKeyword)) {
                $customSuggestions['insufficient_secondary_keyword_usage'] = [
                    'primary_keyword' => $primaryKeyword,
                    'secondary_keywords' => $secondaryKeywordsList,
                    'effective_count' => $effectiveSecondaryKeywords
                ];
            }
        }

        // Additionally, check the overall balance for insufficient secondary keyword usage
        if (!empty($overallBalance)) {
            $balanceStatus = $overallBalance['status'] ?? '';
            $ratio = $overallBalance['ratio'] ?? 1;

            // If the primary keyword dominates too much
            if ($balanceStatus === 'primary_dominant' ||
                $balanceStatus === 'primary_heavy' ||
                $ratio > 5) {
                if (!in_array('insufficient_secondary_keyword_usage', $activeSuggestions)) {
                    $activeSuggestions[] = Suggestion::INSUFFICIENT_SECONDARY_KEYWORDS;

                    // Add custom suggestion if not already added
                    if (!empty($primaryKeyword) && !isset($customSuggestions['insufficient_secondary_keyword_usage'])) {
                        $customSuggestions['insufficient_secondary_keyword_usage'] = [
                            'primary_keyword' => $primaryKeyword,
                            'secondary_keywords' => $secondaryKeywordsList,
                            'primary_secondary_ratio' => $ratio
                        ];
                    }
                }
            }
        }

        // Store the custom suggestions with keyword information for later use
        if (!empty($customSuggestions) && isset($parameters['store_custom_data']) && $parameters['store_custom_data']) {
            $this->contentProvider->setTransient(
                'rc_custom_keyword_suggestions_' . $postId,
                $customSuggestions,
                86400
            );
        }

        return $activeSuggestions;
    }
}