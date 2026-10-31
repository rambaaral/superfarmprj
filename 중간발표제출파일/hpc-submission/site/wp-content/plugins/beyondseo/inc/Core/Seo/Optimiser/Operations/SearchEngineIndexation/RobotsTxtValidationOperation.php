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
 * Class RobotsTxtValidationOperation
 *
 * This operation validates the robots.txt file to ensure it properly allows search engines
 * to crawl important pages while blocking unnecessary sections.
 */
#[SeoMeta(
    name: 'Robots Txt Validation',
    weight: WeightConfiguration::WEIGHT_ROBOTS_TXT_VALIDATION_OPERATION,
    description: 'Fetches and analyzes the robots.txt file to confirm correct directives. Ensures important resources are accessible and harmful blocks are removed, providing tips to balance crawler control and SEO.',
)]
class RobotsTxtValidationOperation extends Operation implements OperationInterface
{
    /**
     * Performs the robots.txt validation operation.
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
                'issues' => [__('Unable to determine site URL', 'beyondseo')],
            ];
        }

        // Fetch robots.txt content
        $robotsTxtUrl = trailingslashit($siteUrl) . 'robots.txt';
        $robotsTxtContent = $this->contentProvider->fetchInternalUrlContent($robotsTxtUrl);

        // Check if robots.txt exists
        if (empty($robotsTxtContent)) {
            return [
                'success' => true,
                'message' => __('No robots.txt file found', 'beyondseo'),
                'robots_txt_exists' => false,
                'critical_pages_allowed' => [],
                'blocked_sections' => [],
                'all_pages_blocked' => false,
                'issues' => [__('No robots.txt file found', 'beyondseo')],
            ];
        }

        // Parse robots.txt directives
        $directives = $this->contentProvider->parseRobotsTxtDirectives($robotsTxtContent);

        // Get critical pages that should be crawlable
        $criticalPages = $this->contentProvider->getCriticalPagePaths();

        // Validate if critical pages are allowed
        $criticalPagesAnalysis = $this->contentProvider->analyzeCriticalPageAccessByRobotsTxt($directives, $criticalPages);

        // Analyze for blocked sections
        $blockedSections = $this->contentProvider->analyzeBlockedSectionsByRobotsTxt($directives);

        // Check if all pages are blocked (Disallow: /)
        $allPagesBlocked = $this->contentProvider->areAllPagesBlockedByRobotsTxt($directives);

        // Identify issues
        $issues = $this->contentProvider->identifyRobotsTxtIssues(
            $criticalPagesAnalysis['blocked_critical_pages'],
            $blockedSections,
            $allPagesBlocked
        );

        // Prepare results
        return [
            'success' => true,
            'message' => empty($issues)
                ? __('Robots.txt validation passed successfully', 'beyondseo')
                : __('Robots.txt validation found issues', 'beyondseo'),
            'robots_txt_exists' => true,
            'robots_txt_content' => $robotsTxtContent,
            'directives' => $directives,
            'critical_pages_allowed' => $criticalPagesAnalysis['allowed_critical_pages'],
            'critical_pages_blocked' => $criticalPagesAnalysis['blocked_critical_pages'],
            'blocked_sections' => $blockedSections,
            'all_pages_blocked' => $allPagesBlocked,
            'issues' => $issues
        ];
    }

    /**
     * Evaluates the quality of the robots.txt configuration.
     *
     * @return float A score based on the robots.txt validation
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // Start with a base score
        $score = 0;

        // If robots.txt doesn't exist, give a minimal score
        if (!($factorData['robots_txt_exists'] ?? false)) {
            return 0.3; // Some sites might not need a robots.txt, but it's generally recommended
        }

        // Check if critical pages are accessible
        $criticalPagesBlocked = $factorData['critical_pages_blocked'] ?? [];
        $criticalPagesAllowed = $factorData['critical_pages_allowed'] ?? [];
        $totalCriticalPages = count($criticalPagesBlocked) + count($criticalPagesAllowed);

        if ($totalCriticalPages > 0) {
            $criticalPageScore = count($criticalPagesAllowed) / $totalCriticalPages;
            $score += $criticalPageScore * 0.5; // 50% of the score is based on critical pages accessibility
        } else {
            $score += 0.5; // If no critical pages defined, assume all is well
        }

        // Check if important sections are blocked
        $recommendedSectionsToBlock = [
            '/' . ltrim(wp_make_link_relative(admin_url()), '/'),
            '/' . ltrim(wp_make_link_relative(includes_url()), '/'),
            '/' . ltrim(wp_make_link_relative(plugins_url()), '/'),
            '/' . ltrim(wp_make_link_relative(wp_login_url()), '/'),
        ];

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

        $score += $blockingScore * 0.3; // 30% of the score is based on blocking recommended sections

        // Check if all pages are blocked (major issue)
        if ($factorData['all_pages_blocked'] ?? false) {
            $score = 0; // Override the score - this is a critical error
        }

        // Check for general issues
        $issues = $factorData['issues'] ?? [];
        if (empty($issues)) {
            $score += 0.2; // Add 20% for having no issues
        }

        return min(1.0, max(0.0, $score)); // Ensure the score is between 0 and 1
    }

    /**
     * Provides suggestions for improving the robots.txt configuration.
     *
     * @return array An array of suggestions
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
            return $suggestions;
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

        // Check if important sections that should be blocked are not blocked
        $recommendedSectionsToBlock = [
            '/' . ltrim(wp_make_link_relative(admin_url()), '/'),
            '/' . ltrim(wp_make_link_relative(includes_url()), '/'),
            '/' . ltrim(wp_make_link_relative(plugins_url()), '/'),
            '/' . ltrim(wp_make_link_relative(wp_login_url()), '/'),
        ];

        $blockedSections = $factorData['blocked_sections'] ?? [];
        $unblockedRecommendedSections = [];

        foreach ($recommendedSectionsToBlock as $section) {
            $isBlocked = false;
            foreach ($blockedSections as $blockedSection) {
                if ($this->contentProvider->isPathMatched($section, $blockedSection)) {
                    $isBlocked = true;
                    break;
                }
            }
            if (!$isBlocked) {
                $unblockedRecommendedSections[] = $section;
            }
        }

        if (!empty($unblockedRecommendedSections)) {
            $suggestions[] = Suggestion::ADMIN_SECTIONS_NOT_BLOCKED;
        }

        // Check if sitemap is referenced in robots.txt
        $directives = $factorData['directives'] ?? [];
        $hasSitemap = false;

        foreach ($directives as $directive) {
            if (!empty($directive['sitemap'])) {
                $hasSitemap = true;
                break;
            }
        }

        if (!$hasSitemap) {
            $suggestions[] = Suggestion::MISSING_SITEMAP_IN_ROBOTS_TXT;
        }

        // Check for structural optimization opportunities
        $directives = $factorData['directives'] ?? [];
        if (isset($directives['_structural_analysis']) && $directives['_structural_analysis']['needs_optimization']) {
            $suggestions[] = Suggestion::OPTIMIZE_ROBOTS_TXT_STRUCTURE;
        }

        return $suggestions;
    }
}