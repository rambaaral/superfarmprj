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
 * Class HyphensInsteadOfUnderscoresOperation
 *
 * This operation checks if URLs use hyphens (-) instead of underscores (_) for word separators,
 * which is an important SEO best practice. Google treats hyphens as word separators,
 * while underscores can be treated as word joiners, affecting how search engines interpret content.
 */
#[SeoMeta(
    name: 'Hyphens Instead Of Underscores',
    weight: WeightConfiguration::WEIGHT_HYPHENS_INSTEAD_OF_UNDERSCORES_OPERATION,
    description: 'Examines page URLs to ensure words are separated with hyphens rather than underscores. Detects any underscores and offers guidance to standardize URL structure, improving readability and search engine recognition.',
)]
class HyphensInsteadOfUnderscoresOperation extends Operation implements OperationInterface
{
    /**
     * Analyzes the URL to check if it uses hyphens instead of underscores.
     *
     * This is a local implementation that extracts the URL path and checks for
     * the presence of underscores, which are not recommended for SEO.
     *
     * @return array|null The analysis result
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get the URL for the post
        $url = $this->contentProvider->getPostUrl($postId);
        if (empty($url)) {
            return [
                'success' => false,
                'message' => __('Could not retrieve URL', 'beyondseo'),
                'has_underscores' => false,
                'underscore_count' => 0,
            ];
        }

        // Parse the URL to get the path component
        $path = wp_parse_url($url, PHP_URL_PATH);

        // Check if the path contains underscores
        $hasUnderscores = str_contains($path, '_');

        // Count underscores if they exist
        $underscoreCount = 0;
        if ($hasUnderscores) {
            $underscoreCount = substr_count($path, '_');
        }

        // Check if the path contains hyphens (for comparison)
        $hasHyphens = str_contains($path, '-');
        $hyphenCount = 0;
        if ($hasHyphens) {
            $hyphenCount = substr_count($path, '-');
        }

        // Create a suggested URL by replacing underscores with hyphens
        $suggestedUrl = '';
        if ($hasUnderscores) {
            $suggestedUrl = str_replace('_', '-', $url);
        }

        return [
            'success' => true,
            'url' => $url,
            'path' => $path,
            'has_underscores' => $hasUnderscores,
            'underscore_count' => $underscoreCount,
            'has_hyphens' => $hasHyphens,
            'hyphen_count' => $hyphenCount,
            'suggested_url' => $suggestedUrl,
        ];
    }

    /**
     * Calculate the score based on the absence of underscores in the URL.
     * A perfect score (1.0) is achieved when no underscores are present.
     * The score decreases based on the number of underscores found.
     *
     * @return float The calculated score (0 to 1)
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;
        // If the URL has no underscores, return a perfect score
        if (!($factorData['has_underscores'] ?? false)) {
            return 1.0;
        }

        // If it has underscores, calculate a reduced score based on the number of underscores
        $underscoreCount = $factorData['underscore_count'] ?? 0;

        // The more underscores, the lower the score
        // Start with 0.5 and reduce by 0.1 for each underscore, but not below 0
        return max(0, 0.5 - (0.1 * $underscoreCount));
    }

    /**
     * Generate suggestions based on the analysis.
     * If underscores are found in the URL, suggest improving keyword placement
     * by replacing them with hyphens for better SEO.
     *
     * @return array The suggestions
     */
    public function suggestions(): array
    {
        $factorData = $this->value;
        $suggestions = [];

        // Only suggest if underscores are found
        if ($factorData['has_underscores'] ?? false) {
            // Use POOR_KEYWORD_PLACEMENT as the most relevant suggestion
            // since underscores affect how search engines interpret keywords in URLs
            $suggestions[] = Suggestion::USE_HYPHENS_IN_URL;

        }

        return $suggestions;
    }
}