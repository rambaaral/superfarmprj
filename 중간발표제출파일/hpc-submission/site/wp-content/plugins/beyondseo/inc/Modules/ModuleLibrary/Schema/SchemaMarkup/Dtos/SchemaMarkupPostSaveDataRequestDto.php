<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SchemaMarkupPostSaveDataRequestDto
 */
class SchemaMarkupPostSaveDataRequestDto
{
    public string $postId;
    public ?string $selectedSchema = null;
}