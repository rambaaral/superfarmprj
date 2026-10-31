<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Review\ReviewState;
use Throwable;
use WP_Screen;

/**
 * Class UpsellNotificationManager
 *
 * This class is responsible for managing upsell notifications across the plugin.
 * It provides methods to create and display upsell notifications in different contexts.
 */
class UpsellNotificationManager
{
    use RcLoggerTrait;

    /**
     * Singleton instance of NotificationManager.
     *
     * @var UpsellNotificationManager|null
     */
    private static ?UpsellNotificationManager $instance = null;

    /**
     * WordPress admin screen IDs for post and page list screens.
     */
    private const SCREEN_EDIT_POST = 'edit-post';
    private const SCREEN_EDIT_PAGE = 'edit-page';

    /**
     * Constructor notification manager.
     *
     * @return self
     */
    public function __construct() {
        // Initialize the notification manager
        $this->init();
        return $this;
    }

    /**
     * Returns the singleton instance of UpsellNotificationManager.
     * @return UpsellNotificationManager|null
     */
    public static function instance(): ?UpsellNotificationManager {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the upsell notification manager.
     */
    public function init(): void
    {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueStyles' ] );

        // Hook into WordPress actions to display notifications on specific screens
        if(WordpressHelpers::isOnboardingCompleted()) {

            if(!CoreHelper::hasUpgradePlans()) {
                return;
            }

            add_action('current_screen', [$this, 'maybeDisplayScreenNotifications']);
        }
    }

    /**
     * Check the current screen and display appropriate notifications.
     * 
     * @param WP_Screen $current_screen The current WordPress admin screen.
     * @return void
     */
    public function maybeDisplayScreenNotifications(WP_Screen $current_screen): void
    {
        // Display notifications based on the current screen ID
        switch ($current_screen->id) {
            case self::SCREEN_EDIT_POST:
                $this->postListNotification();
                break;
            case self::SCREEN_EDIT_PAGE:
                $this->pageListNotification();
                break;
        }
    }

    /**
     * Create a notification to upsell the plugin to a NEW version.
     *
     * @param string $notification_id The ID for the notification (for dismissal tracking)
     * @param string|null $screen Optional screen context to display the notification on
     * @return void
     */
    public function upsellNotification(string $notification_id = 'rankingcoach-pro-upsell', ?string $screen = Notification::SCREEN_DASHBOARD): void
    {
        // Check if the notification has been dismissed
        $notificationManager = NotificationManager::instance();

        if (!$notificationManager->has_notification($notification_id)) {
            // Build HTML without whitespace between tags to prevent wpautop from adding empty paragraphs
            $html = '<div class="rankingcoach-connect-notice">';
            $html .= '<div class="rankingcoach-connect-content">';
            $html .= '<h4>' . esc_html__('They show up everywhere. You can too.', 'beyondseo') . '</h4>';
            $html .= '<p>' . esc_html__('Your competitors are everywhere, not just on a website. Without multi-channel visibility, you`re losing customers. Upgrade now to stand out where it counts: Google, directories, social platforms, and reviews.', 'beyondseo') . '</p>';
            $html .= '<div class="rankingcoach-connect-actions">';
            $html .= '<a href="' . AdminManager::getPageUrl(AdminManager::PAGE_UPSELL ) . '" class="button button-primary">';
            $html .= esc_html__('Boost my visibility', 'beyondseo');
            $html .= '</a>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

            // Styles are added via wp_add_inline_style
            $message = $html;

            // Add the notification with raw HTML flag to prevent WordPress from processing it
            $notificationManager->add(
                $message,
                [
                    'id' => $notification_id,
                    'type' => Notification::INFO,
                    'screen' => $screen,
                    'dismissible' => true,
                    'persistent' => true,
                    'raw_html' => true, // Add this flag if NotificationManager supports it
                ]
            );
        }
    }

    /**
     * Get the upsell widget content HTML.
     *
     * @return void
     */
    public function overviewDashboardWidget(): void
    {
        // Get website analysis data from WordPress options with defaults
        $websiteScore = get_option(BaseConstants::OPTION_ANALYSIS_WEBSITE_SCORE_AVERAGE, 0);
        $pagesCount = get_option(BaseConstants::OPTION_ANALYSIS_WEBSITE_PAGES_COUNT, CoreHelper::getPublicPostsAndPagesCount());
        $scoreMin = get_option(BaseConstants::OPTION_ANALYSIS_SCORE_MIN, 0);
        $scoreMax = get_option(BaseConstants::OPTION_ANALYSIS_SCORE_MAX, 0);
        $reviewGreen = false;
        try {
            $reviewGreen = (new ReviewState())->isValidSiteScore([
                'average_score' => $websiteScore,
                'count' => get_option(BaseConstants::OPTION_ANALYSIS_WEBSITE_PAGES_COUNT, false),
                'min_score' => get_option(BaseConstants::OPTION_ANALYSIS_SCORE_MIN, false),
                'max_score' => $scoreMax,
            ]) && (float) $websiteScore > ReviewState::DASHBOARD_SCORE_THRESHOLD && (float) $scoreMax > 0;
        } catch (Throwable $e) {
        }

        // Calculate score percentage for progress bar
        $scorePercentage = $scoreMax > 0 ? min(100, max(0, ($websiteScore / 100) * 100)) : 0;

        // Determine score color based on value
        $scoreColor = '#28a745'; // Default green
        if ($scorePercentage < 30) {
            $scoreColor = '#dc3545'; // Red for low scores
        } elseif ($scorePercentage < 80) {
            $scoreColor = '#ffc107'; // Yellow/amber for medium scores
        }

        // Load the template with variables
        $template_path = RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Admin/Pages/views/dashboard-widget.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        }
    }

    /**
     * Create a notification for a specific admin page.
     *
     * @param string $page_slug The admin page slug
     * @param string $notification_id Custom notification ID
     * @return void
     */
    public function adminPageNotification(string $page_slug, string $notification_id = ''): void
    {
        if (empty($notification_id)) {
            $notification_id = 'rankingcoach-pro-upsell-' . $page_slug;
        }

        // Create notification for the specific admin page
        $this->upsellNotification($notification_id, $page_slug);
    }

    /**
     * Create a notification for the dashboard.
     *
     * @return void
     */
    public function getUpsellNotification(): void
    {
        $this->upsellNotification('rankingcoach-connect-dashboard', Notification::SCREEN_DASHBOARD);
    }

    /**
     * Create a notification for the post list screen.
     *
     * @return void
     */
    public function postListNotification(): void
    {
        $this->adminPageNotification(self::SCREEN_EDIT_POST);
    }

    /**
     * Create a notification for the page list screen.
     *
     * @return void
     */
    public function pageListNotification(): void
    {
        $this->adminPageNotification(self::SCREEN_EDIT_PAGE);
    }

    /**
     * Remove the dashboard upsell notification.
     *
     * @return void
     */
    public function removeUpsellNotification(): void
    {
        NotificationManager::instance()->remove_by_id('rankingcoach-connect-dashboard');
    }

    /**
     * Enqueue styles for the upsell notifications and dashboard widget.
     *
     * @return void
     */
    public function enqueueStyles(): void
    {
        $screen = get_current_screen();

        if ( ! $screen ) {
            return;
        }

        if ( 'dashboard' === $screen->id ) {
            // Enqueue dashboard widget CSS file
            wp_enqueue_style(
                'rankingcoach-dashboard-widget',
                RANKINGCOACH_PLUGIN_URL . 'inc/Core/Admin/assets/css/dashboard-widget.css',
                [],
                RANKINGCOACH_VERSION
            );
            
            // Enqueue dashboard widget JS file
            wp_enqueue_script(
                'rankingcoach-dashboard-widget',
                RANKINGCOACH_PLUGIN_URL . 'inc/Core/Admin/assets/js/dashboard-widget.js',
                ['jquery'],
                RANKINGCOACH_VERSION,
                true
            );
            
            // Localize script data
            wp_localize_script(
                'rankingcoach-dashboard-widget',
                'RankingCoachDashboardWidget',
                [
                    'restUrl' => rest_url(RANKINGCOACH_REST_APP_BASE . '/onboarding/scan'),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'scanningText' => __('Scanning...', 'beyondseo')
                ]
            );
            
            // Upsell notification CSS attached to the dashboard widget style handle
            wp_add_inline_style( 'rankingcoach-dashboard-widget', $this->getUpsellNotificationCss() );
        }

        if ( in_array( $screen->id, [ self::SCREEN_EDIT_POST, self::SCREEN_EDIT_PAGE ], true ) ) {
            wp_add_inline_style( 'rankingcoach-admin-style', $this->getUpsellNotificationCss() );
        }
    }



    /**
     * Get the CSS for the upsell notification.
     *
     * @return string
     */
    private function getUpsellNotificationCss(): string
    {
        return '
            .rankingcoach-connect-notice {
                display: flex;
                align-items: flex-start;
                gap: 15px;
                padding: 16px 10px;
            }
            .rankingcoach-connect-content {
                flex: 1;
                font-family: sans-serif;
                color: #333;
            } 
            .rankingcoach-connect-content h4 {
                margin: 0 0 12px;
                font-size: 20px;
                font-weight: 300;
            }
            .rankingcoach-connect-content p {
                margin: 8px 0 16px;
                line-height: 1.5;
            }
            .rankingcoach-connect-actions {
                margin-top: 12px;
            }';
    }
}
