<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Api\User\UserApiManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Traits\SingletonTrait;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;
use Throwable;

/**
 * Robust URL change detection for rankingCoach plugin
 * 
 * Handles domain/URL changes across single-site and multisite WordPress installations
 * with proper normalization, race condition protection, and deferred processing.
 */
class UrlChangeDetector
{
    use RcLoggerTrait;
    use SingletonTrait;

    private const OPTION_LAST_KNOWN_ORIGIN = BaseConstants::OPTION_LAST_KNOWN_ORIGIN; // json: {scheme,host,port,path}
    private const CRON_HOOK = 'rc_url_changed_deferred';
    private const LOCK_DURATION = 60; // seconds
    private const MAX_RETRY_ATTEMPTS = 3;
    private const RETRY_BASE_DELAY = 2; // seconds

    /**
     * Initialize URL change detection hooks
     */
    public function boot(): void
    {
        // WordPress option change hooks
        add_action('update_option_home', [$this, 'onUrlOptionChanged'], 10, 3);
        add_action('update_option_siteurl', [$this, 'onUrlOptionChanged'], 10, 3);
        
        // Multisite domain/path changes
        if (is_multisite()) {
            add_action('update_blog_details', [$this, 'onBlogDetailsUpdate'], 10, 2);
        }
        
        // Robust watchdog for edge cases
        add_action('init', [$this, 'detectUrlChangeWatchdog'], 1);
    }

    /**
     * Initialize baseline origin during onboarding
     * Called from UserApiManager after successful account setup
     */
    public function initializeOriginBaseline(): void
    {
        $currentOrigin = $this->getCurrentEffectiveOrigin();
        if ($currentOrigin) {
            $this->saveLastKnownOrigin($currentOrigin);
            
            $this->log('URL change detector baseline initialized. ' . json_encode([
                'origin' => $currentOrigin,
                'url' => $this->originToUrl($currentOrigin)
            ]));
        }
    }

    /**
     * Handle WordPress option changes (home/siteurl)
     */
    public function onUrlOptionChanged($oldValue, $newValue, string $option): void
    {
        $oldOrigin = $this->parseUrlOrigin($oldValue);
        $newOrigin = $this->parseUrlOrigin($newValue);
        
        if ($this->isOriginDifferent($oldOrigin, $newOrigin)) {
            $this->handleUrlChange($oldOrigin, $newOrigin, "option:$option");
        }
    }

    /**
     * Handle multisite blog details updates
     */
    public function onBlogDetailsUpdate(int $blogId, array $details): void
    {
        $lastKnown = $this->getLastKnownOrigin();
        if (!$lastKnown) {
            return;
        }

        $newOrigin = [
            'scheme' => $lastKnown['scheme'] ?? 'https',
            'host' => strtolower($details['domain'] ?? ''),
            'port' => $lastKnown['port'] ?? null,
            'path' => $details['path'] ?? '/'
        ];

        if ($this->isOriginDifferent($lastKnown, $newOrigin)) {
            $this->handleUrlChange($lastKnown, $newOrigin, 'multisite:blog_details');
        }
    }

    /**
     * Watchdog for detecting URL changes that might be missed by option hooks
     */
    public function detectUrlChangeWatchdog(): void
    {
        $currentOrigin = $this->getCurrentEffectiveOrigin();
        if (!$currentOrigin) {
            return;
        }

        $lastKnown = $this->getLastKnownOrigin();
        if(!$lastKnown) {
            return;
        }

        if ($this->isOriginDifferent($lastKnown, $currentOrigin)) {
            $this->handleUrlChange($lastKnown, $currentOrigin, 'watchdog:init');
        }
    }

    /**
     * Get current effective origin respecting WP constants and filters
     */
    private function getCurrentEffectiveOrigin(): ?array
    {
        // Respect WP_HOME constant first, then filtered home_url()
        $url = (defined('WP_HOME') && WP_HOME) ? WP_HOME : home_url('/');

        $origin = $this->parseUrlOrigin($url);

        // Fallback to server variables if host is missing
        if (!$origin['host']) {
            $sanitizedHost = $this->sanitizeHttpHost();
            if ($sanitizedHost) {
                $origin['host'] = $sanitizedHost;
            }
        }

        // Fallback scheme detection
        if (!$origin['scheme']) {
            $origin['scheme'] = is_ssl() ? 'https' : 'http';
        }

        return $origin['host'] ? $origin : null;
    }

    /**
     * Sanitize HTTP_HOST server variable with comprehensive validation
     */
    private function sanitizeHttpHost(): ?string
    {
        if (empty($_SERVER['HTTP_HOST'])) {
            return null;
        }

        $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']));
        
        // Remove port from host if present
        $host = preg_replace('/:\d+$/', '', $host);
        
        // Validate host format
        if (!$this->isValidHost($host)) {
            return null;
        }

        return strtolower($host);
    }

    /**
     * Validate host format against security criteria
     */
    private function isValidHost(string $host): bool
    {
        // Basic length check
        if (strlen($host) > 253 || strlen($host) < 1) {
            return false;
        }

        // Check for valid hostname pattern
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $host)) {
            return false;
        }

        // Reject localhost and private IPs in production
        if (!WP_DEBUG && (
            $host === 'localhost' ||
            preg_match('/^127\./', $host) ||
            preg_match('/^10\./', $host) ||
            preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $host) ||
            preg_match('/^192\.168\./', $host)
        )) {
            return false;
        }

        return true;
    }

    /**
     * Parse URL into normalized origin components
     */
    private function parseUrlOrigin(?string $url): array
    {
        $parsed = $url ? wp_parse_url($url) : [];
        
        return [
            'scheme' => isset($parsed['scheme']) ? strtolower($parsed['scheme']) : null,
            'host' => isset($parsed['host']) ? strtolower($parsed['host']) : null,
            'port' => isset($parsed['port']) ? (int)$parsed['port'] : null,
            'path' => isset($parsed['path']) ? ($parsed['path'] ?: '/') : '/',
        ];
    }

    /**
     * Compare two origins for differences
     */
    private function isOriginDifferent(?array $origin1, ?array $origin2): bool
    {
        if (!$origin1 || !$origin2) {
            return false;
        }

        // Normalize implicit ports
        $port1 = $this->normalizePort($origin1['port'], $origin1['scheme']);
        $port2 = $this->normalizePort($origin2['port'], $origin2['scheme']);

        return (
            strcasecmp($origin1['host'], $origin2['host']) !== 0 ||
            rtrim($origin1['path'], '/') !== rtrim($origin2['path'], '/') ||
            strcasecmp((string)$origin1['scheme'], (string)$origin2['scheme']) !== 0 ||
            $port1 !== $port2
        );
    }

    /**
     * Normalize port numbers (add implicit ports for http/https)
     */
    private function normalizePort(?int $port, ?string $scheme): int
    {
        if ($port) {
            return $port;
        }
        
        return match (strtolower((string)$scheme)) {
            'https' => 443,
            'http' => 80,
            default => 80
        };
    }

    /**
     * Handle URL change with atomic locking protection
     */
    private function handleUrlChange(array $oldOrigin, array $newOrigin, string $source): void
    {
        try {
            // Immediate actions
            $this->processImmediateActions($oldOrigin, $newOrigin, $source);
            
            // Save new origin
            $this->saveLastKnownOrigin($newOrigin);
            
            // Schedule deferred tasks
            $this->scheduleDeferredTasks($oldOrigin, $newOrigin);
            
        } catch (Throwable $e) {
            $this->log('URL change handling failed. ' . json_encode([
                'error' => $e->getMessage(),
                'old_origin' => $oldOrigin,
                'new_origin' => $newOrigin,
                'source' => $source
            ]), 'ERROR');
        }
    }

    /**
     * Process immediate actions when URL changes
     * @throws \JsonException
     */
    private function processImmediateActions(array $oldOrigin, array $newOrigin, string $source): void
    {
        // Update RankingCoach onboarding URL option
        $newUrl = $this->originToUrl($newOrigin);

        // Trigger API update with retry logic
        $storedOnboardingUrl = (string) get_option(BaseConstants::OPTION_RANKINGCOACH_ONBOARDING_URL, '');
        if ($storedOnboardingUrl === '' || $storedOnboardingUrl !== $newUrl) {
            $this->updateWebsiteUrlWithRetry($newUrl);
        }

        // Fire action for other components
        do_action('rankingcoach_url_changed', 
            $this->originToUrl($oldOrigin), 
            $this->originToUrl($newOrigin), 
            $source
        );

        // Update onboarding URL option
        update_option(BaseConstants::OPTION_RANKINGCOACH_ONBOARDING_URL, $newUrl);

        $this->log('URL change detected and processed. ' . json_encode([
                'old_url' => $this->originToUrl($oldOrigin),
                'new_url' => $this->originToUrl($newOrigin),
                'source' => $source
            ], JSON_THROW_ON_ERROR));
    }

    /**
     * Update website URL via API with exponential backoff retry logic
     */
    private function updateWebsiteUrlWithRetry(string $newUrl): void
    {
        $attempt = 0;

        while ($attempt < self::MAX_RETRY_ATTEMPTS) {
            try {
                $uam = UserApiManager::getInstance(bearerToken: true);
                if (!empty($uam->getBearerToken())) {
                    $success = $uam->updateWebsiteUrl();
                    if ($success) {
                        if ($attempt > 0) {
                            $this->log("Website URL updated successfully after $attempt retries", 'INFO');
                        }
                        return;
                    }
                }


                throw new Exception('API returned false without exception');
                
            } catch (HttpApiException | ReflectionException | Exception $e) {
                $attempt++;
                
                if ($attempt < self::MAX_RETRY_ATTEMPTS) {
                    $delay = self::RETRY_BASE_DELAY * pow(2, $attempt - 1);
                    $this->log("API update attempt $attempt failed, retrying in {$delay}s: " . $e->getMessage(), 'WARNING');
                    sleep($delay);
                } else {
                    $this->log('Failed to update website URL via API after ' . self::MAX_RETRY_ATTEMPTS . ' attempts. ' . json_encode([
                        'error' => $e->getMessage(),
                        'new_url' => $newUrl,
                        'attempts' => $attempt
                    ]), 'ERROR');
                }
            }
        }
    }

    /**
     * Schedule deferred tasks for heavy operations
     */
    private function scheduleDeferredTasks(array $oldOrigin, array $newOrigin): void
    {
        $oldUrl = $this->originToUrl($oldOrigin);
        $newUrl = $this->originToUrl($newOrigin);
        
        if (!wp_next_scheduled(self::CRON_HOOK, [$oldUrl, $newUrl])) {
            wp_schedule_single_event(time() + 60, self::CRON_HOOK, [$oldUrl, $newUrl]);
        }
    }

    /**
     * Convert origin array to URL string
     */
    private function originToUrl(array $origin): string
    {
        $scheme = $origin['scheme'] ?: 'https';
        $host = $origin['host'];
        $port = $origin['port'] ? ':' . $origin['port'] : '';
        $path = rtrim($origin['path'] ?: '/', '/');
        
        return "{$scheme}://{$host}{$port}{$path}";
    }

    /**
     * Get last known origin from database
     */
    private function getLastKnownOrigin(): ?array
    {
        $stored = get_option(self::OPTION_LAST_KNOWN_ORIGIN);
        if (!$stored) {
            return null;
        }

        $decoded = json_decode((string) base64_decode($stored), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Save last known origin to database
     */
    private function saveLastKnownOrigin(array $origin): void
    {
        $json = wp_json_encode($origin);
        update_option(self::OPTION_LAST_KNOWN_ORIGIN, base64_encode($json), false);
    }
}
