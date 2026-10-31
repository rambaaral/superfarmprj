<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class HreflangTagsInternational
 */
class HreflangTagsInternational extends BaseModule {

	/**
	 * HreflangTagsInternational constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
	public function __construct(ModuleManager $moduleManager) {
		$initialization = [
			'title' => 'International SEO (Hreflang Tags)',
			'description' => 'Manage hreflang tags and other locale-specific settings to optimize your website for international audiences and improve search visibility in different regions.',
			'version' => '1.0.0',
			'name' => 'international_seo',
			'priority' => 15,
			'dependencies' => [],
			'settings' => [['key' => 'default_language', 'type' => 'string', 'default' => 'en', 'description' => 'Set the default language for your website (e.g., "en", "es", "fr").'], ['key' => 'supported_languages', 'type' => 'array', 'default' => [], 'description' => 'Add an array of supported languages and their corresponding URLs (e.g., [["language" => "es", "url" => "https://example.com/es"], ["language" => "fr", "url" => "https://example.com/fr"]]).']],
			'explain' => 'For websites targeting multiple languages and regions, this module helps manage hreflang tags. Specify the languages and corresponding URLs for different versions of your website, and the module automatically generates the correct hreflang tags to signal to search engines which version to display to users in specific locations.',
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