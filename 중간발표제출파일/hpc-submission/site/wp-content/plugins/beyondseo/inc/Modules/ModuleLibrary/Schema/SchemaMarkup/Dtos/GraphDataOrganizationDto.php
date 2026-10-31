<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataOrganizationDto
 *
 * This class represents the organization data for a graph in schema markup.
 * It includes properties for name, URL, and image.
 */
class GraphDataOrganizationDto
{
    public ?string $name = null;
    public ?string $url = null;
    /** @var int|string|null $image ID or URL */
    public $image = null;
}
