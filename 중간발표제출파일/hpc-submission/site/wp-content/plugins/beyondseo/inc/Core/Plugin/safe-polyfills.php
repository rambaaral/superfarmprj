<?php
declare(strict_types=1);

/**
 * Safe Polyfills for Restricted Hosting Environments
 *
 * This file defines fallback implementations for commonly disabled PHP functions,
 * ensuring that the plugin won't crash even when running in restricted environments.
 *
 * Include it early in plugin bootstrap (before autoload).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('putenv')) {
    function putenv($setting)
    {
        if (str_contains($setting, '=')) {
            [$key, $value] = explode('=', $setting, 2);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            return true;
        }
        return false;
    }
}

if (!function_exists('getenv')) {
    function getenv($key, $local_only = false)
    {
        return isset($_ENV[$key]) ? sanitize_text_field(wp_unslash($_ENV[$key])) : (isset($_SERVER[$key]) ? sanitize_text_field(wp_unslash($_SERVER[$key])) : null);
    }
}

if (!function_exists('ini_set')) {
    function ini_set($option, $value)
    {
        // Silently ignore; log warning in debug mode
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log("[BeyondSEO] DEBUG: ini_set disabled {$option}={$value}");
        }
        return false;
    }
}

if (!function_exists('ini_get')) {
    function ini_get($option) {
        return null;
    }
}

if (!function_exists('set_time_limit')) {
    function set_time_limit($seconds)
    {
        return false;
    }
}

if (!function_exists('ignore_user_abort')) {
    function ignore_user_abort($value)
    {
        return false;
    }
}

if (!function_exists('symlink')) {
    function symlink($target, $link)
    {
        return copy($target, $link);
    }
}

if (!function_exists('link')) {
    function link($target, $link) {
        return copy($target, $link);
    }
}

if (!function_exists('readlink')) {
    function readlink($path) {
        return realpath($path);
    }
}

if (!function_exists('tmpfile')) {
    function tmpfile() {
        $upload = wp_upload_dir();
        $tmpDir = $upload['basedir'] ?? sys_get_temp_dir();

        // tempnam() already creates the file on disk, no need for WP_Filesystem
        $file = tempnam($tmpDir, 'tmp_');
        if ( ! $file ) {
            return false;
        }

        return $file;
    }
}

if (!function_exists('mkdir')) {
    function mkdir($path, $mode = 0777, $recursive = false, $context = null) {
        if (function_exists('wp_mkdir_p')) {
            return wp_mkdir_p($path);
        }
        return false;
    }
}

/**
 * Network and cURL replacements
 */
if (!function_exists('curl_init')) {
    function curl_init($url = null) {
        // Return a dummy resource-like object
        return (object)[
            'url' => $url,
            'options' => []
        ];
    }
}

if (!function_exists('curl_setopt')) {
    function curl_setopt(&$ch, $option, $value) {
        if (is_object($ch)) {
            $ch->options[$option] = $value;
        }
        return true;
    }
}

if (!function_exists('curl_exec')) {
    function curl_exec($ch) {
        // Fallback: try WordPress HTTP API
        if (isset($ch->url)) {
            $response = wp_remote_get($ch->url);
            return is_wp_error($response) ? false : wp_remote_retrieve_body($response);
        }
        return false;
    }
}

if (!function_exists('curl_close')) {
    function curl_close(&$ch) {
        $ch = null;
    }
}

if (!function_exists('fsockopen')) {
    function fsockopen($hostname, $port, &$errno = null, &$errstr = null, $timeout = 30) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[BeyondSEO] DEBUG: fsockopen disabled; using wp_remote_get fallback.');
        }
        return false;
    }
}

/**
 * Cryptography fallbacks
 */
if (!function_exists('openssl_encrypt')) {
    function openssl_encrypt($data, $cipher, $key, $options = 0, $iv = '') {
        return base64_encode($data); // NOT secure, only for graceful degradation
    }
}

if (!function_exists('openssl_decrypt')) {
    function openssl_decrypt($data, $cipher, $key, $options = 0, $iv = '') {
        return base64_decode($data);
    }
}

if (!function_exists('random_bytes')) {
    function random_bytes($length) {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= chr(wp_rand(0, 255));
        }
        return $result;
    }
}

/**
 * Miscellaneous / Debug
 */
if (!function_exists('getmypid')) {
    function getmypid() {
        static $pid = null;
        if ($pid !== null) {
            return $pid;
        }
        if (function_exists('posix_getpid')) {
            $pid = posix_getpid();
            return $pid;
        }
        $pid = wp_rand(10000, 99999);
        return $pid;
    }
}

if (!function_exists('memory_get_usage')) {
    function memory_get_usage($real_usage = false) {
        return 0;
    }
}

if (!function_exists('ob_get_clean')) {
    function ob_get_clean() {
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
}

if (!function_exists('flush')) {
    function flush() {
        // Silently ignore
        return;
    }
}

if (!function_exists('error_log')) {
    function error_log($message, $message_type = 0, $destination = null, $extra_headers = null) {
        // As last resort, echo to screen if debug
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[BeyondSEO] DEBUG: ' . esc_html( $message ));
        }
        return false;
    }
}

/**
 * General notice for restricted hosts
 */
if (!function_exists('beyondseo_safe_env_notice')) {
    function beyondseo_safe_env_notice() { //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $disabled = ini_get('disable_functions');
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[BeyondSEO] DEBUG: RankingCoach Safe Polyfills active. Disabled functions: ' . $disabled);
        }
    }
}
