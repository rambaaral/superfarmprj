<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaDescriptionKeywords;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class PrimarySecondaryKeywordsValidationOperation
 *
 * This class is responsible for validating primary and secondary keywords.
 */
#[SeoMeta(
    name: 'Primary Secondary Keyword Check',
    weight: WeightConfiguration::WEIGHT_PRIMARY_SECONDARY_KEYWORD_CHECK_OPERATION,
    description: 'Analyzes the meta description to ensure both primary and secondary keywords are included naturally. Provides a ratio of keyword usage and points out missing terms to improve search relevance and keyword diversity.',
)]
class PrimarySecondaryKeywordCheckOperation extends Operation implements OperationInterface
{
    /**
     * Validates primary and secondary keywords for the given post-ID.
     *
     * @return array|null The validation results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get keywords set for this post
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);
        $secondaryKeywords = $this->contentProvider->getSecondaryKeywords($postId);

        $keywordUsage = [
            'primary' => [],
            'secondary' => [],
        ];

        // Primary keyword analysis
        if (!empty($primaryKeyword)) {
            $keywordUsage['primary'] = [
                'keyword' => $primaryKeyword,
                'in_meta_description' => $this->contentProvider->isKeywordInMetaDescription($primaryKeyword, $postId),
            ];
        }

        foreach ($secondaryKeywords as $keyword) {
            $keywordUsage['secondary'][] = [
                'keyword' => $keyword,
                'in_meta_description' => $this->contentProvider->isKeywordInMetaDescription($keyword, $postId),
            ];
        }

        // Check for plugin support for keyword optimization
        $hasPluginSupport = $this->contentProvider->detectKeywordPluginSupport();

        // Store the collected data
        return [
            'success' => true,
            'message' => __('Keywords validated successfully', 'beyondseo'),
            'primary_keyword' => $primaryKeyword,
            'secondary_keywords' => $secondaryKeywords,
            'keyword_usage' => $keywordUsage,
            'has_plugin_support' => $hasPluginSupport,
        ];
    }

    /**
     * Evaluate the operation value based on primary and secondary keywords' validation.
     *
     * @return float A score based on the keyword validation
     */
    public function calculateScore(): float
    {
        $primaryKeyword = $this->value['primary_keyword'] ?? '';
        $secondaryKeywords = $this->value['secondary_keywords'] ?? [];
        $keywordUsage = $this->value['keyword_usage'] ?? [];
        $hasPluginSupport = $this->value['has_plugin_support'] ?? false;

        $primaryKeywordScore = 0;

        // 1. Primary keyword existence and implementation (60% of score)
        if (!empty($primaryKeyword) && isset($keywordUsage['primary']['in_meta_description']) && $keywordUsage['primary']['in_meta_description'] ) {
            $primaryKeywordScore = 0.6;
        }

        // 2. Secondary keywords existence and implementation (30% of score)
        $secondaryKeywordsScore = $this->evaluateSecondaryKeywords($secondaryKeywords, $keywordUsage['secondary'] ?? []);

        // 4. SEO plugin support (10% of score)
        $pluginSupportScore = $hasPluginSupport ? 0.1 : 0;

        // Calculate a weighted score
        // Start with the base score
        return $primaryKeywordScore + ($secondaryKeywordsScore * 0.3) + $pluginSupportScore;
    }

    /**
     * Evaluate secondary keywords implementation
     *
     * @param array $secondaryKeywords The secondary keywords
     * @param array $usageData Usage data for secondary keywords
     * @return float Score for secondary keywords implementation
     */
    private function evaluateSecondaryKeywords(array $secondaryKeywords, array $usageData): float
    {
        // If no secondary keywords are set
        if (empty($secondaryKeywords)) {
            return 0;
        }

        $keywordCount = count($secondaryKeywords);

        // Start with a base score based on the number of keywords (optimal: 2-5)
        if ($keywordCount >= 2 && $keywordCount <= 5) {
            $score = 0.6;
        } elseif ($keywordCount === 1) {
            $score = 0.4;
        } elseif ($keywordCount > 5) {
            // Too many secondary keywords
            $score = 0.3;
        } else {
            $score = 0;
        }

        // If we have usage data for secondary keywords
        if (!empty($usageData)) {
            $keywordsWithGoodUsage = 0;

            foreach ($usageData as $usage) {
                $keywordScore = 0;

                // Check for presence in important elements
                if (!empty($usage['in_meta_description'])) {
                    $keywordScore += 1;
                }

                // If this keyword has good usage patterns
                if ($keywordScore >= 1) {
                    $keywordsWithGoodUsage++;
                }
            }

            // Bonus for keywords with good usage
            $percentWithGoodUsage = $keywordCount > 0 ? $keywordsWithGoodUsage / $keywordCount : 0;
            $score += $percentWithGoodUsage * 0.4;
        }

        return min(1.0, $score);
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
        $secondaryKeywords = $factorData['secondary_keywords'] ?? [];
        $keywordUsage = $factorData['keyword_usage'] ?? [];
        $hasPluginSupport = $factorData['has_plugin_support'] ?? false;

        if (empty($primaryKeyword)) {
            $suggestions[] = Suggestion::MISSING_PRIMARY_KEYWORD;
        } else {
            $wordCount = str_word_count($primaryKeyword);
            $isKeywordTooComplex = $wordCount > 5;

            if ($isKeywordTooComplex || !$hasPluginSupport) {
                $suggestions[] = Suggestion::OPTIMISE_PRIMARY_KEYWORD;
            }
            if (isset($keywordUsage['primary']['in_meta_description']) && !$keywordUsage['primary']['in_meta_description']) {
                $suggestions[] = Suggestion::OPTIMISE_PRIMARY_KEYWORD_USAGE;
            }
        }

        if (empty($secondaryKeywords)) {
            $suggestions[] = Suggestion::MISSING_SECONDARY_KEYWORDS;
        } else {
            $useCount = 0;
            foreach ($secondaryKeywords as $keyword) {
                foreach ($keywordUsage['secondary'] as $usage) {
                    if ($keyword === $usage['keyword'] && $usage['in_meta_description']) {
                        $useCount++;
                    }
                }
            }
            /** we should use at least one keyword in the description */
            if ($useCount === 0) {
                $suggestions[] = Suggestion::INSUFFICIENT_SECONDARY_KEYWORDS;
            }
            /** we shouldn't use more than 2 keywords in the description */
            if ($useCount > 2) {
                $suggestions[] = Suggestion::OPTIMISE_SECONDARY_KEYWORDS_USAGE;
            }
        }
        return $suggestions;
    }
}