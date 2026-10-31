<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SchemaMarkupGetDataResponseDto
 * @property string[] $schemaTypes
 */
class SchemaMarkupGetDataResponseDto
{
    public bool $enableSchemaMarkup = false;
    public bool $enableLocalSeo = false;
    public ?string $defaultBusinessType = null;
    public ?string $defaultSchemaType = null;
    public ?string $currentPostSchemaType = null;
    public ?string $currentPostSchemaData = null;
    public array $schemaTypes = [];
    public bool $schemaGenerated = false;
    public bool $fromCache = false;
    public ?string $error = null;
}