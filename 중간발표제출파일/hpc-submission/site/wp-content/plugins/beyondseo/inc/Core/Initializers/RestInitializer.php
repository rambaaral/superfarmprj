<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Initializers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Rest\RestManager;
use RankingCoach\Inc\Interfaces\InitializerInterface;

/**
 * Class RestInitializer
 * 
 * Initializes the REST API functionality for the plugin.
 * Handles both the external API routes and WordPress REST routes.
 */
class RestInitializer implements InitializerInterface
{
    /**
     * Initialize the REST API functionality.
     * 
     * @return void
     */
    public function initialize(): void
    {
        // Initialize the RestManager
        new RestManager();
        
        // Register legacy routes on a later priority
        add_action('rest_api_init', function() {
            $restManager = new RestManager();
            $restManager->registerLegacyRoutes();
        }, 20); // Higher priority number means it runs later
    }
}