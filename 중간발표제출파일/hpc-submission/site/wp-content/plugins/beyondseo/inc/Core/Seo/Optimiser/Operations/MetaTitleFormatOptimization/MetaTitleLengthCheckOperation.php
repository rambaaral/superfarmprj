<?php /** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaTitleFormatOptimization;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class MetaTitleLengthCheckOperation
 *
 * This operation validates whether the meta-title length is within the optimal range (50-60 characters)
 * for SEO best practices. Having the right title length ensures it displays properly in search results
 * without being truncated while containing sufficient information for users and search engines.
 */
#[SeoMeta(
    name: 'Meta Title Length Check',
    weight: WeightConfiguration::WEIGHT_META_TITLE_LENGTH_CHECK_OPERATION,
    description: 'Validates meta title length to ensure optimal display in search results without truncation while containing sufficient information.',
)]
class MetaTitleLengthCheckOperation extends Operation implements OperationInterface
{
    /**
     * Performs the meta-title length check for the given post-ID.
     * This method fetches the meta-title from the page and analyzes its length.
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
                'title_length' => 0,
                'title_text' => '',
                'is_optimal' => false,
                'is_acceptable' => false,
            ];
        }

        // Extract title from the HTML
        $html = $this->contentProvider->getContent($postId);
        $metaTitle = $this->contentProvider->extractMetaTitleFromHTML($html);

        // If title extraction fails, fall back to WordPress meta-data
        if (empty($metaTitle)) {
            $metaTitle = $this->contentProvider->getFallbackMetaTitle($postId);
        }

        // If still no meta-title found, return error
        if (empty($metaTitle)) {
            return [
                'success' => false,
                'message' => __('No meta title found', 'beyondseo'),
                'title_length' => 0,
                'title_text' => '',
                'is_optimal' => false,
                'is_acceptable' => false,
            ];
        }

        // Calculate title length using mb_strlen to handle multibyte characters correctly
        $titleLength = mb_strlen($metaTitle);

        // Check if the title length is within the optimal range
        $isOptimal = $titleLength >= SeoOptimiserConfig::META_TITLE_MIN_OPTIMAL_LENGTH && $titleLength <= SeoOptimiserConfig::META_TITLE_MAX_OPTIMAL_LENGTH;

        // Check if the title length is within the acceptable range
        $isAcceptable = $titleLength >= SeoOptimiserConfig::META_TITLE_MIN_ACCEPTABLE_LENGTH && $titleLength <= SeoOptimiserConfig::META_TITLE_MAX_ACCEPTABLE_LENGTH;

        // Determine the status
        $status = $this->determineTitleStatus($titleLength);

        // Store the results
        return [
            'success' => true,
            'message' => __('Meta title analysis completed successfully', 'beyondseo'),
            'title_length' => $titleLength,
            'title_text' => $metaTitle,
            'is_optimal' => $isOptimal,
            'is_acceptable' => $isAcceptable,
            'status' => $status,
            'optimal_range' => [
                'min' => SeoOptimiserConfig::META_TITLE_MIN_OPTIMAL_LENGTH,
                'max' => SeoOptimiserConfig::META_TITLE_MAX_OPTIMAL_LENGTH,
            ],
            'acceptable_range' => [
                'min' => SeoOptimiserConfig::META_TITLE_MIN_ACCEPTABLE_LENGTH,
                'max' => SeoOptimiserConfig::META_TITLE_MAX_ACCEPTABLE_LENGTH,
            ]
        ];
    }

    /**
     * Calculates a score based on how close the meta-title length is to the optimal range.
     * Scores range from 0 (very poor) to 1 (perfect) based on how closely the title length
     * adheres to SEO best practices.
     *
     * @return float A score from 0 to 1 based on title length appropriateness
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;
        $titleLength = $factorData['title_length'] ?? 0;

        // If the title length is optimal, return a perfect score
        if ($factorData['is_optimal'] ?? false) {
            return 1.0;
        }

        // If the title length is within the acceptable range but not optimal,
        // calculate a proportional score based on how close it is to the optimal range
        if ($factorData['is_acceptable'] ?? false) {
            if ($titleLength < SeoOptimiserConfig::META_TITLE_MIN_OPTIMAL_LENGTH) {
                // Score proportional to how close it is to the minimum optimal length
                $distance = SeoOptimiserConfig::META_TITLE_MIN_OPTIMAL_LENGTH - $titleLength;
                $range = SeoOptimiserConfig::META_TITLE_MIN_OPTIMAL_LENGTH - SeoOptimiserConfig::META_TITLE_MIN_ACCEPTABLE_LENGTH;
            } else { // $titleLength > SeoOptimiserConfig::MAX_OPTIMAL_LENGTH
                // Score proportional to how close it is to the maximum optimal length
                $distance = $titleLength - SeoOptimiserConfig::META_TITLE_MAX_OPTIMAL_LENGTH;
                $range = SeoOptimiserConfig::META_TITLE_MAX_ACCEPTABLE_LENGTH - SeoOptimiserConfig::META_TITLE_MAX_OPTIMAL_LENGTH;
            }
            return 0.7 + (0.3 * (1 - ($distance / $range)));
        }

        // If the title is too short or too long (outside the acceptable range)
        if ($titleLength < SeoOptimiserConfig::META_TITLE_MIN_ACCEPTABLE_LENGTH) {
            // For very short titles, the score ranges from 0 to 0.5
            return min(0.5, $titleLength / SeoOptimiserConfig::META_TITLE_MIN_ACCEPTABLE_LENGTH * 0.5);
        } else { // $titleLength > SeoOptimiserConfig::MAX_ACCEPTABLE_LENGTH
            // For very long titles, score decreases as length increases
            $excessLength = $titleLength - SeoOptimiserConfig::META_TITLE_MAX_ACCEPTABLE_LENGTH;
            $penaltyFactor = min(1, $excessLength / 30);
            return max(0, 0.5 - ($penaltyFactor * 0.5));
        }
    }

    /**
     * Provides suggestions for improving the meta-title length based on analysis results.
     * This helps guide the user on what changes they need to make for better SEO performance.
     *
     * @return array An array of suggestion issue types
     */
    public function suggestions(): array
    {
        $factorData = $this->value;

        $titleLength = $factorData['title_length'] ?? 0;
        $suggestions = [];

        // If the title is empty or not found
        if ($titleLength === 0) {
            $suggestions[] = Suggestion::META_TITLE_MISSING;
            return $suggestions;
        }

        // Title is too short
        if ($titleLength < SeoOptimiserConfig::META_TITLE_MIN_ACCEPTABLE_LENGTH) {
            $suggestions[] = Suggestion::META_TITLE_TOO_SHORT;
        }
        // Title is too long
        elseif ($titleLength > SeoOptimiserConfig::META_TITLE_MAX_ACCEPTABLE_LENGTH) {
            $suggestions[] = Suggestion::META_TITLE_TOO_LONG;
        }

        return $suggestions;
    }

    /**
     * Determines the status of the title based on its length
     * This helps categorize title lengths for providing appropriate feedback
     *
     * @param int $titleLength The length of the title
     * @return string The status of the title
     */
    private function determineTitleStatus(int $titleLength): string
    {
        if ($titleLength < SeoOptimiserConfig::META_TITLE_MIN_ACCEPTABLE_LENGTH) {
            return __('too_short', 'beyondseo');
        } elseif ($titleLength < SeoOptimiserConfig::META_TITLE_MIN_OPTIMAL_LENGTH) {
            return __('slightly_short', 'beyondseo');
        } elseif ($titleLength <= SeoOptimiserConfig::META_TITLE_MAX_OPTIMAL_LENGTH) {
            return __('optimal', 'beyondseo');
        } elseif ($titleLength <= SeoOptimiserConfig::META_TITLE_MAX_ACCEPTABLE_LENGTH) {
            return __('slightly_long', 'beyondseo');
        } else {
            return __('too_long', 'beyondseo');
        }
    }
}