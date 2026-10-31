<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class BacklinkManager
 */
class BacklinkManager extends BaseModule {

	/**
	 * BacklinkManager constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Backlink Manager',
            'description' => 'Tracks and monitors backlinks to your website, analyzing backlink quality, identifying new and lost backlinks, and providing insights into your backlink profile. Helps users improve their link building strategy and understand the impact of backlinks on SEO performance. Integrates with Competitor Analysis to compare backlink profiles.',
            'version' => '1.0.0',
            'name' => 'backlinkManager',
            'priority' => 23,
            'dependencies' => [],
            'settings' => [['key' => 'backlink_check_frequency', 'type' => 'string', 'default' => 'weekly', 'description' => 'How often to check for new and lost backlinks: \'weekly\', \'bi-weekly\', or \'monthly\'.'], ['key' => 'notify_on_backlink_changes', 'type' => 'boolean', 'default' => True, 'description' => 'Send email notifications to the administrator when new backlinks are acquired or existing backlinks are lost.'], ['key' => 'analyze_backlink_quality', 'type' => 'boolean', 'default' => True, 'description' => 'Analyze the quality of backlinks based on factors like domain authority, spam score, and relevance. Helps identify high-quality link building opportunities.']],
            'explain' => 'The Backlink Manager checks for new and lost backlinks every week (according to the default setting). If new backlinks are discovered, the module analyzes their quality and notifies the administrator.  If existing backlinks are lost, the administrator is also notified, allowing them to investigate the cause and potentially recover the lost link. The module provides a detailed backlink profile report, showing the total number of backlinks, their quality scores, and their anchor text distribution. This information, combined with data from \'Competitor Analysis,\' helps users understand their backlink profile\'s strengths and weaknesses compared to competitors and refine their link building strategy accordingly.',
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
