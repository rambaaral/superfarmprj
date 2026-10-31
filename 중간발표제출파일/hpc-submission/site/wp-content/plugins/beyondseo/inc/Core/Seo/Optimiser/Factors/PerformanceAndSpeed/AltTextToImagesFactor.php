<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\PerformanceAndSpeed;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\AltTextToImages\AltTextPresenceCheckOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\AltTextToImages\DescriptiveAltTextOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\AltTextToImages\PrimaryKeywordInAltTextOperation;

/**
 * Class AltTextToImagesFactor
 *
 * This class is responsible for ensuring that all images on the website have an appropriate alt text.
 */
#[SeoMeta(
    name: 'Alt Text To Images',
    weight: WeightConfiguration::WEIGHT_ALT_TEXT_TO_IMAGES_FACTOR,
    description: 'Analyzes alt text presence, keyword integration, and descriptive clarity to assess image accessibility and enhance SEO performance.',
)]
class AltTextToImagesFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        AltTextPresenceCheckOperation::class,
        PrimaryKeywordInAltTextOperation::class,
        DescriptiveAltTextOperation::class
    ];
}