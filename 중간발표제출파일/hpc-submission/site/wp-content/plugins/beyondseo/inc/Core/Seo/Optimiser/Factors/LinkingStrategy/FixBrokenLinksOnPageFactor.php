<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\LinkingStrategy;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\FixBrokenLinksOnPage\BrokenLinksIdentificationOperation;

/**
 * Class FixBrokenLinksOnPageFactor
 *
 * This class is responsible for fixing broken links on a webpage.
 */
#[SeoMeta(
    name: 'Fix Broken Links On Page',
    weight: WeightConfiguration::WEIGHT_FIX_BROKEN_LINKS_ON_PAGE_FACTOR,
    description: 'Identifies broken internal and external links, prioritizing critical issues affecting user experience and SEO performance. If no links are found on the page, this check is marked as passed by default.',
)]
class FixBrokenLinksOnPageFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        BrokenLinksIdentificationOperation::class
    ];
}