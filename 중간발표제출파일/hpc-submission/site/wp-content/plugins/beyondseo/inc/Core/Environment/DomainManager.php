<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Environment;

use RankingCoach\Inc\Core\Base\BaseConstants;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DomainManager
 * 
 * Manages the environment domain whitelist for the EnvironmentDetector system.
 * Provides CRUD operations for configuring which domains map to which environments
 * (local, staging, or production).
 * 
 * This class handles all interactions with the WordPress database option that stores
 * the domain whitelist configuration. It ensures data integrity through validation,
 * sanitization, and proper error handling.
 * 
 * Database Structure:
 * The database option 'rankingcoach_environment_domains' contains a JSON-encoded array:
 * {
 *   "version": 1,
 *   "domains": [
 *     {
 *       "domain": "example.com",
 *       "environment": "staging",
 *       "enabled": true,
 *       "added_at": "2025-01-15T10:30:00Z",
 *       "added_by": 1,
 *       "notes": "Optional notes"
 *     }
 *   ]
 * }
 * 
 * @package RankingCoach\Inc\Core\Environment
 */
class DomainManager
{
    /**
     * Database option name for environment domain whitelist
     */
    private const DB_OPTION_KEY = BaseConstants::OPTION_ENVIRONMENT_DOMAINS;
    
    /**
     * Current schema version for the database structure
     */
    private const SCHEMA_VERSION = 1;
    
    /**
     * Valid environment values
     */
    private const VALID_ENVIRONMENTS = [ 'local', 'staging' ];
    
    /**
     * Maximum domain length
     */
    private const MAX_DOMAIN_LENGTH = 255;
    
    /**
     * Retrieves all domains from the database whitelist.
     * 
     * Returns an array of all configured domain entries, both enabled and disabled.
     * If no domains exist, returns an empty array.
     * 
     * @return array Array of domain configurations, empty array if none exist
     */
    public function getDomains(): array
    {
        $data = $this->getDatabaseData();
        return $data['domains'] ?? [];
    }
    
    /**
     * Adds a new domain to the whitelist.
     * 
     * Validates the domain data, checks for duplicates, sanitizes input,
     * and adds timestamp and user information before saving to the database.
     * After successful addition, clears the EnvironmentDetector cache.
     * 
     * Required data keys:
     * - domain: The domain pattern (e.g., '.dev.', 'localhost', 'staging.example.com')
     * - environment: Must be 'local' or 'staging'
     * 
     * Optional data keys:
     * - notes: Additional notes about the domain configuration
     * 
     * The method automatically adds:
     * - enabled: true (by default)
     * - added_at: Current timestamp in ISO 8601 format
     * - added_by: Current user ID
     * 
     * @param array $data Domain configuration data
     * @return bool True on success, false on failure
     */
    public function addDomain( array $data ): bool
    {
        // Validate the domain data
        $validation = $this->validateDomain( $data );
        if ( ! $validation['valid'] ) {
            return false;
        }
        
        // Sanitize domain and environment
        $domain = sanitize_text_field( $data['domain'] );
        $environment = strtolower( sanitize_text_field( $data['environment'] ) );
        
        // Load current data
        $db_data = $this->getDatabaseData();
        
        // Check for duplicate domains
        foreach ( $db_data['domains'] as $existing_domain ) {
            if ( $existing_domain['domain'] === $domain ) {
                // Domain already exists
                return false;
            }
        }
        
        // Prepare new domain entry
        $new_domain = [
            'domain'      => $domain,
            'environment' => $environment,
            'enabled'     => true,
            'added_at'    => current_time( 'c' ),
            'added_by'    => get_current_user_id(),
            'notes'       => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
        ];
        
        // Add to domains array
        $db_data['domains'][] = $new_domain;
        
        // Save to database
        $saved = $this->saveDatabaseData( $db_data );
        
        // Clear cache on success
        if ( $saved ) {
            EnvironmentDetector::clearCache();
        }
        
        return $saved;
    }
    
    /**
     * Updates an existing domain in the whitelist.
     * 
     * Finds the domain by its domain string and updates the specified fields.
     * Only provided fields are updated; others remain unchanged.
     * After successful update, clears the EnvironmentDetector cache.
     * 
     * Updatable fields:
     * - environment: Must be 'local' or 'staging'
     * - enabled: Boolean value
     * - notes: Optional notes
     * 
     * Note: The domain string itself cannot be changed. To rename a domain,
     * delete the old one and add a new one.
     * 
     * @param string $domain The domain string to update
     * @param array $data New data to apply (only provided keys are updated)
     * @return bool True on success, false if domain not found or validation fails
     */
    public function updateDomain( string $domain, array $data ): bool
    {
        // Sanitize domain identifier
        $domain = sanitize_text_field( $domain );
        
        // Load current data
        $db_data = $this->getDatabaseData();
        
        // Find the domain
        $domain_found = false;
        foreach ( $db_data['domains'] as $index => $existing_domain ) {
            if ( $existing_domain['domain'] === $domain ) {
                $domain_found = true;
                
                // Validate if environment is being updated
                if ( isset( $data['environment'] ) ) {
                    $validation_data = [ 'domain' => $domain, 'environment' => $data['environment'] ];
                    $validation = $this->validateDomain( $validation_data );
                    if ( ! $validation['valid'] ) {
                        return false;
                    }
                    $db_data['domains'][ $index ]['environment'] = strtolower( sanitize_text_field( $data['environment'] ) );
                }
                
                // Update enabled status if provided
                if ( isset( $data['enabled'] ) ) {
                    $db_data['domains'][ $index ]['enabled'] = (bool) $data['enabled'];
                }
                
                // Update notes if provided
                if ( isset( $data['notes'] ) ) {
                    $db_data['domains'][ $index ]['notes'] = sanitize_textarea_field( $data['notes'] );
                }
                
                break;
            }
        }
        
        // Return false if domain not found
        if ( ! $domain_found ) {
            return false;
        }
        
        // Save to database
        $saved = $this->saveDatabaseData( $db_data );
        
        // Clear cache on success
        if ( $saved ) {
            EnvironmentDetector::clearCache();
        }
        
        return $saved;
    }
    
    /**
     * Deletes a domain from the whitelist.
     * 
     * Permanently removes the specified domain configuration from the database.
     * After successful deletion, clears the EnvironmentDetector cache.
     * 
     * @param string $domain The domain string to delete
     * @return bool True on success, false if domain not found
     */
    public function deleteDomain( string $domain ): bool
    {
        // Sanitize domain identifier
        $domain = sanitize_text_field( $domain );
        
        // Load current data
        $db_data = $this->getDatabaseData();
        
        // Find and remove the domain
        $initial_count = count( $db_data['domains'] );
        $db_data['domains'] = array_values( array_filter(
            $db_data['domains'],
            function( $existing_domain ) use ( $domain ) {
                return $existing_domain['domain'] !== $domain;
            }
        ) );
        
        // Check if domain was found and removed
        if ( count( $db_data['domains'] ) === $initial_count ) {
            // Domain not found
            return false;
        }
        
        // Save to database
        $saved = $this->saveDatabaseData( $db_data );
        
        // Clear cache on success
        if ( $saved ) {
            EnvironmentDetector::clearCache();
        }
        
        return $saved;
    }
    
    /**
     * Toggles the enabled status of a domain.
     * 
     * Switches the domain's enabled status between true and false.
     * If the domain is currently enabled, it becomes disabled, and vice versa.
     * After successful toggle, clears the EnvironmentDetector cache.
     * 
     * @param string $domain The domain string to toggle
     * @return bool True on success, false if domain not found
     */
    public function toggleDomain( string $domain ): bool
    {
        // Sanitize domain identifier
        $domain = sanitize_text_field( $domain );
        
        // Load current data
        $db_data = $this->getDatabaseData();
        
        // Find and toggle the domain
        $domain_found = false;
        foreach ( $db_data['domains'] as $index => $existing_domain ) {
            if ( $existing_domain['domain'] === $domain ) {
                $domain_found = true;
                $db_data['domains'][ $index ]['enabled'] = ! $existing_domain['enabled'];
                break;
            }
        }
        
        // Return false if domain not found
        if ( ! $domain_found ) {
            return false;
        }
        
        // Save to database
        $saved = $this->saveDatabaseData( $db_data );
        
        // Clear cache on success
        if ( $saved ) {
            EnvironmentDetector::clearCache();
        }
        
        return $saved;
    }
    
    /**
     * Validates domain configuration data.
     * 
     * Performs comprehensive validation of domain data before it's added or updated.
     * Checks for required fields, valid values, proper formats, and data types.
     * 
     * Validation Rules:
     * - domain: Required, non-empty, no whitespace, valid characters (alphanumeric, dots, hyphens), 1-255 chars
     * - environment: Required, must be 'local' or 'staging' (case-insensitive)
     * 
     * @param array $data Domain configuration data to validate
     * @return array Array with 'valid' boolean and 'errors' array of error messages
     */
    public function validateDomain( array $data ): array
    {
        $errors = [];
        
        // Validate domain field
        if ( ! isset( $data['domain'] ) || empty( trim( $data['domain'] ) ) ) {
            $errors[] = 'Domain is required and cannot be empty.';
        } else {
            $domain = trim( $data['domain'] );
            
            // Check for whitespace
            if ( preg_match( '/\s/', $domain ) ) {
                $errors[] = 'Domain cannot contain whitespace.';
            }
            
            // Check length
            if ( strlen( $domain ) > self::MAX_DOMAIN_LENGTH ) {
                $errors[] = sprintf( 'Domain cannot exceed %d characters.', self::MAX_DOMAIN_LENGTH );
            }
            
            // Check for valid characters (alphanumeric, dots, hyphens)
            if ( ! preg_match( '/^[a-zA-Z0-9.\-]+$/', $domain ) ) {
                $errors[] = 'Domain can only contain letters, numbers, dots, and hyphens.';
            }
        }
        
        // Validate environment field
        if ( ! isset( $data['environment'] ) || empty( trim( $data['environment'] ) ) ) {
            $errors[] = 'Environment is required and cannot be empty.';
        } else {
            $environment = strtolower( trim( $data['environment'] ) );
            if ( ! in_array( $environment, self::VALID_ENVIRONMENTS, true ) ) {
                $errors[] = sprintf(
                    'Environment must be one of: %s.',
                    implode( ', ', self::VALID_ENVIRONMENTS )
                );
            }
        }
        
        return [
            'valid'  => empty( $errors ),
            'errors' => $errors,
        ];
    }
    
    /**
     * Retrieves the complete database data structure.
     * 
     * Loads the domain whitelist from WordPress options and returns the full
     * data structure including version and domains array. If the option doesn't
     * exist or JSON decoding fails, returns a default empty structure.
     * 
     * Error Handling:
     * - JSON decode errors are logged if WP_DEBUG is enabled
     * - Invalid data structures are replaced with default structure
     * - Missing version field is added automatically
     * 
     * @return array Complete database structure with 'version' and 'domains' keys
     */
    private function getDatabaseData(): array
    {
        // Default structure
        $default_data = [
            'version' => self::SCHEMA_VERSION,
            'domains' => [],
        ];
        
        // Load from database
        $json = get_option( self::DB_OPTION_KEY, '' );
        
        if ( empty( $json ) ) {
            return $default_data;
        }
        
        // Decode JSON with error handling
        $data = json_decode( $json, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            // Log JSON decode error if WP_DEBUG is enabled
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    '[BeyondSEO] DEBUG: Failed to decode environment domains JSON. Error: %s',
                    json_last_error_msg()
                ));
            }
            return $default_data;
        }
        
        // Validate structure
        if ( ! is_array( $data ) ) {
            return $default_data;
        }
        
        // Ensure version exists
        if ( ! isset( $data['version'] ) ) {
            $data['version'] = self::SCHEMA_VERSION;
        }
        
        // Ensure domains array exists
        if ( ! isset( $data['domains'] ) || ! is_array( $data['domains'] ) ) {
            $data['domains'] = [];
        }
        
        return $data;
    }
    
    /**
     * Saves the database data structure to WordPress options.
     * 
     * Encodes the data as JSON and stores it in the WordPress options table.
     * Handles JSON encoding errors gracefully and logs errors when WP_DEBUG is enabled.
     * 
     * @param array $data Complete database structure to save
     * @return bool True on success, false on failure
     */
    private function saveDatabaseData( array $data ): bool
    {
        // Ensure version is set
        if ( ! isset( $data['version'] ) ) {
            $data['version'] = self::SCHEMA_VERSION;
        }
        
        // Encode to JSON
        $json = wp_json_encode( $data );
        
        if ( false === $json ) {
            // Log JSON encode error if WP_DEBUG is enabled
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( '[BeyondSEO] DEBUG: Failed to encode environment domains to JSON.' );
            }
            return false;
        }
        
        // Save to database
        return update_option( self::DB_OPTION_KEY, $json );
    }
}