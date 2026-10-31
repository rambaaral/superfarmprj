<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Import;

use InvalidArgumentException;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\PluginSettings;
use RankingCoach\Inc\Core\Storage\MetaTagsStorage;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportRunner
{
    private array $plugins;

    public function __construct()
    {
        $this->plugins = [
            'aioseo' => new AioseoImporter(),
            'rankmath' => new RankMathImporter(),
            'yoast' => new YoastImporter(),
        ];
    }

    public function sources(): array
    {
        $sources = [];
        foreach (array_keys($this->plugins) as $source) {
            $plugin = $this->source($source);
            if ($plugin['installed']) {
                $sources[] = $plugin;
            }
        }
        return $sources;
    }

    public function source(string $source): array
    {
        if (!isset($this->plugins[$source])) {
            throw new RuntimeException('Unknown SEO plugin');
        }
        $plugin = $this->plugins[$source];
        $installed = false;
        $active = false;
        $activePlugins = (array) get_option('active_plugins', []);
        $networkPlugins = is_multisite() ? (array) get_site_option('active_sitewide_plugins', []) : [];
        foreach ($plugin->files() as $file) {
            $installed = $installed || is_file(WP_PLUGIN_DIR . '/' . $file);
            $active = $active || in_array($file, $activePlugins, true) || isset($networkPlugins[$file]);
        }
        return [
            'source' => $source,
            'name' => $plugin->name(),
            'installed' => $installed,
            'active' => $active,
            'imports' => ['settings', 'postMeta'],
        ];
    }

    public function optionCatalog(string $source): array
    {
        if (!isset($this->plugins[$source])) {
            throw new RuntimeException('Unknown SEO plugin');
        }
        $plugin = $this->plugins[$source];
        $labels = [
            'title' => __('SEO titles', 'beyondseo'),
            'description' => __('SEO descriptions', 'beyondseo'),
            'keywords' => __('Keywords', 'beyondseo'),
            'canonical' => __('Canonical URLs', 'beyondseo'),
            'noindex' => __('Noindex directives', 'beyondseo'),
            'social_title' => __('Social titles', 'beyondseo'),
            'social_description' => __('Social descriptions', 'beyondseo'),
            'organization_social_facebook' => __('Facebook profile', 'beyondseo'),
            'organization_social_twitter' => __('Twitter profile', 'beyondseo'),
            'organization_social_instagram' => __('Instagram profile', 'beyondseo'),
            'organization_social_linkedin' => __('LinkedIn profile', 'beyondseo'),
            'organization_social_youtube' => __('YouTube profile', 'beyondseo'),
            'organisation_or_person_name' => __('Organization or person name', 'beyondseo'),
            'sitemap.enabled' => __('Sitemap enabled', 'beyondseo'),
            'google_verification_code' => __('Google verification code', 'beyondseo'),
            'bing_verification_code' => __('Bing verification code', 'beyondseo'),
        ];
        $postFields = array_flip($plugin->postFields());
        $settingKeys = array_flip($plugin->settingKeys());
        $catalog = [];
        foreach ($labels as $id => $label) {
            if (isset($postFields[$id]) || isset($settingKeys[$id])) {
                $catalog[] = [
                    'id' => $id,
                    'label' => $label,
                    'group' => isset($postFields[$id]) ? 'postMeta' : 'settings',
                    'supported' => true,
                ];
            }
        }
        return $catalog;
    }

    public function sourceOptions(string $source): array
    {
        global $wpdb;
        $previous = $wpdb->suppress_errors(true);
        try {
            $wpdb->last_error = '';
            $metadata = $this->source($source);
            if ($wpdb->last_error !== '') {
                throw new RuntimeException('Unable to inspect SEO source');
            }
            unset($metadata['imports']);
            $catalog = $this->optionCatalog($source);
            $availability = array_fill_keys(array_column($catalog, 'id'), false);
            if ($metadata['installed']) {
                $plugin = $this->plugins[$source];
                foreach ($plugin->settings() as $key => $value) {
                    if (array_key_exists($key, $availability)) {
                        $availability[$key] = $this->prepareSettingValue($key, $value) !== null;
                    }
                }
                $pending = array_fill_keys(array_values($plugin->postFields()), true);
                $rows = $source === 'aioseo' ? $plugin->postRows()
                    : $this->mappedPostRows($plugin->postFields());
                $tokens = $plugin->tokens();
                foreach ($rows as $row) {
                    $type = $row['type'];
                    if (!isset($pending[$type])) {
                        continue;
                    }
                    $prepared = $this->prepareField($row['postId'], $type, $row['value'], $tokens);
                    if (array_key_exists('value', $prepared)) {
                        $availability[$type] = true;
                        unset($pending[$type]);
                        if ($pending === []) {
                            break;
                        }
                    }
                }
            }
            foreach ($catalog as &$option) {
                $option['hasData'] = $availability[$option['id']];
            }
            unset($option);
            $metadata['options'] = $catalog;
            return $metadata;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    public static function readSourceOption(string $key): mixed
    {
        global $wpdb;
        $previous = $wpdb->suppress_errors(true);
        try {
            $wpdb->last_error = '';
            $value = get_option($key, []);
            if ($wpdb->last_error !== '') {
                throw new RuntimeException('Unable to read source settings');
            }
            return $value;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    public function normalizeOptions(string $source, array $options = []): array
    {
        $supported = array_column($this->optionCatalog($source), 'id');
        if ($options === []) {
            return $supported;
        }
        if (array_keys($options) !== range(0, count($options) - 1)) {
            throw new InvalidArgumentException('SEO import options must be a list of strings');
        }
        foreach ($options as $option) {
            if (!is_string($option) || !in_array($option, $supported, true)) {
                throw new InvalidArgumentException('Unsupported SEO import option');
            }
        }
        return array_values(array_unique($options));
    }

    public function import(string $source, array $options = []): array
    {
        $options = $this->normalizeOptions($source, $options);
        if (!$this->source($source)['installed']) {
            throw new RuntimeException('SEO plugin is not installed');
        }
        $plugin = $this->plugins[$source];
        $settingKeys = array_values(array_intersect($plugin->settingKeys(), $options));
        $postFields = array_values(array_intersect(array_values($plugin->postFields()), $options));
        $result = ['source' => $source, 'settings' => 0, 'postMeta' => 0, 'skipped' => 0];
        if ($settingKeys !== []) {
            $this->importSettings($plugin->settings($settingKeys), $result);
        }
        if ($postFields !== []) {
            $plugin->importPostMeta($this, $result, $postFields);
        }
        $result['status'] = 'completed';
        return $result;
    }

    private function importSettings(array $values, array &$result): void
    {
        $settings = PluginSettings::instance();
        foreach ($values as $key => $value) {
            $value = $this->prepareSettingValue($key, $value);
            if ($value === null) {
                continue;
            }
            if ($key === 'sitemap.enabled') {
                $sitemap = $settings->get('sitemap');
                if (is_array($sitemap) && $sitemap['enabled'] === $settings->getDefault('sitemap')['enabled']) {
                    $sitemap['enabled'] = $value;
                    $settings->set('sitemap', $sitemap);
                    $result['settings']++;
                }
                continue;
            }
            if ($settings->get($key) === $settings->getDefault($key)) {
                $settings->set($key, $value);
                $result['settings']++;
            }
        }
    }

    private function prepareSettingValue(string $key, mixed $value): mixed
    {
        if ($key === 'sitemap.enabled') {
            return is_bool($value) ? $value : null;
        }
        if (!is_string($value) || $value === '') {
            return null;
        }
        $value = str_contains($key, 'social_') ? esc_url_raw($value) : sanitize_text_field($value);
        return $value === '' ? null : $value;
    }

    public function importMappedPostMeta(array $fields, array $tokens, array &$result): void
    {
        foreach ($this->mappedPostRows($fields) as $row) {
            $this->importField($row['postId'], $row['type'], $row['value'], $tokens, $result);
        }
    }

    private function mappedPostRows(array $fields): iterable
    {
        if ($fields === []) {
            return;
        }
        global $wpdb;
        $keys = array_keys($fields);
        $placeholders = implode(',', array_fill(0, count($keys), '%s'));
        $after = 0;
        do {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT m.meta_id, m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m
                 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
                 WHERE m.meta_id > %d AND m.meta_key IN ($placeholders) AND p.post_status NOT IN ('trash', 'auto-draft')
                 ORDER BY m.meta_id LIMIT 200",
                ...array_merge([$after], $keys)
            ), ARRAY_A);
            if (!is_array($rows) || $wpdb->last_error !== '') {
                throw new RuntimeException('Unable to read source post meta');
            }
            foreach ($rows as $row) {
                $after = (int) $row['meta_id'];
                yield [
                    'postId' => (int) $row['post_id'],
                    'type' => $fields[$row['meta_key']],
                    'value' => maybe_unserialize($row['meta_value']),
                ];
            }
        } while (count($rows) === 200);
    }

    public function importField(int $postId, string $type, mixed $value, array $tokens, array &$result): void
    {
        $prepared = $this->prepareField($postId, $type, $value, $tokens);
        if (isset($prepared['skipped'])) {
            $result['skipped']++;
            return;
        }
        if (!array_key_exists('value', $prepared)) {
            return;
        }
        if ($type === 'noindex') {
            if (update_post_meta($postId, MetaTags::META_NOINDEX_FOR_PAGE, $prepared['value']) === false) {
                throw new RuntimeException('Unable to save noindex');
            }
        } elseif ($type === 'canonical') {
            if (update_post_meta($postId, MetaTags::META_CANONICAL_URL, $prepared['value']) === false) {
                throw new RuntimeException('Unable to save canonical URL');
            }
        } else {
            $storage = new MetaTagsStorage();
            $storage->save($postId, $type, $prepared['value']);
        }
        $result['postMeta']++;
    }

    private function prepareField(int $postId, string $type, mixed $value, array $tokens): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if ($type === 'noindex') {
            $enabled = is_array($value) ? in_array('noindex', $value, true)
                : in_array((string) $value, ['1', 'true'], true);
            if (!$enabled || $this->destinationHasMeta($postId, MetaTags::META_NOINDEX_FOR_PAGE)) {
                return [];
            }
            return ['value' => '1'];
        }
        if ($type === 'canonical') {
            if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
                || $this->destinationHasMeta($postId, MetaTags::META_CANONICAL_URL)) {
                return ['skipped' => true];
            }
            $canonical = esc_url_raw($value);
            if ($canonical === '') {
                throw new RuntimeException('Unable to save canonical URL');
            }
            return ['value' => $canonical];
        }
        $storage = new MetaTagsStorage();
        $legacy = [
            'title' => ['rankingcoach_seo_title_template', 'rankingcoach_seo_title'],
            'description' => ['rankingcoach_seo_description_template', 'rankingcoach_seo_description'],
            'social_title' => ['rankingcoach_social_title', 'rankingcoach_og_title', 'rankingcoach_twitter_title'],
            'social_description' => ['rankingcoach_social_description', 'rankingcoach_og_description', 'rankingcoach_twitter_description'],
            'keywords' => ['rankingcoach_primary_keyword', 'rankingcoach_secondary_keywords'],
        ];
        if ($storage->read($postId, $type) !== [] || array_filter($legacy[$type], fn(string $key): bool => $this->destinationHasMeta($postId, $key)) !== []) {
            return ['skipped' => true];
        }
        if ($type === 'keywords') {
            if (!is_string($value) || str_starts_with(trim($value), '{') || str_starts_with(trim($value), '[')) {
                return ['skipped' => true];
            }
            $keywords = array_values(array_filter(array_map('trim', explode(',', $value))));
            $template = wp_json_encode([
                'primaryKeyword' => sanitize_text_field(array_shift($keywords) ?? ''),
                'additionalKeywords' => array_map('sanitize_text_field', $keywords),
            ]);
        } else {
            if (!is_string($value)) {
                return ['skipped' => true];
            }
            $template = $this->template($value, $tokens, in_array($type, ['description', 'social_description'], true));
            if ($template === null) {
                return ['skipped' => true];
            }
        }
        return ['value' => $template];
    }

    private function destinationHasMeta(int $postId, string $key): bool
    {
        global $wpdb;
        $previous = $wpdb->suppress_errors(true);
        try {
            $wpdb->last_error = '';
            $exists = metadata_exists('post', $postId, $key);
            if ($wpdb->last_error !== '') {
                throw new RuntimeException('Unable to read destination post meta');
            }
            return $exists;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    private function template(string $value, array $tokens, bool $isTextArea): ?string
    {
        $parts = preg_split('/(' . implode('|', array_map(static fn(string $token): string => preg_quote($token, '/'), array_keys($tokens))) . ')/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        $template = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (isset($tokens[$part])) {
                $template[] = ['type' => 'variable', 'key' => $tokens[$part]];
            } else {
                if (preg_match('/%%|%[a-zA-Z_][^%\r\n]*%|#[a-zA-Z_][a-zA-Z0-9_]*/', $part)) {
                    return null;
                }
                $template[] = ['type' => 'text', 'content' => $part];
            }
        }
        return $template === [] ? null : WordpressHelpers::sanitizeTemplateInput($template, $isTextArea);
    }
}
