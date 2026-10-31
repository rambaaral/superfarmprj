<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class SpecializedPluginIntegrations
 */
class SpecializedPluginIntegrations extends BaseModule {

	/**
	 * SpecializedPluginIntegrations constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Specialized Plugin Integrations',
            'description' => 'Provides tailored integrations with specific, popular non-SEO plugins to enhance their functionalities with SEO data and optimizations.',
            'version' => '1.0.0',
            'name' => 'specializedPluginIntegrations',
            'priority' => 2,
            'dependencies' => [],
            'settings' => [['key' => 'woocommerce_integration_enabled', 'type' => 'boolean', 'default' => True, 'description' => 'Enable integration with WooCommerce.'], ['key' => 'contact_form_7_integration_enabled', 'type' => 'boolean', 'default' => false, 'description' => 'Enable integration with Contact Form 7.'], ['key' => 'elementor_integration_enabled', 'type' => 'boolean', 'default' => false, 'description' => 'Enable integration with Elementor.']],
            'explain' => 'This module might integrate with WooCommerce to automatically add product schema markup to product pages, optimize product descriptions for targeted keywords (pulled from `window.rankingCoach`), and track product page performance in search results. It could also connect with Contact Form 7 to add tracking parameters to form URLs, allowing for analysis of form submission sources and conversion rates. Integration with other plugins like page builders (e.g., Elementor) could provide SEO analysis and recommendations within the page builder interface itself.',
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
