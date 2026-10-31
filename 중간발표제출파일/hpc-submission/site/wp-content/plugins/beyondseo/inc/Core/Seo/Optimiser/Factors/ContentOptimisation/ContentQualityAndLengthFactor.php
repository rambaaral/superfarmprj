<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentQualityAndLength\ContentLengthValidationOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentQualityAndLength\MultimediaInclusionCheckOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentQualityAndLength\ReadabilityValidationOperation;

/**
 * Class ImproveContentQualityAndLength
 */
#[SeoMeta(
    name: 'Content Quality And Length',
    weight: WeightConfiguration::WEIGHT_CONTENT_QUALITY_AND_LENGTH_FACTOR,
    description: 'Evaluates content length, depth, readability, and multimedia inclusion to enhance overall content quality and user engagement.',
)]
class ContentQualityAndLengthFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        ContentLengthValidationOperation::class,
        ReadabilityValidationOperation::class,
        MultimediaInclusionCheckOperation::class,
    ];
}
