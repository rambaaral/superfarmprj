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
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class AltTextPresenceCheckOperation
 *
 * This operation validates if every image on a page has an alt text attribute.
 * It helps improve accessibility and SEO by ensuring images have a descriptive text.
 */
#[SeoMeta(
    name: 'Alt Text Presence Check',
    weight: WeightConfiguration::WEIGHT_ALT_TEXT_PRESENCE_CHECK_OPERATION,
    description: 'Validates if every image on a page has an alt text attribute for improved accessibility and SEO optimization.',
)]
class AltTextPresenceCheckOperation extends Operation implements OperationInterface
{
    /**
     * Analyzes all images on a page and checks if they have alt text attributes.
     *
     * @return array|null The analysis results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        // Get the page content
        $htmlContent = $this->contentProvider->getContent($this->postId);
        if (empty($htmlContent)) {
            return [
                'success' => false,
                'message' => __('Empty page content', 'beyondseo'),
                'images' => [],
                'total_images' => 0,
                'images_with_alt' => 0,
                'images_without_alt' => 0,
            ];
        }
        return $this->contentProvider->analyzeImageAltTextAttributes($htmlContent);
    }

    /**
     * Calculate a score based on the percentage of images that have an alt text
     * and the quality of those alt texts.
     *
     * @return float A score between 0 and 1
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        $totalImages = $factorData['total_images'] ?? 0;

        // If no images are found, return a perfect score
        if ($totalImages === 0) {
            return 1.0;
        }

        // Calculate the ratio of images with alt text
        $altTextRatio = $factorData['alt_text_ratio'] ?? 0;

        // Evaluate the quality of an alt text for those images that have it
        $qualitySum = 0;
        $imagesWithAlt = 0;

        foreach ($factorData['images'] ?? [] as $image) {
            if ($image['has_alt']) {
                $qualitySum += $image['quality_score'];
                $imagesWithAlt++;
            }
        }

        $qualityScore = $imagesWithAlt > 0 ? $qualitySum / $imagesWithAlt : 0;

        // The final score combines coverage (80%) and quality (20%)
        return ($altTextRatio * 0.8) + ($qualityScore * 0.2);
    }

    /**
     * Provide suggestions based on the alt text analysis.
     *
     * @return array An array of suggestions
     */
    public function suggestions(): array
    {
        $suggestions = [];

        $factorData = $this->value;

        $totalImages = $factorData['total_images'] ?? 0;
        $imagesWithoutAlt = $factorData['images_without_alt'] ?? 0;

        // No images mean any suggestions needed
        if ($totalImages === 0) {
            return $suggestions;
        }

        // If any images are missing alt text, suggest optimization
        if ($imagesWithoutAlt > 0) {
            $suggestions[] = Suggestion::MISSING_IMAGE_ALT_TEXT;
        }

        return $suggestions;
    }
}