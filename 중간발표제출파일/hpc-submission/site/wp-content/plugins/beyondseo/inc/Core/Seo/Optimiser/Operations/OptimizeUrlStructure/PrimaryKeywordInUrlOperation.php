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
 * Class PrimaryKeywordInUrlOperation
 *
 * This operation checks if the primary keyword is naturally included in the URL,
 * ideally close to the root domain. Having the primary keyword in the URL is a
 * fundamental SEO best practice that improves both user understanding and search
 * engine interpretation of page content.
 */
#[SeoMeta(
    name: 'Primary Keyword In Url',
    weight: WeightConfiguration::WEIGHT_PRIMARY_KEYWORD_IN_URL_OPERATION,
    description: 'Evaluates whether the primary keyword appears in the page URL and how close it is to the domain root. Suggests adjustments when keywords are absent or buried deep in the path.',
)]
class PrimaryKeywordInUrlOperation extends Operation implements OperationInterface
{
    /**
     * Analyzes whether the primary keyword is included in the URL and its proximity
     * to the root domain.
     *
     * @return array|null The analysis results
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
                'primary_keyword' => '',
                'url' => '',
                'url_path' => '',
                'keyword_in_url' => false,
                'path_segments' => 0,
                'keyword_position_score' => 0,
            ];
        }

        // Get the URL for the post
        $url = $this->contentProvider->getPostUrl($postId);

        // Check if the keyword is in the slug using the helper method
        $keywordInUrl = $this->contentProvider->isKeywordInSlug($primaryKeyword, $postId);

        // Parse the URL to extract the path for additional analysis
        $parsedUrl = wp_parse_url($url);
        $path = $parsedUrl['path'] ?? '';

        // Normalize the path for segment analysis
        $pathNormalized = trim($path, '/');
        $segments = explode('/', $pathNormalized);
        $pathSegments = count($segments);

        // Calculate a position score (how close the keyword is to the root)
        $keywordPositionScore = 0;

        if ($keywordInUrl) {
            // Check which segment contains the keyword
            $keywordNormalized = strtolower($primaryKeyword);
            $keywordForUrl = sanitize_title($keywordNormalized);

            foreach ($segments as $index => $segment) {
                if (str_contains(strtolower($segment), $keywordForUrl)) {
                    // If the keyword is in the first segment, max score
                    // Otherwise, the score decreases as you move further from the root
                    $keywordPositionScore = 1 - ($index / max(1, $pathSegments));
                    break;
                }
            }
        }

        // Store the analysis results
        return [
            'success' => true,
            'message' => __('URL structure analysis completed', 'beyondseo'),
            'primary_keyword' => $primaryKeyword,
            'url' => $url,
            'url_path' => $path,
            'keyword_in_url' => $keywordInUrl,
            'path_segments' => $pathSegments,
            'keyword_position_score' => $keywordPositionScore,
        ];
    }

    /**
     * Calculate the score based on whether and how well the primary keyword is included in the URL.
     * The score is based on:
     * 1. Presence of the keyword in the URL (70% of the score)
     * 2. Proximity to the root domain (30% of the score)
     *
     * @return float The score from 0 to 1
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // If the keyword isn't in the URL at all, the score is 0
        if (!$factorData['keyword_in_url']) {
            return 0;
        }

        // Base score for having the keyword in the URL
        $score = 0.7;

        // Bonus for keyword position - closer to the root is better
        if (isset($factorData['keyword_position_score'])) {
            $score += $factorData['keyword_position_score'] * 0.3;
        }

        return $score;
    }

    /**
     * Provide suggestions for improving the URL structure based on the analysis.
     * Suggestions include:
     * - Adding the primary keyword to the URL if it's missing
     * - Moving the keyword closer to the root domain for better SEO impact
     *
     * @return array An array of suggestions
     */
    public function suggestions(): array
    {
        $suggestions = [];
        $factorData = $this->value;

        // If the primary keyword isn't in the URL at all
        if (!$factorData['keyword_in_url']) {
            $suggestions[] = Suggestion::MISSING_PRIMARY_KEYWORD_IN_URL;
        }
        // If the keyword is present but not close to the root (position score < 0.7)
        elseif (($factorData['keyword_position_score'] ?? 1) < 0.7) {
            $suggestions[] = Suggestion::PRIMARY_KEYWORD_TOO_DEEP_IN_URL;
        }

        return $suggestions;
    }
}