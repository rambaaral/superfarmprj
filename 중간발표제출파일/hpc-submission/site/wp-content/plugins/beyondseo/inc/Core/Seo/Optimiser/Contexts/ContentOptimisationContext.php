<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Contexts;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\OptimiserContext;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\AssignKeywordsFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\ContentQualityAndLengthFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\ContentReadabilityFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\FirstParagraphKeywordUsageFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\HeaderTagsStructureFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\MetaDescriptionFormatOptimizationFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\MetaDescriptionKeywordsFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\MetaTitleFormatOptimizationFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\MetaTitleKeywordsFactor;
use RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\PageContentKeywordsFactor;

/**
 * Class ContentOptimisationContext
 * 
 * Represents the content optimization context for SEO analysis.
 */
#[SeoMeta(
    name: 'Content Optimisation',
    weight: WeightConfiguration::WEIGHT_CONTENT_OPTIMISATION_CONTEXT,
    description: 'Analyzes and optimizes content for SEO by focusing on keyword usage, content quality, readability, and meta tags.',
)]
class ContentOptimisationContext extends OptimiserContext
{
    /** @var array $contextFactors List of SEO factors that are part of this context */
    protected static array $contextFactors = [
        AssignKeywordsFactor::class,
        MetaTitleKeywordsFactor::class,
        MetaDescriptionKeywordsFactor::class,
        PageContentKeywordsFactor::class,
        FirstParagraphKeywordUsageFactor::class,
        HeaderTagsStructureFactor::class,
        MetaTitleFormatOptimizationFactor::class,
        MetaDescriptionFormatOptimizationFactor::class,
        ContentQualityAndLengthFactor::class,
        ContentReadabilityFactor::class,
    ];
}