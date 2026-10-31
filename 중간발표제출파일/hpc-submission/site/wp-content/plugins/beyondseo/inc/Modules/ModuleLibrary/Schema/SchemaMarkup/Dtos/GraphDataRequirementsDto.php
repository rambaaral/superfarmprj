<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataRequirementsDto
 *
 * This class represents the requirements data for a graph in schema markup.
 * It includes properties for experience instead of education, experience, and degree.
 */
class GraphDataRequirementsDto
{
    public bool $experienceInsteadOfEducation = false;
    public ?string $experience = null;
    public ?string $degree = null;
}
