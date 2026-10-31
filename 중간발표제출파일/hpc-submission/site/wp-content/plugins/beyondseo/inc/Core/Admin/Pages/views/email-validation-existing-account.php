<?php
/**
 * Email Validation View - Existing Account
 * 
 * This view is displayed when an existing account is being reactivated.
 * It shows a spinner and polling message while waiting for email verification.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="rc-email-validation-existing-account" style="display:none;margin-top:12px;">
    <div style="margin-top:16px;padding:12px;background:#f0f9ff;border-left:4px solid #0284c7;">
        <p style="margin:0 0 8px 0;font-weight:600;color:#075985;">
            <?php esc_html_e('Existing Account Connection', 'beyondseo'); ?>
        </p>
        <p style="margin:0;font-size:13px;color:#666;">
            <?php esc_html_e('We found an existing account with this email. The verification will quickly reconnect your account and restore your previous settings and data.', 'beyondseo'); ?>
        </p>
    </div>

    <div style="background:#f0fff4;border-left:4px solid #059669;padding:16px;margin-bottom:16px;">
        <p style="margin:0 0 8px 0;color:#333;">
            <?php esc_html_e('We\'ve sent a verification email to confirm your identity.', 'beyondseo'); ?>
        </p>
        <p style="margin:0;color:#555;font-size:14px;">
            <?php esc_html_e('Please check your inbox and click the verification link to reconnect your existing account.', 'beyondseo'); ?>
        </p>
    </div>

    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#fff;border:1px solid #ddd;border-radius:4px;">
        <span class="spinner is-active" style="float:none;visibility:visible;margin:0;"></span>
        <div>
            <strong class="rc-spinner-label" style="display:block;margin-bottom:4px;">
                <?php esc_html_e('Waiting for email verification…', 'beyondseo'); ?>
            </strong>
            <span style="font-size:13px;color:#666;">
                <?php esc_html_e('This page will automatically update once your email is verified.', 'beyondseo'); ?>
            </span>
        </div>
    </div>
</div>