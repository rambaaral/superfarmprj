<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Base\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use function beyondseo_rclh;
use function beyondseo_rcdlf;
use function beyondseo_rclh_json;

/**
 * Trait RcLoggerTrait
 */
trait RcLoggerTrait {

    /**
     * Available context keys for logging
     */
    private array $availableContextKeys = [
        // Core functionality
        'core', 'api', 'error', 'debug', 'activation', 'onboarding', 'save_onboarding', 'save_onboarding_step',
        'feedback', 'i18n', 'categories', 'security', 'performance', 'http', 'db',
        'cache', 'cron', 'scheduler', 'admin', 'rest', 'cli', 'extract_auto_onboarding',

        // Business logic
        'compatibility', 'self_check', 'token', 'registration', 'finalizeRegistration', 'registrationPoll',
        'safe_mode', 'extension', 'keywords', 'sitemap', 'breadcrumbs',
        'translations', 'analytics', 'billing', 'subscription', 'support',

        // System operations
        'database', 'auth', 'module', 'integration', 'webhook',
        'file', 'network', 'validation', 'migration', 'backup',
        'cleanup', 'user', 'settings', 'exception',

        // Custom
        'wp_cron_enabler', 'account_sync', 'action_scheduler_job', 'broken_link_checker_job', 'log_cleanup', 'keywords_sync',
        'description_autosuggest', 'title_autosuggest', 'get_location_suggestions', 'categories_translated', 'upsell_check',
    ];

	/**
	 * Sensitive keys that should be obfuscated in logs
	 */
	private array $sensitiveKeys = [
		'email', 'phone', 'mobile', 'name', 'username', 'pass', 'password',
		'address', 'street', 'city', 'location', 'api_key', 'token', 'session',
		'user', 'secret', 'key', 'auth', 'credential', 'login', 'pwd', 'ssn',
		'social_security', 'credit_card', 'card_number', 'cvv', 'cvc', 'pin'
	];

	/**
	 * Regex patterns to identify sensitive data in strings
	 */
	private array $sensitivePatterns = [
		// 📧 Email Address
		'/([a-zA-Z0-9._%+-])[^@]*(@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/',

		// 📱 Phone Numbers (supports +country codes, spaces, dashes, parentheses)
		'/\+?\d[\d\s\-\(\)]{7,}\d/',

		// 💳 Credit Card Numbers (Visa, MasterCard, etc.)
		'/\b(?:\d[ -]*?){13,16}\b/',

		// 🔐 JWT Tokens
		'/eyJ[a-zA-Z0-9_-]+?\.[a-zA-Z0-9._-]+?\.[a-zA-Z0-9._-]+/',

		'/\b\d{3}-\d{2}-\d{4}\b/',

		// 🔑 Bearer/API Tokens (generic token pattern with 32+ chars)
		'/\b[a-f0-9]{32,}\b/i',
	];

	/**
	 * Sanitizes a string by obfuscating sensitive data patterns
	 *
	 * @param string $text The text to sanitize
	 * @return string The sanitized text
	 */
	private function sanitizeString(string $text): string {
		foreach ($this->getSensitivePatterns() as $pattern) {
			$text = preg_replace_callback($pattern, function($matches) use ($pattern) {
				return $this->obfuscateMatch($matches[0], $pattern);
			}, $text);
		}
		return $text;
	}

	/**
	 * Obfuscates a matched sensitive data pattern
	 *
	 * @param string $match The matched string
	 * @param string $pattern The regex pattern that matched
	 * @return string The obfuscated string
	 */
	private function obfuscateMatch(string $match, string $pattern): string {
		// Email pattern - show first char and domain
		if (str_contains($pattern, '@') && preg_match('/([a-zA-Z0-9._%+-])[^@]*(@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $match, $emailMatches)) {
            $domain = $emailMatches[2];
            $domainParts = explode('.', ltrim($domain, '@'));
            $obfuscatedDomain = str_repeat('*', strlen($domainParts[0])) . '.' . end($domainParts);
            return $emailMatches[1] . '***@' . $obfuscatedDomain;
        }
		
		// Phone number pattern
		if (preg_match('/\+?\d[\d\s\-\(\)]{7,}\d/', $match)) {
			$cleaned = preg_replace('/[^\d+]/', '', $match);
			if (strlen($cleaned) > 4) {
				return substr($cleaned, 0, 2) . str_repeat('*', strlen($cleaned) - 4) . substr($cleaned, -2);
			}
		}
		
		// Credit card pattern
		if (preg_match('/\b(?:\d[ -]*?){13,16}\b/', $match)) {
			$cleaned = preg_replace('/[^\d]/', '', $match);
			if (strlen($cleaned) >= 13) {
				return substr($cleaned, 0, 4) . str_repeat('*', strlen($cleaned) - 8) . substr($cleaned, -4);
			}
		}
		
		// SSN pattern
		if (preg_match('/\b\d{3}-\d{2}-\d{4}\b/', $match)) {
			return '***-**-' . substr($match, -4);
		}
		
		// Generic token/key pattern - show first and last few characters
		if (strlen($match) > 8) {
			return substr($match, 0, 4) . str_repeat('*', strlen($match) - 8) . substr($match, -4);
		}
		
		// Fallback - replace most characters with asterisks
		return str_repeat('*', max(1, strlen($match) - 2)) . substr($match, -2);
	}

	/**
	 * Sanitizes an array by obfuscating sensitive data based on keys and patterns
	 *
	 * @param array $data The array to sanitize
	 * @return array The sanitized array
	 */
	private function sanitizeArray(array $data): array {
		$sanitized = [];
		
		foreach ($data as $key => $value) {
			$keyLower = strtolower((string)$key);
			$isSensitiveKey = false;
			
			// Check if key contains any sensitive keywords
			foreach ($this->getSensitiveKeys() as $sensitiveKey) {
				if (str_contains($keyLower, $sensitiveKey)) {
					$isSensitiveKey = true;
					break;
				}
			}
			
			if ($isSensitiveKey) {
				// Obfuscate sensitive key values
				if (is_string($value)) {
					$sanitized[$key] = $this->obfuscateValue($value);
				} elseif (is_array($value)) {
					$sanitized[$key] = $this->sanitizeArray($value);
				} else {
					$sanitized[$key] = '[REDACTED]';
				}
			} else {
				// For non-sensitive keys, still check string values for patterns
				if (is_string($value)) {
					$sanitized[$key] = $this->sanitizeString($value);
				} elseif (is_array($value)) {
					$sanitized[$key] = $this->sanitizeArray($value);
				} else {
					$sanitized[$key] = $value;
				}
			}
		}
		
		return $sanitized;
	}

	/**
	 * Obfuscates a sensitive value
	 *
	 * @param string $value The value to obfuscate
	 * @return string The obfuscated value
	 */
	private function obfuscateValue(string $value): string {
		// First check for specific patterns
		$sanitized = $this->sanitizeString($value);
		
		// If no pattern matched but it's a sensitive key, do generic obfuscation
		if ($sanitized === $value && $value !== '') {
            if (strlen($value) <= 3) {
                return str_repeat('*', strlen($value));
            }

            if (strlen($value) <= 6) {
                return substr($value, 0, 1) . str_repeat('*', strlen($value) - 2) . substr($value, -1);
            }

            return substr($value, 0, 2) . str_repeat('*', strlen($value) - 4) . substr($value, -2);
        }
		
		return $sanitized;
	}

    /**
     * Unified logging method
     *
     * @param string $message The message to log
     * @param string $level The log level (INFO, WARNING, ERROR, DEBUG)
     * @param bool $verbose If true, only logs when RANKINGCOACH_LOG_VERBOSE is enabled
     * @param string $contextKey The context key for categorization
     * @param array $additionalData Additional structured data to include
     * @param array $options Advanced options (context_id, request_id, include_meta, etc.)
     */
	protected function log(
        string $message,
        string $level = 'INFO',
        bool $verbose = false,
        string $contextKey = 'core',
        array $additionalData = [],
        array $options = []
    ): void {
        // Early returns for performance
        if (!defined('RANKINGCOACH_ENABLE_LOGGING') || !RANKINGCOACH_ENABLE_LOGGING) {
            return;
        }

        // Validate log level
        $allowedLevels = ['INFO', 'WARNING', 'ERROR', 'DEBUG'];
        $level = strtoupper($level);
        if (!in_array($level, $allowedLevels, true)) {
            return;
        }

        // Handle verbose logging
        if ($level === 'INFO' && $verbose && !$this->isVerboseLoggingEnabled()) {
            return;
        }

        // Validate and normalize context key
        $contextKey = $this->validateAndNormalizeContextKey($contextKey);

        // Get caller information
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1]['function'] ?? 'UnknownMethod';
        $class = $backtrace[1]['class'] ?? get_class($this);
        $file = isset($backtrace[1]['file']) ? basename($backtrace[1]['file']) : 'UnknownFile';
        $line = $backtrace[1]['line'] ?? 0;

        // Handle sanitization
        $debugPurpose = $options['debug_purpose'] ?? false;
        $shouldSanitize = !($debugPurpose || $level === 'DEBUG');

        $sanitizedMessage = $shouldSanitize ? $this->sanitizeString($message) : $message;
        $sanitizedData = $shouldSanitize ? $this->sanitizeArray($additionalData) : $additionalData;

        // Build log entry
        $logEntry = $this->buildUnifiedLogEntry([
            'level' => $level,
            'context_key' => $contextKey,
            'message' => $sanitizedMessage,
            'class' => $class,
            'method' => $caller,
            'file' => $file,
            'line' => $line,
            'data' => $sanitizedData,
            'options' => $options
        ]);

        // Write to unified log file
        $this->writeUnifiedJsonLog($logEntry);
	}

    /**
     * Build the unified log entry with consistent structure
     */
    private function buildUnifiedLogEntry(array $input): array {
        // Core structure - always present
        $entry = [
            'timestamp' => gmdate('c'),
            'level' => $input['level'],
            'context_key' => $input['context_key'],
            'message' => $input['message'],
            'context_id' => $input['options']['context_id'] ?? uniqid('CTX-', true),
            'plugin_version' => $this->getCachedPluginVersion(),
            'site_url' => $this->getCachedSiteUrl(),
            'caller' => [
                'class' => $input['class'],
                'method' => $input['method'],
                'file' => $input['file'],
                'line' => $input['line']
            ]
        ];

        // Add data if present
        if (!empty($input['data'])) {
            $entry['data'] = $input['data'];
        }

        // Add meta information if requested or in verbose mode
        $includeMeta = $input['options']['include_meta'] ?? $this->isVerboseLoggingEnabled();
        if ($includeMeta) {
            $entry['meta'] = $this->buildMetaInformation($input['options']);
        }

        return $entry;
    }

    /**
     * Build meta information for detailed logging
     */
    private function buildMetaInformation(array $options): array {
        $meta = [];

        // Request ID if available
        $requestId = $options['request_id'] ?? $this->extractRequestId();
        if ($requestId) {
            $meta['request_id'] = $requestId;
        }

        // WordPress context for web requests
        if ($this->isWebRequest()) {
            $meta['wp_context'] = [
                'is_admin' => is_admin(),
                'current_user_id' => get_current_user_id(),
                'is_multisite' => is_multisite()
            ];

            $meta['request_context'] = [
                'method' => WordpressHelpers::sanitize_input('SERVER', 'REQUEST_METHOD') ?: 'CLI',
                'uri' => WordpressHelpers::sanitize_input('SERVER', 'REQUEST_URI') ?: 'N/A',
                'user_agent' => $this->sanitizeString(WordpressHelpers::sanitize_input('SERVER', 'HTTP_USER_AGENT') ?: 'N/A'),
                'ip' => $this->getCachedClientIp()
            ];
        }

        return $meta;
    }

    /**
     * Write JSON log with atomic operations
     */
    private function writeUnifiedJsonLog(array $logEntry): void {
        $json = json_encode(
            $logEntry,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($json === false) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[BeyondSEO] DEBUG: JSON encoding failed');
            }
            return;
        }

        // Use the new unified log file path
        $logFile = beyondseo_rclp_unified();

        if ($logFile === false) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[BeyondSEO] DEBUG: ' . $json);
            }
            return;
        }

        // Atomic write with file locking for concurrency safety
        $result = @file_put_contents(
            $logFile,
            $json . "\n",
            FILE_APPEND | LOCK_EX
        );

        if ($result === false) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[BeyondSEO] DEBUG: File write failed - ' . $json);
            }
        }
    }

    /**
     * Helper methods for the unified logger
     */
    private function validateAndNormalizeContextKey(string $contextKey): string {
        $contextKey = strtolower(trim($contextKey));
        return in_array($contextKey, $this->availableContextKeys, true) ? $contextKey : 'core';
    }

    /**
     * Caches and retrieves the plugin version
     */
    private function getCachedPluginVersion(): string {
        static $version = null;
        if ($version === null) {
            $version = defined('RANKINGCOACH_VERSION')
                ? str_replace('.', '-', RANKINGCOACH_VERSION)
                : 'unknown';
        }
        return $version;
    }

    /**
     * Caches and retrieves the site URL
     */
    private function getCachedSiteUrl(): string {
        static $siteUrl = null;
        if ($siteUrl === null) {
            $siteUrl = function_exists('get_site_url')
                ? get_site_url()
                : (WordpressHelpers::sanitize_input('SERVER', 'HTTP_HOST') ?: 'unknown');
        }
        return $siteUrl;
    }

    /**
     * Extracts a request ID from common HTTP headers
     */
    private function extractRequestId(): ?string {
        $sources = ['HTTP_X_RC_REQUEST_ID', 'HTTP_X_REQUEST_ID', 'HTTP_REQUEST_ID'];
        foreach ($sources as $source) {
            $value = WordpressHelpers::sanitize_input('SERVER', $source);
            if (!empty($value)) {
                return sanitize_text_field($value);
            }
        }
        return null;
    }

    /**
     * Determines if the current execution context is a web request
     */
    private function isWebRequest(): bool {
        return !empty(WordpressHelpers::sanitize_input('SERVER', 'REQUEST_METHOD'));
    }

    /**
     * Caches and retrieves the client's IP address
     */
    private function getCachedClientIp(): string {
        static $ip = null;
        if ($ip === null) {
            $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
            foreach ($ipKeys as $key) {
                $candidateIp = WordpressHelpers::sanitize_input('SERVER', $key);
                if (!empty($candidateIp)) {
                    if (str_contains($candidateIp, ',')) {
                        $candidateIp = trim(explode(',', $candidateIp)[0]);
                    }
                    if (filter_var($candidateIp, FILTER_VALIDATE_IP)) {
                        $ip = $candidateIp;
                        break;
                    }
                }
            }
            $ip = $ip ?? 'unknown';
        }
        return $ip;
    }

    /**
     * Compatibility wrapper for log_json - now uses unified logging system
     * This maintains backward compatibility while routing through the new unified logger
     *
     * @param array $data The data to log as JSON
     * @param string $type The type of log (maps to context_key)
     * @param bool $debugPurpose If true, skips sanitization (for debugging)
     */
    protected function log_json(array $data, string $type = 'core', $debugPurpose = false): void {
        // Extract message from data if present, otherwise create a generic message
        $message = '';
        if (isset($data['message'])) {
            $message = $data['message'];
            unset($data['message']); // Remove to avoid duplication
        } elseif (isset($data['action'])) {
            $message = 'Action: ' . $data['action'];
        } elseif (isset($data['event'])) {
            $message = 'Event: ' . $data['event'];
        } else {
            $message = 'Data logged via log_json';
        }

        // Map old type parameter to context_key
        $contextKey = strtolower(trim($type));

        // Preserve existing context_id if it was in the data
        $options = [];
        if (isset($data['context_id'])) {
            $options['context_id'] = $data['context_id'];
            unset($data['context_id']); // Remove to avoid duplication
        }

        // Set debug purpose option
        if ($debugPurpose) {
            $options['debug_purpose'] = true;
        }

        // For backward compatibility, always include meta information for log_json calls
        // This maintains the rich context that log_json users expect
        $options['include_meta'] = true;

        // Call the new unified log method
        $this->log(
            $message,
            'INFO', // log_json was always INFO level
            false,  // Not verbose by default
            $contextKey,
            $data,  // Additional data
            $options
        );
    }

	/**
	 * Logs an error message and optionally displays an admin notice if the request is within the admin area.
	 *
	 * This helper function logs an error message to the error log and, if the user is in the WordPress admin area,
	 * displays an admin notice with the provided error message. This is useful for notifying administrators of issues
	 * that require attention.
	 *
	 * @param string $message The error message to log and potentially display in an admin notice.
	 *
	 * @return void
	 */
	protected function log_and_notify_admin(string $message): void {
		// Log the error message (sanitization happens in log method)
		$this->log($message, 'WARNING');

		// If in admin context, display an error notice (sanitize for display)
		if (is_admin()) {
			add_action('admin_notices', function() use ($message) {
				printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
			});
		}
	}

	/**
	 * Build a detailed, smart-concatenated message for the exception
	 */
	protected function buildExceptionMessage(Exception $exception, array $context = [], ?string $additionalContent = null): string
	{
		$lines = [];

		// Exception message (sanitize it)
		$sanitizedMessage = $this->sanitizeString($exception->getMessage());
		$firstLine = sprintf( 'Exception Message: %s', $sanitizedMessage);

		// Exception code, if available
		if ($exception->getCode() !== 0) {
            $firstLine .= sprintf( ' Code: %d', $exception->getCode());
		}

		// File and line number where the exception occurred
        $firstLine .= sprintf( ' File: %s on line %d', $exception->getFile(), $exception->getLine());

        $lines[] = $firstLine;

		// Context details (sanitize context array)
        $multiLine = false;
        if($multiLine && (!empty($context) || !empty($additionalContent))) {
            if (!empty($context)) {
                $sanitizedContext = $this->sanitizeArray($context);
                $lines[] = 'Context:';
                foreach ($sanitizedContext as $key => $value) {
                    $lines[] = sprintf( '  - %s: %s', $key, is_array($value) ? json_encode($value) : (string)$value);
                }
            }

            // Additional content, if provided (sanitize it)
            if (!empty($additionalContent)) {
                $sanitizedAdditionalContent = $this->sanitizeString($additionalContent);
                $lines[] = 'Additional Context:';
                $lines[] = $sanitizedAdditionalContent;
            }
        }

		// Join lines with line breaks for readability
        if(count($lines)) {
            return implode("\n", $lines);
        }

        return $firstLine;
	}

	/**
	 * Add additional sensitive keys to the existing list
	 *
	 * @param array $keys Array of sensitive keys to add
	 * @return void
	 */
	protected function addSensitiveKeys(array $keys): void {
		$this->sensitiveKeys = array_unique(array_merge($this->sensitiveKeys, $keys));
	}

	/**
	 * Add additional sensitive patterns to the existing list
	 *
	 * @param array $patterns Array of regex patterns to add
	 * @return void
	 */
	protected function addSensitivePatterns(array $patterns): void {
		$this->sensitivePatterns = array_merge($this->sensitivePatterns, $patterns);
	}

	/**
	 * Get the current list of sensitive keys
	 *
	 * @return array
	 */
	protected function getSensitiveKeys(): array {
		return RankingCoachPlugin::isProductionMode() ? $this->sensitiveKeys : [];
	}

	/**
	 * Get the current list of sensitive patterns
	 *
	 * @return array
	 */
	protected function getSensitivePatterns(): array {
		return RankingCoachPlugin::isProductionMode() ? $this->sensitivePatterns : [];
	}

	/**
	 * Wrapper function to delete old log files using the global helper function
	 *
	 * @param int $days Number of days to keep log files (files older than this will be deleted)
	 * @return int Number of files deleted
	 */
	protected function deleteOldLogFiles(int $days): int {
		if (!function_exists('beyondseo_rcdlf')) {
			$this->log('Log cleanup function rcdlf() not available', 'ERROR');
			return 0;
		}

		$this->log("Starting log cleanup for files older than {$days} days", 'INFO', true);
		$deletedCount = beyondseo_rcdlf($days);
		$this->log("Log cleanup completed. Deleted {$deletedCount} files", 'INFO', true);

		return $deletedCount;
	}

	/**
	 * Check if verbose logging is enabled
	 *
	 * @return bool
	 */
	private function isVerboseLoggingEnabled(): bool {
		return defined('RANKINGCOACH_LOG_VERBOSE') && RANKINGCOACH_LOG_VERBOSE === true;
	}
}
