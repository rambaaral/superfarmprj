<?php
/** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
/** @noinspection RegExpUnnecessaryNonCapturingGroup */
/** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Helpers\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Trait RcTechnicalSeoTrait
 *
 * Provides utility methods for technical SEO analysis including URL structure,
 * canonical tags, duplicate content detection, and robots.txt analysis.
 */
trait RcTechnicalSeoTrait
{
    use RcLoggerTrait;

    /**
     * Check if a URL exists and returns a valid response
     *
     * @param string $url The URL to check
     * @return bool Whether the URL exists and returns a valid response
     */
    public function checkUrlExists(string $url): bool
    {
        $response = $this->fetchInternalUrlContent($url, [
            'timeout'    => 5,
            'sslverify'  => wp_get_environment_type() === 'production', // Skip verification in dev
            'headers'    => [
                'User-Agent' => 'RC-WordPress-Checker/1.0',
            ]
        ], true);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Analyzes URL readability based on various factors.
     *
     * @param string $url The URL to analyze
     * @param string $title The page title for keyword context
     * @param string $slug The page slug
     * @param string $permalinkStructure WordPress permalink structure
     * @return array Analysis results
     */
    public function analyzeUrlReadability(string $url, string $title, string $slug, string $permalinkStructure): array
    {
        // Parse the URL
        $parsedUrl = wp_parse_url($url);
        $path = $parsedUrl['path'] ?? '';

        // Check URL length
        $urlLength = strlen($url);
        $isUrlTooLong = $urlLength > SeoOptimiserConfig::MAX_RECOMMENDED_URL_LENGTH;
        $isUrlExcessivelyLong = $urlLength > SeoOptimiserConfig::MAX_ACCEPTABLE_URL_LENGTH;

        // Check for query parameters
        $hasQueryParams = !empty($parsedUrl['query']);
        $queryParams = [];
        if ($hasQueryParams) {
            parse_str($parsedUrl['query'], $queryParams);
        }

        // Check for excessive numbers in slug
        $hasExcessiveNumbers = $this->urlSlugHasExcessiveNumbers($slug);

        // Check for special characters
        $hasSpecialCharacters = $this->urlSlugHasSpecialCharacters($slug);

        // Check keyword relevance between URL and title
        $keywordRelevance = $this->calculateKeywordRelevanceBetweenSlugAndTitle($slug, $title);

        // Check slug segments
        $slugSegments = array_filter(explode('/', $path));
        $hasExcessiveSegments = count($slugSegments) > SeoOptimiserConfig::MAX_RECOMMENDED_SLUG_SEGMENTS;

        // Check the permalink structure
        $isSeoFriendlyStructure = $this->isSeoFriendlyPermalinkStructure($permalinkStructure);

        return [
            'path' => $path,
            'url_length' => [
                'length' => $urlLength,
                'is_too_long' => $isUrlTooLong,
                'is_excessively_long' => $isUrlExcessivelyLong,
                'max_recommended' => SeoOptimiserConfig::MAX_RECOMMENDED_URL_LENGTH,
            ],
            'query_parameters' => [
                'has_query_params' => $hasQueryParams,
                'params_count' => count($queryParams),
                'params' => array_keys($queryParams),
            ],
            'slug_quality' => [
                'slug' => $slug,
                'has_excessive_numbers' => $hasExcessiveNumbers,
                'has_special_characters' => $hasSpecialCharacters,
                'segment_count' => count($slugSegments),
                'has_excessive_segments' => $hasExcessiveSegments,
            ],
            'keyword_relevance' => [
                'score' => $keywordRelevance,
                'is_relevant' => $keywordRelevance >= 0.5,
            ],
            'permalink_structure' => [
                'structure' => $permalinkStructure,
                'is_seo_friendly' => $isSeoFriendlyStructure,
            ]
        ];
    }

    /**
     * Checks if the slug contains excessive numbers.
     *
     * @param string $slug The URL slug
     * @return bool True if excessive numbers found
     */
    public function urlSlugHasExcessiveNumbers(string $slug): bool
    {
        // Count the number of digits
        $digitCount = preg_match_all('/\d/', $slug);

        // If more than 30% of characters are digits, consider it excessive
        $totalLength = strlen($slug);
        if ($totalLength === 0) {
            return false;
        }

        return ($digitCount / $totalLength) > 0.3;
    }

    /**
     * Checks if the slug contains special characters other than hyphens.
     *
     * @param string $slug The URL slug
     * @return bool True if special characters found
     */
    public function urlSlugHasSpecialCharacters(string $slug): bool
    {
        // Check for characters that aren't alphanumeric or hyphens
        return preg_match('/[^a-zA-Z0-9-]/', $slug) === 1;
    }

    /**
     * Calculates keyword relevance between slug and title.
     *
     * @param string $slug The URL slug
     * @param string $title The page title
     * @return float Relevance score (0-1)
     */
    public function calculateKeywordRelevanceBetweenSlugAndTitle(string $slug, string $title): float
    {
        if (empty($slug) || empty($title)) {
            return 0;
        }

        // Normalize strings for comparison
        $slug = strtolower(str_replace('-', ' ', $slug));
        $title = strtolower($title);

        // Get words from slug and title
        $slugWords = array_filter(explode(' ', $slug));
        $titleWords = array_filter(explode(' ', $title));

        if (empty($slugWords) || empty($titleWords)) {
            return 0;
        }

        // Count matching words
        $matchingWords = array_intersect($slugWords, $titleWords);
        $matchCount = count($matchingWords);

        // If all slug words are in the title, that's ideal
        if ($matchCount === count($slugWords)) {
            return 1.0;
        }

        // Otherwise, calculate a weighted score
        $titleCoverage = $matchCount / count($titleWords);
        $slugPrecision = $matchCount / count($slugWords);

        // Combine metrics with emphasis on slug precision
        return ($titleCoverage * 0.3) + ($slugPrecision * 0.7);
    }

    /**
     * Checks if the permalink structure is SEO-friendly.
     *
     * @param string $permalinkStructure WordPress permalink structure
     * @return bool True if permalink structure is SEO-friendly
     */
    public function isSeoFriendlyPermalinkStructure(string $permalinkStructure): bool
    {
        // Empty structure means "Plain" permalinks (not SEO friendly)
        if (empty($permalinkStructure)) {
            return false;
        }

        // Check for permalink structures containing post-name
        return str_contains($permalinkStructure, '%postname%');
    }

    /**
     * Normalize URLs for comparison by removing trailing slashes, query parameters, and standardizing protocol.
     *
     * @param string $url The URL to normalize
     * @return string The normalized URL
     */
    public function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (empty($url)) {
            return '';
        }

        // Convert to lowercase
        $url = strtolower($url);

        // Remove the trailing slash if present
        $url = rtrim($url, '/');

        $parts = wp_parse_url($url);
        if ($parts === false) {
            return $url; // Return original if parsing fails
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'http';
        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
        $query = $parts['query'] ?? '';
        $fragment = $parts['fragment'] ?? ''; // Canonical should usually ignore fragments

        // Reconstruct URL without fragment and with a normalized path / scheme
        $normalizedUrl = $scheme . '://' . $host . $path;

        // Add a query back, sorted alphabetically for consistent comparison
        if (!empty($query)) {
            parse_str($query, $queryParams); // Parse a query string into an array
            ksort($queryParams); // Sort by key
            $normalizedUrl .= '?' . http_build_query($queryParams); // Rebuild query string
        }

        // Handle a root path specifically - ensure it has a slash
        if ($path === '' && empty($query) && empty($fragment)) {
            $normalizedUrl = rtrim($normalizedUrl, '/') . '/';
        }

        return $normalizedUrl;
    }

    /**
     * Extracts canonical URL from HTML content.
     *
     * @param string $html The HTML content
     * @param int $postId The post ID to analyze
     * @return array The canonical URLs and issues found
     */
    public function extractCanonicalInfo(string $html, int $postId): array
    {
        // Create a DOMDocument and load the HTML
        $dom = $this->loadHTMLInDomXPath($html, true);

        $links = $dom->getElementsByTagName('link');

        $canonicalUrls = [];
        $issues = [];

        foreach ($links as $link) {
            if ($link->getAttribute('rel') === 'canonical') {
                $canonicalUrls[] = $link->getAttribute('href');
            }
        }

        // Check results
        $hasCanonical = count($canonicalUrls) > 0;
        $hasMultipleCanonicals = count($canonicalUrls) > 1;
        $canonicalUrl = $hasCanonical ? $canonicalUrls[0] : '';
        // Get the expected URL for this post
        $expectedUrl = $this->getPostUrl($postId);
        $isSelfReferential = $hasCanonical && $this->normalizeUrl($canonicalUrl) === $this->normalizeUrl($expectedUrl);

        // Validate canonical URL
        $isValid = $hasCanonical && !empty($canonicalUrl) && filter_var($canonicalUrl, FILTER_VALIDATE_URL);

        // Identify issues
        if (!$hasCanonical) {
            $issues[] = 'No canonical tag found';
        } elseif ($hasMultipleCanonicals) {
            $issues[] = 'Multiple canonical tags found';
        }

        if ($hasCanonical && !$isValid) {
            $issues[] = 'Canonical URL is invalid';
        } elseif ($hasCanonical && !$isSelfReferential) {
            $issues[] = 'Canonical URL does not match expected URL';
        }

        return  [
            'canonical_tag_present' => $hasCanonical,
            'canonical_url' => $canonicalUrl,
            'expected_url' => $expectedUrl,
            'is_valid' => $isValid,
            'is_self_referential' => $isSelfReferential,
            'has_multiple_canonicals' => $hasMultipleCanonicals,
            'issues' => $issues
        ];
    }

    /**
     * Extracts the canonical URL from HTML content.
     * If no canonical tag is found, or it's invalid, it returns null.
     *
     * @param string $html The HTML content
     * @param int $postId The post ID to analyze
     * @return string|null The canonical URL or null if not found or invalid
     */
    public function extractCanonicalUrl(string $html, int $postId): ?string
    {
        $canonicalInfo = $this->extractCanonicalInfo($html, $postId);
        if ($canonicalInfo['canonical_tag_present'] && $canonicalInfo['is_valid']) {
            return $canonicalInfo['canonical_url'];
        }
        return null;
    }

    /**
     * Detects parameter-based duplicate content issues, such as URL parameters that
     * don't change the content but create duplicate URLs.
     *
     * @param string $postUrl The URL of the post
     * @return array Parameter-based duplicate URLs
     */
    public function analyzeUrlForParameterDuplicates(string $postUrl): array
    {
        $duplicates = [];
        $urlParts = wp_parse_url($postUrl);
        $query = $urlParts['query'] ?? '';

        if (empty($query)) {
            return $duplicates; // No parameters to check
        }

        // Common URL parameters that might create duplicate content
        $problematicParameters = [
            'utm_', // UTM tracking parameters
            'ref', 'source', 'campaign', // Common referral/tracking params
            'session_', // Session IDs
            'fbclid', 'gclid', 'msclkid', // Ad/social tracking IDs
            'sortby=', 'orderby=', 'filter=', 'page=', // Faceted navigation/pagination params (often need handling)
            'print=true', 'amp=1', // Specific format parameters
        ];

        parse_str($query, $queryParams);

        foreach ($queryParams as $paramKey => $paramValue) {
            $isProblematic = false;
            $matchedPattern = '';

            foreach ($problematicParameters as $pattern) {
                if (str_ends_with($pattern, '=') && $paramKey === rtrim($pattern, '=')) {
                    $isProblematic = true;
                    $matchedPattern = $pattern;
                    break;
                } elseif (str_ends_with($pattern, '_') && str_starts_with($paramKey, rtrim($pattern, '_'))) {
                    $isProblematic = true;
                    $matchedPattern = $pattern . '*';
                    break;
                } elseif ($paramKey === $pattern) {
                    $isProblematic = true;
                    $matchedPattern = $pattern;
                    break;
                }
            }

            if ($isProblematic) {
                // We identify the *potential* duplicate URL (the URL without the param)
                // and note that the *current* URL *has* this problematic parameter.
                $duplicates[] = [
                    'url' => $postUrl, // The URL being analyzed
                    'parameter' => $paramKey . '=' . $paramValue, // The specific parameter found
                    'pattern' => $matchedPattern, // The pattern it matched
                    'type' => 'parameter', // Indicates this is based on pattern matching
                    // We don't know the canonical status of the *hypothetical* clean URL here
                    // This finding highlights the need to ensure the *current* URL has a correct canonical
                    'has_canonical' => null, // Unknown for the cleaner URL
                    'canonical_url' => null, // Unknown for the cleaner URL
                    'canonical_points_to_original' => null, // Unknown for the cleaner URL
                ];
            }
        }

        return $duplicates;
    }

    /**
     * Detects path-based duplicate content issues, such as pagination, print versions,
     * or archive paths that lead to the same content.
     *
     * @param string $postUrl The URL of the post
     * @return array Path-based duplicate URLs
     */
    public function analyzeUrlForPathDuplicates(string $postUrl): array
    {
        $duplicates = [];
        $urlParts = wp_parse_url($postUrl);
        $path = $urlParts['path'] ?? '';

        if (empty($path) || $path === '/') {
            return $duplicates; // No path or just root
        }

        // Common path patterns that might create duplicate content
        // Add more patterns as needed
        $problematicPathPatterns = [
            '\/page\/[0-9]+\/?$', // /page/2/, /page/3/ etc. (pagination)
            '\/print\/?$', // /print/
            '\/amp\/?$', // /amp/
            '\/feed\/?$', // /feed/
            '\/comment-page-[0-9]+\/?$', // /comment-page-1/ etc.
        ];

        foreach ($problematicPathPatterns as $pattern) {
            if (preg_match('#' . $pattern . '#i', $path)) {
                // We identify that the *current* URL *matches* this problematic path pattern.
                // This finding highlights the need to ensure the *current* URL has a correct canonical
                // pointing to the preferred version (likely the base URL without the pattern).
                $duplicates[] = [
                    'url' => $postUrl, // The URL being analyzed
                    'path_pattern' => $pattern, // The pattern it matched
                    'type' => 'path', // Indicates this is based on pattern matching
                    // We don't know the canonical status of the *hypothetical* preferred URL here
                    'has_canonical' => null, // Unknown for the preferred URL
                    'canonical_url' => null, // Unknown for the preferred URL
                    'canonical_points_to_original' => null, // Unknown for the preferred URL
                ];
                // Found one path pattern match, no need to check others for this URL
                break;
            }
        }

        return $duplicates;
    }

    /**
     * Parses the robots.txt content into structured directives and checks for structural issues.
     *
     * @param string $robotsTxtContent The robots.txt content
     * @return array The parsed directives and structural analysis
     */
    public function parseRobotsTxtDirectives(string $robotsTxtContent): array
    {
        $directives = [];
        $currentUserAgent = '*'; // Default to all user agents
        $userAgentCount = 0;
        $disallowWithoutUserAgent = false;
        $inconsistentFormatting = false;

        // Split content into lines and process each line
        $lines = explode("\n", $robotsTxtContent);
        foreach ($lines as $lineNumber => $line) {
            // Remove comments and trim whitespace
            $originalLine = $line;
            $line = preg_replace('/#.*$/', '', $line);
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Check for structural issues
            if (preg_match('/^User-agent:/i', $line)) {
                $userAgentCount++;
            }

            // Check for Disallow without preceding User-agent
            if ($userAgentCount === 0 && preg_match('/^Disallow:/i', $line)) {
                $disallowWithoutUserAgent = true;
            }

            // Check for inconsistent formatting (missing spaces after colon)
            if (preg_match('/^(User-agent|Disallow|Allow|Sitemap):\S/i', $line)) {
                $inconsistentFormatting = true;
            }

            // Extract directive and value
            if (preg_match('/^([^:]+):(.*)$/i', $line, $matches)) {
                $directive = strtolower(trim($matches[1]));
                $value = trim($matches[2]);

                if ($directive === 'user-agent') {
                    $currentUserAgent = $value;
                    if (!isset($directives[$currentUserAgent])) {
                        $directives[$currentUserAgent] = [
                            'allow' => [],
                            'disallow' => [],
                            'crawl-delay' => null,
                            'sitemap' => []
                        ];
                    }
                } elseif ($directive === 'disallow') {
                    $directives[$currentUserAgent]['disallow'][] = $value;
                } elseif ($directive === 'allow') {
                    $directives[$currentUserAgent]['allow'][] = $value;
                } elseif ($directive === 'crawl-delay') {
                    $directives[$currentUserAgent]['crawl-delay'] = $value;
                } elseif ($directive === 'sitemap') {
                    // Sitemaps are global, not per user-agent, but we'll include them here for simplicity
                    if (!isset($directives[$currentUserAgent])) {
                        $directives[$currentUserAgent] = [
                            'allow' => [],
                            'disallow' => [],
                            'crawl-delay' => null,
                            'sitemap' => []
                        ];
                    }
                    if (!in_array($value, $directives[$currentUserAgent]['sitemap'])) {
                        $directives[$currentUserAgent]['sitemap'][] = $value;
                    }
                }
            }
        }

        // Add structural analysis to the result
        $directives['_structural_analysis'] = [
            'needs_optimization' => $disallowWithoutUserAgent || $inconsistentFormatting,
            'disallow_without_user_agent' => $disallowWithoutUserAgent,
            'inconsistent_formatting' => $inconsistentFormatting
        ];

        return $directives;
    }

    /**
     * Analyzes if critical pages are accessible based on robots.txt directives.
     *
     * @param array $directives The parsed robots.txt directives
     * @param array $criticalPages Array of critical page paths
     * @return array Analysis results showing allowed and blocked critical pages
     */
    public function analyzeCriticalPageAccessByRobotsTxt(array $directives, array $criticalPages): array
    {
        $allowedCriticalPages = [];
        $blockedCriticalPages = [];

        // Check for all user agents and Googlebot specifically
        $userAgentsToCheck = ['*'];

        // Add Googlebot if it exists in directives
        if (isset($directives['googlebot'])) {
            $userAgentsToCheck[] = 'googlebot';
        }

        foreach ($criticalPages as $page) {
            $isBlocked = false;

            foreach ($userAgentsToCheck as $userAgent) {
                if (!isset($directives[$userAgent])) {
                    continue;
                }

                $uaDirectives = $directives[$userAgent];

                // Check if the page is explicitly blocked
                foreach ($uaDirectives['disallow'] as $disallow) {
                    if ($disallow === '/' || $this->isPathMatched($page, $disallow)) {
                        $isBlocked = true;
                        break;
                    }
                }

                // Check if it's explicitly allowed (which overrides disallow)
                foreach ($uaDirectives['allow'] as $allow) {
                    if ($this->isPathMatched($page, $allow)) {
                        $isBlocked = false;
                        break;
                    }
                }

                // If blocked by this user agent, no need to check others
                if ($isBlocked) {
                    break;
                }
            }

            if ($isBlocked) {
                $blockedCriticalPages[] = $page;
            } else {
                $allowedCriticalPages[] = $page;
            }
        }

        return [
            'allowed_critical_pages' => $allowedCriticalPages,
            'blocked_critical_pages' => $blockedCriticalPages,
        ];
    }

    /**
     * Analyzes robots.txt for blocked sections.
     *
     * @param array $directives The parsed robots.txt directives
     * @return array An array of blocked sections
     */
    public function analyzeBlockedSectionsByRobotsTxt(array $directives): array
    {
        $blockedSections = [];

        // Common sections that are typically blocked
        $commonBlockableSections = [
            '/' . ltrim(wp_make_link_relative(admin_url()), '/'),
            '/' . ltrim(wp_make_link_relative(includes_url()), '/'),
            '/' . ltrim(wp_make_link_relative(plugins_url()), '/'),
            '/' . ltrim(wp_make_link_relative(get_rest_url()), '/'),
            '/cart/',
            '/checkout/',
            '/my-account/',
            '/admin/',
            '/login/',
            '/' . ltrim(wp_make_link_relative(wp_login_url()), '/'),
            '/search',
            '/tag/',
            '/author/',
            '/cgi-bin/',
            '/feed/',
            '/trackback/',
        ];

        // Check what is actually blocked for important user agents
        $userAgentsToCheck = ['*'];
        if (isset($directives['googlebot'])) {
            $userAgentsToCheck[] = 'googlebot';
        }

        foreach ($userAgentsToCheck as $userAgent) {
            if (!isset($directives[$userAgent])) {
                continue;
            }

            foreach ($directives[$userAgent]['disallow'] as $disallow) {
                if (empty($disallow)) {
                    continue;
                }

                // Check if this is a common blockable section
                foreach ($commonBlockableSections as $section) {
                    if ($this->isPathMatched($section, $disallow) && !in_array($section, $blockedSections)) {
                        $blockedSections[] = $section;
                    }
                }

                // Also add any disallowed section not in our common list
                if (!in_array($disallow, $blockedSections)) {
                    $blockedSections[] = $disallow;
                }
            }
        }

        return $blockedSections;
    }

    /**
     * Checks if all pages are blocked in robots.txt.
     *
     * @param array $directives The parsed robots.txt directives
     * @return bool Whether all pages are blocked
     */
    public function areAllPagesBlockedByRobotsTxt(array $directives): bool
    {
        // Check if Disallow: / is set for all user agents or Googlebot
        $userAgentsToCheck = ['*'];
        if (isset($directives['googlebot'])) {
            $userAgentsToCheck[] = 'googlebot';
        }

        foreach ($userAgentsToCheck as $userAgent) {
            if (isset($directives[$userAgent]) && in_array('/', $directives[$userAgent]['disallow'])) {
                // Check if there are any Allow directives that might override this
                if (empty($directives[$userAgent]['allow'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Identifies issues with robots.txt configuration.
     *
     * @param array $blockedCriticalPages Array of blocked critical pages
     * @param array $blockedSections Array of blocked sections
     * @param bool $allPagesBlocked Whether all pages are blocked
     * @return array An array of identified issues
     */
    public function identifyRobotsTxtIssues(
        array $blockedCriticalPages,
        array $blockedSections,
        bool  $allPagesBlocked
    ): array
    {
        $issues = [];

        // Check if critical pages are blocked
        if (!empty($blockedCriticalPages)) {
            $issues[] = 'Critical pages are blocked: ' . implode(', ', $blockedCriticalPages);
        }

        // Check if all pages are blocked
        if ($allPagesBlocked) {
            $issues[] = 'All pages are blocked with Disallow: /';
        }

        // Check if important sections that should be blocked are not blocked
        // Default recommended sections (excluding /wp-admin/ to match desired reporting)
        $recommendedBlockableSections = [
            '/wp-includes/',
            '/wp-content/plugins/',
            '/wp-login.php',
        ];

        $unblockedRecommendedSections = array_diff($recommendedBlockableSections, $blockedSections);
        if (!empty($unblockedRecommendedSections)) {
            $issues[] = 'Recommended sections are not blocked: ' . implode(', ', $unblockedRecommendedSections);
        }

        return $issues;
    }

    /**
     * Checks the meta-robots tag and X-Robots-Tag header for a single post/page URL.
     *
     * Use this for per-post analysis. Only one HTTP GET is made (or served from cache).
     * A noindex directive on the post URL is flagged as an issue.
     *
     * @param string $postUrl The public URL of the specific post/page being analyzed
     * @return array{meta_robots_issues: array, x_robots_tag_issues: array}
     */
    public function analyzePostRobotsDirectives(string $postUrl): array
    {
        $metaRobotsIssues = [];
        $xRobotsTagIssues = [];

        if (empty($postUrl)) {
            return ['meta_robots_issues' => $metaRobotsIssues, 'x_robots_tag_issues' => $xRobotsTagIssues];
        }

        $body = $this->fetchInternalUrlContent($postUrl);
        if (!empty($body) && !is_wp_error($body)) {
            if ($this->hasMetaRobotsNoindex($body)) {
                $metaRobotsIssues[] = "Page $postUrl has noindex meta-tag";
            }

            $headers = $this->getCachedHeaders($postUrl) ?? $this->getHttpHeaders($postUrl);
            if ($this->hasXRobotsTagNoindex($headers)) {
                $xRobotsTagIssues[] = "Page $postUrl has noindex in X-Robots-Tag header";
            }
        }

        return [
            'meta_robots_issues'  => $metaRobotsIssues,
            'x_robots_tag_issues' => $xRobotsTagIssues,
        ];
    }

    /**
     * Performs a single-pass analysis of both meta-robots tags and X-Robots-Tag headers
     * for all important and admin pages, fetching each URL only once.
     *
     * Each page is fetched via fetchInternalUrlContent() (GET, cached). Headers from
     * the same GET response are retrieved via getCachedHeaders() with no additional
     * HTTP request. Page lists are memoized across calls.
     *
     * @param string $siteUrl The site URL
     * @return array{meta_robots_issues: array, x_robots_tag_issues: array}
     */
    public function analyzePageRobotsDirectives(string $siteUrl): array
    {
        $metaRobotsIssues = [];
        $xRobotsTagIssues = [];

        $importantPages = $this->getImportantPages($siteUrl);
        $adminPages     = $this->getAdminPages($siteUrl);

        foreach ($importantPages as $page) {
            $body = $this->fetchInternalUrlContent($page);
            if (empty($body) || is_wp_error($body)) {
                continue;
            }

            if ($this->hasMetaRobotsNoindex($body)) {
                $metaRobotsIssues[] = "Important page $page has noindex meta-tag";
            }

            $headers = $this->getCachedHeaders($page) ?? $this->getHttpHeaders($page);
            if ($this->hasXRobotsTagNoindex($headers)) {
                $xRobotsTagIssues[] = "Important page $page has noindex in X-Robots-Tag header";
            }
        }

        foreach ($adminPages as $page) {
            $body = $this->fetchInternalUrlContent($page);
            if (empty($body) || is_wp_error($body)) {
                continue;
            }

            if (!$this->hasMetaRobotsNoindex($body)) {
                $metaRobotsIssues[] = "Admin page $page is missing noindex meta-tag";
            }

            $headers = $this->getCachedHeaders($page) ?? $this->getHttpHeaders($page);
            if (!$this->hasXRobotsTagNoindex($headers)) {
                $xRobotsTagIssues[] = "Admin page $page is missing noindex in X-Robots-Tag header";
            }
        }

        return [
            'meta_robots_issues'  => $metaRobotsIssues,
            'x_robots_tag_issues' => $xRobotsTagIssues,
        ];
    }

    /**
     * Checks if HTML content has a meta-robots tag with noindex directive.
     *
     * @param string $html The HTML content to analyze
     * @return bool True if noindex directive is found
     */
    public function hasMetaRobotsNoindex(string $html): bool
    {
        // Use loadHTMLInDomXPath method to create a DOMXPath object
        $xpath = $this->loadHTMLInDomXPath($html);

        if (!$xpath) {
            return false;
        }

        // Check for <meta name="robots" content="noindex"> or <meta name="robots" content="noindex, follow">
        $metaTags = $xpath->query('//meta[@name="robots"]');
        foreach ($metaTags as $tag) {
            $content = $tag->getAttribute('content');
            if (str_contains(strtolower($content), 'noindex')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates a DOMXPath object from the provided HTML content
     *
     * @param string $htmlContent The HTML content to parse
     * @param bool $returnDocument Whether to return the DOMDocument object
     * @return \DOMDocument|\DOMXPath|null The DOMXPath object if successful, or null if an error occurs
     */
    protected function loadHTMLInDomXPath(string $htmlContent, bool $returnDocument = false): \DOMXPath|\DOMDocument|null
    {
        $dom = new \DOMDocument();
        $htmlContent = @mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8');

        libxml_use_internal_errors(true);
        $success = $dom->loadHTML($htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if (!$success) {
            return null;
        }

        // If $returnDocument is true, return the DOMDocument object
        if ($returnDocument) {
            return $dom;
        }

        return new \DOMXPath($dom);
    }

    /**
     * Checks if HTTP headers contain X-Robots-Tag with noindex directive.
     *
     * @param array $headers The HTTP headers to analyze
     * @return bool True if noindex directive is found
     */
    public function hasXRobotsTagNoindex(array $headers): bool
    {
        foreach ($headers as $header => $value) {
            if (strtolower($header) === 'x-robots-tag' && str_contains(strtolower($value), 'noindex')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves HTTP headers for a specified URL.
     *
     * @param string $url The URL to check
     * @return array The HTTP headers retrieved from the URL
     */
    public function getHttpHeaders(string $url): array
    {
        $response = $this->fetchInternalUrlContent($url, [
            'timeout'    => 5,
            'sslverify'  => wp_get_environment_type() === 'production', // Skip verification in dev
            'headers'    => [
                'User-Agent' => 'RC-WordPress-Checker/1.0',
            ]
        ], true);

        if (is_wp_error($response)) {
            return [];
        }

        return wp_remote_retrieve_headers($response)->getAll();
    }
}
