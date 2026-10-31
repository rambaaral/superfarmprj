<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataReviewDto
 *
 * This class represents the review data for a graph in schema markup.
 * It includes properties for headline, content, rating, author, positive notes, and negative notes.
 */
class GraphDataReviewDto
{
    public ?string $headline = null;
    public ?string $content = null;
    public ?string $rating = null;
    public ?string $author = null;
    public ?string $positiveNotes = null;
    public ?string $negativeNotes = null;
}
