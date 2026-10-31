<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaDescriptionFormatOptimization\MetaDescriptionCtaValidationOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaDescriptionFormatOptimization\MetaDescriptionLengthCheckOperation;

/**
 * Class MetaDescriptionFormatOptimizationFactor
 *
 * This class is responsible for creating a meta-description tag for SEO optimization.
 */
#[SeoMeta(
    name: 'Meta Description Format Optimization',
    weight: WeightConfiguration::WEIGHT_META_DESCRIPTION_FORMAT_OPTIMIZATION_FACTOR,
    description: 'Ensures optimal meta descriptions with proper length and compelling call-to-action to improve click-through rates from search results.',
)]
class MetaDescriptionFormatOptimizationFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        MetaDescriptionLengthCheckOperation::class,
        MetaDescriptionCtaValidationOperation::class,
    ];
}