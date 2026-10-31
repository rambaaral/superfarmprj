<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataAudienceDto
 *
 * This class represents the audience data for a graph in schema markup.
 * It includes properties
 *
 */
class GraphDataAudienceDto
{
    public ?string $gender = null;
    public ?string $minimumAge = null;
    public ?string $maximumAge = null;
}
