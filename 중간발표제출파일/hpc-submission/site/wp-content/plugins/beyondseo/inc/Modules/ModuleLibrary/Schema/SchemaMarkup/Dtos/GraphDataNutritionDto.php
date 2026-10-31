<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataNutritionDto
 *
 * This class represents the nutrition data for a graph in schema markup.
 * It includes properties for servings and calories.
 */
class GraphDataNutritionDto
{
    public ?string $servings = null;
    public ?string $calories = null;
}
