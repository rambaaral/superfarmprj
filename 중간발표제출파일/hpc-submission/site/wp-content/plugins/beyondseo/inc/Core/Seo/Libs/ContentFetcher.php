<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Libs;

if (!defined('ABSPATH')) { exit; }

use DOMDocument;
use RuntimeException;

/**
 * ContentFetcher handles URL content retrieval with static caching
 * for improved performance across multiple SEO analysis operations.
 */
class ContentFetcher
{
    /**
     * Static cache for URL content
     * @var array<string, array>
     */
    private static array $contentCache = [];

    /**
     * Static cache for URLs
     * @var array<string, string>
     */
    private static array $urlCache = [];

    /**
     * Static cache for URL fetch durations in seconds
     * @var array<string, float|null>
     */
    private static array $urlTimings = [];

    /**
     * Default request options
     */
    private array $defaultOptions = [
        'timeout' => 10,
        'redirection' => 5,
        'headers' => [
            'User-Agent' => 'SEO Analysis Tool/1.0 (Compatible; +https://example.com/bot)',
        ],
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $devMode = wp_get_environment_type();
        if ($devMode !== 'production') {
            $this->defaultOptions['sslverify'] = false;
        }
    }

    /**
     * Fetch content from URL with caching
     *
     * @param string $url URL to fetch
     * @param bool $useCache Whether to use cache (default true)
     * @param array $options Additional request options
     * @return array Content data with raw HTML, text content, and DOM
     * @throws RuntimeException When content cannot be fetched
     */
    public function fetchContent(string $url, array $options = [], bool $useCache = true): array
    {
        $cacheKey = $this->generateCacheKey($url);

        if ($useCache && isset(self::$contentCache[$cacheKey])) {
            return self::$contentCache[$cacheKey];
        }

        $requestOptions = array_merge($this->defaultOptions, $options);

        $fetchStart = microtime(true);
        $response = wp_remote_get($url, $requestOptions);
        $fetchDuration = round(microtime(true) - $fetchStart, 4);

        if (is_wp_error($response)) {
            throw new RuntimeException(
                sprintf(
                    'Failed to fetch content from URL: %s. Error: %s',
                    esc_html($url),
                    esc_html($response->get_error_message())
                )
            );
        }

        $result = $this->processResponse($response, $url);

        if ($useCache) {
            self::$contentCache[$cacheKey] = $result;
        }
        self::registerUrl($url, $fetchDuration);

        return $result;
    }

    /**
     * Process HTTP response into structured content data
     */
    private function processResponse(array $response, string $url): array
    {
        $html = wp_remote_retrieve_body($response);

        $dom = new DOMDocument();
        if (!empty($html)) {
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
        }

        $textContent = wp_strip_all_tags($html);

        $metaTags = self::extractMetaTags($dom);

        return [
            'raw_html' => $html,
            'text_content' => $textContent,
            'dom' => $dom,
            'meta' => $metaTags,
            'headers' => wp_remote_retrieve_headers($response)->getAll(),
            'status_code' => wp_remote_retrieve_response_code($response),
            'url' => $url,
            'content_length' => strlen($html),
            'fetch_time' => time(),
        ];
    }

    /**
     * Extract meta tags from DOM
     */
    private static function extractMetaTags(DOMDocument $dom): array
    {
        $metaTags = [];
        $metaNodes = $dom->getElementsByTagName('meta');

        foreach ($metaNodes as $meta) {
            $name = $meta->getAttribute('name') ?: $meta->getAttribute('property');
            $content = $meta->getAttribute('content');

            if ($name && $content) {
                $metaTags[$name] = $content;
            }
        }

        return $metaTags;
    }

    /**
     * Generate a cache key from URL
     */
    private function generateCacheKey(string $url): string
    {
        return md5($url);
    }

    /**
     * Get the URL from the cache
     */
    public static function getUrlsFromCache(): array
    {
        $result = [];
        foreach (self::$urlCache as $key => $url) {
            $result[$key] = ['url' => $url, 'duration' => self::$urlTimings[$key] ?? null];
        }
        return $result;
    }

    public static function getCachedRawHtml(string $url): ?string
    {
        $cacheKey = md5($url);
        return self::$contentCache[$cacheKey]['raw_html'] ?? null;
    }

    public static function getCachedHeaders(string $url): ?array
    {
        $cacheKey = md5($url);
        return isset(self::$contentCache[$cacheKey]) ? (self::$contentCache[$cacheKey]['headers'] ?? null) : null;
    }

    public static function storeFromWpResponse(string $url, array $wpResponse, float $duration): void
    {
        $cacheKey = md5($url);
        $html = wp_remote_retrieve_body($wpResponse);

        if (empty($html)) {
            self::registerUrl($url, $duration);
            return;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        self::$contentCache[$cacheKey] = [
            'raw_html'       => $html,
            'text_content'   => wp_strip_all_tags($html),
            'dom'            => $dom,
            'meta'           => self::extractMetaTags($dom),
            'headers'        => wp_remote_retrieve_headers($wpResponse)->getAll(),
            'status_code'    => wp_remote_retrieve_response_code($wpResponse),
            'url'            => $url,
            'content_length' => strlen($html),
            'fetch_time'     => time(),
        ];

        self::registerUrl($url, $duration);
    }

    /**
     * Register a URL as consumed, with an optional fetch duration.
     * Use this as the single entry point for tracking any URL consumed during analysis.
     */
    public static function registerUrl(string $url, ?float $duration = null): void
    {
        $cacheKey = md5($url);
        self::$urlCache[$cacheKey] = $url;
        self::$urlTimings[$cacheKey] = $duration;
    }

    /**
     * Remove a specific URL from the tracking registry.
     */
    public static function removeUrl(string $url): void
    {
        $cacheKey = md5($url);
        unset(self::$urlCache[$cacheKey]);
        unset(self::$urlTimings[$cacheKey]);
    }

    /**
     * @deprecated Use registerUrl() instead
     */
    public static function setUrlToCache(string $url): void
    {
        self::registerUrl($url);
    }

    /**
     * Clear the content cache
     */
    public static function clearCache(): void
    {
        self::$contentCache = [];
    }

    /**
     * Clear the URL cache
     */
    public static function clearUrlCache(): void
    {
        self::$urlCache = [];
        self::$urlTimings = [];
    }

    /**
     * Remove specific URL from cache
     */
    public static function removeFromCache(string $url): void
    {
        $cacheKey = md5($url);
        unset(self::$contentCache[$cacheKey]);
        self::removeUrl($url);
    }
}
