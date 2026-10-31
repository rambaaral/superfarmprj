<?php /** @noinspection PhpRedundantOptionalArgumentInspection */
/** @noinspection PhpUnusedParameterInspection */
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Initializers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Core\Admin\Admin;
use Exception;
use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Traits\SingletonTrait;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\ConflictManager;
use RankingCoach\Inc\Core\CronJobManager;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\HooksManager;
use RankingCoach\Inc\Core\Jobs\AccountSyncJob;
use RankingCoach\Inc\Core\Jobs\BrokenLinkCheckerJob;
use RankingCoach\Inc\Core\Jobs\LogCleanupJob;
use RankingCoach\Inc\Core\Jobs\SyncKeywordsJob;
use RankingCoach\Inc\Core\NotificationManager;
use RankingCoach\Inc\Core\PluginConfiguration;
use RankingCoach\Inc\Core\Review\ReviewState;
use RankingCoach\Inc\Core\RobotsTxt\RobotsTxtManager;
use RankingCoach\Inc\Core\Rss\FeedManager;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Core\UrlChangeDetector;
use RankingCoach\Inc\Interfaces\InitializerInterface;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\SchemaCacheManager;
use RankingCoach\Inc\Modules\ModuleManager;
use RankingCoach\Inc\Traits\ConfigManager;
use RankingCoach\Inc\Traits\HookManager;
use ReflectionException;
use Throwable;

/**
 * Class Installer
 *
 * Handles plugin installation, activation, and deactivation processes.
 * This class is responsible for setting up database tables, registering hooks,
 * managing application passwords, and handling multisite installations.
 *
 * @since 1.0.0
 */
class Installer implements InitializerInterface
{
    use ConfigManager;
    use HookManager;
    use SingletonTrait;
    use RcLoggerTrait;
    
    /**
     * Plugin activation hook name
     */
    private const ACTION_PLUGIN_ACTIVATED = 'activated_plugin';

    /**
     * Plugin deactivation hook name
     */
    private const ACTION_PLUGIN_DEACTIVATED = 'deactivated_plugin';


    /**
     * Initializes the plugin hooks and registers activation/deactivation handlers.
     *
     * This method sets up all necessary hooks for the plugin to function properly.
     * It registers activation and deactivation hooks, as well as hooks for reacting
     * to other plugins' activation and deactivation events.
     *
     * @return void
     * @throws ReflectionException If there's an issue with reflection during initialization
     * @throws Throwable
     */
    public function initialize(): void {

        // Initialize internal hooks and filters
        $this->initializeHooks();

        (new ReviewState())->registerAnalysisHooks();

        add_action('plugins_loaded', static function () {
            try {
                (new ReviewState())->observeActivePlugin();
            } catch (Throwable $e) {
            }
        }, 9);
        add_action('plugins_loaded',  [$this, 'maybeActivateOnce'], 10);
        add_action('init', [HooksManager::getInstance(), 'runDbMigration'], 1);

        // Register plugin lifecycle event listeners
        // These run in the normal WordPress flow with full environment access
        // Exceptions won't prevent plugin activation/deactivation
        add_action(self::ACTION_PLUGIN_ACTIVATED, [$this, 'handlePluginActivation']);

        // Register activation/deactivation hooks
        // These run in an isolated environment with limited WordPress access
        // Exceptions will prevent plugin activation/deactivation
        register_activation_hook(RANKINGCOACH_FILE, [$this, 'maybeActivateOnce']);
//        register_deactivation_hook(RANKINGCOACH_FILE, [$this, 'deactivationOnce']);

        // Register uninstall hook
        // This runs when the plugin is uninstalled, not just deactivated
        register_uninstall_hook(RANKINGCOACH_FILE, [Installer::class, 'handleUninstall']);
    }

    /**
     * Initializes all the plugin's internal hooks and filters.
     *
     * This method sets up various hooks and filters for the plugin's functionality:
     * - Initializes the HooksManager
     * - Registers default settings
     * - Sets up filters for account details and keyword management
     * - Registers actions for database operations and data collection
     * - Initializes the admin manager for form submissions
     * - Sets up cron jobs for regular tasks
     *
     * @return void
     * @throws ReflectionException If there's an issue with reflection during initialization
     * @throws Throwable
     */
    public function initializeHooks(): void {
        // Initialize the hook actions manager
        (new Hooks(new HooksManager()))->initialize();

        // Register core action handlers
        $this->registerCoreActionHandlers();

        // Initialize admin components
        $this->initializeAdminComponents();

        // Configure and initialize cron jobs
        $this->initializeCronJobs();

        // Initialize the RSS feed manager
        $this->initializeRssFeedManager();

        // Initialize the RobotsTxt manager
        $this->initializeRobotsTxtManager();

        // Initialize heartbeat service management
        $this->initializeHeartbeatService();
    }

    /**
     * Initializes admin-related components.
     *
     * @return void
     * @throws ReflectionException
     */
    private function initializeAdminComponents(): void {
        // AdminManager class handles all possible form submissions
        new AdminManager();
    }

    /**
     * Registers the default plugin settings.
     * 
     * @return void
     */
    private function registerDefaultSettings(): void {
        if (get_option(BaseConstants::OPTION_DELETE_ON_DEACTIVATION)) {
            return;
        }
        // Register the default settings saved in the database
        // If that setting is not in the database, it will be added
        // If it is in the database, it will not be overwritten
        SettingsManager::instance()->registerDefaultSettings();
    }

    /**
     * Registers handlers for core plugin actions.
     *
     * @return void
     * @throws Throwable
     */
    private function registerCoreActionHandlers(): void {

        // Define action for creating tables and fetching account details
        add_action(Hooks::RANKINGCOACH_ACTION_COLLECT_DATA_FROM_ALL_AVAILABLE_COLLECTORS, function($context) {
            try {
                // $context means 'settings' or 'activation'
                // UserApiManager::collectDataFromCollectors();
            } catch (Exception $e) {
                $this->log('Error while collecting data from all available collectors. Exception: ' . $e->getMessage(), 'ERROR');
            }
        });
    }

    /**
     * Configures and initializes cron jobs.
     * 
     * @return void
     * @throws Exception
     */
    private function initializeCronJobs(): void {
        // Initialize Cron jobs manager
        CronJobManager::instance()->initialize();
    }

    /**
     * Activates the plugin.
     *
     * This method is called directly by WordPress when the plugin is activated.
     * It runs in an isolated environment with limited WordPress access.
     * Any exceptions will prevent plugin activation and be displayed to the user.
     *
     * @param bool $entireNetwork Whether to activate for the entire network in multisite
     * @return void
     * @throws Exception If activation fails
     */
    public function activationOnce(bool $entireNetwork = false): void {
        if (!is_multisite() || !$entireNetwork) {
            $this->activateSingleSite();
            return;
        }
        $this->networkActivateDeactivate(true);
    }

    /**
     * Handles plugin activation events.
     *
     * This method is called whenever any plugin is activated in WordPress.
     * It runs in the normal WordPress flow with full environment access.
     * Exceptions won't prevent plugin activation since this runs after activation is complete.
     *
     * @param string $plugin The path to the plugin file relative to the plugins directory
     * @return void
     * @throws Exception If an error occurs during post-activation processing
     */
    public function handlePluginActivation(string $plugin): void {

        // If it's not our plugin being activated, we're done
        if ($plugin !== RANKINGCOACH_PLUGIN_BASENAME) {
            return;
        }

        // Clear any leftover pending-deletion flag so re-activation runs migration and settings init
        delete_option(BaseConstants::OPTION_DELETE_ON_DEACTIVATION);

        // Check for conflicts and add notifications when any plugin is activated
        ConflictManager::getInstance()->addConflictNotification($plugin);
    }

    /**
     * Activates the plugin for a single site.
     *
     * This method handles the core activation process for a single WordPress site.
     * It sets up capabilities, initializes modules, and configures plugin options.
     *
     * @return void
     * @throws Exception If activation fails
     */
    private function activateSingleSite(): void {

        $reviewNewInstallation = false;
        try {
            $reviewNewInstallation = (new ReviewState())->isNewInstallation();
        } catch (Throwable $e) {
        }

        // Create database tables and fetch account details
        try {
            $dbm = DatabaseManager::getInstance();
            $dbm->createAllTables();
            HooksManager::getInstance()->runDbMigration();
        } catch (Throwable $e) {
            // Log the error and prevent activation
            $this->log('Error during plugin activation: ' . $e->getMessage(), 'ERROR');
            /* translators: %s: error message */
            throw new Exception(sprintf(('Plugin activation failed: %s'), esc_html($e->getMessage())));
        }

        // Check and update default settings with any new defaults
        SettingsManager::instance()->syncNewSettingsFromDefaults();

        // Check for plugin conflicts
        $this->checkForPluginConflicts();
        
        // Initialize plugin modules
        $this->initializePluginModules();

        // Set up plugin options
        $this->setupPluginOptions();

        // Initialize sitemap flags
        $this->initializeSitemapOptions();

        // Initialize URLChangeDetector baseline
        UrlChangeDetector::getInstance()->initializeOriginBaseline();

        // Schedule all recurring WP-Cron jobs owned by the plugin
        $this->scheduleCronJobs();

        try {
            (new ReviewState())->recordPluginObservation($reviewNewInstallation);
        } catch (Throwable $e) {
        }

        $this->scheduleSetupWizardRedirect();
    }

    /**
     * Opens the setup wizard on the next admin request (AdminManager consumes the transient).
     * Covers both the activation hook and the lazy first-run path of pre-activated installs.
     *
     * @return void
     */
    private function scheduleSetupWizardRedirect(): void {
        if (wp_doing_cron() || wp_doing_ajax() || is_network_admin() || (defined('WP_CLI') && WP_CLI)) {
            return;
        }
        set_transient(BaseConstants::TRANSIENT_SETUP_WIZARD_REDIRECT, 1, MINUTE_IN_SECONDS);
    }

    /**
     * Schedules all recurring WP-Cron jobs owned by the plugin.
     *
     * Onboarding/settings-dependent jobs are scheduled through their own
     * initializeScheduling() method, which already checks whether their
     * respective conditions (enabled setting, onboarding completed, module
     * active, etc.) are met before actually scheduling the event.
     *
     * @return void
     */
    private function scheduleCronJobs(): void {
        try {
            CronJobManager::instance()->scheduleCronJobs();
            SchemaCacheManager::scheduleCleanupCron();
            SyncKeywordsJob::instance()->initializeScheduling();
            BrokenLinkCheckerJob::instance()->initializeScheduling();
            LogCleanupJob::instance()->initializeScheduling();
            AccountSyncJob::instance()->initializeScheduling();
        } catch (Throwable $e) {
            $this->log('Failed to schedule cron jobs during activation: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Unschedules all recurring WP-Cron jobs owned by the plugin.
     *
     * @return void
     */
    private function unscheduleCronJobs(): void {
        try {
            CronJobManager::instance()->clearAllCronJobs();
            SchemaCacheManager::unscheduleCleanupCron();

            SyncKeywordsJob::instance()->unscheduleJob();
            BrokenLinkCheckerJob::instance()->unscheduleJob();
            LogCleanupJob::instance()->unscheduleJob();
            AccountSyncJob::instance()->unscheduleJob();
        } catch (Throwable $e) {
            $this->log('Failed to unschedule cron jobs during deactivation: ' . $e->getMessage(), 'ERROR');
        }
    }

    private function initializeSitemapOptions(): void {
        update_option(BaseConstants::OPTION_FLUSH_REWRITE_RULES, true);
        update_option(BaseConstants::OPTION_SITEMAP_INITIALIZED, true);
        update_option(BaseConstants::OPTION_SITEMAP_TRAILING_SLASH_FIXED, true);
    }

    /**
     * Checks for conflicts with other plugins.
     * 
     * @return void
     */
    private function checkForPluginConflicts(): void {
        ConflictManager::getInstance()->checkPluginConflicts();
    }
    
    /**
     * Initializes all plugin modules.
     * 
     * @return void
     * @throws Exception If module initialization fails
     */
    private function initializePluginModules(): void {
        ModuleManager::instance()->initialize();
    }
    
    /**
     * Sets up core plugin options.
     * 
     * @return void
     */
    public function setupPluginOptions(): void {
        // Set installation date if not already set
        if (false === boolval(get_option(BaseConstants::OPTION_INSTALLATION_DATE))) {
            update_option(BaseConstants::OPTION_INSTALLATION_DATE, current_time('timestamp'));
        }

        $pluginData = PluginConfiguration::getInstance()->getPluginData();
        $pluginVersion = $pluginData['Version'];

        // Set version information
        update_option(BaseConstants::OPTION_PLUGIN_VERSION, $pluginVersion);
        update_option(BaseConstants::OPTION_DB_VERSION, DatabaseManager::getInstance()->getDbVersion());
        update_option(BaseConstants::OPTION_API_VERSION, '1.0.0');
        update_option(BaseConstants::OPTION_RANKINGCOACH_ONBOARDING_URL, home_url(), true);
        
        // Generate and store unique installation ID
        update_option(BaseConstants::OPTION_INSTALLATION_ID, CoreHelper::generate_installation_id());
    }

    /**
     * Deactivates the plugin.
     *
     * This method is called directly by WordPress when the plugin is deactivated.
     * It runs in an isolated environment with limited WordPress access.
     * Any exceptions will prevent plugin deactivation and be displayed to the user.
     *
     * @param bool $entireNetwork Whether to deactivate for the entire network in multisite
     * @return void
     * @throws Throwable
     */
    public function deactivationOnce(bool $entireNetwork = false): void {
        if (!is_multisite() || !$entireNetwork) {
            $this->deactivateSingleSite();
            return;
        }
        $this->networkActivateDeactivate(false);
    }

    /**
     * Deactivates the plugin for a single site.
     *
     * This method handles the core deactivation process for a single WordPress site.
     * It cleans up cron jobs, application passwords, module settings, and database tables.
     *
     * @return void
     * @throws Throwable
     */
    private function deactivateSingleSite(): void {

        // Trigger a hook before deactivation cleanup, and everybody can know that we are deactivating
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
        do_action(Hooks::RANKINGCOACH_ACTION_PLUGIN_DEACTIVATING);
        // How can check if the hook was triggered with php command?
        // $wasTriggered = did_action(Hooks::RANKINGCOACH_ACTION_PLUGIN_DEACTIVATING);

        // Always unschedule all plugin cron jobs on deactivation, regardless of
        // whether the user opted to delete their data, so WordPress cron
        // stops running plugin tasks for a deactivated plugin.
        $this->unscheduleCronJobs();

        // Legacy safety net: also clear any leftover scheduled hooks by name,
        // in case events were scheduled under old/obsolete hook names.
        $this->clearAllWpCronEvents();

        // Clean all data related to the plugin, including application passwords, cron jobs, and database tables
        if (get_option(BaseConstants::OPTION_DELETE_ON_DEACTIVATION)) {
            delete_option(BaseConstants::OPTION_DELETE_ON_DEACTIVATION);
            try {
                $this->cleanUserData();
                // Re-set the flag so it survives cleanup (removePrefixedOptions deleted it).
                // This blocks maybeActivateOnce and registerDefaultSettings from re-creating
                // options in any request between cleanup and actual plugin deactivation.
                update_option(BaseConstants::OPTION_DELETE_ON_DEACTIVATION, 1, false);
                $this->log('Data cleanup completed as requested by user', 'INFO');
            } catch (Throwable $th) {
                // Log the error but allow deactivation to continue
                $this->log('Error during plugin deactivation: ' . $th->getMessage() . ' in ' . $th->getFile() . ' on line ' . $th->getLine(), 'ERROR');
            }
        }
    }

    public function clearAllWpCronEvents(): void {
        $cronHooks = [
            'rankingcoach_daily_event',
            'rankingcoach_twice_hourly_event',
            'rankingcoach_hourly_event',
            'rankingcoach_wp_cron_enabler',
            'rankingcoach_account_sync',
            'rankingcoach_broken_link_checker_scan',
            'rankingcoach_log_cleanup',
            'rankingcoach_keywords_synchronization',
            'rankingcoach_cache_cleanup',
            'rankingcoach_schema_cache_cleanup',
        ];

        foreach ($cronHooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
    
    /**
     * Manages the activation or deactivation of the plugin across a network.
     *
     * This method iterates through all active blogs in a multisite network
     * and activates or deactivates the plugin for each one.
     *
     * @param bool $activate Indicates whether to activate (true) or deactivate (false)
     * @return void
     */
    private function networkActivateDeactivate(bool $activate): void {
        // Get all active sites in the network
        $sites = $this->getActiveSites();
        
        // Process each site
        foreach ($sites as $blogId) {
            $this->processSite($blogId, $activate);
        }
    }
    
    /**
     * Retrieves all active sites in the multisite network.
     * 
     * @return array Array of blog IDs
     */
    private function getActiveSites(): array {
        // Use WordPress's built-in get_sites function if available (WP 4.6+)
        if (function_exists('get_sites') && class_exists('WP_Site_Query')) {
            return $this->getSitesModernWP();
        }
        
        // Fallback for older WordPress versions
        return $this->getSitesLegacyWP();
    }
    
    /**
     * Gets sites using modern WordPress functions (4.6+).
     * 
     * @return array Array of blog IDs
     */
    private function getSitesModernWP(): array {
        $blogIds = [];
        $sites = get_sites(['archived' => 0, 'spam' => 0, 'deleted' => 0]);
        
        foreach ($sites as $site) {
            $blogIds[] = is_object($site) ? $site->blog_id : $site['blog_id'];
        }
        
        return $blogIds;
    }
    
    /**
     * Gets sites using legacy WordPress database queries.
     * 
     * @return array Array of blog IDs
     */
    private function getSitesLegacyWP(): array {
        $dbManager = DatabaseManager::getInstance();
        $blogsTable = $dbManager->db()->prefixTable('blogs');
        
        $results = $dbManager->db()->queryRaw(
            /** @lang=MySQL */"SELECT blog_id FROM {$blogsTable} WHERE archived = '0' AND spam = '0' AND deleted = '0'",
            'ARRAY_N'
        );
        
        $blogIds = [];
        if (is_array($results)) {
            foreach ($results as $row) {
                if (isset($row[0])) {
                    $blogIds[] = (int)$row[0];
                }
            }
        }
        
        return $blogIds ?: [];
    }
    
    /**
     * Processes a single site for activation or deactivation.
     * 
     * @param int $blogId The blog ID to process
     * @param bool $activate Whether to activate or deactivate
     * @return void
     */
    private function processSite(int $blogId, bool $activate): void {
        $callback = $activate ? 'activateSingleSite' : 'deactivateSingleSite';
        
        switch_to_blog($blogId);
        $this->$callback();
        restore_current_blog();
    }

    /**
     * Initializes the RSS feed manager.
     *
     * This method is a placeholder for initializing the RSS feed manager.
     * It can be extended in the future to set up RSS feed generation and management.
     *
     * @return void
     */
    private function initializeRssFeedManager()
    {
        FeedManager::getInstance()->init();
    }

    /**
     * Initializes the RobotsTxt manager.
     *
     * This method is a placeholder for initializing the RobotsTxt manager.
     *
     * @return void
     */
    private function initializeRobotsTxtManager()
    {
        RobotsTxtManager::getInstance()->init();
    }


    /**
     * Initializes the WordPress heartbeat service management.
     *
     * This method sets up the heartbeat service control based on plugin settings.
     * It manages the WordPress admin-ajax heartbeat service to improve performance.
     *
     * @return void
     */
    private function initializeHeartbeatService(): void
    {
        CoreHelper::manageHeartbeatService();
    }

    /**
     * Run activationOnce() exactly once even for MU-plugins/pre-activated installs.
     * Idempotent + race-safe via an option lock.
     */
    public function maybeActivateOnce(): void
    {
        $currentFilter = (string) current_filter();
        $pluginBasename = plugin_basename(RANKINGCOACH_FILE);
        $isDirectActivation = ($currentFilter === 'activate_' . $pluginBasename)
            || (defined('RANKINGCOACH_PLUGIN_BASENAME') && $currentFilter === 'activate_' . RANKINGCOACH_PLUGIN_BASENAME);

        if (!$isDirectActivation && get_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_DONE)) {
            return;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'deactivate') {
            return;
        }

        if (!$isDirectActivation) {
            $isRest = (defined('REST_REQUEST') && REST_REQUEST)
                || (isset($_SERVER['REQUEST_URI']) && str_contains((string)$_SERVER['REQUEST_URI'], '/wp-json/'));
            $isCli = (defined('WP_CLI') && WP_CLI);

            if (!is_admin() && !$isRest && !$isCli) {
                return;
            }

            if (get_option(BaseConstants::OPTION_DELETE_ON_DEACTIVATION)) {
                return;
            }

            $activePlugins = (array) get_option('active_plugins', []);
            $isActive = in_array($pluginBasename, $activePlugins, true)
                || (defined('RANKINGCOACH_PLUGIN_BASENAME') && in_array(RANKINGCOACH_PLUGIN_BASENAME, $activePlugins, true));

            if (is_multisite() && !$isActive) {
                $sitewidePlugins = (array) get_site_option('active_sitewide_plugins', []);
                $isActive = isset($sitewidePlugins[$pluginBasename])
                    || (defined('RANKINGCOACH_PLUGIN_BASENAME') && isset($sitewidePlugins[RANKINGCOACH_PLUGIN_BASENAME]));
            }

            if (!$isActive) {
                return;
            }
        }

        $lockTime = (int) get_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_LOCK, 0);
        if ($lockTime > 0 && (time() - $lockTime) > 60) {
            delete_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_LOCK);
        }

        if (! add_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_LOCK, time(), '', 'no')) {
            return;
        }

        try {
            if ($isDirectActivation || ! get_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_DONE)) {
                $this->activationOnce();
                update_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_DONE, 1, true);
            }
        } catch (\Throwable $e) {
            $this->log('activationOnce failed: ' . $e->getMessage(), 'ERROR');
        } finally {
            delete_option(BaseConstants::OPTION_PLUGIN_ACTIVATION_LOCK);
        }
    }

    /**
     * Handles plugin uninstallation.
     *
     * This method is called when the plugin is uninstalled, not just deactivated.
     * It can be used to clean up any resources or settings that should be removed
     * when the plugin is completely removed from the site.
     *
     * @return void
     */
    public static function handleUninstall(): void
    {
        try {
            self::getInstance()->clearAllWpCronEvents();
        } catch (Throwable $e) {
        }
    }

    /**
     * @throws Exception
     */
    public function cleanUserData(): void {
        CronJobManager::instance()?->clearAllCronJobs();

        // Remove all module settings and tables
        ModuleManager::instance()->uninstall();

        // Reset the notifications option to an empty array
        NotificationManager::instance()->removeAllNotifications();

        // Remove all plugin-prefixed options (rankingcoach_, rc_, bseo_, beyondseo_)
        // while respecting exclusions defined in CoreHelper::EXCLUDED_OPTION_KEYS
        PluginConfiguration::getInstance()->removeOptions();

        // Remove all tables
        DatabaseManager::getInstance()->removeAllTables();

        // Remove all users & posts metadata
        DatabaseTablesManager::getInstance()->removeUsersAndPostsMetadata();

        // Remove testing environment flag if it exists
        delete_option('testing_environment');
    }
}
