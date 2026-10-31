<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Traits\SingletonTrait;

/**
 * Class FrontendManager
 */
class FrontendManager {

	use SingletonTrait;

	/**
	 * FrontendManager constructor.
	 */
	public function __construct() {
		//
	}

	/**
	 * Initialize the frontend manager.
	 */
	public function init(): void {
        // Toolbar styles are now handled by ToolbarManager::enqueue_toolbar_assets()
	}
}