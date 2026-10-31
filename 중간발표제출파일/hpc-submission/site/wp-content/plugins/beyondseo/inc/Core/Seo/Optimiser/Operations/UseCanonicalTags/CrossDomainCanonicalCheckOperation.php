<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\UseCanonicalTags;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;
use RankingCoach\Inc\Core\Seo\Libs\UrlDomain;

/**
 * Class CrossDomainCanonicalCheckOperation
 *
 * This class checks if cross-domain canonical tags are used correctly when
 * content is syndicated to other sites. Proper canonical implementation
 * prevents duplicate content issues in search engines.
 */
#[SeoMeta(
    name: 'CrossDomain Canonical Check',
    weight: WeightConfiguration::WEIGHT_CROSS_DOMAIN_CANONICAL_CHECK_OPERATION,
    description: 'Ensures canonical tags referencing external domains are used correctly when content is syndicated. Validates host consistency and suggests adjustments so search engines attribute authority to the original source.',
)]
class CrossDomainCanonicalCheckOperation extends Operation implements OperationInterface
{
    /**
     * Checks if cross-domain canonical tags are used correctly for the given post-ID.
     *
     * @return array|null The analysis results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get the site domain
        $siteDomain = $this->contentProvider->getSiteUrl();

        // Get the full rendered HTML content
        $content = $this->contentProvider->getContent($postId);

        if (empty($content)) {
            return [
                'success' => false,
                'message' => __('Failed to retrieve content', 'beyondseo'),
                'has_canonical' => false,
                'canonical_url' => '',
                'is_cross_domain' => false,
                'is_correct' => false,
                'syndication_detected' => false,
            ];
        }

        $canonicalUrl = $this->contentProvider->extractCanonicalUrl($content, $postId);
        $hasCanonical = !empty($canonicalUrl);
        $isCrossDomain = false;
        // Check if canonical URL exists and if it's cross-domain
        if ($canonicalUrl) {
            $isCrossDomain = !UrlDomain::sameDomain($canonicalUrl, $siteDomain);
        }

        // Check if the content is syndicated
        $syndicationDetected = $this->contentProvider->detectSyndication($postId, $content);

        // Determine if the canonical implementation is correct
        $isCorrect = $this->isCanonicalCorrect($hasCanonical, $isCrossDomain, $syndicationDetected);

        return [
            'success' => true,
            'message' => __('Cross-domain canonical check completed', 'beyondseo'),
            'has_canonical' => $hasCanonical,
            'canonical_url' => $canonicalUrl,
            'is_cross_domain' => $isCrossDomain,
            'is_correct' => $isCorrect,
            'syndication_detected' => $syndicationDetected,
            'site_domain' => $siteDomain,
            'canonical_domain' => $hasCanonical ? wp_parse_url($canonicalUrl, PHP_URL_HOST) : '',
        ];
    }

    /**
     * Determines if the canonical tag usage is correct based on syndication status.
     *
     * @param bool $hasCanonical Whether a canonical tag exists
     * @param bool $isCrossDomain Whether the canonical points to a different domain
     * @param bool $syndicationDetected Whether content syndication is detected
     * @return bool Whether the canonical usage is correct
     */
    private function isCanonicalCorrect(bool $hasCanonical, bool $isCrossDomain, bool $syndicationDetected): bool
    {
        // If content is syndicated, it MUST have a cross-domain canonical
        if ($syndicationDetected) {
            return $hasCanonical && $isCrossDomain;
        }

        // If content is not syndicated but has a canonical, it should be same-domain
        if ($hasCanonical) {
            return !$isCrossDomain;
        }

        // If there's no canonical at all on non-syndicated content, that's acceptable
        return !$syndicationDetected;
    }

    /**
     * Evaluates the operation score based on canonical tag usage.
     *
     * @return float A score based on the canonical tag validation
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // Check if the operation was successful
        if (!($factorData['success'] ?? false)) {
            return 0;
        }

        $syndicationDetected = $factorData['syndication_detected'] ?? false;
        $hasCanonical = $factorData['has_canonical'] ?? false;
        $isCrossDomain = $factorData['is_cross_domain'] ?? false;

        // Scoring logic varies based on syndication status
        if ($syndicationDetected) {
            // For syndicated content, the score is 1.0 only with proper cross-domain canonical
            if ($hasCanonical && $isCrossDomain) {
                return 1.0;
            }
            // Serious issue: same-domain canonical on syndicated content
            if ($hasCanonical) {
                return 0.3; // Incorrect canonical implementation
            }
            // Critical issue: no canonical on syndicated content
            return 0.0;
        } else {
            // For non-syndicated content:
            if ($hasCanonical && !$isCrossDomain) {
                return 1.0; // Correct same-domain canonical
            }
            if ($hasCanonical && $isCrossDomain) {
                return 0.5; // Suspicious cross-domain canonical with no syndication signals
            }
            // No canonical on regular content it is acceptable but not ideal
            return 0.7;
        }
    }

    /**
     * Provides suggestions for improving canonical tag usage.
     *
     * @return array An array of suggestion issue types
     */
    public function suggestions(): array
    {
        $suggestions = [];
        $factorData = $this->value;

        $syndicationDetected = $factorData['syndication_detected'] ?? false;
        $hasCanonical = $factorData['has_canonical'] ?? false;
        $isCrossDomain = $factorData['is_cross_domain'] ?? false;

        // Only provide suggestions when the score indicates issues
        if ($this->score <= 0.7) {
            // Case 1: Syndicated AND No canonical (Score 0.0)
            if ($syndicationDetected && !$hasCanonical) {
                $suggestions[] = Suggestion::MISSING_CANONICAL_TAG;
                // Could also add a DUPLICATE_CONTENT_UNCANONICALIZED suggestion here
                // $suggestions[] = Suggestion::DUPLICATE_CONTENT_UNCANONICALIZED;
            }
            // Case 2: Syndicated AND Canonical exists AND Canonical is NOT cross-domain (Score 0.3)
            // Implies: $syndicationDetected && $hasCanonical && !$isCrossDomain
            elseif ($syndicationDetected && $hasCanonical && !$isCrossDomain) {
                $suggestions[] = Suggestion::INCORRECT_CANONICAL_TAG; // Or Suggestion::INCORRECT_CANONICAL_TARGET
            }
            // Case 3: NOT Syndicated AND Canonical exists AND Canonical IS cross-domain (Score 0.5)
            // Implies: !$syndicationDetected && $hasCanonical && $isCrossDomain
            elseif (!$syndicationDetected && $hasCanonical && $isCrossDomain) {
                $suggestions[] = Suggestion::INCORRECT_CANONICAL_TAG; // Or Suggestion::INCORRECT_CANONICAL_TARGET
            }
            // Case 4: NOT Syndicated AND No canonical (Score 0.7)
            // Implies:$syndicationDetected && !$hasCanonical
            elseif (!$syndicationDetected && !$hasCanonical) {
                // This is not ideal, even if acceptable. Suggest adding a canonical.
                $suggestions[] = Suggestion::MISSING_CANONICAL_TAG;
            }
        }

        return $suggestions;
    }
}