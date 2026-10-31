<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\AltTextToImages;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class PrimaryKeywordInAltTextOperation
 *
 * This operation checks if the primary keyword is naturally included in the alt text
 * of relevant images on the page. It identifies both missing keywords and overuse
 * (keyword stuffing) in image alt attributes.
 */
#[SeoMeta(
    name: 'Primary Keyword In Alt Text',
    weight: WeightConfiguration::WEIGHT_PRIMARY_KEYWORD_IN_ALT_TEXT_OPERATION,
    description: 'Analyzes image alt texts for the presence of the primary keyword, ensuring it is used naturally and not excessively. Provides suggestions for optimization based on keyword usage in alt attributes.',
)]
class PrimaryKeywordInAltTextOperation extends Operation implements OperationInterface
{
    /**
     * Analyzes image alt texts for primary keyword inclusion.
     *
     * @return array|null The analysis results or null if unable to analyze
     */
    public function run(): ?array
    {
        $postId = $this->postId;

        // Get primary-keyword for the post
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);

        if (empty($primaryKeyword)) {
            return [
                'success' => false,
                'message' => __('No primary keyword found for analysis', 'beyondseo'),
                'images_analyzed' => 0,
            ];
        }

        // Get the fully rendered HTML content
        $htmlContent = $this->contentProvider->getContent($postId);

        if (empty($htmlContent)) {
            return [
                'success' => false,
                'message' => __('Failed to retrieve page content', 'beyondseo'),
                'images_analyzed' => 0,
            ];
        }

        // Parse HTML and analyze images
        $imageAnalysis = $this->contentProvider->analyzeAltTextKeywordUsageInImages($htmlContent, $primaryKeyword);

        if (!$imageAnalysis) {
            return [
                'success' => false,
                'message' => __('Failed to analyze images in content', 'beyondseo'),
                'images_analyzed' => 0,
            ];
        }

        return array_merge(
            [
                'success' => true,
                'message' => __('Analysis completed successfully', 'beyondseo'),
                'primary_keyword' => $primaryKeyword,
            ],
            $imageAnalysis
        );
    }

    /**
     * Calculate the score based on primary keyword usage in image alt text.
     *
     * @return float Score between 0 and 1
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // Default to 0 if there's no valid data
        if (!isset($factorData['success']) || !$factorData['success']) {
            return 0;
        }

        // If no images were found, return a neutral score
        if ($factorData['images_analyzed'] === 0 || $factorData['images_with_alt'] === 0) {
            return 0;
        }

        // The base score starts at 0.5
        $score = 0.5;

        // 1. Percentage of images with alt text
        $altTextPercentage = $factorData['percentage_with_alt'];
        if ($altTextPercentage >= 90) {
            $score += 0.2; // Bonus for high alt text coverage
        } elseif ($altTextPercentage < 50) {
            $score -= 0.1; // Penalty for low alt text coverage
        }

        // 2. Percentage of images with the primary keyword in alt text
        $keywordPercentage = $factorData['percentage_with_keyword'];

        if ($keywordPercentage === 0) {
            // No images with the keyword is a serious issue
            $score -= 0.3;
        } elseif ($factorData['images_with_alt'] >= SeoOptimiserConfig::MIN_IMAGES_TO_ANALYZE_KEYWORD_IN_ALT && $keywordPercentage < SeoOptimiserConfig::MIN_PERCENTAGE_IMAGES_WITH_KEYWORD_IN_ALT) {
            // Too few images with the keyword
            $score -= 0.2;
        } elseif ($factorData['images_with_alt'] >= SeoOptimiserConfig::MIN_IMAGES_TO_ANALYZE_KEYWORD_IN_ALT && $keywordPercentage > SeoOptimiserConfig::MAX_PERCENTAGE_IMAGES_WITH_KEYWORD_IN_ALT) {
            // Too many images with the keyword (might be unnatural)
            $score -= 0.1;
        } else {
            // Optimal range
            $score += 0.2;
        }

        // 3. Check if keywords are naturally included
        $naturalKeywordPercentage = $factorData['percentage_with_natural_keyword'] ?? 0;
        if ($keywordPercentage > 0 && $naturalKeywordPercentage < 50) {
            // Keywords don't appear naturally in alt text
            $score -= 0.1;
        } elseif ($keywordPercentage > 0 && $naturalKeywordPercentage >= 80) {
            // Keywords appear naturally in most alt texts
            $score += 0.1;
        }

        // 4. Penalty for keyword stuffing in alt text
        $stuffingPercentage = $factorData['percentage_with_keyword_stuffing'];
        if ($stuffingPercentage > 0) {
            $score -= min(0.3, $stuffingPercentage / 100); // Up to 0.3 penalty based on stuffing percentage
        }

        // Ensure the score is between 0 and 1
        return max(0, min(1, $score));
    }

    /**
     * Provide suggestions based on the analysis results.
     *
     * @return array An array of suggestion enum values
     */
    public function suggestions(): array
    {
        $factorData = $this->value;
        $suggestions = [];

        // Default suggestions if there's no valid data
        if (!isset($factorData['success']) || !$factorData['success']) {
            return [Suggestion::TECHNICAL_SEO_ISSUES];
        }

        // If no images were found, no specific suggestions needed
        if ($factorData['images_analyzed'] === 0) {
            return [];
        }

        // 1. Check for images missing alt text
        if ($factorData['images_with_alt'] < $factorData['images_analyzed']) {
            $suggestions[] = Suggestion::MISSING_IMAGE_ALT_TEXT;
        }

        // 2. Check for missing primary keyword in alt text
        if ($factorData['images_with_alt'] > SeoOptimiserConfig::MIN_IMAGES_TO_ANALYZE_KEYWORD_IN_ALT) {
            if ($factorData['percentage_with_keyword'] === 0) {
                $suggestions[] = Suggestion::POOR_KEYWORD_PLACEMENT_IN_ALT_TEXT;
            } elseif ($factorData['percentage_with_keyword'] < SeoOptimiserConfig::MIN_PERCENTAGE_IMAGES_WITH_KEYWORD_IN_ALT) {
                $suggestions[] = Suggestion::MISSING_IMAGE_ALT_TEXT_KEYWORD_USAGE;
            }
        }

        // 3. Check for natural keyword usage
        if (isset($factorData['percentage_with_natural_keyword']) &&
            $factorData['percentage_with_keyword'] > 0 &&
            $factorData['percentage_with_natural_keyword'] < 50) {
            $suggestions[] = Suggestion::KEYWORD_NOT_USED_NATURALLY_IN_ALT;
        }

        // 4. Check for keyword stuffing in alt text
        if ($factorData['percentage_with_keyword_stuffing'] > 0) {
            $suggestions[] = Suggestion::KEYWORD_STUFFING_DETECTED;
        }

        // 5. Check if too many images have the keyword (might be unnatural)
        if ($factorData['images_analyzed'] > SeoOptimiserConfig::MIN_IMAGES_TO_ANALYZE_KEYWORD_IN_ALT && $factorData['percentage_with_keyword'] > SeoOptimiserConfig::MAX_PERCENTAGE_IMAGES_WITH_KEYWORD_IN_ALT) {
            $suggestions[] = Suggestion::KEYWORD_OVERUSE_IN_ALT_TEXT;
        }

        return $suggestions;
    }
}