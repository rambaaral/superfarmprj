<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Local;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class LocalSEOEnhancements
 */
class LocalSEOEnhancements extends BaseModule {

	/**
	 * LocalSEOEnhancements constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
	public function __construct(ModuleManager $moduleManager) {
		$initialization = [
			'title' => 'Local SEO Enhancements (Google My Business Integration)',
			'description' => 'Connect and manage your Google My Business profile directly from your WordPress dashboard to enhance your local SEO presence.',
			'version' => '1.0.0',
			'name' => 'localSeoGmb',
			'priority' => 16,
			'dependencies' => [],
			'settings' => [['key' => 'gmb_api_key', 'type' => 'string', 'default' => '', 'description' => 'Enter your Google My Business API key to connect your account.'], ['key' => 'gmb_location_id', 'type' => 'string', 'default' => '', 'description' => 'Enter your Google My Business location ID.']],
			'explain' => 'Connect your Google My Business account to manage your business information, posts, reviews, and other GMB features directly within WordPress.  This integration streamlines your local SEO efforts and helps you maintain consistent and accurate business information across platforms.',
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