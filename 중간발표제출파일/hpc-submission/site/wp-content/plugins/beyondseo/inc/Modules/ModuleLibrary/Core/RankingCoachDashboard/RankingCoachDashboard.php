<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Core\RankingCoachDashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;
use WP_REST_Response;

/**
 * Class RankingCoachDashboard
 */
class RankingCoachDashboard extends BaseModule {

	use RcApiTrait;
	use RcLoggerTrait;

	public const MODULE_NAME = 'rankingcoachDashboard';

	/**
	 * RankingCoachDashboard constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
    public function __construct(ModuleManager $moduleManager) {
        $initialization = [
	        'active' => true,
            'title' => 'rankingCoach Dashboard',
            'description' => 'The central module that coordinates and manages all other Ranking Coach modules. Provides the main interface and reporting dashboard for SEO analysis and recommendations.',
            'version' => '2.0.0',
            'name' => 'rankingcoachDashboard',
            'priority' => 100,
            'dependencies' => [],
            'settings' => [
				['key' => 'dashboard_reporting_frequency', 'type' => 'string', 'default' => 'weekly', 'description' => 'How often SEO reports and summaries are generated on the dashboard: \'daily\', \'weekly\', or \'monthly\'.']
            ],
            'explain' => 'The rankingCoach Dashboard module collects data from all other active modules, such as keyword rankings from \'Text Optimizer\', sitemap status from \'Sitemap,\' and webmaster tools verification from \'Webmaster Tools.\'  It then presents this information in a unified dashboard, providing an overview of the website\'s SEO performance and offering actionable recommendations. The dashboard reporting frequency setting controls how often new reports are generated.',
        ];
        parent::__construct($moduleManager, $initialization);
    }

    /**
     * Registers the hooks for the module.
     * @return void
     */
	public function initializeModule(): void {
		if(!$this->module_active) {
			return;
		}

		// Register the dashboard data filter
		add_filter('rankingcoach_dashboard/data', fn($null) => $this->getModuleData());

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
