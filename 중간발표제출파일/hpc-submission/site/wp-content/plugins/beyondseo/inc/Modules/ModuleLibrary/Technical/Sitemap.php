<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Technical;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class Sitemap
 */
class Sitemap extends BaseModule {

	/**
	 * Sitemap constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'XML Sitemap Generator',
            'description' => 'Generates and submits XML sitemaps to search engines (Google, Bing, etc.) to improve indexation and SEO visibility.  Includes options for customizing sitemap content and update frequency.',
            'version' => '1.0.0',
            'name' => 'sitemap',
            'priority' => 8,
            'dependencies' => [],
            'settings' => [['key' => 'include_images', 'type' => 'boolean', 'default' => true, 'description' => 'Include images in the sitemap to improve image search visibility.'], ['key' => 'frequency', 'type' => 'string', 'default' => 'weekly', 'description' => 'How often the sitemap is regenerated: \'daily\', \'weekly\', \'monthly\', or \'yearly\'.'], ['key' => 'include_post_types', 'type' => 'array', 'default' => ['post', 'page'], 'description' => 'An array of WordPress post types to include in the sitemap (e.g., [\'post\', \'page\', \'product\']).']],
            'explain' => 'This module automatically generates an XML sitemap that includes all posts, pages, and images (based on the default settings). It then submits this sitemap to major search engines like Google and Bing, helping them discover and index new content more efficiently. The sitemap is regenerated weekly according to the default frequency. Users can customize the included post types and update frequency through the module settings.',
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
