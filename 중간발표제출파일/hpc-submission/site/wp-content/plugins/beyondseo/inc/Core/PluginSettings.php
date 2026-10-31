<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Storage\SettingsStorage;
use stdClass;

/**
 * Class PluginSettings
 */
class PluginSettings {

    /** @var array Default plugin settings */
    private const DEFAULT_SETTINGS = [
        'allow_sync_keywords_to_rankingcoach' => true,
        'enable_account_sync' => true,
        'enable_log_cleanup' => true,
        'allow_seo_optimiser_on_saved_posts' => true,
        'beyondseo_comm_opt_in' => false,
        'activation_code_recovery_count' => 0,
        'enable_wp_cron_service' => false,
        'remove_settings_on_deactivation' => false,
        'setup_wizard' => [
            'completed' => false,
            'dismissed' => false,
        ],
        // Temporary store for the wizard's email opt-in until the rankingCoach subscription flow exists.
        'setup_wizard_email' => [
            'email' => '',
            'opt_in' => false,
            'consented_at' => 0,
        ],
        'enable_broken_link_checker_job' => true,
        'disable_wp_heartbeat_service' => false,
        'enable_viewport' => false,
        'open_rc_dashboard_in_new_tab' => false,
        'google_verification_code' => '',
        'bing_verification_code' => '',
        'separators' => [
            'pipe'       => '|',
            'dash'       => '-',
            'en_dash'    => '–',
            'em_dash'    => '—',
            'dot'        => '·',
            'colon'      => ':',
            'bullet'     => '•',
            'angle_double' => '»',
            'angle_single' => '›',
            'tilde'      => '~',
            'asterisk'   => '*',
            'plus'       => '+',
            'slash'      => '/',
            'backslash'  => '\\',
            'equals'     => '=',
            'ellipsis'   => '…',
        ],
        'allowed_countries' => [
            'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'IE' => 'Ireland',
            'DE' => 'Germany', 'AT' => 'Austria', 'CH' => 'Switzerland', 'FR' => 'France',
            'BE' => 'Belgium', 'NL' => 'Netherlands', 'LU' => 'Luxembourg', 'MC' => 'Monaco',
            'IT' => 'Italy', 'ES' => 'Spain', 'PT' => 'Portugal', 'AD' => 'Andorra',
            'DK' => 'Denmark', 'SE' => 'Sweden', 'NO' => 'Norway', 'FI' => 'Finland',
            'IS' => 'Iceland', 'PL' => 'Poland', 'CZ' => 'Czech Republic', 'SK' => 'Slovakia',
            'HU' => 'Hungary', 'RO' => 'Romania', 'BG' => 'Bulgaria', 'GR' => 'Greece',
            'EE' => 'Estonia', 'LV' => 'Latvia', 'LT' => 'Lithuania', 'SI' => 'Slovenia',
            'HR' => 'Croatia', 'RS' => 'Serbia', 'BA' => 'Bosnia and Herzegovina',
            'MK' => 'North Macedonia', 'AL' => 'Albania', 'TR' => 'Turkey', 'CY' => 'Cyprus',
            'MT' => 'Malta', 'AU' => 'Australia', 'NZ' => 'New Zealand', 'MX' => 'Mexico',
            'AR' => 'Argentina', 'BR' => 'Brazil', 'CL' => 'Chile', 'CO' => 'Colombia',
            'PE' => 'Peru', 'UY' => 'Uruguay', 'ZA' => 'South Africa', 'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia', 'IL' => 'Israel', 'IN' => 'India', 'ID' => 'Indonesia',
            'MY' => 'Malaysia', 'PH' => 'Philippines', 'SG' => 'Singapore', 'TH' => 'Thailand',
            'JP' => 'Japan',
        ],
        'supported_languages' => [
            'en' => 'English', 'de' => 'German', 'fr' => 'French', 'it' => 'Italian',
            'es' => 'Spanish', 'pt' => 'Portuguese', 'pl' => 'Polish', 'nl' => 'Dutch',
        ],
        'supported_locales' => [
            'en' => 'en_US', 'en_gb' => 'en_GB', 'en_ca' => 'en_CA', 'de' => 'de_DE',
            'de_at' => 'de_AT', 'fr' => 'fr_FR', 'fr_ca' => 'fr_CA', 'fr_mq' => 'fr_MQ',
            'fr_yt' => 'fr_YT', 'fr_re' => 'fr_RE', 'fr_gp' => 'fr_GP', 'fr_gf' => 'fr_GF',
            'it' => 'it_IT', 'es' => 'es_ES', 'es_mx' => 'es_MX', 'pt' => 'pt_PT',
            'pl' => 'pl_PL', 'nl' => 'nl_NL',
        ],
        'account_details_cache_seconds' => 10800,
        'gmb_categories_cache_seconds' => 10800,
        'top_toolbar_menu_name' => RANKINGCOACH_BRAND_NAME,
        'seo_analysis' => true,
        'seo_score_threshold' => 76,
        'enable_readability_check' => true,
        'focus_keyword_limit' => 5,
        'focus_keyword_analysis' => true,
        'google_analytics_integration' => true,
        'ga_tracking_id' => '',
        'enable_schema_markup' => true,
        'default_schema_type' => 'BlogPosting',
        'site_represents' => 'organization',
        'site_links' => true,
        'organisation_or_person_name' => '',
        'organisation_email' => '',
        'organisation_phone' => '',
        'organisation_logo' => '',
        'organisation_founding_date' => '',
        'organisation_number_of_employees' => [
            'isRange' => false,
            'from' => 0,
            'to' => 0,
            'number' => 0,
        ],
        'run_shortcodes' => false,
        'website_alternate_name' => '',
        'person_manual_name' => '',
        'person_manual_image' => '',
        'organization_social_facebook' => '',
        'organization_social_twitter' => '',
        'organization_social_instagram' => '',
        'organization_social_linkedin' => '',
        'organization_social_youtube' => '',
        'organization_social_tiktok' => '',
        'organization_social_pinterest' => '',
        'organization_social_github' => '',
        'organization_social_tumblr' => '',
        'organization_social_snapchat' => '',
        'organization_social_wikipedia' => '',
        'organization_social_personal_website' => '',
        'organization_additional_social_urls' => [],
        'redirect_manager' => true,
        'redirect_404_to_home' => false,
        'monitoring_404' => true,
        'default_noindex_posts' => false,
        'default_noindex_pages' => false,
        'index_categories' => true,
        'index_tags' => false,
        'enable_social_optimization' => true,
        'default_og_image' => '',
        'default_twitter_card' => 'summary',
        'sitemap' => [
            'enabled' => true,
            'includeImages' => true,
            'maxLinks' => 1000,
            'pingGoogle' => true,
            'pingBing' => true,
        ],
        'enable_local_seo' => true,
        'default_business_type' => 'LocalBusiness',
        'business_latitude' => '',
        'business_longitude' => '',
        'internal_link_suggestions' => true,
        'enable_breadcrumbs' => true,
        'breadcrumb_settings' => [
            'home_text' => 'Home',
            'separator' => ' » ',
            'enable_schema_markup' => true,
            'max_depth' => 4,
            'show_current_as_link' => false,
            'allow_filters' => true,
            'prefix' => 'You are here:',
            'suffix' => '',
            'show_on_posts' => true,
            'show_on_pages' => true,
            'show_on_search' => true,
            'show_on_404' => true,
            'show_on_archives' => true,
            'show_on_categories' => true,
            'show_on_tags' => true,
            'show_on_custom_post_types' => true,
            'show_on_taxonomies' => true,
            'enabled_post_types' => [ 'post', 'page' ],
            'enabled_taxonomies' => [ 'category', 'post_tag', 'product_cat' ],
            'suffixes' => [
                'archive'         => 'Archives for',
                'search'          => 'Search results for',
                '404'             => 'Error 404: Page not found',
                'custom_post'     => 'Custom post type archives for',
                'category'        => 'Categories',
                'taxonomy'        => 'Taxonomy archives for',
            ],
            'prefixes' => [
                'archive'     => '',
                'search'      => '',
                '404'         => '',
                'custom_post' => '',
                'taxonomy'    => '',
            ]
        ],
        'security_noopen' => true,
        'security_nosnippet' => false,
        'enable_lazy_loading' => false,
        'minify_html' => false,
        'enable_robots_txt' => true,
        'include_sitemap_in_robots' => false,
        'enable_rss' => true,
        'rss' => [
            'feeds' => [
                'cleanupEnable' => false,
                'global' => true,
                'globalComments' => true,
                'postComments' => true,
                'attachments' => true,
                'authors' => true,
                'search' => true,
                'archivesIncluded' => [
                    'post',
                    'page',
                    'attachment'
                ],
                'archivesAll' => false,
                'taxonomiesIncluded' => [
                    'category',
                    'post_tag'
                ],
                'taxonomiesAll' => false,
                'atom' => true,
                'rdf' => true,
                'staticBlogPage' => true,
                'paginated' => true
            ],
            'content' => [
                'before' => '',
                'after' => ''
            ]
        ]
    ];

	//======================================
	//======================================

	/** @var string The option key for database storage */
    protected string $option_key = BaseConstants::OPTION_PLUGIN_SETTINGS;

    protected ?object $settings = null;

	/** @var self|null Singleton instance */
	private static ?self $instance = null;

	/**
	 * Private constructor to enforce a Singleton pattern.
	 */
	private function __construct() {
        $this->settings = new stdClass();
		$this->load_settings();
	}

    /**
     * Get the Singleton instance.
     *
     * @param bool $autoload Whether to load settings from the database immediately.
     * @return self
     */
	public static function instance(bool $autoload = false): self {
		if (self::$instance === null) {
			self::$instance = new self();
            if ($autoload) {
                self::$instance->load_settings();
            }
		}
		return self::$instance;
	}

	/**
	 * Load settings from the database and assign them to properties.
	 */
	public function load_settings(): void {
		$saved_settings = get_option($this->option_key, []);
        if(empty($saved_settings)) {
            $this->reset_to_defaults();
            return;
        }
		foreach ($saved_settings as $key => $value) {
            $this->settings->{$key} = $value;
		}
	}

	/**
	 * Save all properties as independent keys in the database.
	 *
	 * Optimized to avoid saving unchanged values.
	 */
	private function save(): void {
		$settings = [];
		foreach (self::DEFAULT_SETTINGS as $key => $default_value) {
			$settings[$key] = $this->settings->{$key} ?? $default_value;
		}

        $storage = new SettingsStorage();
        $snapshot = $storage->read();
        $storage->save($snapshot, array_replace($snapshot['value'], $settings));
	}

	/**
	 * Reset all properties to their default values.
	 */
	public function reset_to_defaults(): void {
		foreach (self::DEFAULT_SETTINGS as $key => $default_value) {
			$this->settings->{$key} = $default_value;
		}
		$this->save();
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key The key of the setting to retrieve.
	 * @return mixed|null The setting value, or null if not found.
	 */
	public function get(string $key): mixed {

        // First try exact match
        if (property_exists($this->settings, $key)) {
            return $this->settings->{$key};
        }

        // If exact match fails, try case-insensitive search
        foreach (self::DEFAULT_SETTINGS as $propertyName => $default_value) {
            if (strcasecmp($propertyName, $key) === 0 && property_exists($this->settings, $propertyName)) {
                return $this->settings->{$propertyName};
            }
        }

        return null;
	}

    /**
     * Get a default setting value.
     *
     * @param string $key The key of the setting to retrieve.
     * @return mixed|null The default value of the setting, or null if not found.
     */
    public  function getDefault(string $key): mixed {
        return self::DEFAULT_SETTINGS[$key] ?? null;
    }

	/**
	 * Set a specific setting value and save immediately.
	 *
	 * Only save to the database if the new value differs from the current value.
	 *
	 * @param string $key The key of the setting to update.
	 * @param mixed $value The new value for the setting.
	 */
	public function set(string $key, mixed $value): void {
        if (!array_key_exists($key, self::DEFAULT_SETTINGS)) {
            return;
        }
        $storage = new SettingsStorage();
        $snapshot = $storage->read();
        $current = $snapshot['rows'] === [] ? self::DEFAULT_SETTINGS : $snapshot['value'];
        $next = $storage->merge($current, [$key => $value]);
        $storage->save($snapshot, $next);
        $this->settings = (object) array_replace(self::DEFAULT_SETTINGS, $storage->read()['value']);
	}

	/**
	 * Get all settings as an associative array.
	 *
	 * @return array All settings as key-value pairs.
	 */
	public function get_all(): array {
		$settings = [];
		foreach (self::DEFAULT_SETTINGS as $key => $default_value) {
            if(property_exists($this->settings, $key)) {
                $settings[$key] = $this->settings->{$key};
            } else {
                $settings[$key] = $default_value;
            }
		}
		return $settings;
	}

	/**
	 * Get the option key for database storage.
	 *
	 * @return string The option key.
	 */
	public function get_option_key(): string {
		return $this->option_key;
	}

    /**
     * Load available variables for SEO templates.
     *
     * This method populates the `variables` property with available WordPress variables.
     */
    public function load_variables(): void
    {
        if(property_exists($this->settings, 'variables')) {
            $this->settings->variables = WordpressHelpers::get_available_WPVariables();
        }
    }
}
