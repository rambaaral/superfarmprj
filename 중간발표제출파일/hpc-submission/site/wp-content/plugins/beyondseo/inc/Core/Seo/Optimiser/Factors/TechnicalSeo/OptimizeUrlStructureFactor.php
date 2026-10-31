<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\TechnicalSeo;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\OptimizeUrlStructure\HyphensInsteadOfUnderscoresOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\OptimizeUrlStructure\PrimaryKeywordInUrlOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\OptimizeUrlStructure\UrlLengthCheckOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\OptimizeUrlStructure\UrlReadabilityOperation;

/**
 * Class OptimizeURLStructureFactor
 *
 * This class is responsible for optimizing the URL structure of a website.
 */
#[SeoMeta(
    name: 'Optimize Url Structure',
    weight: WeightConfiguration::WEIGHT_OPTIMIZE_URL_STRUCTURE_FACTOR,
    description: 'Evaluates URLs for keyword inclusion, readability, proper length, and hyphen usage to improve search engine visibility.',
)]
class OptimizeUrlStructureFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        HyphensInsteadOfUnderscoresOperation::class,
        PrimaryKeywordInUrlOperation::class,
        UrlLengthCheckOperation::class,
        UrlReadabilityOperation::class,
    ];
}