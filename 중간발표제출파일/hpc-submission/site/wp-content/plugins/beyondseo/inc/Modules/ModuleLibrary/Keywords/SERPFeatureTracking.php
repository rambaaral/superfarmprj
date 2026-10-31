<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Keywords;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class SERPFeatureTracking
 */
class SERPFeatureTracking extends BaseModule {

	/**
	 * SERPFeatureTracking constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
	public function __construct(ModuleManager $moduleManager) {
		$initialization = [
			'title' => 'SERP Feature Tracking',
			'description' => 'Track your website\'s performance in SERP features (featured snippets, knowledge panels, etc.) to optimize your content and improve search visibility.',
			'version' => '1.0.0',
			'name' => 'serp_feature_tracking',
			'priority' => 12,
			'dependencies' => [],
			'settings' => [['key' => 'target_keywords', 'type' => 'array', 'default' => [], 'description' => 'Enter an array of target keywords to track for SERP features (e.g., [\'SEO\', \'WordPress\']).'], ['key' => 'target_search_engine', 'type' => 'string', 'default' => 'google', 'description' => 'Select the target search engine for SERP feature tracking (e.g., "google", "bing").']],
			'explain' => 'This module tracks which SERP features your website appears in for your target keywords.  It provides data on featured snippet appearances, knowledge panel listings, and other SERP features, helping you understand how your content is being displayed in search results and identify opportunities to optimize for specific features.',
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