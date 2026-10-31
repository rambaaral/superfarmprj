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
 * Class PerformanceOptimizer
 */
class PerformanceOptimizer extends BaseModule {

	/**
	 * PerformanceOptimizer constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Website Performance Optimizer',
            'description' => 'Optimizes website performance by implementing technical best practices, such as browser caching, GZIP compression, HTML/CSS/JS minification, and database optimization. Improves page load speed, Core Web Vitals, and overall SEO performance.',
            'version' => '1.0.0',
            'name' => 'performanceOptimizer',
            'priority' => 24,
            'dependencies' => [],
            'settings' => [['key' => 'enable_browser_caching', 'type' => 'boolean', 'default' => True, 'description' => 'Enable browser caching to store static assets (images, CSS, JS) locally on the user\'s browser, reducing load times on repeat visits.'], ['key' => 'enable_gzip_compression', 'type' => 'boolean', 'default' => True, 'description' => 'Enable GZIP compression to reduce the size of HTML, CSS, and JavaScript files sent from the server, improving download speeds.'], ['key' => 'minify_html_css_js', 'type' => 'boolean', 'default' => True, 'description' => 'Minify HTML, CSS, and JavaScript files by removing unnecessary characters and whitespace, further reducing file sizes and improving load times.'], ['key' => 'optimize_database_queries', 'type' => 'boolean', 'default' => False, 'description' => 'Optimize database queries to improve data retrieval speed. This can have a significant impact on overall website performance, but requires careful configuration. (See documentation for advanced settings).']],
            'explain' => 'This module implements several performance optimizations. It enables browser caching so that returning visitors load the website faster.  GZIP compression reduces the size of transferred files, and HTML, CSS, and JS minification further shrinks file sizes. If database optimization is enabled, the module also analyzes and optimizes database queries for improved data retrieval speed. All these optimizations combine to improve page load speed, Core Web Vitals scores (like LCP and FID), and enhance the user experience, indirectly benefiting SEO.',
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
