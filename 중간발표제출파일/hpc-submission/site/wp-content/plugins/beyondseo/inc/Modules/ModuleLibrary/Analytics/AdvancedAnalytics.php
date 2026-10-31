<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class AdvancedAnalytics
 */
class AdvancedAnalytics extends BaseModule {

	/**
	 * AdvancedAnalytics constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Advanced SEO Analytics',
            'description' => 'Integrates with Google Analytics and other analytics platforms to provide in-depth SEO performance data, including organic traffic, bounce rate, user behavior, conversion tracking, and other key metrics. Helps users understand how SEO efforts impact website performance.',
            'version' => '1.0.0',
            'name' => 'advancedAnalytics',
            'priority' => 22,
            'dependencies' => [],
            'settings' => [['key' => 'google_analytics_id', 'type' => 'string', 'default' => '', 'description' => 'Your Google Analytics tracking ID (e.g., UA-XXXXXXXX-X). Enter this ID to enable integration with Google Analytics.'], ['key' => 'track_user_behavior_events', 'type' => 'boolean', 'default' => True, 'description' => 'Track specific user interactions and behavior events, such as scroll depth, link clicks, and form submissions.  Provides more granular data on user engagement.'], ['key' => 'enhanced_ecommerce_tracking', 'type' => 'boolean', 'default' => False, 'description' => 'Enable enhanced ecommerce tracking in Google Analytics to collect detailed data about product views, add-to-carts, purchases, and other ecommerce activities. Requires ecommerce functionality on your website.']],
            'explain' => 'After a user connects their Google Analytics account by entering their tracking ID, this module pulls in data about organic traffic, bounce rate, time on page, and other key metrics.  If enhanced ecommerce tracking is enabled, and the user is running WooCommerce, the module also collects detailed ecommerce data, such as product views and purchases.  This information is displayed in reports and dashboards within the SEO plugin, providing a comprehensive view of SEO performance and its impact on user behavior and conversions. The \'track user behavior events\' setting allows collecting more granular data on specific user interactions, such as how far users scroll down a page or which links they click.',
        ];
        parent::__construct($moduleManager, $initialization);
    }

    /**
     * Registers the hooks for the module.
     * @return void
     */
	public function initializeModule(): void {
		parent::initializeModule();
    }

	/**
	 * Create necessary SQL tables if they don't already exist.
	 * @param string $table_name
	 * @param string $charset_collate
	 * @return string
	 * @noinspection SqlNoDataSourceInspection
	 */
	protected function getTableSchema(string $table_name, string $charset_collate): string {
		return '';
	}
}
