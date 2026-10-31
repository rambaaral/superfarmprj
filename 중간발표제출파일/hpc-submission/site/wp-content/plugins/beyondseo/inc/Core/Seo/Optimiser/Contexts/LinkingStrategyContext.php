<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Contexts;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\OptimiserContext;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\LinkingStrategy\FixBrokenLinksOnPageFactor;

/**
 * Class LinkingStrategyContext
 * 
 * Represents the linking strategy context for SEO analysis.
 */
#[SeoMeta(
    name: 'Linking Strategy',
    weight: WeightConfiguration::WEIGHT_LINKING_STRATEGY_CONTEXT,
    description: 'Analyzes and optimizes internal and external linking strategies to enhance site authority and user navigation.',
)]
class LinkingStrategyContext extends OptimiserContext
{
    /** @var array $contextFactors List of SEO factors that are part of this context */
    protected static array $contextFactors = [
        FixBrokenLinksOnPageFactor::class,
    ];
}
