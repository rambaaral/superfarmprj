<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\HeaderTagsStructure\FixingHeaderConsistencyOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\HeaderTagsStructure\HeaderHierarchyCheckOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\HeaderTagsStructure\KeywordsInHeaderCheckOperation;

/**
 * Class HeaderTagsStructureFactor
 *
 */
#[SeoMeta(
    name: 'Header Tags Structure',
    weight: WeightConfiguration::WEIGHT_HEADER_TAGS_STRUCTURE_FACTOR,
    description: 'Analyzes HTML heading tags (h1-h6) hierarchy, keyword usage, and structural consistency to enhance content organization and SEO effectiveness.'
)]
class HeaderTagsStructureFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        HeaderHierarchyCheckOperation::class,
        KeywordsInHeaderCheckOperation::class,
        FixingHeaderConsistencyOperation::class,
    ];
}