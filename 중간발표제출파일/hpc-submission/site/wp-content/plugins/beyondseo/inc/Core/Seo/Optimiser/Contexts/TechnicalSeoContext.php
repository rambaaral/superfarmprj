<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Contexts;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\OptimiserContext;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\TechnicalSeo\OptimizeUrlStructureFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\TechnicalSeo\SchemaMarkupFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\TechnicalSeo\SearchEngineIndexationFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\TechnicalSeo\UseCanonicalTagsFactor;

/**
 * Class TechnicalSEOContext
 * 
 * Represents the technical SEO context for analysis.
 */
#[SeoMeta(
    name: 'Technical Seo',
    weight: WeightConfiguration::WEIGHT_TECHNICAL_SEO_CONTEXT,
    description: 'Analyzes and optimizes technical aspects of SEO, including URL structure, canonical tags, schema markup, and search engine indexation.',
)]
class TechnicalSeoContext extends OptimiserContext
{
    /** @var array $contextFactors List of SEO factors that are part of this context */
    protected static array $contextFactors = [
        OptimizeUrlStructureFactor::class,
        UseCanonicalTagsFactor::class,
        SchemaMarkupFactor::class,
        SearchEngineIndexationFactor::class
    ];
}