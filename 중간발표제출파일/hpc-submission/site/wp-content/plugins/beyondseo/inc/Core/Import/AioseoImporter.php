<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Import;

use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

final class AioseoImporter
{
    private const POST_FIELDS = [
        'title' => 'title', 'description' => 'description', 'focus_keyword' => 'keywords',
        'canonical_url' => 'canonical', 'robots_noindex' => 'noindex',
    ];

    public function name(): string
    {
        return 'All in One SEO';
    }

    public function files(): array
    {
        return ['all-in-one-seo-pack/all_in_one_seo_pack.php', 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php'];
    }

    public function tokens(): array
    {
        return ['#post_title' => 'post_title', '#site_title' => 'site_title'];
    }

    public function postFields(): array
    {
        return self::POST_FIELDS;
    }

    public function settingKeys(): array
    {
        return [
            'organization_social_facebook', 'organization_social_twitter',
            'organization_social_instagram', 'organization_social_linkedin',
            'organization_social_youtube', 'organisation_or_person_name', 'sitemap.enabled',
        ];
    }

    public function settings(array $options = []): array
    {
        $selected = array_flip($options === [] ? $this->settingKeys() : array_intersect($this->settingKeys(), $options));
        if ($selected === []) {
            return [];
        }
        $sourceOptions = ImportRunner::readSourceOption('aioseo_options');
        if (is_string($sourceOptions)) {
            $sourceOptions = json_decode($sourceOptions, true);
        }
        if (!is_array($sourceOptions)) {
            return [];
        }
        $values = [];
        $profiles = $sourceOptions['social']['profiles']['urls'] ?? [];
        foreach (['facebookPageUrl' => 'facebook', 'twitterUrl' => 'twitter', 'instagramUrl' => 'instagram', 'linkedinUrl' => 'linkedin', 'youtubeUrl' => 'youtube'] as $from => $to) {
            $key = 'organization_social_' . $to;
            if (isset($selected[$key])) {
                $values[$key] = $profiles[$from] ?? null;
            }
        }
        if (isset($selected['organisation_or_person_name'])) {
            $values['organisation_or_person_name'] = $sourceOptions['searchAppearance']['global']['schema']['organizationName'] ?? null;
        }
        if (isset($selected['sitemap.enabled'])) {
            $values['sitemap.enabled'] = $sourceOptions['sitemap']['general']['enable'] ?? null;
        }
        return $values;
    }

    public function importPostMeta(ImportRunner $runner, array &$result, array $options = []): void
    {
        $options = $runner->normalizeOptions('aioseo', $options);
        $tokens = $this->tokens();
        foreach ($this->postRows($options) as $row) {
            $runner->importField($row['postId'], $row['type'], $row['value'], $tokens, $result);
        }
    }

    public function postRows(array $options = []): iterable
    {
        $fields = $options === [] ? self::POST_FIELDS
            : array_filter(self::POST_FIELDS, static fn(string $type): bool => in_array($type, $options, true));
        if ($fields === []) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'aioseo_posts';
        $existingTable = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($wpdb->last_error !== '') {
            throw new RuntimeException('Unable to read AIOSEO posts');
        }
        if ($existingTable !== $table) {
            return;
        }
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`");
        if (!is_array($columns) || $wpdb->last_error !== '' || !in_array('id', $columns, true) || !in_array('post_id', $columns, true)) {
            throw new RuntimeException('Unable to read AIOSEO posts');
        }
        $fields = array_intersect_key($fields, array_flip($columns));
        if ($fields === []) {
            return;
        }
        $projection = implode(', ', array_map(static fn(string $column): string => 'a.`' . $column . '`', array_keys($fields)));
        $after = 0;
        do {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT a.id, a.post_id, {$projection}
                 FROM `{$table}` a INNER JOIN {$wpdb->posts} p ON p.ID = a.post_id
                 WHERE a.id > %d AND p.post_status NOT IN ('trash', 'auto-draft') ORDER BY a.id LIMIT 200",
                $after
            ), ARRAY_A);
            if (!is_array($rows) || $wpdb->last_error !== '') {
                throw new RuntimeException('Unable to read AIOSEO posts');
            }
            foreach ($rows as $row) {
                $after = (int) $row['id'];
                foreach ($fields as $column => $type) {
                    yield [
                        'postId' => (int) $row['post_id'],
                        'type' => $type,
                        'value' => $row[$column],
                    ];
                }
            }
        } while (count($rows) === 200);
    }
}
