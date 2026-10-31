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
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class PrimarySecondaryKeywordsValidationOperation
 *
 * This class is responsible for validating primary and secondary keywords.
 */
#[SeoMeta(
    name: 'Primary Secondary Keywords Validation',
    weight: WeightConfiguration::WEIGHT_PRIMARY_SECONDARY_KEYWORDS_VALIDATION_OPERATION,
    description: 'Validates primary and secondary keywords for a post, ensuring they are effectively used in content, titles, headings, and meta descriptions. Analyzes keyword presence, density, and plugin support for SEO optimization.',
)]
class PrimarySecondaryKeywordsValidationOperation extends Operation implements OperationInterface
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

        // Get post-content for analysis
        $content = $this->contentProvider->getContent($postId);
        $cleanContent = $this->contentProvider->cleanContent($content);

        // Basic content stats
        $wordCount = $this->contentProvider->getWordCount($cleanContent);

        // Analyze keyword presence and usage
        $keywordAnalysis = [];
        $keywordUsage = [];

        // Primary keyword analysis
        if (!empty($primaryKeyword)) {
            $keywordAnalysis['primary'] = $this->contentProvider->analyzeKeywordInContent($primaryKeyword, $content, $cleanContent);
            $keywordUsage['primary'] = [
                'keyword' => $primaryKeyword,
                'count' => $keywordAnalysis['primary']['count'],
                'density' => $keywordAnalysis['primary']['density'],
                'in_title' => $this->contentProvider->isKeywordInTitle($primaryKeyword, $postId),
                'in_headings' => $keywordAnalysis['primary']['in_headings'],
                'in_first_paragraph' => $keywordAnalysis['primary']['in_first_paragraph'],
                'in_meta_description' => $this->contentProvider->isKeywordInMetaDescription($primaryKeyword, $postId),
                'in_slug' => $this->contentProvider->isKeywordInSlug($primaryKeyword, $postId),
            ];
        }

        // Secondary keywords analysis
        $keywordAnalysis['secondary'] = [];
        $keywordUsage['secondary'] = [];

        foreach ($secondaryKeywords as $index => $keyword) {
            $keywordAnalysis['secondary'][$index] = $this->contentProvider->analyzeKeywordInContent($keyword, $content, $cleanContent);
            $keywordUsage['secondary'][] = [
                'keyword' => $keyword,
                'count' => $keywordAnalysis['secondary'][$index]['count'],
                'density' => $keywordAnalysis['secondary'][$index]['density'],
                'in_title' => $this->contentProvider->isKeywordInTitle($keyword, $postId),
                'in_headings' => $keywordAnalysis['secondary'][$index]['in_headings'],
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
            'content_analysis' => [
                'word_count' => $wordCount,
                'keyword_analysis' => $keywordAnalysis,
            ],
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
        $contentAnalysis = $this->value['content_analysis'] ?? [];
        $hasPluginSupport = $this->value['has_plugin_support'] ?? false;

        // 1. Primary keyword existence and implementation (40% of score)
        $primaryKeywordScore = $this->evaluatePrimaryKeyword($primaryKeyword, $keywordUsage['primary'] ?? []);

        // 2. Secondary keywords existence and implementation (30% of score)
        $secondaryKeywordsScore = $this->evaluateSecondaryKeywords($secondaryKeywords, $keywordUsage['secondary'] ?? []);

        // 3. Content relevance to keywords (20% of score)
        $contentRelevanceScore = $this->evaluateContentRelevance($contentAnalysis, $primaryKeyword, $secondaryKeywords);

        // 4. SEO plugin support (10% of score)
        $pluginSupportScore = $hasPluginSupport ? 1.0 : 0.7;

        // Calculate a weighted score
        // Start with the base score
        return ($primaryKeywordScore * 0.4) +
            ($secondaryKeywordsScore * 0.3) +
            ($contentRelevanceScore * 0.2) +
            ($pluginSupportScore * 0.1);
    }

    /**
     * Generate suggestions based on the factor data
     *
     * @return array An array of suggestions based on the factor data
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    public function suggestions(): array
    {
        $activeSuggestions = []; // Will hold all identified issue types

        $factorData = $this->value;

        $primaryKeyword = $factorData['primary_keyword'] ?? '';
        $secondaryKeywords = $factorData['secondary_keywords'] ?? [];
        $keywordUsage = $factorData['keyword_usage'] ?? [];
        $hasPluginSupport = $factorData['has_plugin_support'] ?? false;

        // Check if a primary keyword exists
        // If missing, suggest adding one to improve SEO focus
        if (empty($primaryKeyword)) {
            $activeSuggestions[] = Suggestion::MISSING_PRIMARY_KEYWORD;
        } else {
            // Check if primary keyword usage needs optimization
            // This checks if there's exactly one well-formatted primary keyword
            $wordCount = str_word_count($primaryKeyword);
            $isKeywordTooComplex = $wordCount > 5; // Primary keyword should be concise

            if ($isKeywordTooComplex || !$hasPluginSupport) {
                $activeSuggestions[] = Suggestion::POOR_KEYWORD_ASSIGNMENT;
            }
        }

        // Check if enough secondary keywords are present
        // Recommended: 2-3 secondary keywords for comprehensive coverage
        if (count($secondaryKeywords) < 2) {
            $activeSuggestions[] = Suggestion::INSUFFICIENT_SECONDARY_KEYWORDS;
        }

        // Check if the primary keyword is strategically placed
        // It should appear in the title, first paragraph, and at least one heading
        if (!empty($primaryKeyword) && !empty($keywordUsage['primary'])) {
            $placement = $keywordUsage['primary'];
            if (!($placement['in_title'] ?? false) ) {
                $activeSuggestions[] = Suggestion::KEYWORD_MISSING_IN_POST_TITLE;
            }
            if (!($placement['in_first_paragraph'] ?? false)) {
                $activeSuggestions[] = Suggestion::KEYWORD_MISSING_IN_FIRST_PARAGRAPH;
            }
            if (!($placement['in_headings'] ?? false)) {
                $activeSuggestions[] = Suggestion::PRIMARY_KEYWORD_MISSING_IN_HEADINGS;
            }
        }

        // Check if primary keyword density is optimal
        // Should be between 1% and 2.5% for good SEO balance
        if (!empty($keywordUsage['primary']['density'])) {
            $density = $keywordUsage['primary']['density'];
            if ($density < 1.0 || $density > 2.5) {
                $activeSuggestions[] = Suggestion::SUBOPTIMAL_KEYWORD_DENSITY;
            }
        }

        // Additional check for balance of secondary keywords
        // Ensures secondary keywords are used effectively to support the primary topic
        if (count($secondaryKeywords) >= 2) {
            $effectiveSecondaryKeywords = 0;
            foreach ($keywordUsage['secondary'] ?? [] as $secondaryUsage) {
                if (($secondaryUsage['count'] ?? 0) >= 2 &&
                    (($secondaryUsage['in_title'] ?? false) ||
                        ($secondaryUsage['in_headings'] ?? false))) {
                    $effectiveSecondaryKeywords++;
                }
            }

            if ($effectiveSecondaryKeywords < 2) {
                $activeSuggestions[] = Suggestion::INSUFFICIENT_SECONDARY_KEYWORDS;
            }
        }

        return $activeSuggestions;
    }

    /**
     * Evaluate primary keyword implementation
     *
     * @param string $primaryKeyword The primary keyword
     * @param array $usage Usage data for the primary keyword
     * @return float Score for primary keyword implementation
     */
    private function evaluatePrimaryKeyword(string $primaryKeyword, array $usage): float
    {
        // If no primary keyword is set
        if (empty($primaryKeyword)) {
            return 0;
        }

        $score = 0.5; // Start with 0.5 for having a primary keyword defined

        // Check for keyword presence in important elements
        if (!empty($usage)) {
            // Is keyword in title? (very important)
            if (!empty($usage['in_title'])) {
                $score += 0.15;
            }

            // Is the keyword in headings?
            if (!empty($usage['in_headings']) && $usage['in_headings'] > 0) {
                $score += 0.1;
            }

            // Is keyword in first paragraph? (important for SEO)
            if (!empty($usage['in_first_paragraph'])) {
                $score += 0.1;
            }

            // Is keyword in meta description?
            if (!empty($usage['in_meta_description'])) {
                $score += 0.05;
            }

            // Is the keyword in slug?
            if (!empty($usage['in_slug'])) {
                $score += 0.05;
            }

            // Check for keyword density (should be between 0.5% and 2.5%)
            if (!empty($usage['density'])) {
                $density = $usage['density'];

                if ($density > 0.5 && $density <= 2.5) {
                    $score += 0.05;
                } elseif ($density > 2.5) {
                    // Penalty for keyword stuffing
                    $score -= 0.05;
                }
            }
        }

        return min(1.0, $score);
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
                if (!empty($usage['in_title']) || !empty($usage['in_headings']) || !empty($usage['in_meta_description'])) {
                    $keywordScore += 1;
                }

                // Check for appropriate density
                if (!empty($usage['density']) && $usage['density'] > 0.2 && $usage['density'] <= 1.5) {
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
     * Evaluate content relevance to keywords
     *
     * @param array $contentAnalysis Content analysis data
     * @param string $primaryKeyword Primary keyword
     * @param array $secondaryKeywords Secondary keywords
     * @return float Score for content relevance
     */
    private function evaluateContentRelevance(array $contentAnalysis, string $primaryKeyword, array $secondaryKeywords): float
    {
        // Basic checks for content
        $wordCount = $contentAnalysis['word_count'] ?? 0;

        // Start with a base score depending on content length
        if ($wordCount >= 300) {
            $score = 0.7; // Good starting point for sufficient content
        } elseif ($wordCount >= 200) {
            $score = 0.5; // Minimal content
        } else {
            $score = 0.3; // Too little content
        }

        // Check keyword analysis data
        $keywordAnalysis = $contentAnalysis['keyword_analysis'] ?? [];

        // Check if the primary keyword exists
        if (empty($primaryKeyword)) {
            // Penalize if the primary keyword is not set
            $score -= 0.2;
        } else {
            // Check if the primary keyword is used sufficiently and naturally
            if (!empty($keywordAnalysis['primary']['has_sufficient_usage'])) {
                $score += 0.2;
            }
            
            // Check primary keyword relative presence in content
            if (!empty($keywordAnalysis['primary']['count']) && $wordCount > 0) {
                $primaryKeywordDensity = ($keywordAnalysis['primary']['count'] / $wordCount) * 100;
                
                // Optimal density is between 1-2%
                if ($primaryKeywordDensity >= 1 && $primaryKeywordDensity <= 2) {
                    $score += 0.1;
                } elseif ($primaryKeywordDensity > 2) {
                    // Penalize for keyword stuffing
                    $score -= 0.1;
                }
            }
        }

        // Check for secondary keywords usage
        $secondaryCount = count($secondaryKeywords);
        $secondaryWithGoodUsage = 0;

        if (!empty($keywordAnalysis['secondary'])) {
            foreach ($keywordAnalysis['secondary'] as $analysis) {
                if (!empty($analysis['has_sufficient_usage'])) {
                    $secondaryWithGoodUsage++;
                }
            }

            // Add bonus based on the percentage of well-used secondary keywords
            if ($secondaryCount > 0) {
                $score += 0.1 * ($secondaryWithGoodUsage / $secondaryCount);
            }
        }

        return min(1.0, $score);
    }
}