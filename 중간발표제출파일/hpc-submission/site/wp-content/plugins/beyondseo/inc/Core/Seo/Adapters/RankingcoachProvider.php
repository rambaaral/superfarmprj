<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Adapters;

if (!defined('ABSPATH')) { exit; }

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\ContentProviderInterface;

/**
 * Class RankingcoachProvider
 *
 * Provides methods to manage content for SEO in plugin.
 */
class RankingcoachProvider implements ContentProviderInterface
{
    public function getPostUrl(int $postId): string
    {
        // TODO: Implement getPostUrl() method.
        return '';
    }

    public function analyzePostRobotsDirectives(string $postUrl): array
    {
        // TODO: Implement analyzePostRobotsDirectives() method.
        return [
            'meta_robots_issues' => [],
            'x_robots_tag_issues' => []
        ];
    }
}
