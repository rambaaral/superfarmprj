<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\PageContentKeywords\KeywordDensityValidationOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\PageContentKeywords\KeywordDistributionOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\PageContentKeywords\RelatedKeywordInclusionOperation;

/**
 * Class PageContentKeywordsFactor
 */
#[SeoMeta(
    name: 'Page Content Keywords',
    weight: WeightConfiguration::WEIGHT_PAGE_CONTENT_KEYWORDS_FACTOR,
    description: 'Evaluates keyword frequency, contextual relevance, placement, and content freshness to improve search visibility, and enhance overall user engagement.',
)]
class PageContentKeywordsFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        KeywordDensityValidationOperation::class,
        KeywordDistributionOperation::class,
        RelatedKeywordInclusionOperation::class,
    ];
}
