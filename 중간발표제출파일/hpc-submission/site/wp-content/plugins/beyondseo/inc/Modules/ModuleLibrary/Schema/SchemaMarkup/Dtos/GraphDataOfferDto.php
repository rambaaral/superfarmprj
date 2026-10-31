<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataOfferDto
 *
 * This class represents the offer data for a graph in schema markup.
 * It includes properties for price, currency, valid until date, and availability.
 */
class GraphDataOfferDto
{
    public ?string $price = null;
    public ?string $currency = null;
    public ?string $validUntil = null;
    public ?string $availability = null;
}
