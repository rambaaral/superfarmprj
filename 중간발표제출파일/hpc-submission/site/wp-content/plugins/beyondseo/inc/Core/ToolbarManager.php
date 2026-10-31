<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\ExternalLinks;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Traits\SingletonTrait;

/**
 * Class ToolbarManager
 *
 * This class is responsible for managing the WordPress admin toolbar.
 */
class ToolbarManager
{
    use SingletonTrait;

    /**
     * Initialize the toolbar manager.
     */
    public function init(): void
    {
        add_action('admin_bar_menu', [$this, 'add_toolbar_items'], 100);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_toolbar_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_toolbar_assets']);
    }

    /**
     * Enqueue toolbar-specific assets (inline CSS for admin bar items).
     */
    public function enqueue_toolbar_assets(): void
    {
        if (!is_admin_bar_showing()) {
            return;
        }

        $toolbar_css = '
            #wpadminbar .rc-assistant-toolbar .ab-label { font-weight:600; padding-left:2px; }
            #wpadminbar .rc-assistant-toolbar > .ab-item { white-space:nowrap; }
            #wpadminbar .rc-toolbar-icon img { display:inline-block !important; height:16px !important; width:16px !important; max-width:none !important; vertical-align:middle !important; margin-right:4px; }
            #wpadminbar .rc-toolbar-badge { display:inline-flex !important; align-items:center !important; justify-content:center !important; height:18px !important; min-width:18px !important; margin-left:6px !important; padding:0 6px !important; border-radius:999px !important; background-color:#666 !important; font-size:11px !important; font-weight:600 !important; line-height:1 !important; color:#fff !important; box-sizing:border-box !important; vertical-align:middle !important; margin-top:-4px; }
            #wpadminbar .rc-badge-update { background-color:#31a903 !important; cursor:pointer !important; }
            #wpadminbar .rc-badge-activation { background-color:#d63638 !important; width:18px !important; padding:0 !important; font-size:11px !important; border-radius:50% !important; }
        ';
        wp_add_inline_style('admin-bar', $toolbar_css);

        // Badge update removal script -- only needed on plugins page where updates happen
        global $pagenow;
        if ($pagenow === 'plugins.php') {
            $badge_script = '(function($){$(document).on("wp-plugin-update-success",function(){$(".rc-toolbar-badge.rc-badge-update").remove();});})(jQuery);';
            wp_add_inline_script('jquery', $badge_script);
        }
    }

    /**
     * Check if a new version of this plugin is available.
     */
    private function has_plugin_update(): bool
    {
        if (!function_exists('get_site_transient') || !function_exists('plugin_basename')) {
            return false;
        }
        if (!current_user_can('update_plugins')) {
            return false;
        }

        $plugin_file = plugin_basename(dirname(__DIR__, 2) . '/beyondseo.php');

        $updates = get_site_transient('update_plugins');
        if (!is_object($updates) || !isset($updates->response) || !is_array($updates->response)) {
            return false;
        }

        return isset($updates->response[$plugin_file]);
    }

    /**
     * Add toolbar items to the admin bar.
     *
     * @param $admin_bar
     */
    public function add_toolbar_items($admin_bar): void
    {
        // Update badge
        $updateBadge = '';
        if ($this->has_plugin_update()) {
            $updateBadge = sprintf(
                '<span class="rc-toolbar-badge rc-badge-update" 
                    title="%s" 
                    onclick="event.stopPropagation();event.preventDefault();window.location.href=\'' . esc_url(admin_url('plugins.php')) . '\';">
                    %s
                </span>',
                esc_attr__('Go to updates', 'beyondseo'),
                esc_html__('Update', 'beyondseo')
            );
        }

        // Activation badge
        $activationBadge = '';
        if (!WordpressHelpers::isActivationCompleted()) {
            $activationBadge = '<span class="rc-toolbar-badge rc-badge-activation">1</span>';
        }

        // Parent node
        $admin_bar->add_menu([
            'id'    => 'rc-assistant',
            'title' =>
                '<span class="rc-toolbar-icon">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYiIGhlaWdo
                    dD0iMTYiIHZpZXdCb3g9IjAgMCAxNiAxNiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0
                    dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBmaWxsLXJ1bGU9ImV2ZW5v
                    ZGQiIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTMuNTY0MzIgNi4yMjU2NUw4LjY0
                    NTY1IDAuNDYxNzVDOC43MTM5OCAwLjM4NDQ2NyA4Ljg1NDgzIDAuMzA4NjkgOC45
                    NTY0MyAwLjI5MzUxMUM4Ljk1NjQzIDAuMjkzNTExIDEzLjIyODcgLTAuMzg1MzUx
                    IDEzLjg1MjYgMC4zMjE3ODNDMTQuNDc2MiAxLjAyODggMTQuNDg4MSAxLjgxNzk3
                    IDE0LjI4MjQgMi4yODA2MkMxNC4xMDA4IDIuNjg4ODIgMTIuNTIxMiA2LjEyMzEx
                    IDEyLjEzMjcgNi45ODMxOUMxMy4xNDkxIDcuNzAzODggMTQuMDc0NCA4Ljg4ODcz
                    IDE0LjI4MjQgMTAuMTY2NkMxNC43MTMyIDEyLjgxMDQgMTMuNTMzNiAxNi4zMzkz
                    IDcuNzU5NTEgMTUuOTczN0M1LjY4MTYgMTUuOTczNyAyLjA1NDcxIDE1LjIzNjcg
                    MS42NDkwNCAxMi4wNzc3QzEuMjQzOTYgOC45MTg4NiAzLjU2NDMyIDYuMjI1NjUg
                    My41NjQzMiA2LjIyNTY1Wk0xMC45NTgyIDE0Ljg1NzFDMTEuOTYwMSAxNC44NTcx
                    IDEzLjgwMjQgMTMuNDc4MiAxMy43MzcgMTAuODM2N0MxMy42NzE1IDguMTk2MDkg
                    MTEuMzIyNCA3LjExODk3IDExLjMyMjQgNy4xMTg5N0wxMy43NjcgMS43ODc2OEwx
                    My44MDkxIDEuNjk0ODIgMTMuODMxMyAxLjUxNjE3IDEzLjgxMzUgMS40MTY3MkMx
                    My43NzgzIDEuMjIzMTMgMTMuNjU0OCAwLjg5NTAwMiAxMy41NDkxIDAuNzQ3NzI4
                    QzEzLjQwNjUgMC41NDg1ODMgMTMuMTA4OCAwLjQ4MjcwMyAxMi45NDIzIDAuNzM4
                    OTI4QzEyLjk0MjMgMC43Mzg5MjggOC4yOTI0MSA3LjY1OTIxIDguMjkyNDEgMTAu
                    OTM1QzguMjkyNTMgMTQuMjExMSA5Ljk1NjM3IDE0Ljg1NzEgMTAuOTU4MiAxNC44
                    NTcxWk05LjcwNjg4IDEuODQ4MTlIOC43OTYwOUM4LjY5NTkyIDEuODQ4MTkgOC42
                    NjM3IDEuNzg3OTUgOC43MjM1NiAxLjcxMzY1TDkuMTM0MjIgMS4yMDM4OUM5LjE3
                    MzQ3IDEuMTU1MTYgOS4yNTk2NyAxLjEwOTQ2IDkuMzI1NzYgMS4xMDMwMUM5LjMy
                    NTc2IDEuMTAzMDEgOS42NjIxNSAxLjA2ODg5IDEwLjExNzcgMS4wMzQ0NkMxMC41
                    NzMzIDEuMDAwMDIgMTEuMjMyNSAxLjAyNzA1IDExLjIzMjUgMS4wMjcwNUMxMS4z
                    NjU2IDEuMDMwODMgMTEuNDE2NCAxLjExOTU1IDExLjM0NjUgMS4yMjQ1MkwxMC45
                    NzIzIDEuNzg2N0MxMC45Mzc2IDEuODM4OCAxMC44NTU0IDEuODc5MjQgMTAuNzg5
                    IDEuODc2NTlD
                    MTAuNzg5IDEuODc2NTkgOS44NjM1NCAxLjg0ODE5IDkuNzA2ODggMS44NDgxOVoi
                    IGZpbGw9IiNBN0FBQUQiLz48L3N2Zz4=" 
                    alt="rC SEO" />
                 </span>
                 <span class="ab-label">SEO</span>'
                . $updateBadge
                . $activationBadge,
            'href'  => AdminManager::getPageUrl(AdminManager::PAGE_MAIN),
            'meta'  => [
                'title' => sprintf(
                // translators: %s is the brand/plugin name displayed in the menu title.
                    __( 'Menu %s', 'beyondseo' ),
                    RANKINGCOACH_BRAND_NAME
                ),
                'class' => 'rc-assistant-toolbar',
            ],
        ]);

        // Submenu: Settings
        $admin_bar->add_menu([
            'id'     => 'rc-assistant-settings',
            'parent' => 'rc-assistant',
            'title'  => __('Settings', 'beyondseo'),
            'href'   => AdminManager::getPageUrl(AdminManager::PAGE_GENERAL_SETTINGS),
        ]);

        // Submenu: Support
        $admin_bar->add_menu([
            'id'     => 'rc-assistant-support',
            'parent' => 'rc-assistant',
            'title'  => __('Support', 'beyondseo'),
            'href'   => ExternalLinks::getSupportUrl(),
            'meta'   => [
                'target' => '_blank',
            ],
        ]);

        // Submenu: Connect
        if (!CoreHelper::isOnboarded()) {
            $admin_bar->add_menu([
                'id'     => 'rc-assistant-upsell',
                'parent' => 'rc-assistant',
                'title'  => __( 'Connect', 'beyondseo' ),
                'href'   => AdminManager::getPageUrl(AdminManager::PAGE_UPSELL),
            ]);
        }

        // Submenu: Upgrade plan
        if (CoreHelper::hasUpgradePlans()) {
            $admin_bar->add_menu([
                'id'     => 'rc-assistant-upsell',
                'parent' => 'rc-assistant',
                'title'  => __( 'Upgrade', 'beyondseo' ),
                'href'   => AdminManager::getPageUrl(AdminManager::PAGE_UPSELL),
            ]);
        }
    }
}
