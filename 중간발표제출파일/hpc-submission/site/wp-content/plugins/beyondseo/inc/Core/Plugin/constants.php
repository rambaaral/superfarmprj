<?php
declare(strict_types=1);

use RankingCoach\Inc\Core\PluginConfiguration;

if ( ! defined( 'ABSPATH' ) ) {
    exit();
}

// === Environments ===
defined('RANKINGCOACH_LOCAL_ENVIRONMENT')         || define('RANKINGCOACH_LOCAL_ENVIRONMENT', 'local');
defined('RANKINGCOACH_STAGING_ENVIRONMENT')       || define('RANKINGCOACH_STAGING_ENVIRONMENT', 'staging');
defined('RANKINGCOACH_PRODUCTION_ENVIRONMENT')    || define('RANKINGCOACH_PRODUCTION_ENVIRONMENT', 'production');


// === Environment Detection ===
if ( ! defined('RANKINGCOACH_COMMON_DEV_ENVIRONMENT_HOST') ) {
    // default: https://rankingcoach.com
    // leave it NULL and will use the WP site URL
    //define('RANKINGCOACH_COMMON_DEV_ENVIRONMENT_HOST', 'https://www.aradon.ro');
    define('RANKINGCOACH_COMMON_DEV_ENVIRONMENT_HOST', 'https://www.bihon.ro');
}
if ( ! defined('RANKINGCOACH_COMMON_DEV_ENVIRONMENT_EMAIL') ) {
    // default: romeo.tamas@gmail.com
    // leave it NULL and will use the WP admin email
    define('RANKINGCOACH_COMMON_DEV_ENVIRONMENT_EMAIL', null);
}
// prevent to send localhost or IP addresses on production
if ( ! defined('RANKINGCOACH_PRODUCTION_ENVIRONMENT_HOST') ) {
    define('RANKINGCOACH_PRODUCTION_ENVIRONMENT_HOST', 'https://www.arq.ro');
}

/**
 * Define plugin environment (local, staging, production).
 * Can be overridden in wp-config.php by defining RANKINGCOACH_ENVIRONMENT.
 * Uses EnvironmentDetector which checks:
 * 1. wp-config.php override
 * 2. Environment variables
 * 3. localhost detection
 * 4. Database domain whitelist
 * 5. Defaults to production
 */
if ( ! defined('RANKINGCOACH_ENVIRONMENT') ) {
    require_once RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Environment/EnvironmentDetector.php';
    define('RANKINGCOACH_ENVIRONMENT',
        \RankingCoach\Inc\Core\Environment\EnvironmentDetector::detect()
    );
}

// === Plugin Version ===
// Read the plugin file and extract the version from the header (without requiring wp-admin files)
$beyondseoPluginData = PluginConfiguration::getInstance()->getPluginData();
$beyondseoMetaVersion = $beyondseoPluginData['Version'] ?? '1.0.0';
$beyondseoMetaName = $beyondseoPluginData['Name'] ?? 'BeyondSeo';

// === Plugin Metadata ===
defined('RANKINGCOACH_VERSION')         || define('RANKINGCOACH_VERSION', $beyondseoMetaVersion);
defined('RANKINGCOACH_NAME')         || define('RANKINGCOACH_NAME', $beyondseoMetaName);

// Dynamic brand configuration
$beyondseo_brandConfigPath = dirname(RANKINGCOACH_FILE) . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Plugin' . DIRECTORY_SEPARATOR . 'brand-config.json';
$beyondseo_brandName = null;
$beyondseo_brandSlug = null;

if (file_exists($beyondseo_brandConfigPath)) {
    $beyondseo_brandConfig = json_decode(file_get_contents($beyondseo_brandConfigPath), true);
    if (json_last_error() === JSON_ERROR_NONE && isset($beyondseo_brandConfig['brand_name'])) {
        $beyondseo_brandName = $beyondseo_brandConfig['brand_name'];
        $beyondseo_brandSlug = $beyondseo_brandConfig['text-domain'];
    }
}

// Use fallback from config file if brand_name is not set
if (!$beyondseo_brandName && file_exists($beyondseo_brandConfigPath)) {
    $beyondseo_brandConfig = json_decode(file_get_contents($beyondseo_brandConfigPath), true);
    if (json_last_error() === JSON_ERROR_NONE && isset($beyondseo_brandConfig['fallback_brand_name'])) {
        $beyondseo_brandName = $beyondseo_brandConfig['fallback_brand_name'];
    }
}

if(empty($beyondseo_brandName)) {
    // Default brand name if not set in config
    $beyondseo_brandName = 'BeyondSeo';
}

if(empty($beyondseo_brandSlug)) {
    // Default brand slug if not set in config
    $beyondseo_brandSlug = 'beyondseo';
}

defined('RANKINGCOACH_BRAND_NAME')      || define('RANKINGCOACH_BRAND_NAME', $beyondseo_brandName);
defined('RANKINGCOACH_BRAND_SLUG')      || define('RANKINGCOACH_BRAND_SLUG', sanitize_title($beyondseo_brandSlug));

// === File References ===
if (defined('RANKINGCOACH_FILE')) {
    defined('RANKINGCOACH_FILENAME') || define('RANKINGCOACH_FILENAME', strtolower(pathinfo(RANKINGCOACH_FILE, PATHINFO_FILENAME)));
    defined('RANKINGCOACH_PLUGIN_URL') || define('RANKINGCOACH_PLUGIN_URL', \plugin_dir_url(RANKINGCOACH_FILE));
    defined('RANKINGCOACH_PLUGIN_BASENAME') || define('RANKINGCOACH_PLUGIN_BASENAME', (defined('IONOS_IS_STRETCH') ? 'plugins/' : '')  . plugin_basename(RANKINGCOACH_FILE));
}

if (defined('RANKINGCOACH_FILE')) {
    defined('RANKINGCOACH_PLUGIN_FOLDER_NAME') || define('RANKINGCOACH_PLUGIN_FOLDER_NAME', basename(dirname(RANKINGCOACH_FILE)));
}

// === Namespaces ===
defined('RANKINGCOACH_NAMESPACE')       || define('RANKINGCOACH_NAMESPACE', 'RankingCoach\\');
defined('RANKINGCOACH_INC_NAMESPACE')   || define('RANKINGCOACH_INC_NAMESPACE', RANKINGCOACH_NAMESPACE . 'Inc\\');

// === Environment & Debug ===
defined('RANKINGCOACH_ENABLE_LOGGING')  || define('RANKINGCOACH_ENABLE_LOGGING', true);
defined('RANKINGCOACH_WP_DEBUG')        || define('RANKINGCOACH_WP_DEBUG', (defined('WP_DEBUG') && WP_DEBUG));

// === REST API ===
defined('RANKINGCOACH_REST_API_LEGACY_BASE') || define('RANKINGCOACH_REST_API_LEGACY_BASE', 'seo');
defined('RANKINGCOACH_REST_API_BASE')     || define('RANKINGCOACH_REST_API_BASE', 'rankingcoach/' . RANKINGCOACH_REST_API_LEGACY_BASE);
defined('RANKINGCOACH_REST_APP_BASE')     || define('RANKINGCOACH_REST_APP_BASE', 'rankingcoach/' . RANKINGCOACH_REST_API_LEGACY_BASE);
defined('RANKINGCOACH_JWT_AUTH_CORS_ENABLE') || define('RANKINGCOACH_JWT_AUTH_CORS_ENABLE', false);

// === Directory Structure (Base) ===
defined('RANKINGCOACH_PLUGIN_INCLUDES_DIR')     || define('RANKINGCOACH_PLUGIN_INCLUDES_DIR', RANKINGCOACH_PLUGIN_DIR . 'inc' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_PLUGIN_REACT_DIR')        || define('RANKINGCOACH_PLUGIN_REACT_DIR', RANKINGCOACH_PLUGIN_DIR . 'react' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_PLUGIN_LANGUAGE_DIR')     || define('RANKINGCOACH_PLUGIN_LANGUAGE_DIR', RANKINGCOACH_PLUGIN_DIR . 'languages' . DIRECTORY_SEPARATOR);

// === Directory Structure (Core) ===
defined('RANKINGCOACH_PLUGIN_CORE_DIR')         || define('RANKINGCOACH_PLUGIN_CORE_DIR', RANKINGCOACH_PLUGIN_INCLUDES_DIR . 'Core' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_PLUGIN_ADMIN_DIR')        || define('RANKINGCOACH_PLUGIN_ADMIN_DIR', RANKINGCOACH_PLUGIN_CORE_DIR . 'Admin' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_PLUGIN_ADMIN_URL')        || define('RANKINGCOACH_PLUGIN_ADMIN_URL', RANKINGCOACH_PLUGIN_URL . 'inc' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR);

// === Modules ===
defined('RANKINGCOACH_PLUGIN_MODULES_DIR')          || define('RANKINGCOACH_PLUGIN_MODULES_DIR', RANKINGCOACH_PLUGIN_INCLUDES_DIR . 'Modules' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_PLUGIN_MODULES_LIBRARY_DIR')  || define('RANKINGCOACH_PLUGIN_MODULES_LIBRARY_DIR', RANKINGCOACH_PLUGIN_MODULES_DIR . 'ModuleLibrary' . DIRECTORY_SEPARATOR);

// === Logging ===
defined('RANKINGCOACH_LOG_DIR')                     || define('RANKINGCOACH_LOG_DIR',   wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'beyondseo' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_API_LOG_DIR')                 || define('RANKINGCOACH_API_LOG_DIR', wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'beyondseo' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR);
defined('RANKINGCOACH_LOG_VERBOSE')                 || define('RANKINGCOACH_LOG_VERBOSE', RANKINGCOACH_WP_DEBUG && RANKINGCOACH_ENABLE_LOGGING && RANKINGCOACH_ENVIRONMENT !== RANKINGCOACH_PRODUCTION_ENVIRONMENT);

// === Translated Categories Directory ===
defined('RANKINGCOACH_DEFAULT_CATEGORIES_DIR')      || define('RANKINGCOACH_DEFAULT_CATEGORIES_DIR', RANKINGCOACH_PLUGIN_LANGUAGE_DIR . 'categories' . DIRECTORY_SEPARATOR);

// === Capabilities ===
defined('ALLOWED_RANKINGCOACH_PAGES')               || define('ALLOWED_RANKINGCOACH_PAGES', [
    'post-new.php',
    'post.php',
    'page.php',
    'toplevel_page_rankingcoach-main',
    'admin_page_rankingcoach-main',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-main',
    'admin_page_rankingcoach-settings',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-settings',
    'admin_page_rankingcoach-activation',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-activation',
    'admin_page_rankingcoach-registration',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-registration',
    'admin_page_rankingcoach-onboarding',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-onboarding',
    'admin_page_rankingcoach-generalSettings',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-generalSettings',
    'admin_page_rankingcoach-support',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-support',
    'admin_page_rankingcoach-cache',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-cache',
    'admin_page_rankingcoach-connect',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-connect',
    'admin_page_rankingcoach-setupWizard',
    RANKINGCOACH_BRAND_SLUG . '_page_rankingcoach-setupWizard',
]);
defined('ALLOWED_RANKINGCOACH_CUSTOM_TYPES')               || define('ALLOWED_RANKINGCOACH_CUSTOM_TYPES', [
    'post',
    'page',
    'tribe_events',
]);
