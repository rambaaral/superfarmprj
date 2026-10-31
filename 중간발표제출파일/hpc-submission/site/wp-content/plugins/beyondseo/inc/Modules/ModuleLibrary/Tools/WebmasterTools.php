<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class WebmasterTools
 */
class WebmasterTools extends BaseModule {

	/**
	 * WebmasterTools constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Search Console Integration',
            'description' => 'Integrates with Google Search Console and Bing Webmaster Tools to verify website ownership and access performance data and diagnostic information.',
            'version' => '1.0.0',
            'name' => 'webmasterTools',
            'priority' => 28,
            'dependencies' => [],
            'settings' => [['key' => 'google_verification_code', 'type' => 'string', 'default' => '', 'description' => 'Your Google Search Console verification code. This code is used to prove ownership of your website.'], ['key' => 'bing_verification_code', 'type' => 'string', 'default' => '', 'description' => 'Your Bing Webmaster Tools verification code. This code is used to prove ownership of your website with Bing.'], ['key' => 'automatic_submission', 'type' => 'boolean', 'default' => True, 'description' => 'Automatically submit updated sitemaps to Google and Bing upon generation.']],
            'explain' => 'After a user enters their Google Search Console and Bing Webmaster Tools verification codes in the settings, this module automatically adds the necessary meta tags to the site\'s header. This verifies website ownership, allowing access to valuable data in both search consoles.  With automatic submission enabled, this module also submits the generated sitemap to both search engines, ensuring quick indexing of new content.',
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
