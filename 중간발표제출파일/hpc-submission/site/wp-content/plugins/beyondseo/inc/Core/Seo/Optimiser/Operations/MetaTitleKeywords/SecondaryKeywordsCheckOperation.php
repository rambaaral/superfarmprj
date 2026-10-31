<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaTitleKeywords;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class SecondaryKeywordsCheckOperation
 *
 * This class is responsible for validating secondary keywords usage in meta-title.
 * It checks if secondary keywords exist, if they're used in the meta-title,
 * and provides suggestions for optimization.
 */
#[SeoMeta(
    name: 'Secondary Keywords Check',
    weight: WeightConfiguration::WEIGHT_SECONDARY_KEYWORDS_CHECK_OPERATION,
    description: 'Reviews meta titles for the presence of recommended secondary keywords. Calculates how many appear and whether they exceed length guidelines, offering suggestions to balance keyword diversity without overcrowding the title.',
)]
class SecondaryKeywordsCheckOperation extends Operation implements OperationInterface
{
    // Maximum recommended word count for secondary keywords
    private const MAX_KEYWORD_WORD_COUNT = 5;
    
    // Recommended minimum number of secondary keywords
    private const MIN_SECONDARY_KEYWORDS = 2;
    
    // Recommended maximum number of secondary keywords
    private const MAX_SECONDARY_KEYWORDS = 5;
    
    // Score weights
    private const SECONDARY_KEYWORDS_WEIGHT = 0.9;
    private const PLUGIN_SUPPORT_WEIGHT = 0.1;

    /**
     * Validates secondary keywords usage in meta-title for the given post-ID.
     *
     * @return array|null The validation results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get secondary keywords set for this post
        $secondaryKeywords = $this->contentProvider->getSecondaryKeywords($postId);

        $keywordUsage = [];

        // Extract title from the HTML
        $html = $this->contentProvider->getContent($postId);
        $metaTitle = $this->contentProvider->extractMetaTitleFromHTML($html);

        // If title extraction fails, fall back to WordPress meta-data
        if (empty($metaTitle)) {
            $metaTitle = $this->contentProvider->getFallbackMetaTitle($postId);
        }

        // Secondary keyword analysis
        foreach ($secondaryKeywords as $keyword) {
            // Check if the keyword is in the meta title
            $inMetaTitle = $this->contentProvider->isKeywordInMetaTitle($keyword, $postId);
            
            // Calculate keyword metrics
            $wordCount = str_word_count($keyword);
            $keywordPosition = $this->getKeywordPositionInTitle($keyword, $metaTitle);
            
            // Store the keyword usage data
            $keywordUsage[] = [
                'keyword' => $keyword,
                'in_meta_title' => $inMetaTitle,
                'position_in_title' => $keywordPosition,
                'word_count' => $wordCount,
                'is_optimal_length' => $wordCount <= self::MAX_KEYWORD_WORD_COUNT,
            ];
        }

        // Calculate overall metrics
        $keywordMetrics = [
            'total_count' => count($secondaryKeywords),
            'in_title_count' => count(array_filter($keywordUsage, fn($k) => $k['in_meta_title'] === true)),
            'optimal_length_count' => count(array_filter($keywordUsage, fn($k) => $k['is_optimal_length'])),
            'has_optimal_count' => count($secondaryKeywords) >= self::MIN_SECONDARY_KEYWORDS && 
                                  count($secondaryKeywords) <= self::MAX_SECONDARY_KEYWORDS,
        ];

        // Check for plugin support for keyword optimization
        $hasPluginSupport = $this->contentProvider->detectKeywordPluginSupport();

        // Store the collected data
        return [
            'success' => true,
            'message' => __('Secondary keywords validated successfully', 'beyondseo'),
            'secondary_keywords' => $secondaryKeywords,
            'keyword_usage' => $keywordUsage,
            'keyword_metrics' => $keywordMetrics,
            'has_plugin_support' => $hasPluginSupport,
        ];
    }

    /**
     * Evaluate the operation value based on secondary keywords' validation.
     *
     * @return float A score between 0.0 and 1.0 based on the keyword validation
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;
        $secondaryKeywords = $factorData['secondary_keywords'] ?? [];
        $keywordUsage = $factorData['keyword_usage'] ?? [];
        $keywordMetrics = $factorData['keyword_metrics'] ?? [];
        $hasPluginSupport = $factorData['has_plugin_support'] ?? false;

        // Initialize scores
        $keywordCountScore = 0;
        $keywordQualityScore = 0;
        $positionBonus = 0;

        // 1. Secondary keywords existence and implementation (90% of score)
        if (!empty($secondaryKeywords)) {
            // Base score for having secondary keywords (30% of secondary keywords weight)
            $keywordCountScore = self::SECONDARY_KEYWORDS_WEIGHT * 0.3;
            
            // Adjust score based on the optimal number of keywords
            if (isset($keywordMetrics['has_optimal_count']) && $keywordMetrics['has_optimal_count']) {
                $keywordCountScore = self::SECONDARY_KEYWORDS_WEIGHT * 0.4;
            }
            
            // Calculate the score based on keywords in meta-title (50% of secondary keywords weight)
            $inTitleCount = $keywordMetrics['in_title_count'] ?? 0;
            $totalCount = $keywordMetrics['total_count'] ?? 0;
            
            if ($totalCount > 0 && $inTitleCount > 0) {
                // Calculate a ratio of keywords used in the title, with diminishing returns after 1
                $titleRatio = min(1.0, $inTitleCount / min(2, $totalCount));
                $keywordQualityScore = self::SECONDARY_KEYWORDS_WEIGHT * 0.4 * $titleRatio;
                
                // Add position bonus if at least one keyword is near the beginning of the title
                $keywordsAtBeginning = 0;
                foreach ($keywordUsage as $usage) {
                    if (isset($usage['in_meta_title']) && $usage['in_meta_title'] && 
                        isset($usage['position_in_title']) && $usage['position_in_title'] <= 3) {
                        $keywordsAtBeginning++;
                    }
                }
                
                if ($keywordsAtBeginning > 0) {
                    $positionBonus = self::SECONDARY_KEYWORDS_WEIGHT * 0.1;
                }
            }
        }

        // 2. SEO plugin support (10% of score)
        $pluginSupportScore = $hasPluginSupport ? self::PLUGIN_SUPPORT_WEIGHT : 0;

        // Calculate final-score with all components
        $secondaryKeywordsScore = $keywordCountScore + $keywordQualityScore + $positionBonus;
        return min(1.0, $secondaryKeywordsScore + $pluginSupportScore);
    }

    /**
     * Get suggestions for the operation based on the provided parameters.
     *
     * @return array An array of suggestion issue types
     */
    public function suggestions(): array
    {
        // Initialize suggestions
        $suggestions = [];

        $factorData = $this->value;
        $secondaryKeywords = $factorData['secondary_keywords'] ?? [];
        $keywordUsage = $factorData['keyword_usage'] ?? [];
        $keywordMetrics = $factorData['keyword_metrics'] ?? [];

        // Check if secondary keywords exist
        if (empty($secondaryKeywords)) {
            $suggestions[] = Suggestion::MISSING_SECONDARY_KEYWORDS;
            return $suggestions;
        }
        
        // Check if there are enough secondary keywords
        $totalCount = $keywordMetrics['total_count'] ?? count($secondaryKeywords);
        if ($totalCount < self::MIN_SECONDARY_KEYWORDS) {
            $suggestions[] = Suggestion::INSUFFICIENT_SECONDARY_KEYWORDS;
        } elseif ($totalCount > self::MAX_SECONDARY_KEYWORDS) {
            $suggestions[] = Suggestion::TOO_MANY_SECONDARY_KEYWORDS;
        }
        
        // Check if any secondary keyword is used in the meta-title
        $inTitleCount = $keywordMetrics['in_title_count'] ?? 0;
        if ($inTitleCount === 0) {
            $suggestions[] = Suggestion::SECONDARY_KEYWORD_MISSING_IN_META_TITLE;
        } elseif ($inTitleCount < min(2, $totalCount)) {
            $suggestions[] = Suggestion::INSUFFICIENT_SECONDARY_KEYWORDS;
        }
        
        // Check individual keywords for optimization opportunities
        $complexKeywordsCount = 0;
        foreach ($keywordUsage as $usage) {
            if (isset($usage['is_optimal_length']) && !$usage['is_optimal_length']) {
                $complexKeywordsCount++;
            }
        }
        
        // Suggest optimization if more than half of keywords are too complex
        if ($complexKeywordsCount > 0 && $complexKeywordsCount >= ceil($totalCount / 2)) {
            $suggestions[] = Suggestion::OPTIMISE_SECONDARY_KEYWORD;
        }
        
        // Check keyword distribution in meta-title
        $keywordsAtBeginning = 0;
        foreach ($keywordUsage as $usage) {
            if (isset($usage['in_meta_title']) && $usage['in_meta_title'] && 
                isset($usage['position_in_title']) && $usage['position_in_title'] <= 3) {
                $keywordsAtBeginning++;
            }
        }
        
        // Suggest better distribution if no keywords are near the beginning
        if ($inTitleCount > 0 && $keywordsAtBeginning === 0) {
            $suggestions[] = Suggestion::POOR_KEYWORD_DISTRIBUTION;
        }

        return $suggestions;
    }
    
    /**
     * Determine the position of the keyword in the meta-title.
     * 
     * @param string $keyword The keyword to check
     * @param string $metaTitle The meta-title content
     * @return int The position of the keyword in the title (-1 if not found)
     */
    private function getKeywordPositionInTitle(string $keyword, string $metaTitle): int
    {
        if (empty($keyword) || empty($metaTitle)) {
            return -1;
        }
        
        // Convert both to lowercase for case-insensitive comparison
        $lowerKeyword = mb_strtolower($keyword);
        $lowerTitle = mb_strtolower($metaTitle);
        
        // Find position of keyword in title
        $position = mb_strpos($lowerTitle, $lowerKeyword);
        
        if ($position === false) {
            return -1;
        }
        
        // Count words before the keyword to determine position
        $titleBeforeKeyword = mb_substr($lowerTitle, 0, $position);
        return str_word_count($titleBeforeKeyword);
    }
}