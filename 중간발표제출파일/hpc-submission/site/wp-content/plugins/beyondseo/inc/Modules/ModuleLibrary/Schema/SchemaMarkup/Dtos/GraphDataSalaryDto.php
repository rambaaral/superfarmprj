<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GraphDataSalaryDto
 *
 * This class represents the salary data for a graph in schema markup.
 * It includes properties for minimum, maximum, interval, and currency.
 */
class GraphDataSalaryDto
{
    public ?string $minimum = null;
    public ?string $maximum = null;
    public ?string $interval = null;
    public ?string $currency = null;
}
