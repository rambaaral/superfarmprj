<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\TechnicalSeo;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\SearchEngineIndexation\RobotsMetaTagValidationOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\SearchEngineIndexation\RobotsTxtValidationOperation;

/**
 * Class SearchEngineIndexationFactor
 *
 * This class is responsible for ensuring that the website is indexed by search engines.
 */
#[SeoMeta(
    name: 'Search Engine Indexation',
    weight: WeightConfiguration::WEIGHT_SEARCH_ENGINE_INDEXATION_FACTOR,
    description: 'Evaluates website indexability through search engine presence, robots.txt configuration, meta directives, and security status checks.',
)]

class SearchEngineIndexationFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        RobotsTxtValidationOperation::class,
        RobotsMetaTagValidationOperation::class,
    ];
}
