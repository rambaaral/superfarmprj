<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Import;

if (!defined('ABSPATH')) {
    exit;
}

final class YoastImporter
{
    private const POST_FIELDS = [
        '_yoast_wpseo_title' => 'title', '_yoast_wpseo_metadesc' => 'description',
        '_yoast_wpseo_focuskw' => 'keywords', '_yoast_wpseo_canonical' => 'canonical',
        '_yoast_wpseo_meta-robots-noindex' => 'noindex',
        '_yoast_wpseo_opengraph-title' => 'social_title',
        '_yoast_wpseo_opengraph-description' => 'social_description',
    ];

    public function name(): string
    {
        return 'Yoast SEO';
    }

    public function files(): array
    {
        return ['wordpress-seo/wp-seo.php', 'wordpress-seo-premium/wp-seo-premium.php'];
    }

    public function tokens(): array
    {
        return ['%%title%%' => 'post_title', '%%sitename%%' => 'site_title'];
    }

    public function postFields(): array
    {
        return self::POST_FIELDS;
    }

    public function settingKeys(): array
    {
        return [
            'organization_social_facebook', 'organization_social_instagram',
            'organization_social_linkedin', 'organization_social_youtube',
            'google_verification_code', 'bing_verification_code',
        ];
    }

    public function settings(array $options = []): array
    {
        $selected = array_flip($options === [] ? $this->settingKeys() : array_intersect($this->settingKeys(), $options));
        $values = [];
        $socialFields = array_filter(
            ['facebook_site' => 'facebook', 'instagram_url' => 'instagram', 'linkedin_url' => 'linkedin', 'youtube_url' => 'youtube'],
            static fn(string $type): bool => isset($selected['organization_social_' . $type])
        );
        if ($socialFields !== []) {
            $social = ImportRunner::readSourceOption('wpseo_social');
            if (is_array($social)) {
                foreach ($socialFields as $from => $to) {
                    $values['organization_social_' . $to] = $social[$from] ?? null;
                }
            }
        }
        if (isset($selected['google_verification_code']) || isset($selected['bing_verification_code'])) {
            $sourceOptions = ImportRunner::readSourceOption('wpseo');
            if (is_array($sourceOptions)) {
                if (isset($selected['google_verification_code'])) {
                    $values['google_verification_code'] = $sourceOptions['googleverify'] ?? null;
                }
                if (isset($selected['bing_verification_code'])) {
                    $values['bing_verification_code'] = $sourceOptions['msverify'] ?? null;
                }
            }
        }
        return $values;
    }

    public function importPostMeta(ImportRunner $runner, array &$result, array $options = []): void
    {
        $options = $runner->normalizeOptions('yoast', $options);
        $fields = array_filter(self::POST_FIELDS, static fn(string $type): bool => in_array($type, $options, true));
        $runner->importMappedPostMeta($fields, $this->tokens(), $result);
    }
}
