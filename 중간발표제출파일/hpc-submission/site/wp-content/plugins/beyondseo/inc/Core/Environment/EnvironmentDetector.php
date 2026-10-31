<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Environment;

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname(__DIR__) . '/Base/BaseConstants.php';
require_once dirname(__DIR__) . '/Helpers/WordpressHelpers.php';

/**
 * Class EnvironmentDetector
 * 
 * Detects the current runtime environment (local, staging, or production) using a multi-layered
 * detection algorithm with caching for optimal performance.
 * 
 * Detection Priority Order:
 * 1. wp-config.php override (RANKINGCOACH_ENVIRONMENT constant if already defined)
 * 2. Environment variables (WP_ENVIRONMENT_TYPE, RANKINGCOACH_ENV)
 * 3. Hostname localhost check
 * 4. Database whitelist lookup (option: 'rankingcoach_environment_domains')
 * 5. Default to production (fallback)
 * 
 * Caching Strategy:
 * - Results are cached using WordPress transients with 1-hour TTL
 * - Cache key is based on HTTP_HOST to support multi-site environments
 * - Cache can be manually cleared using clearCache() method
 * 
 * Database Structure:
 * The database option 'rankingcoach_environment_domains' should contain a JSON-encoded array:
 * {
 *   "version": 1,
 *   "domains": [
 *     {
 *       "domain": "localhost",
 *       "environment": "local",
 *       "enabled": true,
 *       "added_at": "2025-01-15T10:30:00Z",
 *       "added_by": 1,
 *       "notes": "Local development"
 *     }
 *   ]
 * }
 * 
 * @package RankingCoach\Inc\Core\Environment
 */
class EnvironmentDetector
{
    /**
     * Cache key prefix for WordPress transients
     */
    private const CACHE_KEY_PREFIX = 'rankingcoach_env_detect_';
    
    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;
    
    /**
     * Database option name for environment domain whitelist
     */
    private const DB_OPTION_KEY = BaseConstants::OPTION_ENVIRONMENT_DOMAINS;
    
    /**
     * Detects the current runtime environment using a multi-layered detection algorithm.
     * 
     * This method implements a priority-based detection flow:
     * 1. First checks if RANKINGCOACH_ENVIRONMENT constant is already defined (wp-config.php override)
     * 2. Then checks environment variables (WP_ENVIRONMENT_TYPE, RANKINGCOACH_ENV)
     * 3. Checks if current hostname contains 'localhost'
     * 4. Queries database whitelist for domain-based environment mapping
     * 5. Falls back to production as the safe default
     * 
     * Results are cached for 1 hour using WordPress transients to avoid repeated detection overhead.
     * Cache key includes HTTP_HOST to support multi-site configurations.
     * 
     * @return string One of: 'local', 'staging', or 'production'
     */
    public static function detect(): string
    {
        // Step 1: Check for wp-config.php override
        if ( defined( 'RANKINGCOACH_ENVIRONMENT' ) ) {
            return RANKINGCOACH_ENVIRONMENT;
        }
        
        // Get sanitized hostname for detection and caching
        $host = WordpressHelpers::sanitize_input( 'SERVER', 'HTTP_HOST' );
        if ( empty( $host ) ) {
            $host = 'unknown';
        }
        
        // Check cache first
        $cache_key = self::CACHE_KEY_PREFIX . md5( $host );
        $cached_env = get_transient( $cache_key );

        if ( false !== $cached_env && is_string( $cached_env ) ) {
            return $cached_env;
        }
        
        // Step 2: Check environment variables
        $env_from_var = self::detectFromEnvironmentVariables();
        if ( null !== $env_from_var ) {
            set_transient( $cache_key, $env_from_var, self::CACHE_TTL );
            return $env_from_var;
        }
        
        // Step 3: Check for localhost in hostname
        if ( self::isLocalhost( $host ) ) {
            $env = RANKINGCOACH_LOCAL_ENVIRONMENT;
            set_transient( $cache_key, $env, self::CACHE_TTL );
            return $env;
        }
        
        // Step 4: Check database whitelist
        $env_from_db = self::getFromDatabase();
        if ( null !== $env_from_db ) {
            set_transient( $cache_key, $env_from_db, self::CACHE_TTL );
            return $env_from_db;
        }
        
        // Step 5: Default to production (safe fallback)
        $env = RANKINGCOACH_PRODUCTION_ENVIRONMENT;
        set_transient( $cache_key, $env, self::CACHE_TTL );
        return $env;
    }
    
    /**
     * Retrieves environment setting from database whitelist based on current hostname.
     * 
     * This method loads the domain whitelist from WordPress options and matches the current
     * hostname against configured domain patterns. Only enabled domains are considered.
     * 
     * Pattern Matching Rules:
     * - Supports wildcard patterns (e.g., '.dev.' matches 'mysite.dev.example.com')
     * - Case-insensitive matching
     * - Returns first matching enabled domain
     * 
     * Error Handling:
     * - JSON decode errors are caught and logged (returns null)
     * - Invalid data structures are handled gracefully
     * - Missing or disabled domains return null
     * 
     * @return string|null Environment string ('local', 'staging', 'production') or null if no match
     */
    public static function getFromDatabase(): ?string
    {
        // Get current hostname
        $host = WordpressHelpers::sanitize_input( 'SERVER', 'HTTP_HOST' );
        if ( empty( $host ) ) {
            return null;
        }

        // Load database whitelist
        $whitelist_json = get_option( self::DB_OPTION_KEY, '' );
        
        // Decode JSON with error handling
        $whitelist_data = json_decode( $whitelist_json, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            // Log JSON decode error if WP_DEBUG is enabled
            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    '[BeyondSEO] DEBUG: Failed to decode environment domains JSON. Error: %s',
                    json_last_error_msg()
                ));
            }
            return null;
        }
        
        // Validate data structure
        if ( ! is_array( $whitelist_data ) || ! isset( $whitelist_data['domains'] ) || ! is_array( $whitelist_data['domains'] ) ) {
            return null;
        }
        
        // Match current hostname against domain patterns
        foreach ( $whitelist_data['domains'] as $domain_config ) {
            // Skip if not properly configured or disabled
            if ( ! is_array( $domain_config ) ||
                 ! isset( $domain_config['domain'], $domain_config['environment'], $domain_config['enabled'] ) ||
                 ! $domain_config['enabled'] ) {
                continue;
            }
            
            // Check if current host matches the domain pattern
            if ( self::matchesPattern( $host, $domain_config['domain'] ) ) {
                return $domain_config['environment'];
            }
        }
        
        return null;
    }
    
    /**
     * Clears the cached environment detection result.
     * 
     * This method removes the transient cache for the current hostname, forcing the next
     * detect() call to perform a fresh detection. Useful after updating environment settings
     * or domain whitelists.
     * 
     * @return void
     */
    public static function clearCache(): void
    {
        $host = WordpressHelpers::sanitize_input( 'SERVER', 'HTTP_HOST' );
        if ( empty( $host ) ) {
            $host = 'unknown';
        }
        
        $cache_key = self::CACHE_KEY_PREFIX . md5( $host );
        delete_transient( $cache_key );
    }
    
    /**
     * Checks if a hostname matches a domain pattern.
     * 
     * This method performs case-insensitive substring matching to determine if a hostname
     * matches a given pattern. Patterns can be exact matches or contain wildcards.
     * 
     * Pattern Examples:
     * - 'localhost' matches 'localhost', 'localhost:8080'
     * - '.dev.' matches 'mysite.dev.example.com', 'app.dev.company.net'
     * - 'staging' matches 'staging.example.com', 'mystaging.net'
     * 
     * @param string $host The hostname to check (e.g., 'mysite.dev.example.com')
     * @param string $pattern The pattern to match against (e.g., '.dev.')
     * @return bool True if hostname matches pattern, false otherwise
     */
    private static function matchesPattern( string $host, string $pattern ): bool
    {
        // Convert both to lowercase for case-insensitive comparison
        $host_lower = strtolower( $host );
        $pattern_lower = strtolower( $pattern );
        
        // Check if pattern exists anywhere in the hostname
        return str_contains( $host_lower, $pattern_lower );
    }
    
    /**
     * Detects environment from environment variables.
     * 
     * Checks the following environment variables in priority order:
     * 1. RANKINGCOACH_ENV - Plugin-specific environment variable
     * 2. WP_ENVIRONMENT_TYPE - WordPress core environment variable (WP 5.5+)
     * 
     * @return string|null Environment string or null if not set
     */
    private static function detectFromEnvironmentVariables(): ?string
    {
        // Check plugin-specific environment variable
        $rankingcoach_env = getenv( 'RANKINGCOACH_ENV' );
        if ( false !== $rankingcoach_env && ! empty( $rankingcoach_env ) ) {
            return self::normalizeEnvironmentValue( $rankingcoach_env );
        }
        
        // Check WordPress core environment variable (available since WP 5.5)
        if ( function_exists( 'wp_get_environment_type' ) ) {
            $wp_env = wp_get_environment_type();
            if ( ! empty( $wp_env ) ) {
                return self::normalizeEnvironmentValue( $wp_env );
            }
        }
        
        // Fallback to WP_ENVIRONMENT_TYPE constant
        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && ! empty( WP_ENVIRONMENT_TYPE ) ) {
            return self::normalizeEnvironmentValue( WP_ENVIRONMENT_TYPE );
        }
        
        return null;
    }
    
    /**
     * Checks if the hostname indicates a localhost environment.
     * 
     * @param string $host The hostname to check
     * @return bool True if localhost, false otherwise
     */
    private static function isLocalhost( string $host ): bool
    {
        $host_lower = strtolower( $host );
        return str_contains( $host_lower, 'localhost' );
    }
    
    /**
     * Normalizes environment value from various sources to plugin constants.
     * 
     * Maps common environment type values to plugin-specific constants:
     * - 'local', 'development', 'dev' → 'local'
     * - 'staging', 'stage' → 'staging'  
     * - 'production', 'prod', 'live' → 'production'
     * 
     * @param string $value The environment value to normalize
     * @return string Normalized environment value
     */
    private static function normalizeEnvironmentValue( string $value ): string
    {
        $value_lower = strtolower( trim( $value ) );
        
        // Map common environment names
        $environment_map = [
            'local'       => RANKINGCOACH_LOCAL_ENVIRONMENT,
            'development' => RANKINGCOACH_LOCAL_ENVIRONMENT,
            'dev'         => RANKINGCOACH_LOCAL_ENVIRONMENT,
            'staging'     => RANKINGCOACH_STAGING_ENVIRONMENT,
            'stage'       => RANKINGCOACH_STAGING_ENVIRONMENT,
            'production'  => RANKINGCOACH_PRODUCTION_ENVIRONMENT,
            'prod'        => RANKINGCOACH_PRODUCTION_ENVIRONMENT,
            'live'        => RANKINGCOACH_PRODUCTION_ENVIRONMENT,
        ];
        
        return $environment_map[ $value_lower ] ?? RANKINGCOACH_PRODUCTION_ENVIRONMENT;
    }
}