<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaDescriptionKeywords\DescriptionKeywordOveruseOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaDescriptionKeywords\PrimarySecondaryKeywordCheckOperation;

/**
 * Class MetaDescriptionKeywordsFactor
 *
 * It extends the base Factor class and can be used to evaluate the effectiveness of keyword assignment.
 */
#[SeoMeta(
    name: 'Meta Description Keywords',
    weight: WeightConfiguration::WEIGHT_META_DESCRIPTION_KEYWORDS_FACTOR,
    description: 'Analyzes keyword usage and positioning within meta descriptions to ensure relevance without oversaturation.',
)]
class MetaDescriptionKeywordsFactor extends Factor
{

    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        PrimarySecondaryKeywordCheckOperation::class,
        DescriptionKeywordOveruseOperation::class,
    ];
}