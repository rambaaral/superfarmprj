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

/**
 * Class CanonicalTagValidationOperation
 *
 * This class is responsible for validating the presence and correctness of canonical tags on web pages.
 * It checks if a canonical tag exists, if it's properly formatted, and if it points to the expected URL.
 */
#[SeoMeta(
    name: 'Canonical Tag Validation',
    weight: WeightConfiguration::WEIGHT_CANONICAL_TAG_VALIDATION_OPERATION,
    description: 'Verifies that canonical tags are present and correctly point to the preferred URL. Ensures proper formatting to avoid duplicate content issues and guides updates when canonical references are missing or incorrect.',
)]
class CanonicalTagValidationOperation extends Operation implements OperationInterface
{
    /**
     * Validates the canonical tag for the given post-ID.
     *
     * @return array|null The validation results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get the expected URL for this post
        $expectedUrl = $this->contentProvider->getPostUrl($postId);

        // This is crucial as canonical tags are in the head section, not in the post content
        $html = $this->contentProvider->getContent($postId);
        if (!$html) {
            return [
                'success' => false,
                'message' => __('Failed to retrieve page content', 'beyondseo'),
                'canonical_tag_present' => false,
                'canonical_url' => '',
                'expected_url' => $expectedUrl,
                'is_valid' => false,
                'is_self_referential' => false,
                'has_multiple_canonicals' => false,
                'issues' => [__('Failed to retrieve page content', 'beyondseo')]
            ];
        }

        return array_merge([
                'success' => true,
                'message' => __('Canonical tag validation completed', 'beyondseo'),
            ], $this->contentProvider->extractCanonicalInfo($html, $postId)
        );
    }

    /**
     * Evaluate the operation value based on canonical tag validation.
     *
     * @return float A score based on the canonical tag validation
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // Calculate a score based on canonical tag presence and correctness
        $score = 0;

        // Check if the canonical tag is present
        if ($factorData['canonical_tag_present']) {
            $score += 0.5; // 50% of the score for having a canonical tag

            // Check if the canonical URL is valid
            if ($factorData['is_valid']) {
                $score += 0.3; // 30% of score for having a valid URL

                // Check if the canonical URL matches the expected URL
                if ($factorData['is_self_referential']) {
                    $score += 0.2; // 20% of score for matching expected URL
                }
            }

            // Penalize for multiple canonical tags
            if ($factorData['has_multiple_canonicals']) {
                $score -= 0.3; // Penalty for multiple canonical tags (SEO error)
            }
        }

        // Ensure the score is within the 0-1 range
        return max(0, min(1, $score));
    }

    /**
     * Get suggestions for the operation.
     *
     * @return array An array of suggestions
     */
    public function suggestions(): array
    {
        $suggestions = [];
        $factorData = $this->value;

        // If no canonical tag is present
        if (!$factorData['canonical_tag_present']) {
            $suggestions[] = Suggestion::MISSING_CANONICAL_TAG;
        }

        // If the canonical tag is present but invalid
        elseif (!$factorData['is_valid']) {
            $suggestions[] = Suggestion::INVALID_CANONICAL_TAG;
        }

        // If the canonical URL doesn't match the expected URL (incorrect implementation)
        elseif (!$factorData['is_self_referential']) {
            $suggestions[] = Suggestion::INCORRECT_CANONICAL_TARGET;
        }

        // If multiple canonical tags are present (serious SEO issue)
        if ($factorData['has_multiple_canonicals']) {
            $suggestions[] = Suggestion::MULTIPLE_CANONICAL_TAGS_FOUND;
        }

        return $suggestions;
    }
}