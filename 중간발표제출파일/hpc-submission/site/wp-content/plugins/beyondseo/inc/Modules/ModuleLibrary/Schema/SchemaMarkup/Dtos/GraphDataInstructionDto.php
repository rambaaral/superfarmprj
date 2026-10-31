<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataInstructionDto
 *
 * This class represents the instruction data for a graph in schema markup.
 * It includes properties for name, text, and image.
 */
class GraphDataInstructionDto
{
    public ?string $name = null;
    public ?string $text = null;
    public ?string $image = null;
}
