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

/**
 * Class FirstParagraphKeywordCheckOperation
 *
 * This class is responsible for checking if the primary keyword is used in the first paragraph
 * of the content, which is a crucial SEO best practice for establishing topic relevance early.
 */
#[SeoMeta(
    name: 'First Paragraph Keyword Check',
    weight: WeightConfiguration::WEIGHT_FIRST_PARAGRAPH_KEYWORD_CHECK_OPERATION,
    description: 'Checks if the primary keyword is used in the first paragraph of the content. This is important for SEO as it helps search engines understand the main topic of the page early on.',
)]
class FirstParagraphKeywordCheckOperation extends Operation implements OperationInterface
{
    /**
     * Performs the first paragraph keyword usage analysis for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get the primary keyword for this post
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);

        if (empty($primaryKeyword)) {
            return [
                'success' => false,
                'message' => __('No primary keyword found for this post', 'beyondseo'),
                'has_keyword_in_first_paragraph' => false
            ];
        }

        // Get the content of the post
        $content = $this->contentProvider->getContent($postId, true);

        // Initialize the result structure
        $results = [
            'success' => true,
            'message' => __('First paragraph keyword analysis completed', 'beyondseo'),
            'primary_keyword' => $primaryKeyword
        ];

        // Use the content provider's first paragraph analysis method if available
        if (method_exists($this->contentProvider, 'analyzeFirstParagraphKeywordUsage')) {
            $firstParagraphAnalysis = $this->contentProvider->analyzeFirstParagraphKeywordUsage($primaryKeyword, $content);

            if (!empty($firstParagraphAnalysis)) {
                $results = array_merge($results, [
                    'first_paragraph_text' => $firstParagraphAnalysis['first_paragraph_text'] ?? '',
                    'word_count' => $firstParagraphAnalysis['word_count'] ?? 0,
                    'has_keyword_in_first_paragraph' => $firstParagraphAnalysis['has_primary_keyword'] ?? false,
                    'meets_threshold' => $firstParagraphAnalysis['meets_threshold'] ?? false
                ]);

                // Cache and return if we have successfully used the helper method
                if (isset($firstParagraphAnalysis['first_paragraph_text'], $firstParagraphAnalysis['has_primary_keyword'])) {
                    return $results;
                }
            }
        }

        // If we reach here, we need our fallback implementation
        $firstParagraphText = $this->contentProvider->extractFirstParagraph($content);
        $firstWords = $this->contentProvider->extractFirstWords($firstParagraphText, SeoOptimiserConfig::FIRST_PARAGRAPH_MAXIMUM_ANALYZED_WORDS);

        $normalizedKeyword = strtolower(trim($primaryKeyword));
        $normalizedFirstWords = strtolower($firstWords);

        $hasKeywordInFirstParagraph = str_contains($normalizedFirstWords, $normalizedKeyword);

        $wordCount = str_word_count($firstWords);
        $keywordPosition = $hasKeywordInFirstParagraph ?
            stripos($firstWords, $primaryKeyword) : null;

        $keywordPositionByWord = null;
        if ($keywordPosition !== null) {
            $textBeforeKeyword = substr($firstWords, 0, $keywordPosition);
            $keywordPositionByWord = str_word_count($textBeforeKeyword);
        }

        $positionPercentage = null;
        if ($keywordPositionByWord !== null && $wordCount > 0) {
            $positionPercentage = round(($keywordPositionByWord / $wordCount) * 100, 2);
        }

        $isOptimalPosition = $hasKeywordInFirstParagraph &&
            $keywordPositionByWord !== null &&
            $keywordPositionByWord <= SeoOptimiserConfig::FIRST_PARAGRAPH_GOOD_WORD_POSITION;

        return array_merge($results, [
            'first_paragraph_text' => $firstParagraphText,
            'analyzed_text' => $firstWords,
            'word_count' => $wordCount,
            'has_keyword_in_first_paragraph' => $hasKeywordInFirstParagraph,
            'keyword_position' => $keywordPosition,
            'keyword_position_by_word' => $keywordPositionByWord,
            'position_percentage' => $positionPercentage,
            'optimal_position' => $isOptimalPosition
        ]);
    }

    /**
     * Calculate the score based on whether the primary keyword is used in the first paragraph.
     *
     * @return float A score based on the keyword presence in the first paragraph
     */
    public function calculateScore(): float
    {
        // If we have meets_threshold, use that directly
        if (isset($this->value['meets_threshold'])) {
            return $this->value['meets_threshold'] ? 1.0 : 0.0;
        }

        // If the keyword is in the first paragraph, calculate a score based on the position
        if ($this->value['has_keyword_in_first_paragraph']) {
            $positionByWord = $this->value['keyword_position_by_word'] ?? null;

            // If positioned within the first 15 words, that's ideal (score 1.0)
            if ($positionByWord !== null && $positionByWord <= SeoOptimiserConfig::FIRST_PARAGRAPH_OPTIMAL_WORD_POSITION) {
                return 1.0;
            }

            // If positioned within the first 30 words, that's very good (score 0.9)
            if ($positionByWord !== null && $positionByWord <= SeoOptimiserConfig::FIRST_PARAGRAPH_GOOD_WORD_POSITION) {
                return 0.9;
            }

            // If positioned within the first 50 words, that's good (score 0.8)
            if ($positionByWord !== null && $positionByWord <= SeoOptimiserConfig::FIRST_PARAGRAPH_ACCEPTABLE_WORD_POSITION) {
                return 0.8;
            }

            // If positioned within the first 100 words, that's acceptable (score 0.7)
            if ($positionByWord !== null && $positionByWord <= 100) {
                return 0.7;
            }

            // If positioned within the first 150 words, that's still passing (score 0.6)
            if ($positionByWord !== null && $positionByWord <= SeoOptimiserConfig::FIRST_PARAGRAPH_MAXIMUM_ANALYZED_WORDS) {
                return 0.6;
            }

            // Keyword is present but position couldn't be determined
            return 0.5;
        }

        // Keyword is not in the first paragraph
        return 0;
    }

    /**
     * Generate suggestions if the primary keyword is missing from the first paragraph.
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];

        $factorData = $this->value;

        // Check if the keyword is missing from the first paragraph
        if (!$factorData['has_keyword_in_first_paragraph']) {
            $activeSuggestions[] = Suggestion::KEYWORD_MISSING_IN_FIRST_PARAGRAPH;
            return $activeSuggestions;
        }

        // If we have a position by word, check if it's suboptimal
        if (isset($factorData['keyword_position_by_word'])) {
            $keywordPositionByWord = $factorData['keyword_position_by_word'];
            if ($keywordPositionByWord > SeoOptimiserConfig::FIRST_PARAGRAPH_ACCEPTABLE_WORD_POSITION) {
                $activeSuggestions[] = Suggestion::LATE_KEYWORD_PLACEMENT_IN_FIRST_PARAGRAPH;
            }
        }
        // If we don't have a position but have a "meets_threshold" flag that's false, suggest improvement
        elseif (isset($factorData['meets_threshold']) && !$factorData['meets_threshold']) {
            $activeSuggestions[] = Suggestion::LATE_KEYWORD_PLACEMENT_IN_FIRST_PARAGRAPH;
        }

        return $activeSuggestions;
    }
}