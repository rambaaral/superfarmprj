<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\ImageOptimization;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class ImageCompressionValidationOperation
 *
 * This operation checks if images on a page are properly compressed without losing noticeable quality.
 * It analyzes image file sizes and provides suggestions for optimization when images exceed
 * the defined threshold (e.g., 200KB).
 */
#[SeoMeta(
    name: 'Image Compression Validation',
    weight: WeightConfiguration::WEIGHT_IMAGE_COMPRESSION_VALIDATION_OPERATION,
    description: 'Checks images for excessive file sizes, assessing compression efficiency. Calculates average size and identifies any files above a set threshold, advising compression improvements to speed up page load and enhance SEO.',
)]
class ImageCompressionValidationOperation extends Operation implements OperationInterface
{
    /**
     * Performs image compression validation for the specified post.
     * This method analyzes all images on the page and checks their file sizes.
     *
     * @return array|null The analysis results or null if invalid post-ID
     */
    public function run(): ?array
    {
        // Get page URL
        $pageUrl = $this->contentProvider->getPostUrl($this->postId);
        
        // Get full HTML content
        $htmlContent = $this->contentProvider->getContent($this->postId);
        
        if (empty($htmlContent)) {
            return [
                'success' => false,
                'message' => __('Failed to retrieve content', 'beyondseo'),
            ];
        }

        // Extract and analyze images
        $imageAnalysis = $this->contentProvider->analyzePageImages($htmlContent, $pageUrl, $this->postId);
        
        // Prepare analysis results
        return [
            'success' => true,
            'message' => __('Image compression analysis completed', 'beyondseo'),
            'url' => $pageUrl,
            'total_images' => count($imageAnalysis['images']),
            'oversized_images' => $imageAnalysis['oversized_count'],
            'total_image_size' => $imageAnalysis['total_size'],
            'average_image_size' => $imageAnalysis['total_images'] > 0 ?
                $imageAnalysis['total_size'] / $imageAnalysis['total_images'] : 0,
            'images' => $imageAnalysis['images'],
            'optimization_potential' => $this->contentProvider->analyseImageOptimizationPotential($imageAnalysis),
        ];
    }

    /**
     * Evaluates the image compression score.
     *
     * @return float Score based on image compression (0-1)
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;
        
        // If no images found, give a perfect score
        if (($factorData['total_images'] ?? 0) === 0) {
            return 1.0;
        }
        
        // Calculate score based on the percentage of properly sized images
        $totalImages = $factorData['total_images'] ?? 0;
        $oversizedImages = $factorData['oversized_images'] ?? 0;
        
        if ($totalImages === 0) {
            return 1.0;
        }
        
        $properlyCompressedRatio = 1 - ($oversizedImages / $totalImages);
        
        // Adjust score based on optimization potential
        $optimizationPotential = $factorData['optimization_potential'] ?? 0;
        
        // The final score is weighted between properly compressed ratio (70%) and optimization potential (30%)
        return ($properlyCompressedRatio * 0.7) + ((1 - $optimizationPotential) * 0.3);
    }

    /**
     * Provides suggestions for improving image compression.
     *
     * @return array Suggestions for improvement
     */
    public function suggestions(): array
    {
        $factorData = $this->value;
        $suggestions = [];
        
        if (empty($factorData)) {
            return $suggestions;
        }
        
        // If no images found, no suggestions needed
        if (($factorData['total_images'] ?? 0) === 0) {
            return $suggestions;
        }
        
        $oversizedImages = $factorData['oversized_images'] ?? 0;
        $totalImages = $factorData['total_images'] ?? 0;
        
        // If more than 25% of images are oversized, suggest compression
        if ($totalImages > 0 && ($oversizedImages / $totalImages) > 0.25) {
            $suggestions[] = Suggestion::UNOPTIMIZED_IMAGES;
        }
        
        return $suggestions;
    }
}