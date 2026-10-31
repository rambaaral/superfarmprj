<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataDeliveryTimeDto
 *
 * This class represents the delivery time data for a graph in schema markup.
 * It includes properties for handling time and transit time.
 */
class GraphDataDeliveryTimeDto
{
    public ?GraphDataQuantitativeValueDto $handlingTime = null;
    public ?GraphDataQuantitativeValueDto $transitTime = null;
}
