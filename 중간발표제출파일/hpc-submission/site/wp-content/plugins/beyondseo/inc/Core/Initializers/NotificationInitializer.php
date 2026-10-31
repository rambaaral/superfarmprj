<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Initializers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\NotificationManager;
use RankingCoach\Inc\Core\UpsellNotificationManager;
use RankingCoach\Inc\Interfaces\InitializerInterface;

/**
 * Class NotificationInitializer
 */
class NotificationInitializer implements InitializerInterface {
	/**
	 * Initializes the notification.
	 */
	public function initialize(): void
    {
		// Initialize the notification
		NotificationManager::instance();
        UpsellNotificationManager::instance();
	}
}