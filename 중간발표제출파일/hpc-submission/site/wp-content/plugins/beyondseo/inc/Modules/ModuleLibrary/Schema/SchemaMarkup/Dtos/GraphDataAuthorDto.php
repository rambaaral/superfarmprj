<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataAuthorDto
 *
 * This class represents the author data for a graph in schema markup.
 * It includes properties for the author's name and URL.
 */
class GraphDataAuthorDto
{
    public ?string $name = null;
    public ?string $url = null;
}
