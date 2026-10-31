<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\PerformanceAndSpeed;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\ImageOptimization\ImageCompressionValidationOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\ImageOptimization\NextGenImageFormatValidationOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\ImageOptimization\ResponsiveImageSizingOperation;

/**
 * Class ImageOptimizationFactor
 *
 * This class is responsible for ensuring that images on the website are properly optimized
 * for file size without losing noticeable quality.
 */
#[SeoMeta(
    name: 'Image Optimization',
    weight: WeightConfiguration::WEIGHT_IMAGE_OPTIMIZATION_FACTOR,
    description: 'Evaluates how images are compressed, formatted, and scaled responsively to reduce load times and improve usability across devices. If no images are found on the page, this check is marked as passed by default.',
)]

class ImageOptimizationFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        ImageCompressionValidationOperation::class,
        NextGenImageFormatValidationOperation::class,
        ResponsiveImageSizingOperation::class,
    ];
}