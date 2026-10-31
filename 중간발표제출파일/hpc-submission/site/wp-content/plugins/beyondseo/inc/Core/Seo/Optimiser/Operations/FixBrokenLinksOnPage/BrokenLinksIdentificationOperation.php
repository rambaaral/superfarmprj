<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\FixBrokenLinksOnPage;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;
use Exception;
use RankingCoach\Inc\Modules\ModuleManager;

/**
 * Class BrokenLinksIdentificationOperation
 *
 * Extracts links from post-HTML, picks the first 200 high‑priority ones and sends them
 * to an external service for status checking.
 */
#[SeoMeta(
    name: 'Broken Links Identification',
    weight: WeightConfiguration::WEIGHT_BROKEN_LINKS_IDENTIFICATION_OPERATION,
    description: 'Scans page content to extract and evaluate internal and external links. Checks link status via internal or external service, returning details about broken or redirecting URLs to maintain healthy backlinks.',
)]
class BrokenLinksIdentificationOperation extends Operation implements OperationInterface
{

    /**
     * Runs the broken links detection operation.
     *
     * This method:
     * 1. Extracts all links from the page content
     * 2. Categorizes them as internal or external
     * 3. Checks link status (directly or via external API)
     * 4. Returns detailed information about broken links
     *
     * @return array|null The check results or null if the post ID is invalid
     * @throws Exception
     */
    public function run(): ?array
    {
        // Extract all links from content
        $postId = $this->postId;
        $links = ModuleManager::instance()->initialize()->linkAnalyzer()->getLinksForPost($postId);
        $postUrl = get_permalink($postId);
        // Skip analysis if no links found
        if (empty($links)) {
            return [
                'success' => false,
                'message' => __('No links found to analyze', 'beyondseo'),
                'page_url' => get_permalink($postId),
                'total_links' => 0,
                'internal_links_count' => 0,
                'external_links_count' => 0,
                'broken_links_count' => 0,
                'broken_internal_links' => [],
                'broken_external_links' => [],
                'redirect_links' => [],
                'analysis_method' => 'none',
                'check_date' => current_time('mysql')
            ];
        }
        $internalBrokenLinksCount = $this->countBrokenLinks($links['outbound']);
        $externalBrokenLinksCount = $this->countBrokenLinks($links['external']);
        // Prepare the result
        return [
            'success' => true,
            'message' => __('Broken links analysis completed successfully', 'beyondseo'),
            'page_url' => $postUrl,
            'total_links' => count($links['outbound']) + count($links['external']),
            'outbound_links_count' => count($links['outbound']),
            'external_links_count' => count($links['external']),
            'broken_links_count' => $internalBrokenLinksCount + $externalBrokenLinksCount,
            'broken_outbound_links' => $internalBrokenLinksCount,
            'broken_external_links' => $externalBrokenLinksCount,
            'analysis_method' => 'internal',
            'check_date' => current_time('mysql')
        ];
    }

    /**
     * Counts the number of broken links in the provided array.
     *
     * @param array $links An array of links with their status
     * @return int The count of broken links
     */
    private function countBrokenLinks(array $links): int
    {
        return array_reduce($links, static function ($carry, $link) {
            return $carry + ($link['status'] === 'broken' ? 1 : 0);
        }, 0);
    }

    /**
     * Calculates the score based on the number and type of broken links.
     *
     * The score is calculated based on:
     * 1. The ratio of working links to total links
     * 2. The impact of broken links (internal broken links are weighted more heavily)
     * 3. The ratio of redirect links (which might indicate outdated references)
     *
     * @return float A score between 0 and 1, where 1 means no broken links
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // If analysis didn't complete successfully, return 0
        if (!($factorData['success'] ?? false)) {
            return 0;
        }
        $totalLinks = $factorData['total_links'] ?? 0;

        // No links to check
        if ($totalLinks === 0) {
            return 1.0; // Perfect score if no links to break
        }

        $brokenInternalCount = $factorData['broken_outbound_links'];
        $brokenExternalCount = $factorData['broken_external_links'];

        // Internal links are more important than external links
        // Weight broken internal links 3 times more than external
        $weightedBrokenCount = ($brokenInternalCount * 3) + $brokenExternalCount;

        // Calculate base score based on ratio of working links
        $baseScore = max(0, 1 - ($weightedBrokenCount / ($totalLinks * 3)));

        // Final score
        return max(0, min(1, $baseScore));
    }

    /**
     * Generates suggestions for fixing broken links issues.
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];
        $factorData = $this->value;

        // If analysis didn't complete successfully, no suggestions
        if (!($factorData['success'] ?? false)) {
            return $activeSuggestions;
        }

        $brokenInternalLinks = $factorData['broken_internal_links'] ?? [];
        $brokenExternalLinks = $factorData['broken_external_links'] ?? [];

        // Broken internal links are critical technical SEO issues
        if (!empty($brokenInternalLinks)) {
            $activeSuggestions[] = Suggestion::TECHNICAL_SEO_ISSUES;
        }

        // Broken external links also affect user experience and credibility
        if (!empty($brokenExternalLinks) && !in_array(Suggestion::TECHNICAL_SEO_ISSUES, $activeSuggestions, true)) {
            $activeSuggestions[] = Suggestion::TECHNICAL_SEO_ISSUES;
        }

        return $activeSuggestions;
    }
}