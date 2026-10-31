<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\AssignKeywords\KeywordMappingContentOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\AssignKeywords\PrimarySecondaryKeywordsValidationOperation;

/**
 * Class AssignKeywordsFactor
 */
#[SeoMeta(
    name: 'Assign Keywords',
    weight: WeightConfiguration::WEIGHT_ASSIGN_KEYWORDS_FACTOR,
    description: 'Validates keyword selection, analyzes competition metrics, and prevents cannibalization across content.',
)]
class AssignKeywordsFactor extends Factor
{
    /** @var class-string[] Operations  */
    protected static array $operationsClasses = [
        PrimarySecondaryKeywordsValidationOperation::class,
        KeywordMappingContentOperation::class
    ];
}
