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
 * Class CompetitorAnalysis
 */
class CompetitorAnalysis extends BaseModule {

	/**
	 * CompetitorAnalysis constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Competitor SEO Analysis',
            'description' => 'Analyzes competitor websites to provide insights into their SEO strategies, including keyword rankings, backlink profiles, content performance, and other key metrics. Helps identify opportunities to improve your own SEO strategy and outperform competitors.',
            'version' => '1.0.0',
            'name' => 'competitorAnalysis',
            'priority' => 21,
            'dependencies' => [],
            'settings' => [['key' => 'competitor_websites', 'type' => 'array', 'default' => [], 'description' => 'A list of competitor website URLs to track (e.g., [\'https://competitor1.com\', \'https://competitor2.com\']). Separate URLs with commas.'], ['key' => 'analysis_frequency', 'type' => 'string', 'default' => 'weekly', 'description' => 'How often to perform competitor analysis: \'weekly\', \'bi-weekly\', or \'monthly\'.'], ['key' => 'focus_keywords', 'type' => 'array', 'default' => [], 'description' => 'A list of focus keywords to analyze competitor performance against (e.g., [\'SEO\', \'WordPress\']).  Leave blank to use the target keywords from the Keyword Rank Tracker.']],
            'explain' => 'A user enters the URLs of their main competitors into the module settings. The Competitor SEO Analysis module then analyzes these websites weekly (based on the configured analysis frequency), tracking their rankings for the specified focus keywords (or using the keywords from \'Keyword Rank Tracker\'). The module generates reports showing competitor keyword rankings, backlink profiles, and content performance. These insights help users understand competitor strategies, identify opportunities to improve their own SEO, and gain a competitive edge in search results.',
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
