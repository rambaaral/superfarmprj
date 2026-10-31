<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Import;

if (!defined('ABSPATH')) {
    exit;
}

final class RankMathImporter
{
    private const POST_FIELDS = [
        'rank_math_title' => 'title', 'rank_math_description' => 'description',
        'rank_math_focus_keyword' => 'keywords', 'rank_math_canonical_url' => 'canonical',
        'rank_math_robots' => 'noindex', 'rank_math_facebook_title' => 'social_title',
        'rank_math_facebook_description' => 'social_description',
    ];

    public function name(): string
    {
        return 'Rank Math';
    }

    public function files(): array
    {
        return ['seo-by-rank-math/rank-math.php', 'seo-by-rank-math-pro/rank-math-pro.php'];
    }

    public function tokens(): array
    {
        return ['%title%' => 'post_title', '%sitename%' => 'site_title'];
    }

    public function postFields(): array
    {
        return self::POST_FIELDS;
    }

    public function settingKeys(): array
    {
        return ['organization_social_facebook'];
    }

    public function settings(array $options = []): array
    {
        if ($options !== [] && !in_array('organization_social_facebook', $options, true)) {
            return [];
        }
        $values = ImportRunner::readSourceOption('rank-math-options-titles');
        return is_array($values) ? ['organization_social_facebook' => $values['social_url_facebook'] ?? null] : [];
    }

    public function importPostMeta(ImportRunner $runner, array &$result, array $options = []): void
    {
        $options = $runner->normalizeOptions('rankmath', $options);
        $fields = array_filter(self::POST_FIELDS, static fn(string $type): bool => in_array($type, $options, true));
        $runner->importMappedPostMeta($fields, $this->tokens(), $result);
    }
}
