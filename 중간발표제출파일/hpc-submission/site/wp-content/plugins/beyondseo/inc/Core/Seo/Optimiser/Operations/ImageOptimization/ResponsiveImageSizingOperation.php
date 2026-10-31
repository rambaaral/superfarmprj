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
 * Class ResponsiveImageSizingOperation
 *
 * This operation checks if images on a page properly use the srcset attribute,
 * which allows browsers to load differently sized images based on the device's screen size.
 * This is a fundamental aspect of technical SEO that improves page load speed,
 * enhances mobile experience, and reduces bandwidth usage.
 */
#[SeoMeta(
    name: 'Responsive Image Sizing',
    weight: WeightConfiguration::WEIGHT_RESPONSIVE_IMAGE_SIZING_OPERATION,
    description: 'Validates usage of the srcset attribute so images load appropriate sizes on different devices. Identifies pages with too many non-responsive images and advises implementing responsive techniques to improve performance and mobile SEO.',
)]
class ResponsiveImageSizingOperation extends Operation implements OperationInterface
{
    // Threshold for determining if a page has too many non-responsive images
    private const NON_RESPONSIVE_THRESHOLD = 0.3; // 30% of images
    
    // Minimum number of images required for analysis
    private const MIN_IMAGES_FOR_ANALYSIS = 2;

    /**
     * Performs responsive image sizing validation for the specified post.
     * This method analyzes all images on the page and checks if they use srcset attributes.
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

        // Analyze responsive image usage
        $responsiveAnalysis = $this->contentProvider->analyzeResponsiveImages($htmlContent, $pageUrl);
        
        // Prepare analysis results
        return [
            'success' => true,
            'message' => __('Responsive image sizing analysis completed', 'beyondseo'),
            'url' => $pageUrl,
            'total_images' => $responsiveAnalysis['total_images'],
            'responsive_images' => $responsiveAnalysis['responsive_images'],
            'non_responsive_images' => $responsiveAnalysis['non_responsive_images'],
            'responsive_percentage' => $responsiveAnalysis['responsive_percentage'],
            'images' => $responsiveAnalysis['images'],
            'has_sizes_attribute' => $responsiveAnalysis['has_sizes_attribute'],
            'potential_bandwidth_savings' => $responsiveAnalysis['potential_bandwidth_savings'],
        ];
    }

    /**
     * Evaluates the responsive image sizing score.
     *
     * @return float Score based on responsive image usage (0-1)
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // If no images or fewer than a minimum required, give a perfect score
        $totalImages = $factorData['total_images'] ?? 0;
        if ($totalImages < self::MIN_IMAGES_FOR_ANALYSIS) {
            return 1.0;
        }
        
        // Calculate score based on the percentage of responsive images
        $responsiveImages = $factorData['responsive_images'] ?? 0;
        $hasSizesAttribute = $factorData['has_sizes_attribute'] ?? 0;
        
        // Base score is directly proportional to the percentage of responsive images
        $baseScore = $responsiveImages / $totalImages;
        
        // Bonus for using sizes attribute (up to 20% bonus)
        $sizesBonus = 0;
        if ($responsiveImages > 0) {
            $sizesPercentage = $hasSizesAttribute / $responsiveImages;
            $sizesBonus = $sizesPercentage * 0.2;
        }
        
        // Calculate the final score with bonus
        // Minimum score is 0.1, maximum is 1.0
        return min(1.0, max(0.1, $baseScore + $sizesBonus));
    }

    /**
     * Provides suggestions for improving responsive image usage.
     *
     * @return array Suggestions for improvement
     */
    public function suggestions(): array
    {
        $factorData = $this->value;
        $suggestions = [];
        
        // If no images or fewer than a minimum required, no suggestions needed
        $totalImages = $factorData['total_images'] ?? 0;
        if ($totalImages < self::MIN_IMAGES_FOR_ANALYSIS) {
            return $suggestions;
        }
        
        $responsiveImages = $factorData['responsive_images'] ?? 0;
        $nonResponsiveImages = $factorData['non_responsive_images'] ?? 0;
        $hasSizesAttribute = $factorData['has_sizes_attribute'] ?? 0;
        
        // If more than the threshold percentage of images is non-responsive, suggest adding srcset
        if ($totalImages > 0 && ($nonResponsiveImages / $totalImages) > self::NON_RESPONSIVE_THRESHOLD) {
            $suggestions[] = Suggestion::MISSING_RESPONSIVE_IMAGES;
            
            // If there are also large unoptimized images, add that suggestion too
            $potentialBandwidthSavings = $factorData['potential_bandwidth_savings'] ?? 0;
            if ($potentialBandwidthSavings > 500000) { // More than 500KB potential savings
                $suggestions[] = Suggestion::UNOPTIMIZED_IMAGES;
            }
        }
        
        // If responsive images don't have a size attribute, suggest adding it
        if ($responsiveImages > 0 && $hasSizesAttribute < $responsiveImages * 0.5) {
            $suggestions[] = Suggestion::INCOMPLETE_RESPONSIVE_IMAGES;
        }
        
        return $suggestions;
    }
}