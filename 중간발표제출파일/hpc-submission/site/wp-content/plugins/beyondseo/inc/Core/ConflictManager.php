<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

/**
 * Class ConflictManager
 * 
 * Handles detection and resolution of plugin conflicts with rankingCoach.
 */
class ConflictManager {

	/**
	 * Singleton instance of ConflictManager.
	 *
	 * @var ConflictManager|null
	 */
	private static ?ConflictManager $instance = null;

	/**
	 * List of incompatible plugins with their file paths (relative to plugins directory) and human-readable names.
	 *
	 * @var array
	 */
	private array $incompatible_plugins = [
        'wordpress-seo/wp-seo.php' => 'Yoast SEO',
        'seo-by-rank-math/rank-math.php' => 'Rank Math SEO',
        'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
        'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' => 'All in One SEO Pro',
        'autodescription/autodescription.php' => 'The SEO Framework',
        'seo-ultimate/seo-ultimate.php' => 'SEO Ultimate',
        'premium-seo-pack/index.php' => 'Premium SEO Pack',
        'squirrly-seo/squirrly.php' => 'Squirrly SEO',
        'seo-press/seopress.php' => 'SEOPress',
        'slim-seo/slim-seo.php' => 'Slim SEO',
        'wp-seo-structured-data-schema/wp-seo-structured-data-schema.php' => 'WP SEO Structured Data Schema',
        'all-in-one-schemaorg-rich-snippets/index.php' => 'All In One Schema.org Rich Snippets'
	];

	/**
	 * ID for the conflict notification.
	 * 
	 * @var string
	 */
	private const NOTIFICATION_ID = 'rankingcoach-plugin-conflicts';

	/**
	 * Returns the singleton instance of ConflictManager.
	 *
	 * @return ConflictManager
	 */
	public static function getInstance(): ConflictManager {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
    }
	
	/**
	 * Check if a simple notification style is enabled.
	 *
	 * @return bool Whether a simple notification style is enabled.
	 */
	private function isSimpleNotificationEnabled(): bool {
	    return (bool) get_option(BaseConstants::OPTION_SIMPLE_CONFLICT_NOTICE, true);
	}

    /**
     * Checks for conflicts with incompatible plugins and displays a notification if any are found.
     */
    public function checkPluginConflicts(): void {
        $conflicting_plugins = $this->getConflictingPlugins();

        if (!empty($conflicting_plugins)) {
            $this->showConflictNotification($conflicting_plugins);
        }
    }

    /**
     * Gets a list of active plugins that conflict with rankingCoach.
     * 
     * @return array Array of conflicting plugins with their file paths as keys and names as values.
     */
    private function getConflictingPlugins(): array {
        $active_plugins = get_option('active_plugins', []);

        return array_filter($this->incompatible_plugins, function ($plugin_path) use ($active_plugins) {
            return in_array($plugin_path, $active_plugins);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Displays the appropriate conflict notification based on settings.
     * 
     * @param array $conflicting_plugins Array of conflicting plugins.
     */
    private function showConflictNotification(array $conflicting_plugins): void {
        $friendly_names = array_values($conflicting_plugins);
        
        $message = $this->isSimpleNotificationEnabled() 
            ? $this->getSimpleNotificationHtml($friendly_names)
            : $this->getStyledNotificationHtml($friendly_names);

        // Check if the notification already exists
        $notification_manager = NotificationManager::instance();

        if (!$notification_manager?->has_notification(self::NOTIFICATION_ID)) {
            $notification_manager?->add(
                $message,
                [
                    'id' => self::NOTIFICATION_ID,
                    'type' => Notification::ERROR,
                    'screen' => Notification::SCREEN_ANY,
                    'dismissible' => true,
                    'persistent' => true,
                ]
            );
        }
    }

    /**
     * Generates HTML for a simple notification style.
     * 
     * @param array $plugin_names Names of conflicting plugins.
     * @return string HTML for the simple notification.
     */
    private function getSimpleNotificationHtml(array $plugin_names): string {
        $plugin_list = '<strong>' . implode('</strong>, <strong>', $plugin_names) . '.</strong>';

	    $message = count($plugin_names) > 1
		    /* translators: %1$s: brand name of the plugin, %2$s: list of plugin names */
		    ? sprintf(__('To ensure %1$s works properly, please deactivate the conflicting plugins: %2$s', 'beyondseo'), RANKINGCOACH_BRAND_NAME, $plugin_list)
		    /* translators: %1$s: brand name of the plugin, %2$s: plugin name */
		    : sprintf(__('To ensure %1$s works properly, please deactivate the conflicting plugin: %2$s', 'beyondseo'), RANKINGCOACH_BRAND_NAME, $plugin_list);
        
        return '<p>' . $message . '</p>';
    }

    /**
     * Generates HTML for a styled notification.
     * 
     * @param array $plugin_names Names of conflicting plugins.
     * @return string HTML for the styled notification.
     */
    private function getStyledNotificationHtml(array $plugin_names): string {
        $single_plugin = count($plugin_names) === 1 ? $plugin_names[0] : '';
        $content = '';
        
        if (count($plugin_names) > 1) {
            $content = '<ul><li><strong>' . implode('</strong></li><li><strong>', $plugin_names) . '</strong></li></ul>';
        }

        $compatibility_message = count($plugin_names) > 1
            /* translators: %s: brand name of the plugin */
            ? sprintf(__('<strong>%s</strong> is not compatible with the following plugins:', 'beyondseo'), RANKINGCOACH_BRAND_NAME)
            /* translators: 1: brand name of the plugin, 2: name of the single conflicting plugin */
            : sprintf(__('<strong>%1$s</strong> is not compatible with the following plugin: <strong>%2$s</strong>', 'beyondseo'), RANKINGCOACH_BRAND_NAME, $single_plugin);
            
        $deactivation_message = count($plugin_names) > 1
            /* translators: %s: brand name of the plugin */
            ? sprintf(__('To ensure %s works properly, please deactivate the conflicting plugins.', 'beyondseo'), RANKINGCOACH_BRAND_NAME)
            /* translators: %s: brand name of the plugin */
            : sprintf(__('To ensure %s works properly, please deactivate the conflicting plugin.', 'beyondseo'), RANKINGCOACH_BRAND_NAME);

        return '
            <div class="rankingcoach-conflict-notice">
                <div class="rankingcoach-conflict-icon">⚠️</div>
                <div class="rankingcoach-conflict-content">
                    <h4>' . __('Plugin Conflict Detected', 'beyondseo') . '</h4>
                    <p>' . $compatibility_message . '</p>
                    ' . $content . '
                    <p>' . $deactivation_message . '</p>
                </div>
            </div>';
    }

    /**
     * Enqueues assets for the conflict notification.
     */
    public function enqueueAssets(): void {
        if ( ! NotificationManager::instance()?->has_notification( self::NOTIFICATION_ID ) ) {
            return;
        }

        if ( ! $this->isSimpleNotificationEnabled() ) {
            wp_register_style( 'rankingcoach-conflict-notice', false, [], RANKINGCOACH_VERSION );
            wp_enqueue_style( 'rankingcoach-conflict-notice' );
            wp_add_inline_style( 'rankingcoach-conflict-notice', $this->getNotificationCss() );
        }
    }

    /**
     * Returns the CSS for the conflict notification.
     * 
     * @return string
     */
    private function getNotificationCss(): string {
        return '
                .rankingcoach-conflict-notice {
                    display: flex;
                    align-items: flex-start;
                    gap: 15px;
                    padding: 16px 16px 0 16px;
                    margin: 10px 0 20px;
                    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
                    border-radius: 6px;
                }
                .rankingcoach-conflict-icon {
                    font-size: 28px;
                    margin-top: 2px;
                }
                .rankingcoach-conflict-content {
                    flex: 1;
                    font-family: sans-serif;
                    color: #333;
                }
                .rankingcoach-conflict-content h4 {
                    margin: 0 0 12px;
                    font-size: 18px;
                    font-weight: 600;
                    color: #d35400;
                }
                .rankingcoach-conflict-content p {
                    margin: 8px 0;
                    line-height: 1.5;
                }
                .rankingcoach-conflict-content ul {
                    margin: 10px 0 12px;
                    padding-left: 20px;
                    list-style: disc;
                }
                .rankingcoach-conflict-content li {
                    margin-bottom: 4px;
                }';
    }

    // create  a function the add the conflict notification when the plugin is activated, thinking as the same
    // function will give parameter the plugin name and the plugin path, like the removeConflictNotification
    /**
     * Adds a conflict notification when a plugin is activated.
     *
     * @param string $plugin The plugin file path.
     */
    public function addConflictNotification(string $plugin): void
    {
        // Check if the activated plugin is our plugin
        if ($plugin === RANKINGCOACH_PLUGIN_BASENAME) {
            // When our plugin is activated, check for all conflicts
            $conflicting_plugins = $this->getConflictingPlugins();
            if (!empty($conflicting_plugins)) {
                $this->showConflictNotification($conflicting_plugins);
            }
            return;
        }
        
        // Check if the activated plugin is in the incompatible plugins list
        if (!array_key_exists($plugin, $this->incompatible_plugins)) {
            return;
        }

        // Get all active conflicting plugins
        $conflicting_plugins = $this->getConflictingPlugins();
        
        // If we have conflicting plugins (including the newly activated one)
        if (!empty($conflicting_plugins)) {
            $notification_manager = NotificationManager::instance();
            
            // If notification already exists, update it with the new list
            if ($notification_manager?->has_notification(self::NOTIFICATION_ID)) {
                $notification_manager?->remove_by_id(self::NOTIFICATION_ID);
            }
            
            // Show notification with all conflicting plugins
            $this->showConflictNotification($conflicting_plugins);
        }
    }

    /**
     * Removes or updates the conflict notification when a plugin is deactivated.
     *
     * @param string $plugin The plugin file path.
     */
    public function removeConflictNotification(string $plugin): void
    {
        $notification_manager = NotificationManager::instance();
        
        // If our plugin is being deactivated, remove all conflict notifications
        if ($plugin === RANKINGCOACH_PLUGIN_BASENAME) {
            if ($notification_manager?->has_notification(self::NOTIFICATION_ID)) {
                $notification_manager?->remove_by_id(self::NOTIFICATION_ID);
            }
            return;
        }
        
        // Check if the deactivated plugin is in the incompatible plugins list
        if (!array_key_exists($plugin, $this->incompatible_plugins)) {
            return;
        }

        // Check if we have a notification before proceeding
        if (!$notification_manager?->has_notification(self::NOTIFICATION_ID)) {
            return;
        }
        
        // Get all active plugins before checking conflicts
        $active_plugins = get_option('active_plugins', []);
        
        // Check if the plugin being deactivated is still in the active list
        // (WordPress calls our hook before actually removing it from the active list)
        $plugin_key = array_search($plugin, $active_plugins);
        if ($plugin_key !== false) {
            // Remove the plugin from our temporary active plugins list
            unset($active_plugins[$plugin_key]);
        }
        
        // Check for remaining conflicts after this plugin is deactivated
        $remaining_conflicts = array_filter($this->incompatible_plugins, function($plugin_path) use ($active_plugins) {
            return in_array($plugin_path, $active_plugins);
        }, ARRAY_FILTER_USE_KEY);

        $notification_manager?->remove_by_id(self::NOTIFICATION_ID);
        if (!empty($remaining_conflicts)) {
            // If there are still conflicting plugins active, update the notification
            $this->showConflictNotification($remaining_conflicts);
        }
    }
}
