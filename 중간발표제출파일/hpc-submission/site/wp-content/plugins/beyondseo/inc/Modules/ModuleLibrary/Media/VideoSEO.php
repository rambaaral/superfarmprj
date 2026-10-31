<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Media;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class VideoSEO
 */
class VideoSEO extends BaseModule {

	/**
	 * VideoSEO constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Video SEO Optimizer',
            'description' => 'Enhances video content visibility in search results by generating video sitemaps, adding VideoObject schema markup, and optimizing video metadata (titles, descriptions, tags). Helps improve video rankings and drive more traffic from video search.',
            'version' => '1.0.0',
            'name' => 'videoSeo',
            'priority' => 26,
            'dependencies' => [],
            'settings' => [['key' => 'generate_video_sitemap', 'type' => 'boolean', 'default' => True, 'description' => 'Generate a separate sitemap specifically for video content, improving the discoverability of videos by search engines.'], ['key' => 'enable_video_schema', 'type' => 'boolean', 'default' => True, 'description' => 'Add VideoObject schema markup to video pages, providing search engines with detailed information about your videos, such as title, description, thumbnail, and duration. This can enhance visibility in video search results and rich snippets.'], ['key' => 'autogenerate_video_metadata', 'type' => 'boolean', 'default' => False, 'description' => 'Automatically generate optimized video metadata (title, description, tags) based on video content analysis.  Requires integration with a video hosting/processing service.']],
            'explain' => 'If a user embeds a video on their website, this module automatically generates a VideoObject schema markup using the data from the settings and including information like the video title, description, thumbnail URL, and duration. The module also creates a dedicated video sitemap, making it easier for search engines to index the video content. If \'autogenerate_video_metadata\' is enabled and connected to a supported service, the module can analyze the video content and automatically suggest optimized titles, descriptions, and tags, saving users time and improving video SEO.',
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
