<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Admin\Pages\RegistrationPage;
use RankingCoach\Inc\Core\ChannelFlow\FlowState;
use RankingCoach\Inc\Core\Settings\SettingsManager;

/** @var RegistrationPage $this */
/** @var array|null $channelMeta Passed from controller via OptionStore::retrieveChannel() */
/** @var FlowState|null $flowState Passed from controller via OptionStore::retrieveFlowState() */
/** @var bool $inEmailValidation Computed in controller (RegistrationPage.php line 64) */
/** @var string|null $registrationStep from controller (optional) */
$beyondseo_registrationStep = isset($registrationStep) ? (string)$registrationStep : null;

// Defensive: only compute if not already set by controller
if (!isset($inEmailValidation)) {
    $beyondseo_inEmailValidation = false;
    if (isset($flowState) && $flowState instanceof FlowState) {
        $beyondseo_inEmailValidation = ($flowState->registered === true && $flowState->emailVerified === false);
    }
} else {
    $beyondseo_inEmailValidation = $inEmailValidation;
}

// Use channel metadata passed from controller (consistent with OnboardingPage pattern)
$beyondseo_registrationType = $channelMeta['channel'] ?? 'direct';

// Current WP user data
$beyondseo_user        = wp_get_current_user();
$beyondseo_first_name  = (string) get_user_meta($beyondseo_user->ID, 'first_name', true);
$beyondseo_last_name   = (string) get_user_meta($beyondseo_user->ID, 'last_name', true);
$beyondseo_first_name  = $beyondseo_first_name ?: (string) ($beyondseo_user->user_firstname ?? '');
$beyondseo_last_name   = $beyondseo_last_name ?: (string) ($beyondseo_user->user_lastname ?? '');
$beyondseo_email       = (string) ($beyondseo_user->user_email ?? get_bloginfo('admin_email'));
$beyondseo_rest_nonce  = wp_create_nonce('wp_rest');

// Determine default country from locale (e.g., en_US -> US)
$beyondseo_locale = (string) get_locale();
$beyondseo_defaultCountry = 'US';
if (str_contains($beyondseo_locale, '_')) {
    $beyondseo_parts = explode('_', $beyondseo_locale);
    if (!empty($beyondseo_parts[1])) {
        $beyondseo_defaultCountry = strtoupper($beyondseo_parts[1]);
    }
}

// Try to load countries from settings; if not >= 60, fallback to static 60-item list
$beyondseo_countries = [];
try {
    $beyondseo_settings  = SettingsManager::instance();
    $beyondseo_fromStore = $beyondseo_settings->allowed_countries ?? [];
    if (is_object($beyondseo_fromStore)) {
        $beyondseo_fromStore = (array) $beyondseo_fromStore;
    }
    if (is_array($beyondseo_fromStore)) {
        $beyondseo_countries = $beyondseo_fromStore;
    }
} catch (Throwable $e) {
    $beyondseo_countries = [];
}

if (count($beyondseo_countries) < 60) {
    // Fallback: placeholder list of exactly 60 countries to be replaced with official RC list
    $beyondseo_countries = [
        'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'IE' => 'Ireland',
        'DE' => 'Germany', 'AT' => 'Austria', 'CH' => 'Switzerland',
        'FR' => 'France', 'BE' => 'Belgium', 'NL' => 'Netherlands', 'LU' => 'Luxembourg', 'MC' => 'Monaco',
        'IT' => 'Italy', 'ES' => 'Spain', 'PT' => 'Portugal', 'AD' => 'Andorra',
        'DK' => 'Denmark', 'SE' => 'Sweden', 'NO' => 'Norway', 'FI' => 'Finland', 'IS' => 'Iceland',
        'PL' => 'Poland', 'CZ' => 'Czech Republic', 'SK' => 'Slovakia', 'HU' => 'Hungary',
        'RO' => 'Romania', 'BG' => 'Bulgaria', 'GR' => 'Greece',
        'EE' => 'Estonia', 'LV' => 'Latvia', 'LT' => 'Lithuania',
        'SI' => 'Slovenia', 'HR' => 'Croatia', 'RS' => 'Serbia', 'BA' => 'Bosnia and Herzegovina', 'MK' => 'North Macedonia', 'AL' => 'Albania',
        'TR' => 'Turkey', 'CY' => 'Cyprus', 'MT' => 'Malta',
        'AU' => 'Australia', 'NZ' => 'New Zealand',
        'MX' => 'Mexico', 'AR' => 'Argentina', 'BR' => 'Brazil', 'CL' => 'Chile', 'CO' => 'Colombia', 'PE' => 'Peru', 'UY' => 'Uruguay',
        'ZA' => 'South Africa', 'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'IL' => 'Israel',
        'IN' => 'India', 'ID' => 'Indonesia', 'MY' => 'Malaysia', 'PH' => 'Philippines', 'SG' => 'Singapore', 'TH' => 'Thailand', 'JP' => 'Japan',
    ];
}
asort($beyondseo_countries);
?>

<div class="wrap rc-registration-wrap" style="max-width: 760px; margin: 40px auto;">
    <div style="display:flex; align-items:center; justify-content:center; margin-bottom:24px;">
        <img
            src="<?php echo esc_url(plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/icons/beyondSEO-logo.svg'); ?>"
            alt="<?php echo esc_attr__('BeyondSEO Logo', 'beyondseo'); ?>"
            style="width:240px; max-width:100%; height:auto;"
        />
    </div>

    <h1 style="margin-bottom:8px;"><?php esc_html_e('Registration', 'beyondseo'); ?></h1>

    <!-- Screen 1: Registration form -->
    <div id="rc-screen-1" style="<?php echo esc_html($beyondseo_inEmailValidation ? 'display:none;' : ''); ?>">
        <p style="color:#555;margin-top:0;"><?php esc_html_e('Please complete your registration to continue.', 'beyondseo'); ?></p>
        <form id="rc-registration-form" method="post" action="#" novalidate>
            <!-- Hidden fields: auto-filled from current WP user -->
            <input type="hidden" id="first_name" name="first_name" value="<?php echo esc_attr($beyondseo_first_name); ?>">
            <input type="hidden" id="last_name" name="last_name" value="<?php echo esc_attr($beyondseo_last_name); ?>">

            <!-- Visible email field -->
            <div style="margin:16px 0;">
                <label for="email" style="display:block;font-weight:600;"><?php esc_html_e('Email', 'beyondseo'); ?></label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo esc_attr($beyondseo_email); ?>"
                    required
                    style="width:100%;max-width:480px;padding:8px;"
                    placeholder="<?php echo esc_attr__('name@company.com', 'beyondseo'); ?>"
                />
            </div>

            <!-- Country selector -->
            <div style="margin:16px 0;">
                <label for="country" style="display:block;font-weight:600;"><?php esc_html_e('Country', 'beyondseo'); ?></label>
                <select id="country" name="country" required style="width:100%;max-width:480px;padding:8px;">
                    <option value=""><?php esc_html_e('Select your country', 'beyondseo'); ?></option>
                    <?php foreach ($beyondseo_countries as $beyondseo_code => $beyondseo_label): ?>
                        <option value="<?php echo esc_attr($beyondseo_code); ?>" <?php selected(strtoupper((string)$beyondseo_code), $beyondseo_defaultCountry); ?>>
                            <?php echo esc_html($beyondseo_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Hidden REST nonce field for polling -->
            <input type="hidden" id="rc_rest_nonce" name="rc_rest_nonce" value="<?php echo esc_attr($beyondseo_rest_nonce); ?>">

            <!-- Registration type from channel detection -->
            <input type="hidden" id="rc_registration_type" name="rc_registration_type" value="<?php echo esc_attr($beyondseo_registrationType); ?>">

            <!-- Marketing consent -->
            <div style="margin:12px 0;">
                <label for="marketingConsent" style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                    <input
                        type="checkbox"
                        id="marketingConsent"
                        name="marketingConsent"
                        aria-describedby="marketing_consent_error"
                        style="margin-top:2px;"
                    />
                    <span><?php esc_html_e('I agree to receive product updates and marketing communications.', 'beyondseo'); ?></span>
                </label>
                <div id="marketing_consent_error" class="rc-error" style="display:none;color:#b3261e;margin-top:4px;font-size:12px;"></div>
            </div>

            <button type="submit" class="button button-primary">
                <?php esc_html_e('Register & Continue', 'beyondseo'); ?>
            </button>
        </form>

        <p style="font-size:13px;color:#666;margin-top:12px;">
            <?php esc_html_e('You will need to validate your email before proceeding.', 'beyondseo'); ?>
        </p>
    </div>

    <?php
    // Include both email validation views - JavaScript controls visibility based on accountStatus
    // These containers handle the email verification polling state
    include __DIR__ . '/email-validation-new-account.php';
    include __DIR__ . '/email-validation-existing-account.php';
    ?>

    <!-- Shared error/status container visible for all screens -->
    <div id="registration_error" class="rc-error" style="display:none;color:#b3261e;margin:8px 0;"></div>
</div>
