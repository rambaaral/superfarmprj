<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataIdentifiersDto
 *
 * This class represents the identifiers data for a graph in schema markup.
 * It includes properties for SKU, GTIN, MPN, and ISBN.
 */
class GraphDataIdentifiersDto
{
    public ?string $sku = null;
    public ?string $gtin = null;
    public ?string $mpn = null;
    public ?string $isbn = null;
}
