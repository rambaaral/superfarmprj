<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataLocationDto
 *
 * This class represents the location data for a graph in schema markup.
 * It includes properties for street address, locality, postal code, region, country,
 * and additional properties for applicant location requirements.
 */
class GraphDataLocationDto
{
    public ?string $streetAddress = null;
    public ?string $locality = null;
    public ?string $postalCode = null;
    public ?string $region = null;
    public ?string $country = null;
    // For applicantLocationRequirements items
    public ?string $type = null;
    public ?string $name = null;
}
