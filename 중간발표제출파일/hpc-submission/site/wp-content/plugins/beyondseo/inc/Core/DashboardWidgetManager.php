<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Traits\SingletonTrait;

/**
 * Class DashboardWidgetManager
 *
 * This class is responsible for managing the dashboard widget.
 */
class DashboardWidgetManager
{
    use SingletonTrait;

    /**
     * Initialize the dashboard widget manager.
     */
    public function init(): void
    {
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }

    /**
     * Add a dashboard widget.
     */
    public function add_dashboard_widget(): void
    {
        try {
            if (get_current_screen()?->id === 'dashboard') {
                ReactApp::get(['dashboardReview']);
            }
        } catch (\Throwable $e) {
        }

        wp_add_dashboard_widget(
            BaseConstants::OPTION_UPSELL_WIDGET_ID,
             // translators: %s is the brand name of the plugin
             sprintf(esc_html__('%s Overview', 'beyondseo'), RANKINGCOACH_BRAND_NAME),
            [$this, 'display_dashboard_widget']
        );
    }

    /**
     * Display the dashboard widget content.
     */
    public function display_dashboard_widget(): void
    {
        UpsellNotificationManager::instance()->overviewDashboardWidget();
    }
}
