<?php
/** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentReadability;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class ContentFormattingValidationOperation
 *
 * This class is responsible for validating content formatting:
 * - Proper use of headings (h1-h6) and their hierarchy
 * - Presence of bullet points (lists) where appropriate
 * - Short, readable paragraphs
 */
#[SeoMeta(
    name: 'Content Formatting Validation',
    weight: WeightConfiguration::WEIGHT_CONTENT_FORMATTING_VALIDATION_OPERATION,
    description: 'Validates content formatting for headings, paragraphs, and bullet points to ensure readability and SEO best practices.',
)]
class ContentFormattingValidationOperation extends Operation implements OperationInterface
{
    /**
     * Performs content formatting validation for the given post-ID.
     *
     * @return array|null The validation results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get full HTML content - using the method that can handle page builders, etc.
        $htmlContent = $this->contentProvider->getContent($postId);
        if (empty($htmlContent)) {
            return [
                'success' => false,
                'message' => __('Unable to retrieve content', 'beyondseo'),
            ];
        }

        // Get clean content for word counting
        $cleanContent = $this->contentProvider->cleanContent($htmlContent);
        $totalWordCount = $this->contentProvider->getWordCount($cleanContent);

        // Analyze content formatting
        $headingAnalysis = $this->contentProvider->analyzeHeadingStructure($htmlContent, $totalWordCount);
        $paragraphAnalysis = $this->contentProvider->analyzeParagraphsStructure($htmlContent);
        $bulletPointAnalysis = $this->contentProvider->analyzeBulletPointsStructure($htmlContent, $totalWordCount, $cleanContent);

        // Store the analysis results
        return [
            'success' => true,
            'message' => __('Content formatting validated successfully', 'beyondseo'),
            'word_count' => $totalWordCount,
            'heading_analysis' => $headingAnalysis,
            'paragraph_analysis' => $paragraphAnalysis,
            'bullet_point_analysis' => $bulletPointAnalysis,
        ];
    }

    /**
     * Evaluates the content formatting quality and calculates a score.
     *
     * @return float A score from 0 to 1 based on content formatting quality
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;
        // Heading analysis score (40% of total)
        $headingScore = $this->calculateHeadingScore($factorData['heading_analysis']);

        // Paragraph analysis score (40% of total)
        $paragraphScore = $this->calculateParagraphScore($factorData['paragraph_analysis']);

        // Bullet point analysis score (20% of total)
        $bulletPointScore = $this->calculateBulletPointScore(
            $factorData['bullet_point_analysis'],
            $factorData['word_count']
        );

        // Calculate weighted total score
        return ($headingScore * 0.4) + ($paragraphScore * 0.4) + ($bulletPointScore * 0.2);
    }

    /**
     * Calculates a score for heading structure.
     *
     * @param array $headingAnalysis The heading analysis data
     * @return float A score from 0 to 1 for heading structure
     */
    private function calculateHeadingScore(array $headingAnalysis): float
    {
        $score = 1.0; // Start with a perfect score

        // Check if there's an H1 heading
        if (!$headingAnalysis['has_h1']) {
            $score -= 0.2; // Reduce score if no H1
        }

        // Check heading density
        if ($headingAnalysis['has_too_few_headings']) {
            // Reduce the score based on how far below the minimum recommendation
            $deficit = SeoOptimiserConfig::CONTENT_MIN_RECOMMENDED_HEADINGS_PER_1000_WORDS - $headingAnalysis['headings_per_1000_words'];
            $score -= min(0.4, $deficit * 0.1); // Maximum penalty of 0.4
        } elseif ($headingAnalysis['has_too_many_headings']) {
            // Reduce the score based on how far above the maximum recommendation
            $excess = $headingAnalysis['headings_per_1000_words'] - SeoOptimiserConfig::CONTENT_MAX_RECOMMENDED_HEADINGS_PER_1000_WORDS;
            $score -= min(0.3, $excess * 0.05); // Maximum penalty of 0.3
        }

        // Check heading hierarchy
        if (!empty($headingAnalysis['hierarchy_issues'])) {
            $score -= min(0.3, count($headingAnalysis['hierarchy_issues']) * 0.1); // Maximum penalty of 0.3
        }

        // Check for empty headings
        if (!empty($headingAnalysis['empty_headings'])) {
            $score -= min(0.2, count($headingAnalysis['empty_headings']) * 0.1); // Maximum penalty of 0.2
        }

        // Check for long headings
        if (!empty($headingAnalysis['long_headings'])) {
            $score -= min(0.1, count($headingAnalysis['long_headings']) * 0.03); // Maximum penalty of 0.1
        }

        return max(0, $score); // Ensure the score is not negative
    }

    /**
     * Calculates a score for paragraph structure.
     *
     * @param array $paragraphAnalysis The paragraph analysis data
     * @return float A score from 0 to 1 for paragraph structure
     */
    private function calculateParagraphScore(array $paragraphAnalysis): float
    {
        $score = 1.0; // Start with a perfect score

        // Check average paragraph length
        if ($paragraphAnalysis['avg_paragraph_length'] > SeoOptimiserConfig::CONTENT_MAX_OPTIMAL_PARAGRAPH_LENGTH) {
            // Reduce score based on how far above the optimal length
            $excess = $paragraphAnalysis['avg_paragraph_length'] - SeoOptimiserConfig::CONTENT_MAX_OPTIMAL_PARAGRAPH_LENGTH;
            $penaltyRatio = $excess / (SeoOptimiserConfig::CONTENT_MAX_ACCEPTABLE_PARAGRAPH_LENGTH - SeoOptimiserConfig::CONTENT_MAX_OPTIMAL_PARAGRAPH_LENGTH);
            $score -= min(0.5, $penaltyRatio * 0.5); // Maximum penalty of 0.5
        }

        // Additional penalty for having too many long paragraphs
        if ($paragraphAnalysis['has_too_many_long_paragraphs']) {
            // Penalty proportional to the long paragraph ratio
            $longRatio = $paragraphAnalysis['long_paragraph_ratio'] / 100; // Convert percentage to ratio
            $score -= min(0.4, $longRatio * 0.8); // Maximum penalty of 0.4
        }

        // Check if there are enough paragraphs for content readability
        // Generally, we want at least one paragraph per 150-200 words
        $totalParagraphs = $paragraphAnalysis['total_paragraphs'];
        if ($totalParagraphs > 0) {
            $avgWordsPerParagraph = $paragraphAnalysis['avg_paragraph_length'];
            if ($avgWordsPerParagraph > 200) {
                $densityPenalty = min(0.3, ($avgWordsPerParagraph - 200) / 300);
                $score -= $densityPenalty;
            }
        }

        return max(0, $score); // Ensure the score is not negative
    }

    /**
     * Calculates a score for bullet point usage.
     *
     * @param array $bulletPointAnalysis The bullet point analysis data
     * @param int $wordCount The total word count of the content
     * @return float A score from 0 to 1 for bullet point usage
     */
    private function calculateBulletPointScore(array $bulletPointAnalysis, int $wordCount): float
    {
        // For very short content, bullet points are less important
        if ($wordCount < 500) {
            return 1.0;
        }

        $score = 1.0; // Start with a perfect score

        // Reduce the score if content needs more lists - more penalty if opportunities are detected
        if ($bulletPointAnalysis['needs_more_lists']) {
            if ($bulletPointAnalysis['list_opportunities_detected']) {
                $score -= 0.7; // Bigger penalty if opportunities are detected but not used
            } else {
                $score -= 0.4; // Smaller penalty if just based on content length
            }
        }

        // Check if bullet points have a reasonable number of items
        if ($bulletPointAnalysis['total_lists'] > 0) {
            if ($bulletPointAnalysis['avg_items_per_list'] < 2) {
                // Lists are too short
                $score -= 0.2;
            } elseif ($bulletPointAnalysis['avg_items_per_list'] > 10) {
                // Lists are too long
                $score -= 0.3;
            }
        }

        return max(0, $score); // Ensure the score is not negative
    }

    /**
     * Provides suggestions for improving content formatting.
     *
     * @return array An array of suggestion issue types
     */
    public function suggestions(): array
    {
        $suggestions = [];

        $factorData = $this->value;

        // Heading structure suggestions
        $headingAnalysis = $factorData['heading_analysis'];
        if (!$headingAnalysis['has_h1']) {
            $suggestions[] = Suggestion::MISSING_H1_TAG;
        }

        if ($headingAnalysis['has_too_few_headings']) {
            $suggestions[] = Suggestion::INSUFFICIENT_HEADINGS;
        }

        if ($headingAnalysis['has_too_many_headings']) {
            $suggestions[] = Suggestion::EXCESSIVE_HEADINGS;
        }

        if (!empty($headingAnalysis['hierarchy_issues'])) {
            // This is a content structure issue - using LACKS_CONTENT_DEPTH as the closest match
            $suggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
        }

        // Paragraph structure suggestions
        $paragraphAnalysis = $factorData['paragraph_analysis'];
        if ($paragraphAnalysis['has_too_many_long_paragraphs']) {
            $suggestions[] = Suggestion::PARAGRAPHS_TOO_LONG;
        }

        // Bullet point suggestions
        $bulletPointAnalysis = $factorData['bullet_point_analysis'];
        $wordCount = $factorData['word_count'];

        if ($wordCount > 1000 && $bulletPointAnalysis['needs_more_lists'] && $bulletPointAnalysis['list_opportunities_detected']) {
            // More specific suggestion when opportunities are detected
            $suggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
        }

        return $suggestions;
    }
}