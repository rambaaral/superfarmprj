<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Factors\TechnicalSeo;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\UseCanonicalTags\CanonicalTagValidationOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\UseCanonicalTags\CrossDomainCanonicalCheckOperation;
use RankingCoach\Inc\Core\Seo\Optimiser\Operations\UseCanonicalTags\DuplicateContentDetectionOperation;

/**
 * Class UseCanonicalTagsFactor
 *
 * This class is responsible for ensuring that canonical tags are used correctly on a webpage.
 */
#[SeoMeta(
    name: 'Use Canonical Tags',
    weight: WeightConfiguration::WEIGHT_USE_CANONICAL_TAGS_FACTOR,
    description: 'Evaluates canonical tag implementation, cross-domain references, and duplicate content detection to prevent search engine indexing issues.',
)]
class UseCanonicalTagsFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        CanonicalTagValidationOperation::class,
        CrossDomainCanonicalCheckOperation::class,
        DuplicateContentDetectionOperation::class,
    ];
}
