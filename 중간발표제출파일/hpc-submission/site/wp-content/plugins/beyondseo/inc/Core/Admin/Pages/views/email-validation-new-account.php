<?php
/**
 * Email Validation View - New Account
 * 
 * This view is displayed when a new account is being created.
 * It shows a spinner and polling message while waiting for email verification.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="rc-email-validation-new-account" style="display:none;margin-top:12px;">
    <div style="margin-top:16px;padding:12px;background:#fffbf0;border-left:4px solid #f0b849;">
        <p style="margin:0 0 8px 0;font-weight:600;color:#8a6d3b;">
            <?php esc_html_e('New Account Setup', 'beyondseo'); ?>
        </p>
        <p style="margin:0;font-size:13px;color:#666;">
            <?php esc_html_e('As this is a new account, the verification process may take a few moments. We\'re setting up your workspace and preparing everything for you.', 'beyondseo'); ?>
        </p>
    </div>

    <div style="background:#f0f6ff;border-left:4px solid #0073aa;padding:16px;margin-bottom:16px;">
        <p style="margin:0 0 8px 0;color:#333;">
            <?php esc_html_e('We\'ve sent a confirmation email to verify your address.', 'beyondseo'); ?>
        </p>
        <p style="margin:0;color:#555;font-size:14px;">
            <?php esc_html_e('Please check your inbox and click the verification link to complete your registration.', 'beyondseo'); ?>
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