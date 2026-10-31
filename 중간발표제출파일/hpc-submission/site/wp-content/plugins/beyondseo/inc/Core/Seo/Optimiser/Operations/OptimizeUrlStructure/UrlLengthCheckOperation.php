<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\OptimizeUrlStructure;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class UrlLengthCheckOperation
 *
 * This operation checks if a page URL is too long and provides suggestions
 * for optimization if needed.
 */
#[SeoMeta(
    name: 'Url Length Check',
    weight: WeightConfiguration::WEIGHT_URL_LENGTH_CHECK_OPERATION,
    description: 'Measures the total length of the page URL and compares it to a recommended threshold. Alerts when URLs become excessively long, offering tips to shorten and simplify paths for better crawling.',
)]
class UrlLengthCheckOperation extends Operation implements OperationInterface
{
    // Maximum recommended URL length
    private const MAX_RECOMMENDED_URL_LENGTH = 100;

    /**
     * Performs the URL length check for the given post-ID.
     *
     * @return array|null The analysis results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get the URL for the post
        $url = $this->contentProvider->getPostUrl($postId);
        $urlLength = strlen($url);

        // Check if the URL is too long
        $isTooLong = $urlLength > self::MAX_RECOMMENDED_URL_LENGTH;

        // Generate suggestions for the URL if it's too long
        $suggestions = [];
        if ($isTooLong) {
            $suggestions = $this->generateUrlOptimizationSuggestions($url);
        }

        return [
            'success' => true,
            'message' => $isTooLong
                ? __('URL is too long. Consider shortening it.', 'beyondseo')
                : __('URL length is optimal.', 'beyondseo'),
            'url' => $url,
            'length' => $urlLength,
            'max_recommended_length' => self::MAX_RECOMMENDED_URL_LENGTH,
            'is_too_long' => $isTooLong,
            'optimization_suggestions' => $suggestions
        ];
    }

    /**
     * Evaluates the score based on URL length.
     *
     * @return float A score between 0 and 1
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;
        $urlLength = $factorData['length'] ?? 0;
        $maxLength = self::MAX_RECOMMENDED_URL_LENGTH;

        if ($urlLength <= $maxLength) {
            // URL is within the recommended length-perfect score
            return 1.0;
        }

        // Calculate score based on how much the URL exceeds the recommended length,
        // Score decreases linearly from 1.0 to 0.0 as URL length increases from max to 2*max
        $excessLength = $urlLength - $maxLength;
        return max(0, 1 - ($excessLength / $maxLength));
    }

    /**
     * Generate suggestions for URL optimization if the URL is too long.
     *
     * @param string $url The URL to optimize
     * @return array An array of optimization suggestions
     */
    private function generateUrlOptimizationSuggestions(string $url): array
    {
        $suggestions = [];

        // Check if the URL contains query parameters that could be removed
        if (str_contains($url, '?')) {
            $suggestions[] = __('Remove unnecessary query parameters to shorten the URL.', 'beyondseo');
        }

        // Check if the URL contains numbers that might be dates
        if (preg_match('/\/\d{4}\/\d{2}\//', $url)) {
            $suggestions[] = __('Consider removing date information from permalink structure.', 'beyondseo');
        }

        // Check if the URL contains common filler words that could be removed
        $fillerWords = ['and', 'or', 'the', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'with', 'by', 'of'];
        $containsFillerWords = false;

        foreach ($fillerWords as $word) {
            if (preg_match('/\/' . $word . '\//', $url)) {
                $containsFillerWords = true;
                break;
            }
        }

        if ($containsFillerWords) {
            $suggestions[] = __('Remove unnecessary filler words like "and", "the", etc. from the URL.', 'beyondseo');
        }

        // Check if the URL contains very long words
        if (preg_match('/\/[a-zA-Z0-9-]{20,}\//', $url)) {
            $suggestions[] = __('Shorten very long words or phrases in the URL.', 'beyondseo');
        }

        // If no specific suggestions were found, add a general one
        if (empty($suggestions)) {
            $suggestions[] = __('Try to make your URL more concise by removing unnecessary words.', 'beyondseo');
        }

        return $suggestions;
    }

    /**
     * Determines suggestions for the operation based on the URL length.
     *
     * @return array An array of suggestion issue types
     */
    public function suggestions(): array
    {
        $factorData = $this->value;

        $isTooLong = $factorData['is_too_long'] ?? false;

        if (!$isTooLong) {
            return [];
        }

        $suggestions = [];
        $postId = $parameters['postId'] ?? 0;

        // Check if the URL contains the primary keyword
        if ($postId > 0) {
            $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);
            $url = $factorData['url'] ?? '';

            if (!empty($primaryKeyword) &&
                !str_contains(strtolower($url), strtolower($primaryKeyword))) {
                // URL doesn't contain a primary keyword
                $suggestions[] = Suggestion::MISSING_PRIMARY_KEYWORD_IN_URL;
            }
        }

        // Add appropriate suggestions based on URL length issues
        $suggestions[] = Suggestion::URL_TOO_LONG;

        // Use a custom approach to get unique enum values
        return $this->getUniqueEnumValues($suggestions);
    }
}