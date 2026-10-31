<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces;

if (!defined('ABSPATH')) { exit; }

/**
 * Interface ContentProviderInterface
 *
 * Provides methods to manage content for SEO operations.
 */
interface ContentProviderInterface
{
    /**
     * Returns the public URL of a post/page by its ID.
     *
     * @param int $postId
     * @return string The permalink, or empty string if not found
     */
    public function getPostUrl(int $postId): string;

    /**
     * Checks meta-robots tag and X-Robots-Tag header for a single post/page URL.
     *
     * @param string $postUrl The public URL of the specific post/page being analyzed
     * @return array{meta_robots_issues: array, x_robots_tag_issues: array}
     */
    public function analyzePostRobotsDirectives(string $postUrl): array;
}
