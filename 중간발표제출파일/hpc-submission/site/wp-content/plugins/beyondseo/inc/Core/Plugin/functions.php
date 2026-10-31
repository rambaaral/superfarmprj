<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit();
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\PluginConfiguration;
use RankingCoach\Inc\Exceptions\ExceptionHandler;
use RankingCoach\Inc\Modules\ModuleManager;
use Composer\Autoload\ClassLoader;

require_once RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Plugin/plugin-check.php';

/**
 * RankingCoach - autoload function for Core classes.
 *
 * Requirements to use this autoloader:
 * - the constants RANKINGCOACH_INC_NAMESPACE and RANKINGCOACH_PLUGIN_DIR must be defined.
 * - the spl_autoload_register function must be called before any class is used.
 * - the RankingCoachPlugin::initialize() method must be called to set up the plugin.
 *
 * @param string $class The fully qualified class name.
 * @return void
 */
spl_autoload_register(function (string $class): void {
    // Define namespace to directory mappings with all major namespaces
    $namespace_mappings = [
        RANKINGCOACH_INC_NAMESPACE . 'Core\\Base\\' => RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Base/',
        RANKINGCOACH_INC_NAMESPACE . 'Core\\Rest\\' => RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Rest/',
        RANKINGCOACH_INC_NAMESPACE . 'Core\\Helpers\\' => RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Helpers/',
        RANKINGCOACH_INC_NAMESPACE . 'Core\\' => RANKINGCOACH_PLUGIN_DIR . 'inc/Core/',
        RANKINGCOACH_INC_NAMESPACE . 'Modules\\' => RANKINGCOACH_PLUGIN_DIR . 'inc/Modules/',
        RANKINGCOACH_INC_NAMESPACE . 'Exceptions\\' => RANKINGCOACH_PLUGIN_DIR . 'inc/Exceptions/',
        RANKINGCOACH_INC_NAMESPACE . 'Integrations\\' => RANKINGCOACH_PLUGIN_DIR . 'inc/Integrations/',
        RANKINGCOACH_INC_NAMESPACE . 'Interfaces\\' => RANKINGCOACH_PLUGIN_DIR . 'inc/Interfaces/',
        RANKINGCOACH_INC_NAMESPACE . 'Traits\\' => RANKINGCOACH_PLUGIN_DIR . 'inc/Traits/',
        RANKINGCOACH_INC_NAMESPACE => RANKINGCOACH_PLUGIN_DIR . 'inc/'
    ];

    // Find the appropriate base directory for the namespace
    foreach ($namespace_mappings as $namespace => $directory) {
        if (str_starts_with($class, $namespace)) {
            $relative_class = substr($class, strlen($namespace));
            $file = $directory . str_replace('\\', '/', $relative_class) . '.php';

            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

/**
 * RankingCoach - debug function for early loading of text-domain (translations).
 * Only executes when WP_DEBUG is enabled.
 */
add_action('muplugins_loaded', function() {
    // Only run in debug mode
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    // Include debug file for text domain loading issues
    if (file_exists(RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Plugin/debug-textdomain.php')) {
        require_once RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Plugin/debug-textdomain.php';
        rc_debug_textdomain_init();
    }
}, 1);

/**
 * Helper function to get the module instance
 *
 * @param string $moduleName - The name of the module.
 * @param bool $returnInfo
 *
 * @return mixed
 * @throws ReflectionException
 * @throws Exception
 */
function beyondseo_rcm( string $moduleName, bool $returnInfo = true ): mixed { //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
    /** @var ModuleManager $moduleManager */
    $moduleManager = ModuleManager::instance();
    return $moduleManager->loadModule($moduleName, $returnInfo);
}

/**
 * Helper function to get the plugin metadata
 * Naming: rcpd - rankingCoach plugin data
 *
 * @return array|null
 */
function beyondseo_rcpd(): ?array { //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound

    return PluginConfiguration::getInstance()->getPluginData();
}

/**
 * Helper function to render the admin error notice
 * Naming: rcren - rankingCoach render error notice
 * @param Exception $e
 * @return void
 */
function beyondseo_rcren(Exception $e): void { //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
    if (is_admin()) {
        update_option(BaseConstants::OPTION_LAST_ERROR_MESSAGE, $e->getMessage());
        add_action('admin_notices', [ RankingCoachPlugin::class, 'renderPluginErrorNotice']);
    } else {
        beyondseo_rclh($e->getMessage(), 'ERROR');
    }
}

/**
 * Helper function to log handler on rankingCoach way
 *
 * @param string $message - The message to be logged.
 * @param string $level - The level of the log message. The default is 'INFO'.
 *                      - Allowed values are 'INFO', 'WARNING', 'ERROR', 'DEBUG'.
 */
function beyondseo_rclh(string $message, string $level = 'INFO'): void { //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
    // Early return if logging is disabled
    if (!defined('RANKINGCOACH_ENABLE_LOGGING') || !RANKINGCOACH_ENABLE_LOGGING) return;

    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        // In non-debug mode, only log WARNING and ERROR
        if ($level !== 'ERROR') {
            return;
        }
    }


    // Validate log level
    $allowed_levels = ['INFO', 'WARNING', 'ERROR', 'DEBUG'];
    if ( !in_array($level, $allowed_levels) ) {
        return;
    }

    // Format the message
    $timestamp = gmdate('Y-m-d H:i:s');
    $formatted_message = "[$timestamp] [$level] $message";

    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[BeyondSEO] DEBUG: ' . $formatted_message);
    }

    // Get log file path using helper function
    $log_file = beyondseo_rclp('standard');
    if ($log_file === false) {
        return; // Logging disabled or directory creation failed
    }
    
    // Write to a log file with error handling
    $log_dir = dirname($log_file);
    
    if (function_exists('wp_filesystem_direct_access')) {
        if (wp_filesystem_direct_access() || (file_exists($log_file) && wp_is_writable($log_file))) {
            @file_put_contents($log_file, $formatted_message . "\n", FILE_APPEND);
        }
    } else {
        @file_put_contents($log_file, $formatted_message . "\n", FILE_APPEND);
    }
}

/**
 * Helper function to log JSON data in a specific format
 * Naming: rclh_json - rankingCoach log handler for JSON data
 *
 * @param array $data - The data to be logged.
 * @param string $type - The type of the log. Default is 'core'.
 */
function beyondseo_rclh_json(array $data, string $type = 'core'): void { //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
    // Early return if logging is disabled
    if (!defined('RANKINGCOACH_ENABLE_LOGGING') || !RANKINGCOACH_ENABLE_LOGGING) return;
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return; // Only log in debug mode
    }

    // Normalize the type to lowercase and trim whitespace
    $type = strtolower(trim($type));

    $timestamp = gmdate('c'); // ISO 8601 format
    $data['timestamp'] = $timestamp;

    if (empty($data['context_id'])) {
        $data['context_id'] = uniqid('CTX-', true); // CTX-6650fa1edb1cc.15258107
    }

    $version = beyondseo_rclv();
    $data['plugin_version'] = $version;
    $data['site_url'] = function_exists('get_site_url') ? get_site_url() : (WordpressHelpers::sanitize_input('SERVER', 'HTTP_HOST') ?: 'unknown');

    // Get log file path using helper function
    $log_file = beyondseo_rclp($type);
    if ($log_file === false) {
        return; // Logging disabled or directory creation failed
    }

    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

    if ($json !== false) {
        if (@file_put_contents($log_file, $json . "\n", FILE_APPEND) === false) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[BeyondSEO] DEBUG: ' . $json);
            }
        }
    }
}

/**
 * Helper function to get the version used in log writing
 * Naming: rclv - rankingCoach log version
 *
 * @return string
 */
function beyondseo_rclv(): string { //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
    $version = defined('RANKINGCOACH_VERSION') ? str_replace('.', '-', RANKINGCOACH_VERSION) : 'unknown';
    
    if (function_exists('did_action') && did_action('plugins_loaded')) {
        $pluginData = beyondseo_rcpd();
        if ($pluginData && !empty($pluginData['Version'])) {
            $version = str_replace('.', '-', $pluginData['Version']);
        }
    }
    
    return $version;
}

/**
 * Helper function to create log file path and ensure directory exists
 * Naming: rclp - rankingCoach log path
 *
 * @param string $type - The type of log file ('standard' for .log, or custom type for .jsonl)
 * @param string|null $date - Date string (Y-m-d format), defaults to current date
 * @return string|false - Returns the full file path or false if directory creation fails
 */
function beyondseo_rclp(string $type = 'standard', ?string $date = null): string|false { //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
    // Early return if logging is disabled
    if (!defined('RANKINGCOACH_ENABLE_LOGGING') || !RANKINGCOACH_ENABLE_LOGGING) {
        return false;
    }
    
    $date = $date ?? gmdate('Y-m-d');
    $version = beyondseo_rclv();
    
    // Ensure log directory exists
    if (!is_dir(RANKINGCOACH_LOG_DIR)) {
        if (!@wp_mkdir_p(RANKINGCOACH_LOG_DIR)) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log("[BeyondSEO] DEBUG: Failed to create log directory: " . RANKINGCOACH_LOG_DIR);
            }
            return false;
        }
    }
    
    // Generate filename based on type
    if ($type === 'standard') {
        $filename = "rc_{$version}_{$date}.log";
    } else {
        $filename = "rc_{$version}_{$type}_{$date}.jsonl";
    }
    
    return RANKINGCOACH_LOG_DIR . $filename;
}

/**
 * Unified JSONL log file path - single file per version+date (no type split)
 * This replaces the type-based file splitting with a unified approach
 *
 * @param string|null $date Date string (Y-m-d format), defaults to current date
 * @return string|false Returns the full file path or false if directory creation fails
 */
function beyondseo_rclp_unified(?string $date = null): string|false { //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
    // Early return if logging is disabled
    if (!defined('RANKINGCOACH_ENABLE_LOGGING') || !RANKINGCOACH_ENABLE_LOGGING) {
        return false;
    }
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return false; // Only log in debug mode
    }

    $date = $date ?? gmdate('Y-m-d');
    $version = beyondseo_rclv();

    // Ensure log directory exists
    if (!is_dir(RANKINGCOACH_LOG_DIR)) {
        if (!@wp_mkdir_p(RANKINGCOACH_LOG_DIR)) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[BeyondSEO] DEBUG: Failed to create log directory: ' . RANKINGCOACH_LOG_DIR);
            }
            return false;
        }
    }

    // Unified filename: rc_{version}_{date}.jsonl (no type)
    $filename = "rc_{$version}_{$date}.jsonl";

    return RANKINGCOACH_LOG_DIR . $filename;
}

/**
 * Helper function to delete old log files
 * Naming: rcdlf - delete log files
 *
 * @param int $days - Number of days to keep log files (files older than this will be deleted)
 * @return int - Number of files deleted
 */
function beyondseo_rcdlf(int $days): int { //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
    // Early return if logging is disabled
    if (!defined('RANKINGCOACH_ENABLE_LOGGING') || !RANKINGCOACH_ENABLE_LOGGING) {
        return 0;
    }
    
    if ($days <= 0) {
        beyondseo_rclh('Invalid days parameter for log cleanup: ' . $days, 'WARNING');
        return 0;
    }
    
    $log_dir = RANKINGCOACH_LOG_DIR;
    
    // Check if log directory exists
    if (!is_dir($log_dir)) {
        return 0;
    }
    
    $deleted_count = 0;
    $cutoff_time = time() - ($days * 24 * 60 * 60);
    
    try {
        $files = scandir($log_dir);
        if ($files === false) {
            beyondseo_rclh('Failed to scan log directory: ' . $log_dir, 'ERROR');
            return 0;
        }
        
        foreach ($files as $file) {
            // Skip current and parent directory entries
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $file_path = $log_dir . $file;
            
            // Skip if not a file
            if (!is_file($file_path)) {
                continue;
            }
            
            // Check if it's a log file (ends with .log or .jsonl)
            if (!preg_match('/\.(log|jsonl)$/', $file)) {
                continue;
            }
            
            // Check if file is older than cutoff time
            $file_time = filemtime($file_path);
            if ($file_time !== false && $file_time < $cutoff_time) {
                if (@wp_delete_file($file_path)) {
                    $deleted_count++;
                    beyondseo_rclh("Deleted old log file: $file", 'INFO');
                } else {
                    beyondseo_rclh("Failed to delete log file: $file", 'WARNING');
                }
            }
        }
        
        beyondseo_rclh("Log cleanup completed. Deleted $deleted_count files older than $days days.", 'INFO');
        
    } catch (Exception $e) {
        beyondseo_rclh('Error during log cleanup: ' . $e->getMessage(), 'ERROR');
    }
    
    return $deleted_count;
}

/**
 * Helper function to get the exception handler instance
 * @return ExceptionHandler
 */
function beyondseo_rceh(): ExceptionHandler //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
{
    static $instance = null;
    if ($instance === null) {
        $instance = ExceptionHandler::getInstance(RANKINGCOACH_PLUGIN_BASENAME);
    }
    return $instance;
}

/**
 * Read translated categories from categories_<locale>.json
 * - Verifies the directory and file exist.
 * - Falls back to categories_en.json when the locale has no file of its own.
 * - Returns decoded JSON as array on success; empty array otherwise. Never throws.
 *
 * @param string $locale Locale identifier
 * @param string $assocKey
 * @return array
 */
function beyondseo_get_translated_categories(string $locale = 'en', string $assocKey = 'id'): array //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
{
    try {
        if (!defined('RANKINGCOACH_DEFAULT_CATEGORIES_DIR')) {
            return [];
        }

        if (!is_dir(RANKINGCOACH_DEFAULT_CATEGORIES_DIR)) {
            return [];
        }

        // Sanitize locale
        $safeLocale = sanitize_text_field($locale);
        $filePath = RANKINGCOACH_DEFAULT_CATEGORIES_DIR . 'categories_' . $safeLocale . '.json';

        // Locales without their own category file (e.g. cs, sk) use the English names rather than
        // an empty list, which would leave the onboarding category picker with nothing to show.
        if ($safeLocale !== 'en' && (!file_exists($filePath) || !is_readable($filePath))) {
            $filePath = RANKINGCOACH_DEFAULT_CATEGORIES_DIR . 'categories_en.json';
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $contents = @file_get_contents($filePath);
        if ($contents === false || $contents === '') {
            return [];
        }

        // Decode to associative array safely
        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        $result = [];
        foreach ($data as $element) {
            if (!isset($element[$assocKey])) {
                continue;
            }
            if($assocKey === 'name') {
                $element['name'] = ucfirst(strtolower($element['name']));
                $result[$element['name']] = $element;
            }
            else {
                $result[$element[$assocKey]] = $element;
            }
        }
        return $result;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Loads Composer autoloader with a wrapper to prevent class redeclaration.
 *
 * @param string $autoloadPath Path to vendor/autoload.php
 * @return ClassLoader The wrapped autoloader instance
 * @throws Exception If autoload file not found or wrapping fails
 */
function beyondseo_load_wrapped_autoloader(string $autoloadPath): ClassLoader { //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
    if (!file_exists($autoloadPath)) {
        $message = 'Composer autoload file not found: ' . $autoloadPath;
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[BeyondSEO] DEBUG: ' . $message); // Log for debugging, as in original
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        throw new Exception($message);
    }

    $loader = require $autoloadPath;

    if (!$loader instanceof ClassLoader) {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        throw new Exception('Invalid autoloader instance from: ' . $autoloadPath);
    }

    // Wrap loadClass to skip existing classes/interfaces/traits
    $ref = new ReflectionClass($loader);
    $method = $ref->getMethod('loadClass');
    $method->setAccessible(true);
    $originalLoadClass = $method->getClosure($loader);

    spl_autoload_unregister([$loader, 'loadClass']);

    spl_autoload_register(function ($class) use ($originalLoadClass) {
        if (
            class_exists($class, false) ||
            interface_exists($class, false) ||
            trait_exists($class, false)
        ) {
            return true; // Skip if already declared
        }

        return $originalLoadClass($class);
    }, true, true);

    return $loader;
}
