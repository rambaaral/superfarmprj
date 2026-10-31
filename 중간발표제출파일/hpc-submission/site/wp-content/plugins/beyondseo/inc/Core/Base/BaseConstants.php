<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use ReflectionClass;

/**
 * Class BaseConstants
 */
class BaseConstants
{
    // DEACTIVATION CONSTANTS
    public const OPTION_DELETE_ON_DEACTIVATION = 'rankingcoach_delete_on_deactivation';

    // VERSION-RELATED CONSTANTS
    public const OPTION_PLUGIN_VERSION = 'rankingcoach_plugin_version';
    public const OPTION_DB_VERSION = 'rankingcoach_db_version';
    public const OPTION_API_VERSION = 'rankingcoach_api_version';

    // REGISTRATION & LICENSING
    public const OPTION_LAST_REGISTRATION_ATTEMPT = 'rankingcoach_last_registration_attempt';
    public const OPTION_REGISTRATION_COUNTRY_SHORTCODE = 'rankingcoach_registration_country_shortcode';
    public const OPTION_REGISTRATION_EMAIL_ADDRESS = 'rankingcoach_registration_email_address';
    public const OPTION_PARTNER_INTEGRATION = 'partner_integration_beyondseo';
    public const OPTION_PARTNER_INTEGRATION_NAME = 'partner_integration_beyondseo_name';

    // INSTALLATION & ONBOARDING CONSTANTS
    public const OPTION_INSTALLATION_DATE = 'rankingcoach_installation_date';
    public const OPTION_INSTALLATION_ID = 'rankingcoach_installation_id';
    public const OPTION_ONBOARDING_COLLECT_DATE = 'rankingcoach_onboarding_collect_date';
    public const OPTION_ONBOARDING_FINISH_DATE = 'rankingcoach_onboarding_finish_date';
    public const OPTION_ONBOARDING_STEPS = 'rankingcoach_onboarding_steps';
    public const OPTION_ACCOUNT_ONBOARDING_ON_RC = 'rankingcoach_onboarding_made_on_rc';
    public const OPTION_ACCOUNT_ONBOARDING_ON_RC_LAST_UPDATE = 'rankingcoach_onboarding_made_on_rc_last_update';
    public const OPTION_ACCOUNT_ONBOARDING_ON_WP = 'rankingcoach_onboarding_made_on_wp';
    public const OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE = 'rankingcoach_onboarding_made_on_wp_last_update';
    public const OPTION_ACCOUNT_ONBOARDING_COMPLETED = 'rankingcoach_account_onboarding_completed';

    // AUTHENTICATION & ACCESS CONSTANTS
    public const OPTION_REFRESH_TOKEN = 'rankingcoach_refresh_token';
    public const OPTION_ACCESS_TOKEN = 'rankingcoach_access_token';
    public const OPTION_APPLICATION_PASSWORD = 'rankingcoach_application_key';
    public const OPTION_ACTIVATION_CODE = 'rankingcoach_activation_code';

    // ACCOUNT & SUBSCRIPTION CONSTANTS
    public const OPTION_RANKINGCOACH_ACCOUNT_ID = 'rankingcoach_account_id';
    public const OPTION_RANKINGCOACH_PROJECT_ID = 'rankingcoach_project_id';
    public const OPTION_RANKINGCOACH_LOCATION_ID = 'rankingcoach_location_id';
    public const OPTION_RANKINGCOACH_SUBSCRIPTION = 'rankingcoach_subscription';
    public const OPTION_RANKINGCOACH_COUNTRY_CODE = 'rankingcoach_country_code';
    public const OPTION_RANKINGCOACH_SUBSCRIPTION_HISTORY = 'rankingcoach_subscription_history';
    public const OPTION_RANKINGCOACH_UPGRADE_PLANS = 'rankingcoach_upgrade_plans';
    public const OPTION_RANKINGCOACH_ONBOARDING_URL = 'rankingcoach_onboarding_url';
    public const OPTION_RANKINGCOACH_COUPON_CODE = 'rankingcoach_coupon_code';
    public const OPTION_RANKINGCOACH_REGISTER_COUNTRY_CODE = 'rankingcoach_register_country_code';

    // PLUGIN SETTINGS & MODULES
    public const OPTION_PLUGIN_SETTINGS = 'rankingcoach_settings';
    public const OPTION_LOADED_MODULES = 'rankingcoach_loaded_modules';
    public const OPTION_INSTALLED_MODULES = 'rankingcoach_installed_modules';
    public const OPTION_AUTOUPDATE_PLUGIN_UPDATE_INFO = 'rankingcoach_autoupdate_plugin_update_info';
    public const OPTION_PLUGIN_ACTIVATION_DONE = 'rankingcoach_plugin_activation_once_done';
    public const OPTION_PLUGIN_ACTIVATION_LOCK = 'rankingcoach_plugin_activation_once_lock';

    // ANALYTICS & REPORTING CONSTANTS
    public const OPTION_CRONJOB_REPORT = 'rankingcoach_cronjob_report';
    public const OPTION_CRONJOB_DAILY_EVENT = 'rankingcoach_daily_event';
    public const OPTION_CRONJOB_TWICE_HOURLY_EVENT = 'rankingcoach_twice_hourly_event';
    public const OPTION_CRONJOB_HOURLY_EVENT = 'rankingcoach_hourly_event';
    public const OPTION_NOTIFICATIONS = 'rankingcoach_notifications';
    public const OPTION_SYNC_KEYWORDS_LAST_SYNC = 'rankingcoach_sync_keywords_last_sync';
    public const OPTION_SYNC_KEYWORDS_REMAINS_KEYWORDS = 'rankingcoach_sync_keywords_remains_keywords';
    public const OPTION_WP_CRON_LAST_CHECK = 'rankingcoach_wp_cron_last_check';
    public const OPTION_NOT_CONNECTED_NOTICE_DISMISSED = 'rankingcoach_not_connected_notice_dismissed';
    public const OPTION_RANKINGCOACH_MAX_ALLOWED_KEYWORDS = 'rankingcoach_max_allowed_keywords';
    public const OPTION_USE_PLUGIN_PAGE_KEYWORDS_DATA = 'rankingcoach_use_plugin_page_keywords_data';
    public const OPTION_REMEMBERED_KEYWORDS = 'rankingcoach_remembered_keywords';
    
    // ANALYSIS CONSTANTS
    public const OPTION_ANALYSIS_WEBSITE_SCORE_AVERAGE = 'rankingcoach_analysis_website_score';
    public const OPTION_ANALYSIS_WEBSITE_PAGES_COUNT = 'rankingcoach_analysis_website_pages_count';
    public const OPTION_ANALYSIS_SCORE_MIN = 'rankingcoach_analysis_score_min';
    public const OPTION_ANALYSIS_SCORE_MAX = 'rankingcoach_analysis_score_max';

    // SEO ANALYSIS CONSTANTS
    public const OPTION_ANALYSIS_SEO_SCORE = 'rankingcoach_analysis_seo_score';
    public const OPTION_ANALYSIS_DATE_TIMESTAMP = 'rankingcoach_analysis_date_timestamp';
    public const OPTION_ANALYSIS_STATUS = 'rankingcoach_analysis_status';
    public const OPTION_ANALYSIS_ISSUES_COUNT = 'rankingcoach_analysis_issues_count';
    public const OPTION_ANALYSIS_SUGGESTIONS_COUNT = 'rankingcoach_analysis_suggestions_count';
    public const OPTION_ANALYSIS_SCORE_BREAKDOWN = 'rankingcoach_analysis_score_breakdown';

    // ANALYSIS CACHING & OPTIMIZATION CONSTANTS
    public const OPTION_ANALYSIS_CONTENT_HASH = 'rankingcoach_analysis_content_hash';
    public const OPTION_ANALYSIS_HASH_ALGORITHM = 'sha256'; // Hash algorithm for content comparison
    
    // CATEGORY CONSTANTS
    public const OPTION_CATEGORIES_LAST_UPDATE = 'rankingcoach_translated_categories_last_update';
    public const OPTION_CATEGORIES_LAST_LANGUAGE = 'rankingcoach_translated_categories_last_language';

    // SCHEMA CONSTANTS
    public const OPTION_SCHEMA_TYPE = 'rankingcoach_schema_type';
    public const OPTION_SCHEMA_OUTPUT = 'rankingcoach_schema_output';
    public const OPTION_SCHEMA_CACHE = 'rankingcoach_schema_cache';
    public const OPTION_SCHEMA_CACHE_HASH = 'rankingcoach_schema_cache_hash';
    public const OPTION_SCHEMA_CACHE_CLEANUP = 'rankingcoach_schema_cache_cleanup';
    public const OPTION_SCHEMA_CACHE_CLEARED = 'rankingcoach_schema_cache_cleared';
    public const OPTION_SCHEMA_VALIDATION_RESULTS = 'rankingcoach_schema_validation_results';

    // SITEMAP
    public const OPTION_SITEMAP_INITIALIZED = 'rankingcoach_sitemap_initialized';
    public const OPTION_SITEMAP_TRAILING_SLASH_FIXED = 'rankingcoach_sitemap_trailing_slash_fixed';
    public const OPTION_FLUSH_REWRITE_RULES = 'rankingcoach_flush_rewrite_rules';
    
    // ERROR & CONFLICT HANDLING
    public const OPTION_SIMPLE_CONFLICT_NOTICE = 'rankingcoach_simple_conflict_notice';
    public const OPTION_DEACTIVATE_CONFLICTING_PLUGINS = 'rankingcoach_deactivate_conflicting_plugins';
    public const OPTION_LAST_ERROR_MESSAGE = 'rankingcoach_last_error_message';
    public const OPTION_SETUP_AI_FAIL_ERROR_COUNT = 'rankingcoach_setup_ai_fail_error_count';

    // FEATURES
    public const OPTION_METATAGS_TWITTER_HANDLE = 'rankingcoach_metatags_twitter_handle';
    public const OPTION_SELECTED_SOCIAL_IMAGE_SOURCE = 'rankingcoach_selected_social_image_source';
    public const OPTION_DEFAULT_SOCIAL_IMAGE = 'rankingcoach_default_social_image';
    public const OPTION_SOCIAL_IMAGE_SOURCES = 'rankingcoach_social_image_sources';
    
    // UPSELLING
    public const OPTION_UPSELL_URLS = 'rankingcoach_upsell_urls';
    public const OPTION_UPSELL_FORCE_CHECK = 'rankingcoach_upsell_force_check';
    public const OPTION_UPSELL_LAST_CHECK_TIMESTAMP = 'rankingcoach_upsell_last_check_timestamp';
    public const OPTION_UPSELL_RETRY_COUNT = 'rankingcoach_upsell_retry_count';

    // SECURITY & AUTHENTICATION
    public const OPTION_SECURE_TOKEN = 'rankingcoach_secure_token_with_ttl';

    // RESELLER ACCOUNT
    public const OPTION_IS_RESELLER_ACCOUNT = 'rankingcoach_is_reseller_account';
    public const OPTION_ACCOUNT_SETUP_SETTINGS = 'rankingcoach_account_setup_settings';
    public const OPTION_LOCATION_SETUP_SETTINGS = 'rankingcoach_location_setup_settings';

    // ENVIRONMENT DETECTION
    public const OPTION_ENVIRONMENT_DOMAINS = 'rankingcoach_environment_domains';

    // WIDGET & DASHBOARD
    public const OPTION_UPSELL_WIDGET_ID = 'rankingcoach_upsell_widget';

    // Setup wizard
    public const TRANSIENT_SETUP_WIZARD_REDIRECT = 'rankingcoach_setup_wizard_redirect';
    /** Suggestion types the setup wizard checks */
    public const SETUP_WIZARD_CHECKS = [
        Suggestion::PAGE_NOT_INDEXED,
        Suggestion::META_DESCRIPTION_MISSING,
        Suggestion::META_TITLE_MISSING,
        Suggestion::MISSING_PRIMARY_KEYWORD,
        Suggestion::KEYWORD_MISSING_IN_META_TITLE,
        Suggestion::MISSING_H1_TAG,
        Suggestion::PRIMARY_KEYWORD_MISSING_IN_HEADINGS,
    ];
    public const OPTION_REVIEW_DISPLAY_COUNT = 'rankingcoach_review_display_count';
    public const OPTION_REVIEW_COOLDOWN_UNTIL = 'rankingcoach_review_cooldown_until';
    public const OPTION_REVIEW_PERMANENT_STOP = 'rankingcoach_review_permanent_stop';
    public const OPTION_REVIEW_PLUGIN_OBSERVED_AT = 'rankingcoach_review_plugin_observed_at';
    public const OPTION_REVIEW_CUSTOMER_STATE = 'rankingcoach_review_customer_state';
    public const OPTION_REVIEW_ONBOARDING_COMPLETED_AT = 'rankingcoach_review_onboarding_completed_at';
    public const OPTION_REVIEW_MEANINGFUL_USE_AT = 'rankingcoach_review_meaningful_use_at';
    public const OPTION_REVIEW_POST_MILESTONES = 'rankingcoach_review_post_milestones';
    public const OPTION_REVIEW_SITE_GREEN = 'rankingcoach_review_site_green';

    // SINGLE POST META KEYS
    public const META_KEY_CUSTOM_SOCIAL_IMAGE = 'rankingcoach_custom_social_image';
    public const META_KEY_MANUAL_IMAGE = 'rankingcoach_manual_image';
    public const META_KEY_LOCAL_KEYWORDS = 'rankingcoach_local_keywords';
    public const META_KEY_TITLE_SEPARATOR = 'rankingcoach_title_separator';
    public const META_KEY_IS_SYNDICATED = 'rankingcoach_is_syndicated';
    public const META_KEY_SEO_KEYWORDS_TEMPLATE = 'rankingcoach_seo_keywords_template';
    public const META_KEY_SEO_KEYWORDS_VARIABLES = 'rankingcoach_seo_keywords_variables';
    public const META_KEY_PRIMARY_KEYWORD = 'rankingcoach_primary_keyword';
    public const META_KEY_SECONDARY_KEYWORDS = 'rankingcoach_secondary_keywords';
    public const META_KEY_SEO_KEYWORDS = 'rankingcoach_seo_keywords';
    public const META_KEY_SEO_TITLE_TEMPLATE = 'rankingcoach_seo_title_template';
    public const META_KEY_SEO_TITLE_VARIABLES = 'rankingcoach_seo_title_variables';
    public const META_KEY_SEO_TITLE = 'rankingcoach_seo_title';
    public const META_KEY_SEO_DESCRIPTION_TEMPLATE = 'rankingcoach_seo_description_template';
    public const META_KEY_SEO_DESCRIPTION_VARIABLES = 'rankingcoach_seo_description_variables';
    public const META_KEY_SEO_DESCRIPTION = 'rankingcoach_seo_description';

    // EXTERNAL URLS
    public const URL_DOCUMENTATION = 'https://grow.rankingcoach.com/wordpress';
    public const URL_SUPPORT = 'https://grow.rankingcoach.com/wordpress/contact';
    public const URL_SUPPORT_IONOS = 'https://my.ionos.com/support/contact';
    public const URL_SUPPORT_IONOS_DE = 'https://mein.ionos.de/support/contact';
    public const URL_REVIEW = 'https://wordpress.org/support/plugin/beyondseo/reviews/#new-post';
    public const URL_PRIVACY_POLICY = 'https://www.rankingcoach.com/en-us/privacy-policy';
    public const URL_TERMS_AND_CONDITIONS = 'https://www.rankingcoach.com/en-us/terms-and-conditions';
    public const URL_CUSTOMER_REVIEWS = 'https://www.reviews.io/company-reviews/store/www.rankingcoach.com#page:Qr';

    // PARTNER CHANNELS (as detected and stored by ChannelFlow\ChannelResolver)
    public const CHANNEL_DIRECT = 'direct';
    // Channel value reported to rankingCoach support for direct-channel installs ("dc" = direct channel).
    public const SUPPORT_CHANNEL_DEFAULT = 'dc';
    public const CHANNEL_IONOS = 'ionos';
    public const CHANNEL_EXTENDIFY = 'extendify';

    /**
     * Support desks of partners that handle customer support themselves, keyed by partner
     * channel and then by market code (uppercase, e.g. "DE"). PARTNER_SUPPORT_DEFAULT_MARKET
     * is the partner-wide fallback when the market is unknown or has no dedicated entry.
     */
    public const PARTNER_SUPPORT_DEFAULT_MARKET = '*';
    public const PARTNER_SUPPORT_URLS = [
        self::CHANNEL_IONOS => [
            'DE' => self::URL_SUPPORT,
            self::PARTNER_SUPPORT_DEFAULT_MARKET => self::URL_SUPPORT,
        ],
    ];

    // ORIGINS CONSTANTS
    public const OPTION_LAST_KNOWN_ORIGIN = 'rankingcoach_last_known_origin';

    // ADDRESS PREFILLING
    public const OPTION_PREFILLED_ADDRESS = 'rankingcoach_prefilled_address';

    /**
     * Retrieves all constant names defined in the current class using reflection.
     *
     * @return array An associative array of constant names and their values.
     */
    public static function getOptionNames(): array
    {
        $reflectionClass = new ReflectionClass(__CLASS__);
        $constants = $reflectionClass->getConstants();
        $optionNames = [];

        foreach ($constants as $key => $value) {
            if (strpos($key, 'OPTION_') === 0 && $key !== 'OPTION_ANALYSIS_HASH_ALGORITHM') {
                $optionNames[$key] = $value;
            }
        }

        if (class_exists('RankingCoach\Inc\Core\Helpers\CoreHelper') && method_exists('RankingCoach\Inc\Core\Helpers\CoreHelper', 'filterExcludedOptions')) {
            $optionNames = \RankingCoach\Inc\Core\Helpers\CoreHelper::filterExcludedOptions($optionNames);
        }

        return $optionNames;
    }
}
