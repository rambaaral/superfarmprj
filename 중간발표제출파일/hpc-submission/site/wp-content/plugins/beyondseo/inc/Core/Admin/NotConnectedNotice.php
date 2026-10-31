<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

/**
 * Class NotConnectedNotice
 *
 * Displays a dismissible warning admin notice on the Plugins page when the
 * plugin is not connected to an account yet (onboarding not completed),
 * linking to the connect page. The notice disappears on its own once the
 * site is connected, and can be dismissed permanently via the X button
 * (the dismissal is persisted in wp_options).
 */
class NotConnectedNotice
{
    /**
     * HTML id of the notice element, also used to scope the dismiss script.
     */
    private const NOTICE_ID = 'rankingcoach-not-connected-notice';

    /**
     * AJAX action (and nonce action) used to persist the dismissal.
     */
    private const DISMISS_ACTION = 'rankingcoach_dismiss_not_connected_notice';

    /**
     * Register the admin hooks.
     *
     * @return void
     */
    public function init(): void
    {
        add_action('admin_notices', [$this, 'renderNotice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueDismissScript']);
        add_action('wp_ajax_' . self::DISMISS_ACTION, [$this, 'handleDismiss']);
    }

    /**
     * Whether the notice should be shown in the current request.
     *
     * @return bool
     */
    private function shouldShow(): bool
    {
        global $pagenow;

        if ('plugins.php' !== $pagenow) {
            return false;
        }

        if (!current_user_can('manage_options')) {
            return false;
        }

        if (get_option(BaseConstants::OPTION_NOT_CONNECTED_NOTICE_DISMISSED)) {
            return false;
        }

        return !CoreHelper::isOnboarded();
    }

    /**
     * Render the not-connected admin notice on the Plugins page.
     *
     * @return void
     */
    public function renderNotice(): void
    {
        if (!$this->shouldShow()) {
            return;
        }

        ?>
        <div id="<?php echo esc_attr(self::NOTICE_ID); ?>"
             class="notice notice-warning is-dismissible"
             data-security="<?php echo esc_attr(wp_create_nonce(self::DISMISS_ACTION)); ?>">
            <p>
                <?php
                printf(
                    /* translators: 1: plugin brand name wrapped in bold tags, 2: opening anchor tag linking to the connect page, 3: closing anchor tag */
                    esc_html__('Your site is not connected to %1$s. %2$sConnect now%3$s to start optimizing your SEO and get found online.', 'beyondseo'),
                    '<b>' . esc_html(RANKINGCOACH_BRAND_NAME) . '</b>',
                    '<a href="' . esc_url(AdminManager::getPageUrl(AdminManager::PAGE_UPSELL)) . '">',
                    '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Enqueue the inline script that persists the dismissal when the X is clicked.
     *
     * @return void
     */
    public function enqueueDismissScript(): void
    {
        if (!$this->shouldShow()) {
            return;
        }

        $script = "
            ;(function($) {
                $( '#" . self::NOTICE_ID . "' ).on( 'click', '.notice-dismiss', function() {
                    let notice = $( this ).parent();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: '" . self::DISMISS_ACTION . "',
                            security: notice.data('security') || ''
                        },
                        error: function() {
                            console.log('Error dismissing notification');
                        }
                    });
                });
            })(jQuery);";

        wp_register_script('rankingcoach-not-connected-notice-dismiss', '', ['jquery'], RANKINGCOACH_VERSION, true);
        wp_enqueue_script('rankingcoach-not-connected-notice-dismiss');
        wp_add_inline_script('rankingcoach-not-connected-notice-dismiss', $script);
    }

    /**
     * AJAX handler: persist the dismissal so the notice is not shown again.
     *
     * @return void
     */
    public function handleDismiss(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have sufficient permissions to perform this action.', 'beyondseo'),
            ]);
            return;
        }

        $nonce = WordpressHelpers::sanitize_input('POST', 'security');

        if (!wp_verify_nonce($nonce, self::DISMISS_ACTION)) {
            wp_send_json_error([
                'message' => __('Security check failed. Please try again.', 'beyondseo'),
            ]);
            return;
        }

        update_option(BaseConstants::OPTION_NOT_CONNECTED_NOTICE_DISMISSED, time(), false);

        wp_send_json_success([
            'message' => __('Notification dismissed successfully.', 'beyondseo'),
        ]);
    }
}
