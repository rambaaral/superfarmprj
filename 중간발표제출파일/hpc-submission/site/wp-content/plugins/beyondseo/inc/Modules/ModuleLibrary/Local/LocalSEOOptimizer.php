<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Local;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class LocalSEO
 */
class LocalSEOOptimizer extends BaseModule {

	/**
	 * LocalSEO constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Local SEO Optimizer',
            'description' => 'Optimizes websites for local search visibility by adding LocalBusiness schema markup, managing location-based keywords, and integrating with location-based services. Helps businesses rank higher in local search results.',
            'version' => '1.0.0',
            'name' => 'localSeoOptimizer',
            'priority' => 16,
            'dependencies' => [],
            'settings' => [['key' => 'enable_local_business_schema', 'type' => 'boolean', 'default' => True, 'description' => 'Enable LocalBusiness schema markup to provide search engines with detailed information about your business, including name, address, phone number, and operating hours.'], ['key' => 'business_name', 'type' => 'string', 'default' => '', 'description' => 'The name of your local business. This should match the name used on your Google My Business profile (if you have one).'], ['key' => 'business_address', 'type' => 'string', 'default' => '', 'description' => 'The full street address of your business location.'], ['key' => 'business_phone', 'type' => 'string', 'default' => '', 'description' => 'The primary phone number for your business.']],
            'explain' => 'For a bakery in San Francisco, the module adds LocalBusiness schema to the website, including the bakery\'s name, address, phone number, and hours of operation. This structured data helps search engines understand that this is a local business and increases visibility in local search results (e.g., when someone searches for \'bakery in San Francisco\'). The module can also integrate with Google Maps or other location services to display the business location on the website, improving user engagement.',
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
