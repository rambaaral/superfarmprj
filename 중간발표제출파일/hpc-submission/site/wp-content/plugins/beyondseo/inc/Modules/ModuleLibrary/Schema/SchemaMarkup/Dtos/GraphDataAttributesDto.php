<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataAttributesDto
 *
 * This class represents the data attributes for a graph in schema markup.
 * It includes properties for material, color, pattern, size, and energy rating.
 */
class GraphDataAttributesDto
{
    public ?string $material = null;
    public ?string $color = null;
    public ?string $pattern = null;
    public ?string $size = null;
    public ?string $energyRating = null;
}
