<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaDescriptionFormatOptimization;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class MetaDescriptionLengthCheckOperation
 *
 * This operation validates whether the meta-description length is within the optimal range (150-160 characters)
 * for SEO best practices. Having the right meta-description length ensures it displays properly in search results
 * without being truncated while still containing enough information to entice users to click.
 */
#[SeoMeta(
    name: 'Meta Description Length Check',
    weight: WeightConfiguration::WEIGHT_META_DESCRIPTION_LENGTH_CHECK_OPERATION,
    description: 'Checks if meta descriptions fall within the optimal character range to avoid truncation in search results. Provides recommendations when descriptions are too short or lengthy, helping maximize snippet visibility and engagement.',
)]
class MetaDescriptionLengthCheckOperation extends Operation implements OperationInterface
{
    /**
     * Performs the meta-description length check for the given post-ID.
     * This method fetches the meta-description from the page and analyzes its length.
     *
     * @return array|null The validation results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get the page URL for the post
        $pageUrl = $this->contentProvider->getPostUrl($postId);

        if (empty($pageUrl)) {
            return [
                'success' => false,
                'message' => __('Unable to get post URL', 'beyondseo'),
                'description_length' => 0,
                'description_text' => '',
                'is_optimal' => false,
                'is_acceptable' => false,
            ];
        }

        // Get meta-description directly from post-meta (primary method)
        $metaDescription = $this->contentProvider->getMetaDescription($postId);

        // If meta-description not found in post-meta, try to extract from HTML
        if (empty($metaDescription)) {
            // Extract meta-description from the HTML
            $html = $this->contentProvider->getContent($postId);
            $metaDescription = $this->contentProvider->extractMetaDescriptionFromHTML($html);

            if (empty($metaDescription)) {
                $metaDescription = $this->contentProvider->getFallbackMetaDescription($postId);
            }
        }

        // If still no meta-description found, return error
        if (empty($metaDescription)) {
            return [
                'success' => false,
                'message' => __('No meta description found', 'beyondseo'),
                'description_length' => 0,
                'description_text' => '',
                'is_optimal' => false,
                'is_acceptable' => false,
            ];
        }

        // Calculate description length using mb_strlen to handle multibyte characters correctly
        $descriptionLength = mb_strlen($metaDescription);

        // Check if the description length is within the optimal range
        $isOptimal = $descriptionLength >= SeoOptimiserConfig::META_DESCRIPTION_MIN_OPTIMAL_LENGTH &&
            $descriptionLength <= SeoOptimiserConfig::META_DESCRIPTION_MAX_OPTIMAL_LENGTH;

        // Check if the description length is within the acceptable range
        $isAcceptable = $descriptionLength >= SeoOptimiserConfig::META_DESCRIPTION_MIN_ACCEPTABLE_LENGTH &&
            $descriptionLength <= SeoOptimiserConfig::META_DESCRIPTION_MAX_ACCEPTABLE_LENGTH;

        // Determine the status
        $status = $this->determineDescriptionStatus($descriptionLength);

        // Store the results
        return [
            'success' => true,
            'message' => __('Meta description analysis completed successfully', 'beyondseo'),
            'description_length' => $descriptionLength,
            'description_text' => $metaDescription,
            'is_optimal' => $isOptimal,
            'is_acceptable' => $isAcceptable,
            'status' => $status,
            'optimal_range' => [
                'min' => SeoOptimiserConfig::META_DESCRIPTION_MIN_OPTIMAL_LENGTH,
                'max' => SeoOptimiserConfig::META_DESCRIPTION_MAX_OPTIMAL_LENGTH,
            ],
            'acceptable_range' => [
                'min' => SeoOptimiserConfig::META_DESCRIPTION_MIN_ACCEPTABLE_LENGTH,
                'max' => SeoOptimiserConfig::META_DESCRIPTION_MAX_ACCEPTABLE_LENGTH,
            ]
        ];
    }

    /**
     * Calculates a score based on how close the meta-description length is to the optimal range.
     * Scores range from 0 (very poor) to 1 (perfect) based on how closely the description length
     * adheres to SEO best practices.
     *
     * @return float A score from 0 to 1 based on description length appropriateness
     */
    public function calculateScore(): float
    {
        $descriptionLength = $this->value['description_length'] ?? 0;

        // If the description length is optimal, return a perfect score
        if ($this->value['is_optimal'] ?? false) {
            return 1.0;
        }

        // If the description length is within the acceptable range but not optimal,
        // calculate a proportional score based on how close it is to the optimal range
        if ($this->value['is_acceptable'] ?? false) {
            if ($descriptionLength < SeoOptimiserConfig::META_DESCRIPTION_MIN_OPTIMAL_LENGTH) {
                // Score proportional to how close it is to the minimum optimal length
                $distance = SeoOptimiserConfig::META_DESCRIPTION_MIN_OPTIMAL_LENGTH - $descriptionLength;
                $range = SeoOptimiserConfig::META_DESCRIPTION_MIN_OPTIMAL_LENGTH - SeoOptimiserConfig::META_DESCRIPTION_MIN_ACCEPTABLE_LENGTH;
            } else { // $descriptionLength > SeoOptimiserConfig::META_DESCRIPTION_MAX_OPTIMAL_LENGTH
                // Score proportional to how close it is to the maximum optimal length
                $distance = $descriptionLength - SeoOptimiserConfig::META_DESCRIPTION_MAX_OPTIMAL_LENGTH;
                $range = SeoOptimiserConfig::META_DESCRIPTION_MAX_ACCEPTABLE_LENGTH - SeoOptimiserConfig::META_DESCRIPTION_MAX_OPTIMAL_LENGTH;
            }
            return 0.7 + (0.3 * (1 - ($distance / $range)));
        }

        // If the description is too short or too long (outside the acceptable range)
        if ($descriptionLength < SeoOptimiserConfig::META_DESCRIPTION_MIN_ACCEPTABLE_LENGTH) {
            // For very short descriptions, the score ranges from 0 to 0.5
            return min(0.5, $descriptionLength / SeoOptimiserConfig::META_DESCRIPTION_MIN_ACCEPTABLE_LENGTH * 0.5);
        }

        // $descriptionLength > SeoOptimiserConfig::META_DESCRIPTION_MAX_ACCEPTABLE_LENGTH
        // For very long descriptions, score decreases as length increases
        $excessLength = $descriptionLength - SeoOptimiserConfig::META_DESCRIPTION_MAX_ACCEPTABLE_LENGTH;
        $penaltyFactor = min(1, $excessLength / 30);
        return max(0, 0.5 - ($penaltyFactor * 0.5));
    }

    /**
     * Provides suggestions for improving the meta-description length based on analysis results.
     * This helps guide the user on what changes they need to make for better SEO performance.
     *
     * @return array An array of suggestion issue types
     */
    public function suggestions(): array
    {
        $factorData = $this->value;

        $descriptionLength = $factorData['description_length'] ?? 0;
        $suggestions = [];

        // If the description is empty or not found
        if ($descriptionLength === 0) {
            $suggestions[] = Suggestion::META_DESCRIPTION_MISSING;
            return $suggestions;
        }

        // Description is too short
        if ($descriptionLength < SeoOptimiserConfig::META_DESCRIPTION_MIN_ACCEPTABLE_LENGTH) {
            $suggestions[] = Suggestion::META_DESCRIPTION_TOO_SHORT;
        }
        // Description is too long
        if ($descriptionLength > SeoOptimiserConfig::META_DESCRIPTION_MAX_ACCEPTABLE_LENGTH) {
            $suggestions[] = Suggestion::META_DESCRIPTION_TOO_LONG;
        }

        // The Description is slightly short but within acceptable range
        if ($descriptionLength >= SeoOptimiserConfig::META_DESCRIPTION_MIN_ACCEPTABLE_LENGTH &&
            $descriptionLength < SeoOptimiserConfig::META_DESCRIPTION_MIN_OPTIMAL_LENGTH) {
            $suggestions[] = Suggestion::META_DESCRIPTION_SLIGHTLY_SHORT;
        }

        return $suggestions;
    }

    /**
     * Determines the status of the meta-description based on its length
     * This helps categorize description lengths for providing appropriate feedback
     *
     * @param int $descriptionLength The length of the meta-description
     * @return string The status of the meta-description
     */
    private function determineDescriptionStatus(int $descriptionLength): string
    {
        if ($descriptionLength < SeoOptimiserConfig::META_DESCRIPTION_MIN_ACCEPTABLE_LENGTH) {
            return __('too_short', 'beyondseo');
        } elseif ($descriptionLength < SeoOptimiserConfig::META_DESCRIPTION_MIN_OPTIMAL_LENGTH) {
            return __('slightly_short', 'beyondseo');
        } elseif ($descriptionLength <= SeoOptimiserConfig::META_DESCRIPTION_MAX_OPTIMAL_LENGTH) {
            return __('optimal', 'beyondseo');
        } elseif ($descriptionLength <= SeoOptimiserConfig::META_DESCRIPTION_MAX_ACCEPTABLE_LENGTH) {
            return __('slightly_long', 'beyondseo');
        } else {
            return __('too_long', 'beyondseo');
        }
    }
}