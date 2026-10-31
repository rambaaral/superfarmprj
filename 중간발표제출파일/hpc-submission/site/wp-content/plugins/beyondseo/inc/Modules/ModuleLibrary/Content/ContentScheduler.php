<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Content;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class ContentScheduler
 */
class ContentScheduler extends BaseModule {

	/**
	 * ContentScheduler constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
            'title' => 'Content Update Scheduler',
            'description' => 'Allows users to schedule content updates and revisions, helping maintain content freshness and improve SEO performance over time. Integrates with the Content Analysis module to track the impact of updates on SEO metrics.',
            'version' => '1.0.0',
            'name' => 'contentScheduler',
            'priority' => 17,
            'dependencies' => [],
            'settings' => [['key' => 'enable_scheduled_updates', 'type' => 'boolean', 'default' => True, 'description' => 'Enable the content scheduling feature. When enabled, you can schedule updates for individual posts and pages.'], ['key' => 'default_update_interval', 'type' => 'string', 'default' => 'weekly', 'description' => 'The default time interval between scheduled content updates: \'daily\', \'weekly\', \'monthly\', or \'yearly\'.'], ['key' => 'send_update_reminders', 'type' => 'boolean', 'default' => True, 'description' => 'Send email reminders to content authors or administrators when a scheduled update is due.']],
            'explain' => 'A user schedules a blog post to be reviewed and updated every month. The Content Update Scheduler sends a reminder email one week before the scheduled update date. After the post is updated, the \'Content Analysis\' module automatically analyzes the revised content, tracking changes in keyword density, readability, and other SEO metrics.  This helps users understand the impact of content updates on their SEO performance and refine their content strategy over time.',
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
