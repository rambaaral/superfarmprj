<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataEditionDto
 *
 * This class represents the edition data for a graph in schema markup.
 * It includes properties for name, book edition, author, ISBN, format, and date published.
 */
class GraphDataEditionDto
{
    public ?string $name = null;
    public ?string $bookEdition = null;
    public ?string $author = null;
    public ?string $isbn = null;
    public ?string $format = null;
    public ?string $datePublished = null;
}
