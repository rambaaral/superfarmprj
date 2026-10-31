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
use WP_Debug_Data;
use WP_REST_Request;
use WP_REST_Response;

class SupportController
{
    use RcApiTrait;
    use RcLoggerTrait;

    public function siteInfo(WP_REST_Request $request): WP_REST_Response
    {
        try {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/update.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/misc.php';
            require_once ABSPATH . 'wp-admin/includes/theme.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';

            WP_Debug_Data::check_for_updates();

            $info = WP_Debug_Data::debug_data();
            $wpSiteInfoText = WP_Debug_Data::format($info, 'debug');

            $siteInfo = $wpSiteInfoText . "\n\n" . $this->getRankingCoachOptionsText();

            return new WP_REST_Response(['siteInfo' => $siteInfo], 200);
        } catch (Throwable $e) {
            $this->log('Error in SupportController::siteInfo: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    private function getRankingCoachOptionsText(): string
    {
        global $wpdb;

        $excludedKeys = [
            BaseConstants::OPTION_ACCESS_TOKEN,
            BaseConstants::OPTION_REFRESH_TOKEN,
            BaseConstants::OPTION_APPLICATION_PASSWORD,
            BaseConstants::OPTION_SECURE_TOKEN,
        ];

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name ASC",
                $wpdb->esc_like('rankingcoach_') . '%'
            )
        );

        $lines = [];
        foreach ((array) $rows as $row) {
            if (in_array($row->option_name, $excludedKeys, true)) {
                continue;
            }

            $value = maybe_unserialize($row->option_value);
            if (is_array($value) || is_object($value)) {
                $value = wp_json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $value = '';
            } else {
                $value = (string) $value;
            }

            $lines[] = $row->option_name . ': ' . $value;
        }

        return "### rankingCoach Plugin Options ###\n\n" . implode("\n", $lines);
    }
}
