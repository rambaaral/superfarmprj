<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataRatingDto
 *
 * This class represents the rating data for a graph in schema markup.
 * It includes properties for minimum and maximum ratings.
 */
class GraphDataRatingDto
{
    public ?string $minimum = null;
    public ?string $maximum = null;
}
