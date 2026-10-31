<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\AutoSetup\Onboarding\Collectors;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

class WordPressDataCollector extends AbstractCollector
{
    public string $collector = 'WordPress';

    public function businessWebsiteUrl(): string
    {
        $siteUrl = sanitize_url(get_option('siteurl'));
        if (wp_get_environment_type() !== 'production' && wp_get_environment_type() !== 'staging') {
            return RANKINGCOACH_COMMON_DEV_ENVIRONMENT_HOST ?? $siteUrl;
        }

        $url = get_site_url();
        if (WordpressHelpers::isLocalhostUrl($url)) {
            return preg_replace('#^http://#', 'https://', RANKINGCOACH_PRODUCTION_ENVIRONMENT_HOST);
        }
        return preg_replace('#^http://#', 'https://', $url);
    }

    public function businessEmailAddress(): string
    {
        $adminEmail = get_option('admin_email');
        if (wp_get_environment_type() !== 'production' && empty($adminEmail)) {
            return RANKINGCOACH_COMMON_DEV_ENVIRONMENT_EMAIL ?? ('admin@' . wp_parse_url(get_site_url(), PHP_URL_HOST));
        }
        return $adminEmail;
    }
}
