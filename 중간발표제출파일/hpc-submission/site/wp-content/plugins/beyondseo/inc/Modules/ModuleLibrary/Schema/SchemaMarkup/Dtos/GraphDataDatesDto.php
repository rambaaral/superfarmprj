<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataDatesDto
 *
 * This class represents the date-related data for a graph in schema markup.
 * It includes properties for publication, modification, posting, and expiration dates.
 */
class GraphDataDatesDto
{
    public bool $include = true;
    public ?string $datePublished = null;
    public ?string $dateModified = null;
    public ?string $datePosted = null;
    public ?string $dateExpires = null;
}
