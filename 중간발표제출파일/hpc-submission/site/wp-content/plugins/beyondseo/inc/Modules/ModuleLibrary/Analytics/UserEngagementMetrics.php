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
 * Class UserEngagementMetrics
 */
class UserEngagementMetrics extends BaseModule {

	/**
	 * UserEngagementMetrics constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'User Engagement Metrics Tracker',
            'description' => 'Tracks and analyzes user engagement metrics, such as time on page, scroll depth, bounce rate, and conversion rates, to assess content effectiveness and identify areas for improvement. Integrates with Advanced SEO Analytics for a comprehensive view of user behavior.',
            'version' => '1.0.0',
            'name' => 'userEngagementMetrics',
            'priority' => 27,
            'dependencies' => [],
            'settings' => [['key' => 'track_time_on_page', 'type' => 'boolean', 'default' => True, 'description' => 'Track the average time users spend on each page, providing insights into content engagement and readability.'], ['key' => 'track_scroll_depth', 'type' => 'boolean', 'default' => True, 'description' => 'Track how far users scroll down each page, helping identify content drop-off points and optimize content length and structure.'], ['key' => 'track_bounce_rate', 'type' => 'boolean', 'default' => True, 'description' => 'Track the bounce rate (percentage of users who leave after viewing only one page) to identify pages with poor user engagement or unclear calls to action.'], ['key' => 'conversion_goal', 'type' => 'string', 'default' => '', 'description' => 'Define a conversion goal, such as form submissions, product purchases, or newsletter sign-ups. This allows the module to track conversion rates and assess the effectiveness of your content in driving desired actions. (See documentation for setting up conversion goals).']],
            'explain' => 'This module tracks how long users spend on each page and how far they scroll.  It also monitors the bounce rate to identify pages that need improvement.  By defining a conversion goal, such as a form submission, the module can track the conversion rate of different pages and identify high-performing content. This data, combined with insights from \'Advanced SEO Analytics\', provides a complete understanding of user behavior and helps content creators optimize their content for maximum engagement and conversions.  For example, if users are consistently bouncing from a particular page, the module might suggest improving the page\'s readability, adding clearer calls to action, or redesigning the layout.',
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
