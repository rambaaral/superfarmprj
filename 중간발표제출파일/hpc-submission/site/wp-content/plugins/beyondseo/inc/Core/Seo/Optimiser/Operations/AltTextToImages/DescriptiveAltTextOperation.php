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
 * Class DescriptiveAltTextOperation
 *
 * This class is responsible for analyzing the quality and relevance of an image alt text.
 * It validates whether an alt text is present, descriptive, and useful for both SEO and accessibility.
 * Poor alt text (e.g., filenames like "image1.jpg") is flagged as needing improvement.
 */
#[SeoMeta(
    name: 'Descriptive Alt Text',
    weight: WeightConfiguration::WEIGHT_DESCRIPTIVE_ALT_TEXT_OPERATION,
    description: 'Analyzes the quality of image alt text on a page, ensuring it is descriptive and useful for SEO and accessibility. Flags poor alt text (e.g., filenames) as needing improvement.',
)]
class DescriptiveAltTextOperation extends Operation implements OperationInterface
{
    /**
     * Analyzes alt text quality for images on the page.
     *
     * @return array|null Analysis results or null if content retrieval fails
     */
    public function run(): ?array
    {
        $postId = $this->postId;

        // Get the HTML content of the page
        $htmlContent = $this->contentProvider->getContent($postId);

        if (empty($htmlContent)) {
            return [
                'success' => false,
                'message' => __('Failed to retrieve page content', 'beyondseo'),
                'images_found' => 0,
            ];
        }

        // Extract images with their alt attributes
        $images = $this->contentProvider->extractImagesWithAltFromContent($htmlContent);

        if (empty($images)) {
            return [
                'success' => true,
                'message' => __('No images found on the page', 'beyondseo'),
                'images_found' => 0,
                'images_with_alt' => 0,
                'images_with_descriptive_alt' => 0,
                'percentage_with_alt' => 0,
                'percentage_with_descriptive_alt' => 0,
                'images' => [],
            ];
        }

        // Process each image and evaluate alt text quality
        $imagesTotal = count($images);
        $imagesWithAlt = 0;
        $imagesWithDescriptiveAlt = 0;
        $imageDetails = [];

        foreach ($images as $image) {
            $altText = $image['alt'] ?? '';
            $hasAlt = !empty($altText);

            if ($hasAlt) {
                $imagesWithAlt++;

                // Evaluate alt text quality using the helper method
                $altQualityScore = $this->contentProvider->evaluateAltTextQuality($altText);
                $isDescriptive = $altQualityScore >= 0.6; // Threshold for a descriptive alt text

                if ($isDescriptive) {
                    $imagesWithDescriptiveAlt++;
                }

                $imageDetails[] = [
                    'src' => $image['src'],
                    'alt' => $altText,
                    'has_alt' => true,
                    'is_descriptive' => $isDescriptive,
                    'quality_score' => $altQualityScore,
                    'issues' => $this->identifyAltTextIssues($altText),
                ];
            } else {
                $imageDetails[] = [
                    'src' => $image['src'],
                    'alt' => '',
                    'has_alt' => false,
                    'is_descriptive' => false,
                    'quality_score' => 0,
                    'issues' => [__('Missing alt text', 'beyondseo')],
                ];
            }
        }

        $percentageWithAlt = $imagesTotal > 0 ? ($imagesWithAlt / $imagesTotal) * 100 : 0;
        $percentageWithDescriptiveAlt = $imagesWithAlt > 0 ? ($imagesWithDescriptiveAlt / $imagesWithAlt) * 100 : 0;

        return [
            'success' => true,
            'message' => __('Alt text analysis completed', 'beyondseo'),
            'images_found' => $imagesTotal,
            'images_with_alt' => $imagesWithAlt,
            'images_with_descriptive_alt' => $imagesWithDescriptiveAlt,
            'percentage_with_alt' => round($percentageWithAlt, 2),
            'percentage_with_descriptive_alt' => round($percentageWithDescriptiveAlt, 2),
            'images' => $imageDetails,
        ];
    }

    /**
     * Identify specific issues with an alt text.
     *
     * @param string $altText The alt text to check
     * @return array Array of issues found
     */
    private function identifyAltTextIssues(string $altText): array
    {
        $issues = [];

        // Check if an alt text is just a filename
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $altText) || preg_match('/^image\d+$/i', $altText)) {
            $issues[] = __('Alt text appears to be a filename', 'beyondseo');
        }

        // Check if an alt text is too short to be descriptive
        if (str_word_count($altText) < 2) {
            $issues[] = __('Alt text is too short to be descriptive', 'beyondseo');
        }

        // Check for generic alt text patterns
        $genericPatterns = [
            '/^image$/i',
            '/^img$/i',
            '/^picture$/i',
            '/^photo$/i',
            '/^graphic$/i',
            '/^icon$/i',
            '/^logo$/i',
            '/^banner$/i',
            '/^button$/i',
            '/^image\s*\d+$/i',
        ];

        foreach ($genericPatterns as $pattern) {
            if (preg_match($pattern, $altText)) {
                $issues[] = __('Generic alt text detected', 'beyondseo');
                break;
            }
        }

        return $issues;
    }

    /**
     * Calculate the overall score for alt text quality.
     *
     * @return float A score between 0 and 1
     */
    public function calculateScore(): float
    {
        $data = $this->value;

        // If no images were found, return a neutral score
        if (!isset($data['images_found']) || $data['images_found'] === 0) {
            return 1.0; // No images mean any problems with alt text
        }

        // If images were found but none have an alt text, the score is very low
        if (isset($data['images_with_alt']) && $data['images_with_alt'] === 0) {
            return 0.0;
        }

        // Base score on percentage of images with alt text and percentage with descriptive alt text
        $percentWithAlt = isset($data['percentage_with_alt']) ? $data['percentage_with_alt'] / 100 : 0;
        $percentWithDescriptiveAlt = isset($data['percentage_with_descriptive_alt']) ? $data['percentage_with_descriptive_alt'] / 100 : 0;

        // Weighted score calculation:
        // - 40% weight to having alt text at all (accessibility minimum requirement)
        // - 60% weight to having descriptive alt text (SEO quality requirement)
        return (0.4 * $percentWithAlt) + (0.6 * $percentWithDescriptiveAlt);
    }

    /**
     * Generate suggestions based on alt text analysis.
     *
     * @return array Array of suggestion enums
     */
    public function suggestions(): array
    {
        $data = $this->value;
        $suggestions = [];

        // If the analysis failed or no images were found, return empty suggestions
        if (!isset($data['success']) || !$data['success'] || !isset($data['images_found']) || $data['images_found'] === 0) {
            return $suggestions;
        }

        // Missing alt texts - critical accessibility issue
        if (isset($data['percentage_with_alt']) && $data['percentage_with_alt'] < 100) {
            $suggestions[] = Suggestion::MISSING_IMAGE_ALT_TEXT;
        }

        // Alt texts present but not descriptive enough
        if (isset($data['percentage_with_alt'], $data['percentage_with_descriptive_alt']) && $data['percentage_with_alt'] > 0 && $data['percentage_with_descriptive_alt'] < 70) {
            $suggestions[] = Suggestion::MISSING_IMAGE_DESCRIPTIVE_ALT_TEXT;
        }

        // Check for specific problematic patterns like filenames or generic text
        $hasFilenameAltText = false;
        $hasGenericAltText = false;

        if (isset($data['images'])) {
            foreach ($data['images'] as $image) {
                if (!isset($image['issues'])) continue;

                foreach ($image['issues'] as $issue) {
                    if ($issue === __('Alt text appears to be a filename', 'beyondseo')) {
                        $hasFilenameAltText = true;
                    } elseif ($issue === __('Generic alt text detected', 'beyondseo')) {
                        $hasGenericAltText = true;
                    }
                }
            }
        }

        // Add specific suggestions based on detailed issues found
        if ($hasFilenameAltText || $hasGenericAltText) {
            $suggestions[] = Suggestion::UNOPTIMIZED_IMAGES;
        }

        // Use a custom approach to get unique enum values
        return $this->getUniqueEnumValues($suggestions);
    }
}