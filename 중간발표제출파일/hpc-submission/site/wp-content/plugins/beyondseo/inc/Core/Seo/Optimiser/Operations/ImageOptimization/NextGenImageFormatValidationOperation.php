<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\ImageOptimization;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class NextGenImageFormatValidationOperation
 *
 * This operation validates if next-gen image formats (WebP, AVIF) are being used
 * instead of older formats like JPEG or PNG. It analyzes all images on a page
 * and provides suggestions for converting to more efficient formats.
 */
#[SeoMeta(
    name: 'NextGen Image Format Validation',
    weight: WeightConfiguration::WEIGHT_NEXT_GEN_IMAGE_FORMAT_VALIDATION_OPERATION,
    description: 'Assesses whether page images use modern formats like WebP or AVIF instead of older JPEG or PNG. Reports proportion of legacy files and recommends converting them to enhance load speed and compatibility.',
)]
class NextGenImageFormatValidationOperation extends Operation implements OperationInterface
{
    // Threshold for determining if a page has too many legacy format images
    private const LEGACY_FORMAT_THRESHOLD = 0.5; // 50% of images

    /**
     * Performs next-gen image format validation for the specified post.
     * This method analyzes all images on the page and checks their formats.
     *
     * @return array|null The analysis results or null if invalid post-ID
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get page URL
        $pageUrl = $this->contentProvider->getPostUrl($postId);
        
        // Get full HTML content
        $htmlContent = $this->contentProvider->getContent($postId);
        
        if (empty($htmlContent)) {
            return [
                'success' => false,
                'message' => __('Failed to retrieve content', 'beyondseo'),
            ];
        }

        // Extract and analyze images
        $imageAnalysis = $this->contentProvider->analyzeImageFormats($htmlContent, $pageUrl);
        
        // Prepare analysis results
        return [
            'success' => true,
            'message' => __('Next-gen image format analysis completed', 'beyondseo'),
            'url' => $pageUrl,
            'total_images' => count($imageAnalysis['images']),
            'legacy_format_images' => $imageAnalysis['legacy_format_count'],
            'next_gen_format_images' => $imageAnalysis['next_gen_format_count'],
            'legacy_format_percentage' => $imageAnalysis['total_images'] > 0 ?
                ($imageAnalysis['legacy_format_count'] / $imageAnalysis['total_images']) * 100 : 0,
            'potential_savings' => $imageAnalysis['potential_savings'],
            'images' => $imageAnalysis['images'],
        ];
    }

    /**
     * Evaluates the next-gen image format score.
     *
     * @return float Score based on next-gen image format usage (0-1)
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;
        
        // If no images found, give a perfect score
        if (($factorData['total_images'] ?? 0) === 0) {
            return 1.0;
        }
        
        // Calculate score based on the percentage of next-gen format images
        $totalImages = $factorData['total_images'] ?? 0;
        $legacyFormatImages = $factorData['legacy_format_images'] ?? 0;
        $nextGenFormatImages = $factorData['next_gen_format_images'] ?? 0;
        $potentialSavings = $factorData['potential_savings'] ?? 0;
        
        if ($totalImages === 0) {
            return 1.0;
        }
        
        // Calculate the percentage of next-gen format images and legacy format images
        $nextGenPercentage = $nextGenFormatImages / $totalImages;
        $legacyPercentage = $legacyFormatImages / $totalImages;
        
        // Base score is directly proportional to the percentage of next-gen images
        // A site with all next-gen images gets a perfect base score
        $baseScore = $nextGenPercentage;
        
        // Apply penalties for a high percentage of legacy formats
        // The more legacy format images, the higher the penalty
        $legacyPenalty = 0;
        if ($legacyPercentage > self::LEGACY_FORMAT_THRESHOLD) {
            // Calculate penalty based on how much the legacy percentage exceeds the threshold,
            // Maximum penalty is 0.3 when all images are a legacy format
            $legacyPenalty = min(0.3, ($legacyPercentage - self::LEGACY_FORMAT_THRESHOLD) * 0.6);
        }
        
        // Apply penalty for potential savings
        // The higher the potential savings, the higher the penalty
        $savingsPenalty = 0;
        if ($potentialSavings > 0) {
            // The maximum penalty is 0.2 when potential savings exceed 1MB
            $savingsPenalty = min(0.2, $potentialSavings / 5000000);
        }
        
        // Calculate final score with penalties
        // Minimum score is 0.2, maximum is 1.0
        return max(0.2, $baseScore - $legacyPenalty - $savingsPenalty);
    }

    /**
     * Provides suggestions for improving image formats.
     *
     * @return array Suggestions for improvement
     */
    public function suggestions(): array
    {
        $factorData = $this->value;
        $suggestions = [];
        
        // If no images found, no suggestions needed
        if (($factorData['total_images'] ?? 0) === 0) {
            return $suggestions;
        }
        
        $totalImages = $factorData['total_images'] ?? 0;
        $legacyFormatImages = $factorData['legacy_format_images'] ?? 0;
        $nextGenFormatImages = $factorData['next_gen_format_images'] ?? 0;
        $potentialSavings = $factorData['potential_savings'] ?? 0;
        
        // If more than 50% of images are in legacy formats, suggest conversion to next-gen formats
        if ($totalImages > 0 && ($legacyFormatImages / $totalImages) > self::LEGACY_FORMAT_THRESHOLD) {
            $suggestions[] = Suggestion::LEGACY_IMAGE_FORMATS;
        }
        
        // If there are no next-gen format images at all, suggest implementing WebP support
        if ($totalImages > 0 && $nextGenFormatImages === 0) {
            $suggestions[] = Suggestion::MISSING_WEBP_SUPPORT;
        }
        
        // If there are large unoptimized images (based on potential savings),
        // Add the general image optimization suggestion
        if ($potentialSavings > 500000) { // More than 500KB potential savings
            $suggestions[] = Suggestion::UNOPTIMIZED_IMAGES;
        }
        
        return $suggestions;
    }
}
