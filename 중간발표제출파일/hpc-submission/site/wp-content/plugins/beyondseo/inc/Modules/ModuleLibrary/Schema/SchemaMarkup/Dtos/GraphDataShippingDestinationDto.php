<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataShippingDestinationDto
 *
 * This class represents the shipping destination data for a graph in schema markup.
 * It includes properties for country, states, postal codes, rate, and delivery time.
 */
class GraphDataShippingDestinationDto
{
    public ?string $country = null;
    public ?string $states = null;      // JSON encoded array
    public ?string $postalCodes = null; // JSON encoded array
    public ?string $rate = null;
    public ?GraphDataDeliveryTimeDto $deliveryTime = null;
}
