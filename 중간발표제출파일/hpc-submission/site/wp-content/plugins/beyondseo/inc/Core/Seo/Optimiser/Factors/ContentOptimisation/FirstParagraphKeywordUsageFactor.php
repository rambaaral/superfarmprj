<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\FirstParagraphKeywordUsage\FirstParagraphKeywordCheckOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\FirstParagraphKeywordUsage\FirstParagraphKeywordStuffingOperation;

/**
 * Class FirstParagraphKeywordUsageFactor
 *
 */
#[SeoMeta(
    name: 'First Paragraph Keyword Usage',
    weight: WeightConfiguration::WEIGHT_FIRST_PARAGRAPH_KEYWORD_USAGE_FACTOR,
    description: 'Evaluates keyword placement, density, and engagement in opening paragraphs to optimize topic relevance and reader connection.',
)]
class FirstParagraphKeywordUsageFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        FirstParagraphKeywordCheckOperation::class,
        FirstParagraphKeywordStuffingOperation::class,
    ];
}
