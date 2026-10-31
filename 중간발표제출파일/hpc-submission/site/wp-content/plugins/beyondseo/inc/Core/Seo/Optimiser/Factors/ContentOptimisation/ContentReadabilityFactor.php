<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentReadability\AudienceTargetedAdjustmentsOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentReadability\ContentFormattingValidationOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentReadability\ReadabilityScoreValidationOperation;

/**
 * Class ContentReadabilityFactor
 *
 * This class is responsible for evaluating the readability of content on a page.
 */
#[SeoMeta(
    name: 'Content Readability',
    weight: WeightConfiguration::WEIGHT_CONTENT_READABILITY_FACTOR,
    description: 'Checks how easy the content is to read, how it is structured, and whether it suits the intended audience to improve engagement and clarity.',
)]
class ContentReadabilityFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        ReadabilityScoreValidationOperation::class,
        ContentFormattingValidationOperation::class,
        AudienceTargetedAdjustmentsOperation::class,
    ];
}