<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\AssignKeywords;

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
 * Class KeywordMappingContentOperation
 *
 * This class is responsible for verifying keyword assignment across relevant pages
 * and detecting/preventing keyword cannibalization.
 */
#[SeoMeta(
    name: 'Keyword Mapping Content',
    weight: WeightConfiguration::WEIGHT_KEYWORD_MAPPING_CONTENT_OPERATION,
    description: 'Performs keyword mapping analysis across site content, detecting cannibalization and coverage issues.',
)]
class KeywordMappingContentOperation extends Operation implements OperationInterface
{
    /**
     * Performs keyword mapping analysis across site content.
     *
     * @return array|null The analysis results
     */
    public function run(): ?array
    {
        try {
            $postId = $this->postId;
            
            // Validate post ID
            if (!$postId || $postId <= 0) {
                return $this->getErrorResponse(__('Invalid post ID provided', 'beyondseo'));
            }

            // Get all published posts and pages with error handling
            try {
                $allContent = $this->contentProvider->fetchPosts();
            } catch (Throwable $e) {
                return $this->getErrorResponse(__('Failed to fetch content for analysis', 'beyondseo'));
            }

            if (empty($allContent)) {
                return $this->getErrorResponse(__('No published content found', 'beyondseo'));
            }

            // Extract keyword data for all content with error handling
            try {
                $keywordMapping = $this->contentProvider->buildKeywordMap($allContent) ?? [];
            } catch (Throwable $e) {
                return $this->getErrorResponse(__('Failed to build keyword mapping', 'beyondseo'));
            }

            // Analyze for cannibalization issues with error handling
            $cannibalizationIssues = [];
            try {
                $cannibalizationIssues = $this->contentProvider->detectCannibalizationIssues($keywordMapping);
            } catch (Throwable $e) {
                // Continue with empty array
            }

            // Analyze keyword coverage with error handling
            $coverageAnalysis = [];
            try {
                $coverageAnalysis = $this->contentProvider->analyzeKeywordCoverage($keywordMapping);
            } catch (Throwable $e) {
                // Continue with empty array
            }

            // Generate topic clusters with error handling
            $topicClusters = [];
            try {
                $topicClusters = $this->contentProvider->generateTopicClusters($keywordMapping);
            } catch (Throwable $e) {
                // Continue with empty array
            }

            // Get current post data with error handling
            $currentPostData = $this->getCurrentPostData($postId, $cannibalizationIssues);

            // Prepare the results
            return [
                'success' => true,
                'message' => __('Keyword mapping analysis completed', 'beyondseo'),
                'keyword_mapping' => $keywordMapping,
                'cannibalization_issues' => $cannibalizationIssues,
                'coverage_analysis' => $coverageAnalysis,
                'topic_clusters' => $topicClusters,
                'current_post' => $currentPostData
            ];
        } catch (Throwable $e) {
            return $this->getErrorResponse(__('Critical error during keyword mapping analysis', 'beyondseo'));
        }
    }

    /**
     * Get current post data with error handling
     *
     * @param int $postId
     * @param array $cannibalizationIssues
     * @return array
     */
    private function getCurrentPostData(int $postId, array $cannibalizationIssues): array
    {
        return [
            'id' => $postId,
            'primary_keyword' => $this->contentProvider->getPrimaryKeyword($postId) ?? '',
            'secondary_keywords' => $this->contentProvider->getSecondaryKeywords($postId) ?? [],
            'cannibalization_conflicts' => $this->contentProvider->findCannibalizationConflictsForPost($postId, $cannibalizationIssues) ?? []
        ];
    }

    /**
     * Get standardized error response
     *
     * @param string $message
     * @return array
     */
    private function getErrorResponse(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'keyword_mapping' => [],
            'cannibalization_issues' => [],
            'coverage_analysis' => [],
            'topic_clusters' => [],
            'current_post' => [
                'id' => $this->postId ?? 0,
                'primary_keyword' => '',
                'secondary_keywords' => [],
                'cannibalization_conflicts' => []
            ]
        ];
    }

    /**
     * Evaluate the operation value based on keyword mapping and cannibalization analysis.
     *
     * @return float A score based on the keyword mapping analysis
     */
    public function calculateScore(): float
    {
        // Validate that value exists and is an array
        if (!$this->value) {
            return 0.0;
        }

        // Extract data for scoring with safe defaults
        $keywordMapping = $this->value['keyword_mapping'] ?? [];
        $cannibalizationIssues = $this->value['cannibalization_issues'] ?? [];
        $coverageAnalysis = $this->value['coverage_analysis'] ?? [];
        $topicClusters = $this->value['topic_clusters'] ?? [];
        $currentPostData = $this->value['current_post'] ?? [];

        // Ensure all extracted data are arrays
        $keywordMapping = is_array($keywordMapping) ? $keywordMapping : [];
        $cannibalizationIssues = is_array($cannibalizationIssues) ? $cannibalizationIssues : [];
        $coverageAnalysis = is_array($coverageAnalysis) ? $coverageAnalysis : [];
        $topicClusters = is_array($topicClusters) ? $topicClusters : [];
        $currentPostData = is_array($currentPostData) ? $currentPostData : [];

        // 1. Keyword distribution score (30% of total)
        $distributionScore = $this->evaluateKeywordDistribution($keywordMapping, $coverageAnalysis) ?? 0.0;

        // 2. Cannibalization score (40% of the total)
        $cannibalizationScore = $this->evaluateCannibalization($cannibalizationIssues, $currentPostData) ?? 0.0;

        // 3. Topic clustering score (20% of total)
        $topicClusteringScore = $this->evaluateTopicClustering($topicClusters) ?? 0.0;

        // 4. Current page optimization score (10% of the total)
        $currentPageScore = $this->evaluateCurrentPageOptimization($currentPostData) ?? 0.0;

        // Calculate weighted final score
        $finalScore = ($distributionScore * 0.3) +
            ($cannibalizationScore * 0.4) +
            ($topicClusteringScore * 0.2) +
            ($currentPageScore * 0.1);

        // Ensure score is within valid range
        return max(0.0, min(1.0, $finalScore)) ?? 0.0;
    }

    /**
     * Evaluate keyword distribution across the site
     *
     * @param array $keywordMapping Keyword mapping data
     * @param array $coverageAnalysis Coverage analysis data
     * @return float Distribution score (0-1)
     */
    private function evaluateKeywordDistribution(array $keywordMapping, array $coverageAnalysis): float
    {
        try {
            // Start with a base score
            $score = 0.5;

            // Validate input arrays
            if (!$keywordMapping || !$coverageAnalysis) {
                return $score;
            }

            // If we don't have enough pages for a meaningful analysis
            if (count($keywordMapping) < SeoOptimiserConfig::MIN_PAGES_FOR_COMPLETE_ANALYSIS) {
                return $score;
            }
        } catch (Throwable $e) {
            return 0.5; // Return base score on error
        }

        // Check keyword diversity (higher is better, but not too high)
        if (isset($coverageAnalysis['keyword_diversity_score'])) {
            $diversity = $coverageAnalysis['keyword_diversity_score'];

            if ($diversity >= 1.0 && $diversity <= 2.0) {
                // Ideal range - each keyword used on 1-2 pages on average
                $score += 0.2;
            } elseif ($diversity > 2.0 && $diversity <= 3.0) {
                // Acceptable but not ideal
                $score += 0.1;
            } elseif ($diversity > 3.0) {
                // Too much repetition of keywords
                $score -= 0.1;
            }
        }

        // Check for overused keywords (penalize if too many)
        if (isset($coverageAnalysis['overused_keywords']) && is_array($coverageAnalysis['overused_keywords'])) {
            $overusedCount = count($coverageAnalysis['overused_keywords']);
            $totalKeywords = $coverageAnalysis['unique_keywords'] ?? 0;
            
            // Prevent division by zero
            if ($totalKeywords > 0) {
                $overusedPercentage = ($overusedCount / $totalKeywords) * 100;

                if ($overusedPercentage > 20) {
                    // Too many overused keywords
                    $score -= 0.15;
                } elseif ($overusedPercentage > 10) {
                    // Some overused keywords
                    $score -= 0.05;
                }
            }
        }

        // Check for keyword gaps (penalize if too many)
        if (isset($coverageAnalysis['keyword_gaps'])) {
            $gapsCount = count($coverageAnalysis['keyword_gaps']);

            if ($gapsCount > 5) {
                // Many keyword gaps
                $score -= 0.1;
            } elseif ($gapsCount <= 2) {
                // Few keyword gaps - good coverage
                $score += 0.1;
            }
        }

        // Check if most pages have primary keywords assigned
        $pagesWithPrimaryKeywords = array_filter($keywordMapping, static function ($entry) {
            return is_array($entry) && !empty($entry['primary_keyword']);
        });

        $keywordMappingCount = count($keywordMapping);
        $primaryKeywordPercentage = $keywordMappingCount > 0 
            ? (count($pagesWithPrimaryKeywords) / $keywordMappingCount) * 100 
            : 0;

        if ($primaryKeywordPercentage >= 90) {
            // Excellent primary keyword coverage
            $score += 0.2;
        } elseif ($primaryKeywordPercentage >= 70) {
            // Good primary keyword coverage
            $score += 0.1;
        } elseif ($primaryKeywordPercentage < 50) {
            // Poor primary keyword coverage
            $score -= 0.1;
        }

        // Cap score between 0 and 1
        return max(0, min(1, $score));
    }

    /**
     * Evaluate cannibalization issues
     *
     * @param array $cannibalizationIssues Cannibalization issues
     * @param array $currentPostData Current post data
     * @return float Cannibalization score (0-1)
     */
    private function evaluateCannibalization(array $cannibalizationIssues, array $currentPostData): float
    {
        try {
            // Validate input arrays
            if (!$cannibalizationIssues || !$currentPostData) {
                return 1.0; // Perfect score if no valid data to analyze
            }

            // Start with a perfect score and deduct for issues
            $score = 1.0;
        } catch (Throwable $e) {
            return 1.0; // Return perfect score on error
        }

        // Count issues by severity
        $highSeverityIssues = array_filter($cannibalizationIssues, static function ($issue) {
            return is_array($issue) && isset($issue['severity']) && $issue['severity'] === 'high';
        });

        $mediumSeverityIssues = array_filter($cannibalizationIssues, static function ($issue) {
            return is_array($issue) && isset($issue['severity']) && $issue['severity'] === 'medium';
        });

        // Deduct for high-severity issues (primary keyword conflicts)
        $score -= count($highSeverityIssues) * 0.15;

        // Deduct for medium severity issues (semantic similarity, keyword overuse)
        $score -= count($mediumSeverityIssues) * 0.05;

        // Check if the current post has conflicts
        if (!empty($currentPostData['cannibalization_conflicts'])) {
            // Additional penalty for the current post having conflicts
            $score -= count($currentPostData['cannibalization_conflicts']) * 0.1;
        }

        // Cap score between 0 and 1
        return max(0, min(1, $score));
    }

    /**
     * Evaluate topic clustering
     *
     * @param array $topicClusters Topic clusters data
     * @return float Topic clustering score (0-1)
     */
    private function evaluateTopicClustering(array $topicClusters): float
    {
        try {

            // Start with a base score
            $score = 0.5;

            // If no clusters found, return the base score
            if (empty($topicClusters)) {
                return $score;
            }
        } catch (Throwable $e) {
            return 0.5; // Return base score on error
        }

        // Evaluate cluster quality
        $clusterQualityScores = [];

        foreach ($topicClusters as $cluster) {
            if (!is_array($cluster)) {
                continue;
            }
            
            $supportingPages = $cluster['supporting_pages'] ?? [];
            $relatedKeywords = $cluster['related_keywords'] ?? [];
            
            $supportingPagesCount = is_array($supportingPages) ? count($supportingPages) : 0;
            $relatedKeywordsCount = is_array($relatedKeywords) ? count($relatedKeywords) : 0;

            // Calculate the quality score for this cluster
            $clusterScore = 0.5;

            // Ideal cluster has 3-7 supporting pages
            if ($supportingPagesCount >= 3 && $supportingPagesCount <= 7) {
                $clusterScore += 0.3;
            } elseif ($supportingPagesCount > 7) {
                $clusterScore += 0.1; // Too many pages
            } elseif ($supportingPagesCount >= 1) {
                $clusterScore += 0.1; // At least some supporting pages
            }

            // Should have some related keywords
            if ($relatedKeywordsCount >= 3) {
                $clusterScore += 0.2;
            } elseif ($relatedKeywordsCount >= 1) {
                $clusterScore += 0.1;
            }

            $clusterQualityScores[] = $clusterScore;
        }

        // Calculate average cluster quality with division by zero protection
        $clusterCount = count($clusterQualityScores);
        $averageClusterQuality = $clusterCount > 0 
            ? array_sum($clusterQualityScores) / $clusterCount 
            : 0;

        // Adjust score based on cluster quality and count
        $score = ($averageClusterQuality * 0.7) + (min(1, count($topicClusters) / 5) * 0.3);

        // Cap score between 0 and 1
        return max(0, min(1, $score));
    }

    /**
     * Evaluate current page optimization
     *
     * @param array $currentPostData Current post data
     * @return float Current page score (0-1)
     */
    private function evaluateCurrentPageOptimization(array $currentPostData): float
    {
        try {
            // Validate input array
            if (empty($currentPostData)) {
                return 0.5; // Base score if invalid data
            }

            // Start with a base score
            $score = 0.5;
        } catch (Throwable $e) {
            return 0.5; // Return base score on error
        }

        // Check if current post has primary keyword
        if (!empty($currentPostData['primary_keyword'])) {
            $score += 0.2;

            // Check if it has secondary keywords
            if (!empty($currentPostData['secondary_keywords'])) {
                $score += 0.1;

                // Bonus for having the right number of secondary keywords (2-4)
                $secondaryCount = count($currentPostData['secondary_keywords']);
                if ($secondaryCount >= 2 && $secondaryCount <= 4) {
                    $score += 0.1;
                }
            }
        } else {
            // No primary keyword is a significant issue
            $score -= 0.3;
        }

        // Check for cannibalization conflicts
        if (!empty($currentPostData['cannibalization_conflicts'])) {
            // Deduct based on conflict count and type
            $conflicts = $currentPostData['cannibalization_conflicts'];
            $conflicts = is_array($conflicts) ? $conflicts : [];
            
            $highSeverityConflicts = array_filter($conflicts, static function ($conflict) {
                return is_array($conflict) && isset($conflict['type']) && $conflict['type'] === 'primary_keyword_conflict';
            });

            $score -= count($highSeverityConflicts) * 0.2;
            $score -= (count($conflicts) - count($highSeverityConflicts)) * 0.1;
        } else {
            // Bonus for no conflicts
            $score += 0.1;
        }

        // Cap score between 0 and 1
        return max(0, min(1, $score));
    }

    /**
     * Generate suggestions based on keyword mapping analysis.
     *
     * @return array List of suggestions based on analysis
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    public function suggestions(): array
    {
        try {
            $activeSuggestions = []; // Will hold all identified issue types

            // Validate that value exists and is an array
            if (empty($this->value)) {
                return $activeSuggestions;
            }

            $factorData = $this->value;

            // Extract data for analysis with safe defaults
            $keywordMapping = $factorData['keyword_mapping'] ?? [];
            $cannibalizationIssues = $factorData['cannibalization_issues'] ?? [];
            $coverageAnalysis = $factorData['coverage_analysis'] ?? [];
            $topicClusters = $factorData['topic_clusters'] ?? [];
            $currentPostData = $factorData['current_post'] ?? [];

            // Ensure all extracted data are arrays
            $keywordMapping = is_array($keywordMapping) ? $keywordMapping : [];
            $cannibalizationIssues = is_array($cannibalizationIssues) ? $cannibalizationIssues : [];
            $coverageAnalysis = is_array($coverageAnalysis) ? $coverageAnalysis : [];
            $topicClusters = is_array($topicClusters) ? $topicClusters : [];
            $currentPostData = is_array($currentPostData) ? $currentPostData : [];
        } catch (Throwable $e) {
            return [];
        }

        // 1. Check for a missing keyword map
        $pagesWithPrimaryKeywords = array_filter($keywordMapping, static function ($entry) {
            return is_array($entry) && !empty($entry['primary_keyword']);
        });

        $keywordMappingCount = count($keywordMapping);
        $primaryKeywordPercentage = $keywordMappingCount > 0
            ? (count($pagesWithPrimaryKeywords) / $keywordMappingCount) * 100
            : 0;

        if ($primaryKeywordPercentage < 70 || count($keywordMapping) < SeoOptimiserConfig::MIN_PAGES_FOR_COMPLETE_ANALYSIS) {
            $activeSuggestions[] = Suggestion::MISSING_KEYWORD_MAP;
        }

        // 2. Check for keyword cannibalization
        if (!empty($cannibalizationIssues)) {
            // Focus especially on high-severity issues and current post-conflicts
            $highSeverityIssues = array_filter($cannibalizationIssues, static function ($issue) {
                return is_array($issue) && isset($issue['severity']) && $issue['severity'] === 'high';
            });

            $hasCurrentPostConflicts = !empty($currentPostData['cannibalization_conflicts']);

            if (!empty($highSeverityIssues) || count($cannibalizationIssues) > 2 || $hasCurrentPostConflicts) {
                $activeSuggestions[] = Suggestion::KEYWORD_CANNIBALIZATION;
            }
        }

        // 3. Check keyword distribution
        if (isset($coverageAnalysis['keyword_diversity_score'])) {
            $diversity = $coverageAnalysis['keyword_diversity_score'];
            $overusedKeywords = $coverageAnalysis['overused_keywords'] ?? [];
            $overusedCount = count($overusedKeywords);
            $totalKeywords = $coverageAnalysis['unique_keywords'] ?? 0;
            $overusedPercentage = $totalKeywords > 0 ? ($overusedCount / $totalKeywords) * 100 : 0;

            if ($diversity > 3.0 || $overusedPercentage > 20) {
                $activeSuggestions[] = Suggestion::IMPROVE_KEYWORD_DISTRIBUTION;
            }
        }

        // 4. Check for keyword coverage gaps
        if (isset($coverageAnalysis['keyword_gaps'])) {
            $gapsCount = count($coverageAnalysis['keyword_gaps']);

            if ($gapsCount > 3) {
                $activeSuggestions[] = Suggestion::MISSING_KEYWORD_COVERAGE;
            }
        }

        // 5. Check for weak topical authority
        $clusterQualityScores = [];

        if (!empty($topicClusters)) {
            foreach ($topicClusters as $cluster) {
                if (!is_array($cluster)) {
                    continue;
                }
                
                $supportingPages = $cluster['supporting_pages'] ?? [];
                $relatedKeywords = $cluster['related_keywords'] ?? [];
                
                $supportingPagesCount = is_array($supportingPages) ? count($supportingPages) : 0;
                $relatedKeywordsCount = is_array($relatedKeywords) ? count($relatedKeywords) : 0;

                // Calculate quality score similar to evaluateTopicClustering method
                $clusterScore = 0.5;

                if ($supportingPagesCount >= 3 && $supportingPagesCount <= 7) {
                    $clusterScore += 0.3;
                } elseif ($supportingPagesCount > 7) {
                    $clusterScore += 0.1;
                } elseif ($supportingPagesCount >= 1) {
                    $clusterScore += 0.1;
                }

                if ($relatedKeywordsCount >= 3) {
                    $clusterScore += 0.2;
                } elseif ($relatedKeywordsCount >= 1) {
                    $clusterScore += 0.1;
                }

                $clusterQualityScores[] = $clusterScore;
            }
        }

        $clusterScoreCount = count($clusterQualityScores);
        $averageClusterQuality = $clusterScoreCount > 0
            ? array_sum($clusterQualityScores) / $clusterScoreCount
            : 0;

        if ($averageClusterQuality < 0.7 || empty($topicClusters)) {
            $activeSuggestions[] = Suggestion::WEAK_TOPICAL_AUTHORITY;
        }

        return $activeSuggestions;
    }
}