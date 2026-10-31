<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaTitleKeywords\PrimaryKeywordCheckOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaTitleKeywords\SecondaryKeywordsCheckOperation;

/**
 * Class MetaTitleKeywordsFactor
 *
 * This class represents a factor for checking the presence of keywords in the Meta Title.
 * It extends the base Factor class and can be used to evaluate the effectiveness of keyword assignment.
 */
#[SeoMeta(
    name: 'Meta Title Keywords',
    weight: WeightConfiguration::WEIGHT_META_TITLE_KEYWORDS_FACTOR,
    description: 'Evaluates primary and secondary keyword placement in meta titles, prioritizing optimal positioning for maximum search visibility.',
)]
class MetaTitleKeywordsFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        PrimaryKeywordCheckOperation::class,
        SecondaryKeywordsCheckOperation::class,
    ];
}