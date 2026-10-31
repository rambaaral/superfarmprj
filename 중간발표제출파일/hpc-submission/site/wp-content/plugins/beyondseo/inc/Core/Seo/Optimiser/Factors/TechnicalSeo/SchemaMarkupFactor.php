<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\TechnicalSeo;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\SchemaMarkup\SchemaMarkupValidationOperation;

/**
 * Class SchemaMarkupFactor
 *
 * This class is responsible for ensuring that schema markup is used correctly on a webpage.
 */
#[SeoMeta(
    name: 'Schema Markup',
    weight: WeightConfiguration::WEIGHT_SCHEMA_MARKUP_FACTOR,
    description: 'Analyzes structured data for proper schema usage and guideline adherence to improve indexing, rich results, and search performance.',
)]
class SchemaMarkupFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        SchemaMarkupValidationOperation::class,
    ];
}