<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Initializers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\SeoOptimiserAjax;
use RankingCoach\Inc\Interfaces\InitializerInterface;

/**
 * Class SeoOptimiserAjaxInitializer
 */
class SeoOptimiserAjaxInitializer implements InitializerInterface {
    /**
     * Initialize the SEO Optimiser Ajax functionality
     */
    public function initialize(): void
    {
        // Initialize the Ajax handler
        new SeoOptimiserAjax();
    }
}