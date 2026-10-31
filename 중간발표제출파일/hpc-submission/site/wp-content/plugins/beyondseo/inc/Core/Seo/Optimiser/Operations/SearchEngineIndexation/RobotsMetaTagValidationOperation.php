<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\SearchEngineIndexation;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class RobotsMetaTagValidationOperation
 *
 * This operation validates robots.txt, meta-robots tags, and X-Robots-Tag HTTP headers
 * to ensure they properly allow search engines to crawl important pages while blocking
 * unnecessary sections of the website.
 */
#[SeoMeta(
    name: 'Robots Meta Tag Validation',
    weight: WeightConfiguration::WEIGHT_ROBOTS_META_TAG_VALIDATION_OPERATION,
    description: 'Checks meta robots tags, HTTP headers, and robots.txt to verify that important pages are crawlable while restricting unwanted sections. Highlights misconfigured directives that could prevent indexing.',
)]
class RobotsMetaTagValidationOperation extends Operation implements OperationInterface
{
    /**
     * Performs the robots.txt and meta-tag validation operation.
     *
     * @return array|null The operation results or null if invalid
     */
    public function run(): ?array
    {
        // Get site URL
        $siteUrl = $this->contentProvider->getSiteUrl();
        if (empty($siteUrl)) {
            return [
                'success' => false,
                'message' => __('Unable to determine site URL', 'beyondseo'),
                'robots_txt_exists' => false,
                'critical_pages_allowed' => [],
                'blocked_sections' => [],
                'all_pages_blocked' => false,
                'meta_robots_issues' => [],
                'x_robots_tag_issues' => [],
                'issues' => [__('Unable to determine site URL', 'beyondseo')],
            ];
        }

        // Fetch robots.txt content
        $robotsTxtUrl = trailingslashit($siteUrl) . 'robots.txt';
        $robotsTxtContent = $this->contentProvider->fetchInternalUrlContent($robotsTxtUrl);

        // Check if robots.txt exists
        $robotsTxtExists = !empty($robotsTxtContent);
        $robotsTxtDirectives = [];
        $criticalPagesAnalysis = [
            'allowed_critical_pages' => [],
            'blocked_critical_pages' => [],
        ];
        $blockedSections = [];
        $allPagesBlocked = false;
        $robotsTxtIssues = [];

        // Analyze robots.txt if it exists
        if ($robotsTxtExists) {
            // Parse robots.txt directives
            $robotsTxtDirectives = $this->contentProvider->parseRobotsTxtDirectives($robotsTxtContent);

            // Get critical pages that should be crawlable
            $criticalPages = $this->contentProvider->getCriticalPagePaths();

            // Validate if critical pages are allowed
            $criticalPagesAnalysis = $this->contentProvider->analyzeCriticalPageAccessByRobotsTxt($robotsTxtDirectives, $criticalPages);

            // Analyze for blocked sections
            $blockedSections = $this->contentProvider->analyzeBlockedSectionsByRobotsTxt($robotsTxtDirectives);

            // Check if all pages are blocked (Disallow: /)
            $allPagesBlocked = $this->contentProvider->areAllPagesBlockedByRobotsTxt($robotsTxtDirectives);

            // Identify issues
            $robotsTxtIssues = $this->contentProvider->identifyRobotsTxtIssues(
                $criticalPagesAnalysis['blocked_critical_pages'],
                $blockedSections,
                $allPagesBlocked
            );
        } else {
            $robotsTxtIssues[] = __('No robots.txt file found', 'beyondseo');
        }

        // Analyze meta-robots tag and X-Robots-Tag header for the specific post/page only
        $postUrl = $this->contentProvider->getPostUrl($this->postId);
        $robotsDirectives = $this->contentProvider->analyzePostRobotsDirectives($postUrl);
        $metaRobotsIssues = $robotsDirectives['meta_robots_issues'];
        $xRobotsTagIssues = $robotsDirectives['x_robots_tag_issues'];

        // Combine all issues
        $allIssues = array_merge($robotsTxtIssues, $metaRobotsIssues, $xRobotsTagIssues);

        // Prepare results
        return [
            'success' => true,
            'message' => empty($allIssues)
                ? __('Robots.txt and meta-tag validation passed successfully', 'beyondseo')
                : __('Robots.txt and meta-tag validation found issues', 'beyondseo'),
            'robots_txt_exists' => $robotsTxtExists,
            'robots_txt_content' => $robotsTxtContent,
            'directives' => $robotsTxtDirectives,
            'critical_pages_allowed' => $criticalPagesAnalysis['allowed_critical_pages'],
            'critical_pages_blocked' => $criticalPagesAnalysis['blocked_critical_pages'],
            'blocked_sections' => $blockedSections,
            'all_pages_blocked' => $allPagesBlocked,
            'meta_robots_issues' => $metaRobotsIssues,
            'x_robots_tag_issues' => $xRobotsTagIssues,
            'issues' => $allIssues
        ];
    }


    /**
     * Evaluates the quality of the robots.txt and meta-tag configuration.
     *
     * @return float A score between 0.0 and 1.0 based on the validation results
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // Start with a base score
        $score = 0;

        // If robots.txt doesn't exist, give a minimal score
        if (!($factorData['robots_txt_exists'] ?? false)) {
            $score += 0.2; // Some sites might not need a robots.txt, but it's generally recommended
        } else {
            $score += 0.4; // Having a robots.txt file is good
        }

        // Check if critical pages are accessible
        $criticalPagesBlocked = $factorData['critical_pages_blocked'] ?? [];
        $criticalPagesAllowed = $factorData['critical_pages_allowed'] ?? [];
        $totalCriticalPages = count($criticalPagesBlocked) + count($criticalPagesAllowed);

        if ($totalCriticalPages > 0) {
            $criticalPageScore = count($criticalPagesAllowed) / $totalCriticalPages;
            $score += $criticalPageScore * 0.2; // 20% of the score is based on critical pages accessibility
        } else {
            $score += 0.2; // If no critical pages defined, assume all is well
        }

        // Check if important sections are blocked
        $siteUrl = $this->contentProvider->getSiteUrl();
        $adminPagesUrls = $this->contentProvider->getAdminPages($siteUrl);

        // Extract paths from URLs
        $recommendedSectionsToBlock = [];
        foreach ($adminPagesUrls as $url) {
            $path = wp_parse_url($url, PHP_URL_PATH);
            if ($path) {
                $recommendedSectionsToBlock[] = $path;
            }
        }

        $blockedSections = $factorData['blocked_sections'] ?? [];
        $blockedRecommendedCount = 0;

        foreach ($recommendedSectionsToBlock as $section) {
            foreach ($blockedSections as $blockedSection) {
                if ($this->contentProvider->isPathMatched($section, $blockedSection)) {
                    $blockedRecommendedCount++;
                    break;
                }
            }
        }

        $blockingScore = count($recommendedSectionsToBlock) > 0
            ? $blockedRecommendedCount / count($recommendedSectionsToBlock)
            : 0;

        $score += $blockingScore * 0.2; // 20% of the score is based on blocking recommended sections

        // Check if all pages are blocked (major issue)
        if ($factorData['all_pages_blocked'] ?? false) {
            return 0; // This is a critical error, return 0 directly
        }
        
        // Check for meta-robots issues
        $metaRobotsIssues = $factorData['meta_robots_issues'] ?? [];
        if (empty($metaRobotsIssues)) {
            $score += 0.1; // Add 10% for having no meta-robots issues
        } else {
            // Deduct points based on the number of issues
            $score -= min(0.1, count($metaRobotsIssues) * 0.02);
        }

        // Check for X-Robots-Tag issues
        $xRobotsTagIssues = $factorData['x_robots_tag_issues'] ?? [];
        if (empty($xRobotsTagIssues)) {
            $score += 0.1; // Add 10% for having no X-Robots-Tag issues
        } else {
            // Deduct points based on the number of issues
            $score -= min(0.1, count($xRobotsTagIssues) * 0.02);
        }

        return min(1.0, max(0.0, $score)); // Ensure the score is between 0 and 1
    }

    /**
     * Provides suggestions for improving the robots.txt and meta-tag configuration.
     *
     * @return array An array of improvement suggestions
     */
    public function suggestions(): array
    {
        $factorData = $this->value;
        $suggestions = [];

        if (empty($factorData)) {
            // If factorData is not available, suggest setting up a basic robots.txt
            $suggestions[] = Suggestion::ROBOTS_TXT_MISSING;
            return $suggestions;
        }

        // If robots.txt doesn't exist, suggest creating one
        if (!($factorData['robots_txt_exists'] ?? true)) {
            $suggestions[] = Suggestion::ROBOTS_TXT_MISSING;
        }

        // Check if all pages are blocked
        if ($factorData['all_pages_blocked'] ?? false) {
            $suggestions[] = Suggestion::ALL_PAGES_BLOCKED;
        }

        // Check if critical pages are blocked
        $criticalPagesBlocked = $factorData['critical_pages_blocked'] ?? [];
        if (!empty($criticalPagesBlocked)) {
            $suggestions[] = Suggestion::CRITICAL_PAGES_BLOCKED;
        }

        // Add meta robots suggestions
        $metaRobotsIssues = $factorData['meta_robots_issues'] ?? [];
        if (!empty($metaRobotsIssues)) {
            $suggestions[] = Suggestion::META_ROBOTS_ISSUES;
        }

        // Add X-Robots-Tag suggestions
        $xRobotsTagIssues = $factorData['x_robots_tag_issues'] ?? [];
        if (!empty($xRobotsTagIssues)) {
            $suggestions[] = Suggestion::X_ROBOTS_TAG_ISSUES;
        }

        return $suggestions;
    }
}