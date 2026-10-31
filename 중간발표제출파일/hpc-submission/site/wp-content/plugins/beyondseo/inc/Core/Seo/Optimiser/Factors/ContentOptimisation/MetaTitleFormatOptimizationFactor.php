<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaTitleFormatOptimization\MetaTitleLengthCheckOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaTitleFormatOptimization\MetaTitleQualityAnalyzerOperation;

/**
 * Class MetaTitleFormatOptimizationFactor
 *
 * This class is responsible for creating a meta-title tag for SEO optimization.
 */
#[SeoMeta(
    name: 'Meta Title Format Optimization',
    weight: WeightConfiguration::WEIGHT_META_TITLE_FORMAT_OPTIMIZATION_FACTOR,
    description: 'Evaluates meta title length and quality, ensuring optimal character count and structure for search engine visibility.',
)]
class MetaTitleFormatOptimizationFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        MetaTitleLengthCheckOperation::class,
        MetaTitleQualityAnalyzerOperation::class,
    ];
}