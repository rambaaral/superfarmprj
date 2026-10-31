<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Initializers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\HooksManager;
use RankingCoach\Inc\Core\Rest\RestManager;
use RankingCoach\Inc\Interfaces\InitializerInterface;

/**
 * Class HooksInitializer
 */
class Hooks implements InitializerInterface {

	use RcLoggerTrait;

    // Actions
    public const RANKINGCOACH_ACTION_CREATE_TABLES_AND_FETCH_ACCOUNT_DETAILS = 'rankingcoach_create_tables_and_fetch_account_details';
    public const RANKINGCOACH_ACTION_COLLECT_DATA_FROM_ALL_AVAILABLE_COLLECTORS = 'rankingcoach_collect_data_from_all_available_collectors';
    public const RANKINGCOACH_ACTION_UPDATE_RANKINGCOACH_SETUP = 'rankingcoach_update_rankingcoach_setup';

    // SEO Optimiser Actions
    public const RANKINGCOACH_ACTION_ANALYZE_SEO = 'rankingcoach_analyze_seo';
    public const RANKINGCOACH_ACTION_RETRIEVE_SEO_ANALYSIS = 'rankingcoach_retrieve_seo_analysis';
    public const RANKINGCOACH_ACTION_PLUGIN_DEACTIVATING = 'rankingcoach_plugin_deactivating';

    // SEO Optimiser Filters
    public const RANKINGCOACH_FILTER_SHOULD_THROTTLE_SEO_ANALYSIS = 'rankingcoach_should_throttle_seo_analysis';
    public const RANKINGCOACH_FILTER_GLOBAL_SEO_METRICS = 'rankingcoach_global_seo_metrics';

    // Filters
    public const RANKINGCOACH_FILTER_CACHED_ACCOUNT_DETAILS = 'rankingcoach_cached_account_details';

    /** @var HooksManager $hooksManager The object that manages hooks. */
    private HooksManager $hooksManager;

	/**
	 * HooksInitializer constructor.
	 */
	public function __construct(HooksManager $hooksActionManager ) {
		$this->hooksManager = $hooksActionManager;
	}

    /**
     * Getter for the HooksManager instance.
     *
     * @return HooksManager
     */
    public function getterHooksManager(): HooksManager {
        return $this->hooksManager;
    }

	/**
	 * Initializes the hooks.
	 * @return void
	 * @throws Exception
	 */
	public function initialize(): void {

        // WordPress Core Hooks - ordered by execution sequence
        add_action( 'plugins_loaded',   [ $this->hooksManager, 'pluginLoaded'], 0 );
        add_action( 'plugins_loaded',   [ $this->hooksManager, 'registerEarlyComponents'], 1 );

        // Use priority 10 (default) for init to ensure text domain loading happens at the appropriate time
        add_action( 'init',             [ $this->hooksManager, 'pluginInit'], 10 );

        add_action( 'wp',               [ $this->hooksManager, 'loadHeadMetatags'], 1);

		add_action( 'rest_api_init',    [ $this->hooksManager, 'initRestApi'] );

        // When the plugin is updated
        add_action( 'upgrader_process_complete', [ $this->hooksManager, 'pluginUpgraderProcessComplete'], 10, 2 );

        // Filters for page and post
        add_filter( 'rest_prepare_post', [ RestManager::class, 'addFilteredExcerptToRestResponse'], 10, 3);
        add_filter( 'rest_prepare_page', [ RestManager::class, 'addFilteredExcerptToRestResponse'], 10, 3);
        add_filter( 'rest_prepare_attachment', [ RestManager::class, 'addFilteredExcerptToRestResponse'], 10, 3);

        // Register the rewrite rules and API checks
        add_filter('rankingcoach_sslverify', function ($verify) {
            return wp_get_environment_type() !== 'production' ? false : $verify;
        });

        // Admin-specific hooks
        if ( is_admin() ) {
            add_action( 'plugins_loaded', [ $this->hooksManager, 'adminInit'], 99);
        }
        else {
            add_action( 'init', [ $this->hooksManager, 'frontendInit'], 3 );
        }
    }
}
