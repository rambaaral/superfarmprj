<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Performance;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class PageSpeed
 */
class PageSpeed extends BaseModule {

	/**
	 * PageSpeed constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Page Speed Optimizer',
            'description' => 'Analyzes and improves page speed through various optimization techniques, such as image lazy loading, JavaScript/CSS minification, and caching, to enhance SEO performance and user experience.',
            'version' => '1.0.0',
            'name' => 'pageSpeed',
            'priority' => 7,
            'dependencies' => [],
            'settings' => [['key' => 'enable_lazy_loading', 'type' => 'boolean', 'default' => True, 'description' => 'Enable lazy loading for images. Images will only be loaded when they are visible in the viewport, improving initial page load time.'], ['key' => 'minify_js_css', 'type' => 'boolean', 'default' => True, 'description' => 'Minify JavaScript and CSS files to reduce their size and improve download speed. This removes unnecessary characters and whitespace.'], ['key' => 'enable_browser_caching', 'type' => 'boolean', 'default' => True, 'description' => 'Enable browser caching to store static assets (images, CSS, JS) locally on the user\'s browser, reducing the number of requests on subsequent visits.']],
            'explain' => 'With lazy loading enabled, images on a long webpage are only loaded as the user scrolls down, significantly improving the initial page load time.  Minification of JavaScript and CSS files further reduces the overall page size, leading to faster downloads.  Browser caching allows repeat visitors to load the page even faster, as their browsers already have the necessary assets stored locally. These optimizations improve Core Web Vitals and enhance the user experience.',
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
