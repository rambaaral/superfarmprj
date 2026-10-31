<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataQuantitativeValueDto
 *
 * This class represents the quantitative value data for a graph in schema markup.
 * It includes properties for minimum and maximum values, and unit code.
 */
class GraphDataQuantitativeValueDto
{
    public ?int $minValue = null;
    public ?int $maxValue = null;
    public ?string $unitCode = null;
}
