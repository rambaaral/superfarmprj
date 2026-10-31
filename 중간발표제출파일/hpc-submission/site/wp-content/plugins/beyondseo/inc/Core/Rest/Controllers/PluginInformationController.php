<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rest\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

class PluginInformationController
{
    use RcApiTrait;
    use RcLoggerTrait;

    public function info(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $installedAtOpt = get_option(BaseConstants::OPTION_INSTALLATION_DATE, null);
            $installedAt = (string)($installedAtOpt ?? time());

            if (is_numeric($installedAt)) {
                $installedDateTime = gmdate('Y-m-d H:i:s', (int)$installedAt);
            } elseif (strtotime($installedAt) !== false) {
                $installedDateTime = gmdate('Y-m-d H:i:s', strtotime($installedAt));
            } else {
                $installedDateTime = gmdate('Y-m-d H:i:s');
            }

            $siteUrl = get_site_url();
            $homeUrl = get_home_url();
            $theme = wp_get_theme();

            $settings = get_option(BaseConstants::OPTION_PLUGIN_SETTINGS, []);
            if (!is_array($settings)) {
                $settings = [];
            }

            $isPluginOnboarded = get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP, false) == true &&
                !empty(get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE, null));
            $lastPluginUpdate = get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE, null);
            if ($lastPluginUpdate !== null) {
                $lastPluginUpdate = (int)$lastPluginUpdate;
            }

            $isApplicationOnboarded = get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC, false) == true &&
                !empty(get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC_LAST_UPDATE, null));
            $lastApplicationUpdate = get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC_LAST_UPDATE, null);
            if ($lastApplicationUpdate !== null) {
                $lastApplicationUpdate = (int)$lastApplicationUpdate;
            }

            $setupData = [
                'isPluginOnboarded'      => $isPluginOnboarded,
                'lastPluginUpdate'       => $lastPluginUpdate,
                'isApplicationOnboarded' => $isApplicationOnboarded,
                'lastApplicationUpdate'  => $lastApplicationUpdate,
                'account'                => null,
                'objectType'             => 'App\\Domain\\Integrations\\WordPress\\Setup\\Entities\\WPSetup',
            ];

            $parsedUrl = wp_parse_url($siteUrl);
            $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
            $needsWww = str_contains(substr($host, 0, 4), 'www.');
            $domainName = $needsWww ? substr($host, 4) : $host;
            $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
            if ($path === '/') {
                $path = '';
            }
            if (strlen($path) > 1 && $path[0] !== '/') {
                $path = '/' . $path;
            }

            $domain = [
                'name'                 => $domainName,
                'path'                 => $path,
                'needsWww'             => $needsWww,
                'needsHttps'           => is_ssl(),
                'excludePathInPageUrl' => false,
                'id'                   => null,
                'objectType'           => 'App\\Domain\\Seo\\Entities\\Domains\\Domain',
            ];

            $cmsType = [
                'name'       => 'WordPress',
                'objectType' => 'App\\Domain\\Seo\\Entities\\CMSTypes\\CMSType',
            ];

            $allowedCountries = [];
            if (isset($settings['allowed_countries'])) {
                $allowedCountries = (array)$settings['allowed_countries'];
            }

            $websiteSettings = [
                'siteUrl'            => $siteUrl,
                'homeUrl'            => $homeUrl,
                'blogName'           => get_bloginfo('name'),
                'blogDescription'    => get_bloginfo('description'),
                'adminEmail'         => get_bloginfo('admin_email'),
                'siteLanguage'       => get_locale(),
                'isMultisite'        => is_multisite(),
                'activePlugins'      => json_encode(get_option('active_plugins') ?? ''),
                'stylesheet'         => get_stylesheet(),
                'theme'              => $theme->get('Name'),
                'themeVersion'       => $theme->get('Version'),
                'themeAuthor'        => $theme->get('Author'),
                'permalinkStructure' => get_option('permalink_structure'),
                'allowedCountries'   => $allowedCountries,
                'objectType'         => 'App\\Domain\\Integrations\\WordPress\\Seo\\Entities\\Websites\\WPWebsiteSetting',
            ];

            $website = [
                'settings'   => $websiteSettings,
                'database'   => null,
                'domain'     => $domain,
                'cmsType'    => $cmsType,
                'id'         => null,
                'objectType' => 'App\\Domain\\Integrations\\WordPress\\Seo\\Entities\\Websites\\WPWebsite',
            ];

            $pluginData = [
                'version'           => defined('RANKINGCOACH_VERSION') ? RANKINGCOACH_VERSION : (get_option(BaseConstants::OPTION_PLUGIN_VERSION) ?: ''),
                'settings'          => (object)$settings,
                'website'           => $website,
                'setupData'         => $setupData,
                'installedAt'       => $installedAt,
                'installedDateTime' => $installedDateTime,
                'installationHash'  => get_option(BaseConstants::OPTION_INSTALLATION_ID, null),
                'debugData'         => null,
                'objectType'        => 'App\\Domain\\Integrations\\WordPress\\Plugin\\Entities\\WPPlugin',
            ];

            $subscription = get_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION, null);
            switch ($subscription) {
                case 'seo_wp_free':
                case 'radar_wp_test':
                case 'ventraip_wp_free':
                    $rcSubscriptionName = 'Free';
                    break;
                case 'seo_ai_small':
                    $rcSubscriptionName = 'Standard';
                    break;
                case 'seo_ai_medium':
                case 'seo_ai_medium2025':
                    $rcSubscriptionName = 'Advanced';
                    break;
                case 'seo_ai_large':
                case 'seo_wp_pro':
                    $rcSubscriptionName = 'Pro';
                    break;
                case 'seo_ai_social':
                case 'seo_wp_social':
                    $rcSubscriptionName = 'Social';
                    break;
                case 'annual_360':
                case 'monthly_360':
                case '360_wp_test':
                case '360_wp_test_annual':
                    $rcSubscriptionName = '360';
                    break;
                case 'monthly_360_eu':
                case 'annual_360_eu':
                case 'monthly_360_int':
                case 'annual_360_int':
                case 'monthly_360_us':
                case 'annual_360_us':
                    $rcSubscriptionName = '360';
                    break;
                default:
                    $rcSubscriptionName = 'Free';
                    break;
            }

            $rcRemainingKeywords = (int)get_option(BaseConstants::OPTION_RANKINGCOACH_MAX_ALLOWED_KEYWORDS, 10);

            $data = [
                'pluginData'          => $pluginData,
                'userData'            => null,
                'rcAccountId'         => (int)get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID),
                'rcProjectId'         => (int)get_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID),
                'rcSubscriptionName'  => $rcSubscriptionName,
                'rcRemainingKeywords' => $rcRemainingKeywords,
            ];

            return new WP_REST_Response($data, 200);
        } catch (Throwable $e) {
            $this->log('Error in PluginInformationController::info: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }
}
