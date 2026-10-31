<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Keywords;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class KeywordRankTracker
 */
class KeywordRankTracker extends BaseModule {

	/**
	 * KeywordRankTracker constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Keyword Rank Tracker',
            'description' => 'Tracks the ranking performance of specified keywords in major search engines (Google, Bing) over time. Provides reports and visualizations to monitor keyword progress and identify SEO opportunities.',
            'version' => '1.0.0',
            'name' => 'keywordRankTracker',
            'priority' => 18,
            'dependencies' => [],
            'settings' => [['key' => 'target_keywords', 'type' => 'array', 'default' => [], 'description' => 'A list of target keywords to track (e.g., [\'SEO\', \'WordPress\', \'content marketing\']).  These keywords should align with your website\'s content and target audience. Separate keywords with commas.'], ['key' => 'update_frequency', 'type' => 'string', 'default' => 'weekly', 'description' => 'How often to check and update keyword rankings: \'daily\', \'weekly\', or \'monthly\'.'], ['key' => 'search_engine', 'type' => 'string', 'default' => 'google', 'description' => 'The primary search engine to track keyword rankings in: \'google\', \'bing\', or \'both\'.']],
            'explain' => 'A user specifies a list of target keywords, such as \'SEO,\' \'WordPress,\' and \'content marketing,\' in the module settings. The Keyword Rank Tracker then checks the ranking positions of these keywords in Google every week (according to the default update frequency). The module generates reports and charts showing the ranking progress of each keyword over time. This allows users to monitor the effectiveness of their SEO efforts and make adjustments to their content strategy to improve keyword rankings.  The module can also provide alerts if rankings drop significantly.',
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
