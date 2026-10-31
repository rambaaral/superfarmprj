<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentQualityAndLength;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class ContentLengthValidationOperation
 *
 * This class is responsible for validating the content length of a post.
 * It extends the base Operation class and implements specific logic for content length validation.
 */
#[SeoMeta(
    name: 'Content Length Validation',
    weight: WeightConfiguration::WEIGHT_CONTENT_LENGTH_VALIDATION_OPERATION,
    description: 'Validates the content length of a post against predefined benchmarks. Analyzes word count, structure, and provides suggestions for optimization.',
)]
class ContentLengthValidationOperation extends Operation implements OperationInterface
{
    /**
     * Performs content update analysis for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get content type
        $postType = $this->contentProvider->getPostType($postId);

        // Extract content with the appropriate method
        $content = $this->contentProvider->getContent($postId);

        // Clean the content for analysis
        $cleanContent = $this->contentProvider->cleanContent($content);

        // Count words in the content
        $wordCount = $this->contentProvider->getWordCount($cleanContent);

        // Get the appropriate benchmark based on the content type
        $benchmark = $this->contentProvider->getLengthBenchmarksForContentType($postId);

        // Analyze content length against the benchmark
        $contentLengthAnalysis = $this->analyzeContentLength($wordCount, $benchmark);

        // Analyze content structure (headings, paragraphs, etc.)
        $structureAnalysis = $this->contentProvider->analyzeContentStructure($content, $wordCount);

        // Prepare result data
        return [
            'success' => true,
            'message' => __('Content length analysis completed successfully', 'beyondseo'),
            'word_count' => $wordCount,
            'content_type' => $postType,
            'benchmark' => $benchmark,
            'content_length_status' => $contentLengthAnalysis['status'],
            'content_structure' => $structureAnalysis
        ];
    }

    /**
     * Calculate the score based on the performed analysis
     *
     * @return float A score based on the content freshness and quality
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        $wordCount = $factorData['word_count'] ?? 0;
        $benchmark = $factorData['benchmark'] ?? $this->contentProvider->getLengthBenchmarksForContentType($this->postId);
        $structureData = $factorData['content_structure'] ?? [];

        // Calculate length score (60% of total)
        $lengthScore = $this->calculateLengthScore($wordCount, $benchmark);

        // Calculate structure score (40% of total)
        $structureScore = $this->calculateStructureScore($structureData);

        // Combine scores with appropriate weighting
        return ($lengthScore * 0.6) + ($structureScore * 0.4);
    }

    /**
     * Generate suggestions based on content update analysis
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];

        $factorData = $this->value;

        $wordCount = $factorData['word_count'] ?? 0;
        $contentLengthStatus = $factorData['content_length_status'] ?? '';
        $benchmark = $factorData['benchmark'] ?? SeoOptimiserConfig::CONTENT_LENGTH_BENCHMARKS['default'];
        $structureData = $factorData['content_structure'] ?? [];

        // Check content length issues
        if ($contentLengthStatus === 'too_short') {
            $activeSuggestions[] = Suggestion::CONTENT_TOO_SHORT;
        } elseif ($contentLengthStatus === 'too_long') {
            $activeSuggestions[] = Suggestion::CONTENT_TOO_LONG;
        }

        // Check heading structure
        if (($structureData['headings_count'] ?? 0) < 3) {
            $activeSuggestions[] = Suggestion::INSUFFICIENT_HEADINGS;
        }

        // Check paragraph structure
        if (($structureData['avg_paragraph_length'] ?? 0) > 150) {
            $activeSuggestions[] = Suggestion::PARAGRAPHS_TOO_LONG;
        }

        // Check for content depth based on word count
        if ($wordCount < $benchmark['min']) {
            $activeSuggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
        }

        return $activeSuggestions;
    }

    /**
     * Calculate a score based on content length compared to benchmarks.
     *
     * @param int $wordCount Word count
     * @param array $benchmark Benchmark values
     * @return float Score between 0 and 1
     */
    private function calculateLengthScore(int $wordCount, array $benchmark): float
    {
        if ($wordCount < 100) {
            // Extremely short content
            return 0.1;
        }

        if ($wordCount < $benchmark['min']) {
            // Below minimum but not extremely short
            return 0.1 + (0.4 * $wordCount / $benchmark['min']);
        }

        if ($wordCount <= $benchmark['optimal']) {
            // Between minimum and optimal
            $range = $benchmark['optimal'] - $benchmark['min'];
            $position = $wordCount - $benchmark['min'];
            return 0.5 + (0.5 * $position / $range);
        }

        if ($wordCount <= $benchmark['max']) {
            // Between optimal and maximum
            $range = $benchmark['max'] - $benchmark['optimal'];
            $position = $wordCount - $benchmark['optimal'];
            $overagePercentage = $position / $range;
            return 1.0 - (0.3 * $overagePercentage);
        }

        // Above maximum
        $excessPercentage = min(1, ($wordCount - $benchmark['max']) / $benchmark['max']);
        return 0.7 - (0.4 * $excessPercentage);
    }

    /**
     * Calculate a score based on content structure analysis.
     *
     * @param array $structureData Structure analysis data
     * @return float Score between 0 and 1
     */
    private function calculateStructureScore(array $structureData): float
    {
        $score = 0.5; // Start with a neutral score

        // Evaluate heading structure
        $headingsCount = $structureData['headings_count'] ?? 0;
        if ($headingsCount >= 3) {
            $score += 0.1; // Good number of headings

            // Check heading hierarchy (H1, H2, H3)
            $hasH1 = ($structureData['headings_breakdown']['h1'] ?? 0) > 0;
            $hasH2 = ($structureData['headings_breakdown']['h2'] ?? 0) > 0;
            $hasH3 = ($structureData['headings_breakdown']['h3'] ?? 0) > 0;

            if ($hasH1) {
                $score += 0.05; // Has H1
                if ($hasH2) {
                    $score += 0.05; // Has proper H1 → H2 hierarchy
                    if ($hasH3) {
                        $score += 0.05; // Has complete H1 → H2 → H3 hierarchy
                    }
                }
            } elseif ($hasH2 || $hasH3) {
                // Penalize for headings without a proper hierarchy
                $score -= 0.05; // Headings exist but without a proper hierarchy
            }
        } elseif ($headingsCount === 0) {
            $score -= 0.2; // No headings are bad for SEO
        }

        // Evaluate paragraph structure
        $avgParagraphLength = $structureData['avg_paragraph_length'] ?? 0;
        if ($avgParagraphLength > 0 && $avgParagraphLength <= 80) {
            $score += 0.1; // Optimal paragraph length
        } elseif ($avgParagraphLength > 150) {
            $score -= 0.1; // Paragraphs too long
        }

        // Evaluate words per heading ratio
        $wordsPerHeading = $structureData['words_per_heading'] ?? 0;
        if ($wordsPerHeading >= 200 && $wordsPerHeading <= 400) {
            $score += 0.1; // Optimal heading distribution
        } elseif ($wordsPerHeading > 500) {
            $score -= 0.1; // Too few headings for content length
        }

        // Bonus for multimedia content
        if ($structureData['has_multimedia'] ?? false) {
            $score += 0.1;
        }

        // Ensure the score is between 0 and 1
        return max(0, min(1, $score));
    }

    /**
     * Analyze content length against benchmark values.
     *
     * @param int $wordCount Word count
     * @param array $benchmark Benchmark values
     * @return array Analysis results
     */
    private function analyzeContentLength(int $wordCount, array $benchmark): array
    {
        $status = 'optimal';

        if ($wordCount < $benchmark['min']) {
            $status = 'too_short';
            $percentOfOptimal = ($wordCount / $benchmark['min']) * 100;
        } elseif ($wordCount > $benchmark['max']) {
            $status = 'too_long';
            $percentOfOptimal = ($benchmark['max'] / $wordCount) * 100;
        } else {
            // Within an acceptable range
            $percentOfOptimal = ($wordCount / $benchmark['optimal']) * 100;

            if ($wordCount < $benchmark['optimal']) {
                $status = 'slightly_short';
            } elseif ($wordCount > $benchmark['optimal']) {
                $status = 'slightly_long';
            }
        }

        return [
            'status' => $status,
            'percent_of_optimal' => round($percentOfOptimal, 2),
            'difference_from_optimal' => $wordCount - $benchmark['optimal']
        ];
    }
}