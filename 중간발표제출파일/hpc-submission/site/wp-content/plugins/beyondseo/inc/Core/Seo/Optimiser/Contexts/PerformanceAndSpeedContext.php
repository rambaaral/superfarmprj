<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Contexts;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\OptimiserContext;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\PerformanceAndSpeed\AltTextToImagesFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\PerformanceAndSpeed\ImageOptimizationFactor;

/**
 * Class PerformanceAndSpeedContext
 * 
 * Represents the performance and speed context for SEO analysis.
 */
#[SeoMeta(
    name: 'Performance And Speed',
    weight: WeightConfiguration::WEIGHT_PERFORMANCE_AND_SPEED_CONTEXT,
    description: 'Analyzes and optimizes website performance and speed to enhance user experience and search engine rankings.',
)]
class PerformanceAndSpeedContext extends OptimiserContext
{
    /** @var array $contextFactors List of SEO factors that are part of this context */
    protected static array $contextFactors = [
        ImageOptimizationFactor::class,
        AltTextToImagesFactor::class,
    ];
}
