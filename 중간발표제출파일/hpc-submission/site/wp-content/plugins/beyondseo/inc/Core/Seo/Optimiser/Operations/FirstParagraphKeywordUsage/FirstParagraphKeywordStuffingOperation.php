<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\FirstParagraphKeywordUsage;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;
use DOMDocument;

/**
 * Class FirstParagraphKeywordStuffingOperation
 *
 * This operation detects keyword stuffing in the first paragraph of content and
 * provides recommendations for more natural keyword usage alternatives.
 */
#[SeoMeta(
    name: 'First Paragraph Keyword Stuffing',
    weight: WeightConfiguration::WEIGHT_FIRST_PARAGRAPH_KEYWORD_STUFFING_OPERATION,
    description: 'Detects keyword stuffing in the first paragraph and computes language-agnostic distribution metrics for repetition and proximity. This is crucial for maintaining readability and avoiding penalties from search engines.',
)]
class FirstParagraphKeywordStuffingOperation extends Operation implements OperationInterface
{
    // Thresholds for keyword stuffing detection
    private const STUFFING_THRESHOLD = 3.0; // Percentage above which is considered stuffing
    private const SEVERE_STUFFING_THRESHOLD = 5.0; // Percentage for severe stuffing
    private const MIN_PARAGRAPH_WORDS = 30; // Minimum words for reliable analysis

    // Language-agnostic distribution thresholds
    private const PROXIMITY_THRESHOLD_CHARS = 20; // Max chars between keyword starts to be considered close
    private const REPETITION_RATIO_THRESHOLD = 2.0; // Occurrences per 100 words considered high repetition

    /**
     * Performs keyword stuffing detection in the first paragraph for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get keywords for this post
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);
        $secondaryKeywords = $this->contentProvider->getSecondaryKeywords($postId);

        if (empty($primaryKeyword) && empty($secondaryKeywords)) {
            return [
                'success' => false,
                'message' => __('No keywords found for this post', 'beyondseo'),
                'data' => []
            ];
        }

        // Get content to analyze
        $content = $this->contentProvider->getContent($postId, true);

        // Extract the first paragraph using DOM
        $firstParagraph = $this->contentProvider->extractFirstParagraphWithDOM($content);

        if (empty($firstParagraph)) {
            return [
                'success' => false,
                'message' => __('No first paragraph found in the content', 'beyondseo'),
                'data' => []
            ];
        }

        // Clean the first paragraph text (remove HTML tags, etc.)
        $cleanFirstParagraph = $this->contentProvider->cleanContent($firstParagraph);
        $wordCount = str_word_count($cleanFirstParagraph);

        // Skip if the paragraph is too short for meaningful analysis
        if ($wordCount < self::MIN_PARAGRAPH_WORDS) {
            return [
                'success' => true,
                'message' => __('First paragraph too short for keyword stuffing analysis', 'beyondseo'),
                'data' => [
                    'first_paragraph' => $cleanFirstParagraph,
                    'word_count' => $wordCount,
                    'is_stuffing_detected' => false
                ]
            ];
        }

        // Analyze keyword density in the first paragraph
        $keywordAnalysis = $this->analyzeKeywordDensity($cleanFirstParagraph, $primaryKeyword, $secondaryKeywords);

        // Check if keyword stuffing is detected based on thresholds
        $isStuffingDetected = $keywordAnalysis['primary']['density'] > self::STUFFING_THRESHOLD ||
            $keywordAnalysis['combined_density'] > self::STUFFING_THRESHOLD;

        // Compute language-agnostic distribution metrics and flags
        $distributionMetrics = $this->computeDistributionMetrics($cleanFirstParagraph, $primaryKeyword, $secondaryKeywords);
        $distributionFlags = $this->computeDistributionFlags($distributionMetrics);

        // Create result data
        return [
            'success' => true,
            'message' => $isStuffingDetected ? __('Keyword stuffing detected in first paragraph', 'beyondseo') : __('No keyword stuffing detected', 'beyondseo'),
            'data' => [
                'first_paragraph' => $cleanFirstParagraph,
                'word_count' => $wordCount,
                'is_stuffing_detected' => $isStuffingDetected,
                'keyword_analysis' => $keywordAnalysis,
                'distribution_metrics' => $distributionMetrics,
                'distribution_flags' => $distributionFlags
            ]
        ];
    }

    /**
     * Analyze keyword density in the first paragraph.
     *
     * @param string $paragraph The paragraph text
     * @param string $primaryKeyword The primary keyword
     * @param array $secondaryKeywords The secondary keywords
     * @return array Analysis results
     */
    private function analyzeKeywordDensity(string $paragraph, string $primaryKeyword, array $secondaryKeywords): array
    {
        $wordCount = str_word_count($paragraph);
        $normalizedParagraph = strtolower($paragraph);

        // Analyze primary keyword
        $primaryAnalysis = $this->analyzeKeyword($normalizedParagraph, $primaryKeyword, $wordCount);

        // Analyze secondary keywords
        $secondaryAnalyses = [];
        $totalSecondaryOccurrences = 0;

        foreach ($secondaryKeywords as $secondaryKeyword) {
            if (!empty($secondaryKeyword)) {
                $secondaryAnalysis = $this->analyzeKeyword($normalizedParagraph, $secondaryKeyword, $wordCount);
                $secondaryAnalyses[$secondaryKeyword] = $secondaryAnalysis;
                $totalSecondaryOccurrences += $secondaryAnalysis['occurrences'];
            }
        }

        // Calculate combined density
        $totalOccurrences = $primaryAnalysis['occurrences'] + $totalSecondaryOccurrences;
        $combinedDensity = $wordCount > 0 ? ($totalOccurrences / $wordCount) * 100 : 0;

        return [
            'word_count' => $wordCount,
            'primary' => $primaryAnalysis,
            'secondary' => $secondaryAnalyses,
            'total_occurrences' => $totalOccurrences,
            'combined_density' => round($combinedDensity, 2),
            'stuffing_threshold' => self::STUFFING_THRESHOLD,
            'severe_stuffing_threshold' => self::SEVERE_STUFFING_THRESHOLD
        ];
    }

    /**
     * Analyze a specific keyword's usage in the paragraph.
     *
     * @param string $paragraph The normalized paragraph
     * @param string $keyword The keyword to analyze
     * @param int $wordCount Total word count
     * @return array Analysis results
     */
    private function analyzeKeyword(string $paragraph, string $keyword, int $wordCount): array
    {
        $normalizedKeyword = strtolower(trim($keyword));
        $keywordWordCount = str_word_count($normalizedKeyword);

        // Count occurrences
        $occurrences = substr_count($paragraph, $normalizedKeyword);

        // Calculate density based on whether the keyword is a phrase
        $density = 0;
        if ($wordCount > 0) {
            if ($keywordWordCount > 1) {
                // For multi-word keywords, calculate based on phrase occurrences
                $density = ($occurrences * $keywordWordCount / $wordCount) * 100;
            } else {
                // For single words, simple density calculation
                $density = ($occurrences / $wordCount) * 100;
            }
        }

        // Determine density status
        $densityStatus = 'optimal';
        if ($density === 0) {
            $densityStatus = 'none';
        } elseif ($density < SeoOptimiserConfig::OPTIMAL_DENSITY_MIN) {
            $densityStatus = 'low';
        } elseif ($density > self::SEVERE_STUFFING_THRESHOLD) {
            $densityStatus = 'severe_stuffing';
        } elseif ($density > self::STUFFING_THRESHOLD) {
            $densityStatus = 'stuffing';
        }

        if ($occurrences <= 1) {
            $densityStatus = 'none';
            $density = 0; // Reset density if only one occurrence
        }

        return [
            'keyword' => $keyword,
            'occurrences' => $occurrences,
            'density' => round($density, 2),
            'status' => $densityStatus
        ];
    }

    /**
     * Compute language-agnostic distribution metrics for keyword occurrences.
     */
    private function computeDistributionMetrics(string $paragraph, string $primaryKeyword, array $secondaryKeywords): array
    {
        $normalizedParagraph = strtolower($paragraph);
        $wordCount = max(1, str_word_count($paragraph)); // avoid division by zero

        // Build keyword list: primary + non-empty secondary
        $keywords = array_values(array_filter(
            array_merge([$primaryKeyword], $secondaryKeywords),
            static fn($k) => is_string($k) && $k !== ''
        ));

        $occurrencesPrimary = 0;
        $occurrencesSecondaryTotal = 0;
        $allDistances = [];
        $minDistance = null;
        $largestClusterSize = 0;

        foreach ($keywords as $idx => $keyword) {
            $normalizedKeyword = strtolower(trim($keyword));
            if ($normalizedKeyword === '') {
                continue;
            }

            $positions = $this->findAllPositions($normalizedParagraph, $normalizedKeyword);
            $occCount = count($positions);

            if ($idx === 0) {
                $occurrencesPrimary += $occCount;
            } else {
                $occurrencesSecondaryTotal += $occCount;
            }

            if ($occCount > 1) {
                $keywordLen = strlen($normalizedKeyword);
                $clusterSize = 1;
                for ($i = 0; $i < $occCount - 1; $i++) {
                    $distance = $positions[$i + 1] - ($positions[$i] + $keywordLen);
                    $allDistances[] = $distance;
                    if ($minDistance === null || $distance < $minDistance) {
                        $minDistance = $distance;
                    }

                    if ($distance < self::PROXIMITY_THRESHOLD_CHARS) {
                        $clusterSize++;
                    } else {
                        if ($clusterSize > $largestClusterSize) {
                            $largestClusterSize = $clusterSize;
                        }
                        $clusterSize = 1;
                    }
                }
                if ($clusterSize > $largestClusterSize) {
                    $largestClusterSize = $clusterSize;
                }
            }
        }

        $avgDistance = null;
        if (!empty($allDistances)) {
            $avgDistance = array_sum($allDistances) / count($allDistances);
        }

        $totalOccurrences = $occurrencesPrimary + $occurrencesSecondaryTotal;
        $repetitionRatioPer100Words = ($totalOccurrences / $wordCount) * 100;

        return [
            'occurrences_primary' => $occurrencesPrimary,
            'occurrences_secondary_total' => $occurrencesSecondaryTotal,
            'min_char_distance' => $minDistance,
            'avg_char_distance' => $avgDistance !== null ? round($avgDistance, 2) : null,
            'largest_cluster_size' => $largestClusterSize,
            'repetition_ratio_per_100_words' => round($repetitionRatioPer100Words, 2),
            'thresholds' => [
                'proximity_threshold_chars' => self::PROXIMITY_THRESHOLD_CHARS,
                'repetition_ratio_threshold' => self::REPETITION_RATIO_THRESHOLD,
            ],
        ];
    }

    /**
     * Compute boolean flags from distribution metrics.
     */
    private function computeDistributionFlags(array $metrics): array
    {
        $minDist = $metrics['min_char_distance'] ?? null;
        $largestClusterSize = (int)($metrics['largest_cluster_size'] ?? 0);
        $ratio = (float)($metrics['repetition_ratio_per_100_words'] ?? 0.0);

        $poorDistribution = false;
        if ($largestClusterSize >= 3) {
            $poorDistribution = true;
        }
        if ($minDist !== null && $minDist < self::PROXIMITY_THRESHOLD_CHARS) {
            $poorDistribution = true;
        }

        $highRepetitionRate = $ratio > self::REPETITION_RATIO_THRESHOLD;

        return [
            'poor_distribution' => $poorDistribution,
            'high_repetition_rate' => $highRepetitionRate,
        ];
    }

    /**
     * Find all positions of a substring in a string.
     *
     * @param string $haystack The string to search in
     * @param string $needle The substring to find
     * @return array Array of positions
     */
    private function findAllPositions(string $haystack, string $needle): array
    {
        $positions = [];
        $pos = 0;

        while (($pos = strpos($haystack, $needle, $pos)) !== false) {
            $positions[] = $pos;
            $pos += strlen($needle);
        }

        return $positions;
    }

    /**
     * Detect occurrences that are too close to each other.
     *
     * @param array $positions Array of positions
     * @param int $keywordLength Length of the keyword
     * @return array Pairs of positions that are too close
     */
    private function detectCloseOccurrences(array $positions, int $keywordLength): array
    {
        $closeOccurrences = [];
        $proximityThreshold = self::PROXIMITY_THRESHOLD_CHARS; // Characters between occurrences

        for ($i = 0; $i < count($positions) - 1; $i++) {
            $distance = $positions[$i + 1] - ($positions[$i] + $keywordLength);

            if ($distance < $proximityThreshold) {
                $closeOccurrences[] = [
                    'first_position' => $positions[$i],
                    'second_position' => $positions[$i + 1],
                    'distance' => $distance
                ];
            }
        }

        return $closeOccurrences;
    }





    /**
     * Calculate the score based on the performed analysis.
     *
     * @return float A score based on the keyword stuffing analysis
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        $data = $factorData['data'];
        $isStuffingDetected = $data['is_stuffing_detected'] ?? false;

        // If the paragraph is very short, give a neutral score
        if (($data['word_count'] ?? 0) < self::MIN_PARAGRAPH_WORDS) {
            return 0.5;
        }

        // If no stuffing detected, give a high score
        if (!$isStuffingDetected) {
            return 1.0;
        }

        // Get keyword analysis data
        $keywordAnalysis = $data['keyword_analysis'] ?? [];
        $primaryDensity = $keywordAnalysis['primary']['density'] ?? 0;
        $combinedDensity = $keywordAnalysis['combined_density'] ?? 0;

        // Calculate the score based on how severe the stuffing is
        $score = 1.0;

        // Primary keyword stuffing penalty
        if ($primaryDensity > self::SEVERE_STUFFING_THRESHOLD) {
            // Severe stuffing (lower score significantly)
            $score *= 0.3;
        } elseif ($primaryDensity > self::STUFFING_THRESHOLD) {
            // Moderate stuffing
            $excessFactor = ($primaryDensity - self::STUFFING_THRESHOLD) /
                (self::SEVERE_STUFFING_THRESHOLD - self::STUFFING_THRESHOLD);
            $score *= (0.7 - (0.4 * $excessFactor));
        }

        // Combined density penalty (if different from primary)
        if ($combinedDensity > $primaryDensity && $combinedDensity > self::STUFFING_THRESHOLD) {
            if ($combinedDensity > self::SEVERE_STUFFING_THRESHOLD) {
                $score *= 0.5; // Additional severe penalty
            } else {
                $excessFactor = ($combinedDensity - self::STUFFING_THRESHOLD) /
                    (self::SEVERE_STUFFING_THRESHOLD - self::STUFFING_THRESHOLD);
                $score *= (0.8 - (0.3 * $excessFactor));
            }
        }

        // Distribution-based penalties (language-agnostic)
        $distributionFlags = $data['distribution_flags'] ?? [];
        $distributionMetrics = $data['distribution_metrics'] ?? [];

        if (!empty($distributionFlags['poor_distribution'])) {
            $score *= 0.8;
            $largestCluster = (int)($distributionMetrics['largest_cluster_size'] ?? 0);
            if ($largestCluster >= 3) {
                $score *= max(0.5, 1 - 0.1 * ($largestCluster - 2));
            }
        }
        if (!empty($distributionFlags['high_repetition_rate'])) {
            $score *= 0.85;
        }

        // Ensure the score is between 0 and 1
        return max(0, min(1, $score));
    }

    /**
     * Generate suggestions based on keyword stuffing analysis.
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];

        // Get factor data for this operation
        $factorData = $this->value;

        $data = $factorData['data'];
        $isStuffingDetected = $data['is_stuffing_detected'] ?? false;

        // If the paragraph is very short, no suggestions needed
        if (($data['word_count'] ?? 0) < self::MIN_PARAGRAPH_WORDS) {
            return $activeSuggestions;
        }

        // If keyword stuffing is detected, add relevant suggestions
        if ($isStuffingDetected) {
            //$activeSuggestions[] = Suggestion::KEYWORD_STUFFING_DETECTED;

            // Get more detailed information for specific suggestions
            $keywordAnalysis = $data['keyword_analysis'] ?? [];
            $primaryDensity = $keywordAnalysis['primary']['density'] ?? 0;
            $combinedDensity = $keywordAnalysis['combined_density'] ?? 0;
            $distributionFlags = $data['distribution_flags'] ?? [];

            // Check for stuffing based on densities
            if ($primaryDensity > self::STUFFING_THRESHOLD || $combinedDensity > self::SEVERE_STUFFING_THRESHOLD) {
                $activeSuggestions[] = Suggestion::KEYWORD_STUFFING_IN_FIRST_PARAGRAPH;
            }

            // Poor distribution independent of language
            if (!empty($distributionFlags['poor_distribution']) || !empty($distributionFlags['high_repetition_rate'])) {
                $activeSuggestions[] = Suggestion::POOR_KEYWORD_DISTRIBUTION;
            }
        }

        return $activeSuggestions;
    }
}