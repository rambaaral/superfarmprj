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
 * Class-RelatedKeywordInclusionOperation
 *
 * This operation analyzes how well-related keywords are incorporated in content,
 * ensuring they appear naturally and provide semantic relevance to the primary topic.
 * Modern search engines use these related terms to better understand content context.
 */
#[SeoMeta(
    name: 'Related Keyword Inclusion',
    weight: WeightConfiguration::WEIGHT_RELATED_KEYWORD_INCLUSION_OPERATION,
    description: 'Checks how supplementary keywords related to the main topic are integrated into the content. Evaluates their placement and frequency to enhance semantic context without overshadowing the primary keyword focus.',
)]
class RelatedKeywordInclusionOperation extends Operation implements OperationInterface
{
    /**
     * Performs related keyword analysis on page content.
     *
     * @return array|null The analysis results
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get the primary keyword
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);

        // Get related keywords
        $relatedKeywords = $this->contentProvider->getSecondaryKeywords($postId);

        // Check if keywords are available
        if (empty($primaryKeyword) || empty($relatedKeywords)) {
            return [
                'success' => false,
                'message' => __('No primary keyword or related keywords found for analysis', 'beyondseo'),
                'related_keywords_analysis' => []
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
                    'related_keywords_analysis' => []
                ];
            }
        } catch (Throwable $e) {
            return [
                'success' => false,
                /* translators: %s is the error message */
                'message' => sprintf(__('Error fetching content: %s', 'beyondseo'), $e->getMessage()),
                'related_keywords_analysis' => []
            ];
        }

        // Get total word count
        $totalWordCount = $this->contentProvider->getWordCount($cleanContent);

        // Analyze each related keyword
        $keywordAnalyses = [];
        $presentKeywords = 0;
        $keywordsWithGoodDistribution = 0;
        $keywordsWithGoodContext = 0;

        foreach ($relatedKeywords as $relatedKeyword) {
            $analysis = $this->contentProvider->analyzeRelatedKeyword(
                $relatedKeyword,
                $primaryKeyword,
                $content,
                $cleanContent,
                $totalWordCount
            );

            $keywordAnalyses[$relatedKeyword] = $analysis;

            // Count keywords that meet criteria
            if ($analysis['is_present']) {
                $presentKeywords++;

                if ($analysis['distribution_score'] >= SeoOptimiserConfig::NATURAL_DISTRIBUTION_THRESHOLD) {
                    $keywordsWithGoodDistribution++;
                }

                if ($analysis['context_score'] >= SeoOptimiserConfig::SEMANTIC_CONTEXT_THRESHOLD) {
                    $keywordsWithGoodContext++;
                }
            }
        }

        // Calculate overall scores
        $presenceScore = count($relatedKeywords) > 0 ?
            ($presentKeywords / count($relatedKeywords)) : 0;

        $distributionScore = $presentKeywords > 0 ?
            ($keywordsWithGoodDistribution / $presentKeywords) : 0;

        $contextScore = $presentKeywords > 0 ?
            ($keywordsWithGoodContext / $presentKeywords) : 0;

        // Prepare analysis for keywords that are missing
        $missingKeywords = [];
        foreach ($keywordAnalyses as $keyword => $analysis) {
            if (!$analysis['is_present']) {
                $missingKeywords[] = $keyword;
            }
        }

        // Prepare the results
        return [
            'success' => true,
            'message' => __('Related keywords analysis completed', 'beyondseo'),
            'related_keywords_analysis' => [
                'total_related_keywords' => count($relatedKeywords),
                'total_present' => $presentKeywords,
                'missing_keywords' => $missingKeywords,
                'with_good_distribution' => $keywordsWithGoodDistribution,
                'with_good_context' => $keywordsWithGoodContext,
                'presence_score' => round($presenceScore, 2),
                'distribution_score' => round($distributionScore, 2),
                'context_score' => round($contextScore, 2),
                'individual_analyses' => $keywordAnalyses
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

        // Extract analysis data
        $analysis = $factorData['related_keywords_analysis'] ?? [];
        if (empty($analysis)) {
            return 0;
        }

        $countRelatedKeywords = $analysis['total_related_keywords'] ?? 0;
        $presentKeywords = $analysis['total_present'] ?? 0;
        $keywordsWithGoodDistribution = $analysis['with_good_distribution'] ?? 0;
        $keywordsWithGoodContext = $analysis['with_good_context'] ?? 0;
        $relatedKeywords = $this->contentProvider->getSecondaryKeywords($this->postId);

        // Calculate overall scores
        $presenceScore = $countRelatedKeywords > 0 ?
            ($presentKeywords / $countRelatedKeywords) : 0;

        $distributionScore = $presentKeywords > 0 ?
            ($keywordsWithGoodDistribution / $presentKeywords) : 0;

        $contextScore = $presentKeywords > 0 ?
            ($keywordsWithGoodContext / $presentKeywords) : 0;

        // Calculate overall related keywords score
        $overallScore = $this->calculateOverallScore(
            $presenceScore,
            $distributionScore,
            $contextScore,
            count($relatedKeywords)
        );

        // Get the overall score
        $score = round($overallScore, 2) ?? 0;

        // Ensure the score is between 0 and 1
        return max(0, min(1, $score));
    }

    /**
     * Calculate the overall score for related keyword inclusion
     *
     * @param float $presenceScore Score for keyword presence
     * @param float $distributionScore Score for keyword distribution
     * @param float $contextScore Score for semantic context
     * @param int $totalKeywords Total number of related keywords
     * @return float Overall score from 0 to 1
     */
    private function calculateOverallScore(
        float $presenceScore,
        float $distributionScore,
        float $contextScore,
        int   $totalKeywords
    ): float
    {
        // If no related keywords were provided, return a neutral score
        if ($totalKeywords === 0) {
            return 0.5;
        }

        // If there are very few related keywords (1-2), be more lenient
        $presenceWeight = 0.5;
        $contextWeight = 0.3;
        $distributionWeight = 0.2;

        if ($totalKeywords <= 2) {
            // For very few keywords, presence becomes even more important
            $presenceWeight = 0.6;
            $distributionWeight = 0.1;
        }

        // If very few related keywords are present, penalize the score
        if ($presenceScore < 0.3) {
            return $presenceScore * 0.7; // Slightly more forgiving than 0.5
        }

        // Weight the components
        $weightedScore =
            ($presenceScore * $presenceWeight) +
            ($contextScore * $contextWeight) +
            ($distributionScore * $distributionWeight);

        // Apply a bonus if a high percentage of keywords are present with good context
        if ($presenceScore > 0.7 && $contextScore > 0.7) {
            $weightedScore = min(1, $weightedScore * 1.1);
        }

        // Apply a penalty if distribution is very poor despite good presence
        if ($presenceScore > 0.7 && $distributionScore < 0.3) {
            $weightedScore *= 0.9;
        }

        return max(0, min(1, $weightedScore));
    }

    /**
     * Generate suggestions based on the factor data from related keyword analysis
     *
     * @return array An array of suggestions based on the factor data
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    public function suggestions(): array
    {
        $activeSuggestions = []; // Will hold all identified issue types

        $factorData = $this->value;

        // Get the related keywords analysis data
        $analysis = $factorData['related_keywords_analysis'] ?? [];
        if (empty($analysis)) {
            return $activeSuggestions;
        }

        // Extract key metrics from analysis
        $totalRelatedKeywords = $analysis['total_related_keywords'] ?? 0;
        $presentKeywords = $analysis['total_present'] ?? 0;
        $missingKeywords = $analysis['missing_keywords'] ?? [];
        $presenceScore = $analysis['presence_score'] ?? 0;
        $distributionScore = $analysis['distribution_score'] ?? 0;
        $contextScore = $analysis['context_score'] ?? 0;
        $individualAnalyses = $analysis['individual_analyses'] ?? [];

        // 1. Check if related keywords are missing
        if ($totalRelatedKeywords > 0 && $presenceScore < 0.5) {
            $activeSuggestions[] = Suggestion::MISSING_RELATED_KEYWORDS;
        }

        // 2. Check if the distribution of keywords is poor
        if ($presentKeywords > 0 && $distributionScore < SeoOptimiserConfig::NATURAL_DISTRIBUTION_THRESHOLD) {
            $activeSuggestions[] = Suggestion::POOR_RELATED_KEYWORDS_DISTRIBUTION;
        }

        // 3. Check if the semantic context is not enough
        if ($presentKeywords > 0 && $contextScore < SeoOptimiserConfig::SEMANTIC_CONTEXT_THRESHOLD) {
            $activeSuggestions[] = Suggestion::INSUFFICIENT_SEMANTIC_CONTEXT;
        }

        // 4. Check for important related terms missing
        // Consider longer multi-word phrases as more important terms
        $importantMissingTerms = array_filter($missingKeywords, static function($keyword) {
            return str_word_count($keyword) > 1;
        });

        if (!empty($importantMissingTerms)) {
            $activeSuggestions[] = Suggestion::IMPORTANT_RELATED_TERMS_MISSING;
        }

        // 5. Check for unbalanced keyword usage
        if ($totalRelatedKeywords >= 3 && $presentKeywords >= 2) {
            // Calculate keyword usage balance
            $counts = [];
            foreach ($individualAnalyses as $analysis) {
                if ($analysis['is_present']) {
                    $counts[] = $analysis['count'];
                }
            }

            if (!empty($counts)) {
                $maxCount = max($counts);
                $minCount = min($counts);
                $avgCount = array_sum($counts) / count($counts);

                // Calculate dispersion - high dispersion suggests unbalanced usage
                $variance = array_sum(array_map(static function($count) use ($avgCount) {
                        return pow($count - $avgCount, 2);
                    }, $counts)) / count($counts);

                $standardDeviation = sqrt($variance);
                $coefficientOfVariation = $standardDeviation / max(1, $avgCount);

                // If we have high variance in keyword usage or extreme differences
                if ($coefficientOfVariation > 0.7 || ($maxCount > 8 && $minCount <= 1)) {
                    $activeSuggestions[] = Suggestion::POOR_KEYWORD_DISTRIBUTION;
                }
            }
        }

        return $activeSuggestions;
    }
}