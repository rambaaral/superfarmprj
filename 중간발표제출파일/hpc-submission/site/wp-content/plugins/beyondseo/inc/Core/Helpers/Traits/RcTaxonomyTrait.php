<?php
/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
/** @noinspection RegExpUnnecessaryNonCapturingGroup */
/** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Helpers\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Trait RcTaxonomyTrait
 */
trait RcTaxonomyTrait
{
    use RcLoggerTrait;

    /**
     * Get a category URL for the current post
     *
     * @return string URL of a category or empty string if none found
     */
    public function getCategoryUrl(): string
    {
        if (empty($this->postId)) {
            return '';
        }

        $categories = get_the_category($this->postId);

        if (!empty($categories)) {
            return get_category_link($categories[0]->term_id);
        }

        return '';
    }
}