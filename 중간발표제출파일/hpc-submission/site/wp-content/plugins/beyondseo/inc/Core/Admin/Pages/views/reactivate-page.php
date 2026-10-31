<?php
use RankingCoach\Inc\Core\Admin\AdminManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap rc-activation-wrap">
    <div style="text-align:center;margin-bottom:24px;">
        <img src="<?php echo esc_url(plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/icons/beyondSEO-logo.svg'); ?>"
             style="width:240px;height:auto;">
    </div>

    <h1><?php esc_html_e('Authentication Required', 'beyondseo'); ?></h1>
    <p><?php esc_html_e("Your session has expired or the activation is no longer valid. Please reactivate your account to continue.", 'beyondseo'); ?></p>
    
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('rc_reset_and_reactivate'); ?>
        <input type="hidden" name="action" value="rc_reset_and_reactivate">
        <button type="submit" class="rc-activation-button">
            <?php esc_html_e('Reset and Reactivate', 'beyondseo'); ?>
        </button>
    </form>

    <p style="font-size:14px;color:#777;margin-top:30px;">
        <?php esc_html_e('Need help? Contact our support team for assistance.', 'beyondseo'); ?>
    </p>
</div>
