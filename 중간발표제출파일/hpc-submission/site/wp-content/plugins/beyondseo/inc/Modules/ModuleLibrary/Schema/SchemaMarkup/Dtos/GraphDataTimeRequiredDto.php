<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataTimeRequiredDto
 *
 * This class represents the time required data for a graph in schema markup.
 * It includes properties for preparation and cooking time.
 */
class GraphDataTimeRequiredDto
{
    public ?string $preparation = null;
    public ?string $cooking = null;
}
