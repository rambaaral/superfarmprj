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
 * Class PrimaryKeywordCheckOperation
 *
 * This class is responsible for validating primary keyword usage in meta-title.
 * It checks if the primary keyword exists, if it's used in the meta-title,
 * and provides suggestions for optimization.
 */
#[SeoMeta(
    name: 'Primary Keyword Check',
    weight: WeightConfiguration::WEIGHT_PRIMARY_KEYWORD_CHECK_OPERATION,
    description: 'Checks if the primary keyword appears in the meta title and ensures it meets word count guidelines. Provides feedback on placement and plugin support to optimize title relevance and user engagement.',
)]
class PrimaryKeywordCheckOperation extends Operation implements OperationInterface
{
    // Maximum recommended word count for the primary keyword
    private const MAX_KEYWORD_WORD_COUNT = 5;
    
    // Score weights
    private const KEYWORD_IN_TITLE_WEIGHT = 0.9;
    private const PLUGIN_SUPPORT_WEIGHT = 0.1;

    /**
     * Validates primary keyword usage in meta-title for the given post-ID.
     *
     * @return array|null The validation results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get a primary keyword set for this post
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);

        $keywordUsage = [];
        $keywordMetrics = [];

        // Primary keyword analysis
        if (!empty($primaryKeyword)) {
            // Check if keyword is in meta title
            $inMetaTitle = $this->contentProvider->isKeywordInMetaTitle($primaryKeyword, $postId);

            // Extract title from the HTML
            $html = $this->contentProvider->getContent($postId);
            $metaTitle = $this->contentProvider->extractMetaTitleFromHTML($html);

            // If title extraction fails, fall back to WordPress meta-data
            if (empty($metaTitle)) {
                $metaTitle = $this->contentProvider->getFallbackMetaTitle($postId);
            }
            
            // Calculate keyword metrics
            $wordCount = str_word_count($primaryKeyword);
            $keywordPosition = $this->getKeywordPositionInTitle($primaryKeyword, $metaTitle);
            
            $keywordUsage = [
                'in_meta_title' => $inMetaTitle,
                'position_in_title' => $keywordPosition,
            ];
            
            $keywordMetrics = [
                'word_count' => $wordCount,
                'is_optimal_length' => $wordCount <= self::MAX_KEYWORD_WORD_COUNT,
            ];
        }

        // Check for plugin support for keyword optimization
        $hasPluginSupport = $this->contentProvider->detectKeywordPluginSupport();

        // Store the collected data
        return [
            'success' => true,
            'message' => __('Primary keyword validated successfully', 'beyondseo'),
            'primary_keyword' => $primaryKeyword,
            'meta_title' => $metaTitle ?? '',
            'keyword_usage' => $keywordUsage,
            'keyword_metrics' => $keywordMetrics,
            'has_plugin_support' => $hasPluginSupport,
        ];
    }

    /**
     * Evaluate the operation value based on primary keyword validation.
     *
     * @return float A score between 0.0 and 1.0 based on the keyword validation
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;
        $primaryKeyword = $factorData['primary_keyword'] ?? '';
        $keywordUsage = $factorData['keyword_usage'] ?? [];
        $keywordMetrics = $factorData['keyword_metrics'] ?? [];
        $hasPluginSupport = $factorData['has_plugin_support'] ?? false;

        // Initialize scores
        $primaryKeywordScore = 0;
        $positionBonus = 0;
        $lengthBonus = 0;

        // 1. Primary keyword existence and implementation (90% of score)
        if (!empty($primaryKeyword)) {
            // Base score for having a primary keyword (1/3 of the keyword weight)
            $primaryKeywordScore = self::KEYWORD_IN_TITLE_WEIGHT * 0.33;
            
            // Bonus for keyword in meta-title (additional 4/9 of the keyword weight)
            if (isset($keywordUsage['in_meta_title']) && $keywordUsage['in_meta_title']) {
                $primaryKeywordScore += self::KEYWORD_IN_TITLE_WEIGHT * 0.44;
                
                // Position bonus - higher score if the keyword is at the beginning of the title
                $position = $keywordUsage['position_in_title'] ?? -1;
                if ($position === 0) {
                    $positionBonus = self::KEYWORD_IN_TITLE_WEIGHT * 0.11; // In the very beginning
                } elseif ($position > 0 && $position <= 3) {
                    $positionBonus = self::KEYWORD_IN_TITLE_WEIGHT * 0.06; // Near the beginning
                }
            }
            
            // Length bonus - optimal keyword length (remaining 1/9 of the keyword weight)
            if (isset($keywordMetrics['is_optimal_length']) && $keywordMetrics['is_optimal_length']) {
                $lengthBonus = self::KEYWORD_IN_TITLE_WEIGHT * 0.11;
            }
        }

        // 2. SEO plugin support (10% of score)
        $pluginSupportScore = $hasPluginSupport ? self::PLUGIN_SUPPORT_WEIGHT : 0;

        // Calculate the final score with all components
        return min(1.0, $primaryKeywordScore + $positionBonus + $lengthBonus + $pluginSupportScore);
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
        $primaryKeyword = $factorData['primary_keyword'] ?? '';
        $keywordUsage = $factorData['keyword_usage'] ?? [];
        $keywordMetrics = $factorData['keyword_metrics'] ?? [];

        if (empty($primaryKeyword)) {
            $suggestions[] = Suggestion::MISSING_PRIMARY_KEYWORD;
            return $suggestions;
        }
        
        // Check keyword complexity
        $wordCount = $keywordMetrics['word_count'] ?? str_word_count($primaryKeyword);
        if ($wordCount > self::MAX_KEYWORD_WORD_COUNT) {
            $suggestions[] = Suggestion::OPTIMISE_PRIMARY_KEYWORD;
        }
        
        // Check keyword placement in meta title
        if (isset($keywordUsage['in_meta_title']) && !$keywordUsage['in_meta_title']) {
            $suggestions[] = Suggestion::KEYWORD_MISSING_IN_META_TITLE;
        } elseif (isset($keywordUsage['in_meta_title']) && $keywordUsage['in_meta_title'] === true) {
            // Check keyword position in the title
            $position = $keywordUsage['position_in_title'] ?? -1;
            if ($position > 3) {
                $suggestions[] = Suggestion::POOR_KEYWORD_PLACEMENT_IN_META_TITLE;
            }
        }

        return $suggestions;
    }
    
    /**
     * Determine the position of the keyword in the meta-title.
     * 
     * @param string $keyword The primary keyword to check
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