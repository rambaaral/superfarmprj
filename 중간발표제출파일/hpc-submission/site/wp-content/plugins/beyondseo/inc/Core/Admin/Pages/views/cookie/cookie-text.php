<?php
// Centralized cookie UI text and per-browser instructions.
// Returns an associative array with keys: cookieTitle, cookieDescription, browserInstructions.
// Kept in a separate file to decouple from controller and keep translations close to UI.

if (!defined('ABSPATH')) {
    exit;
}

return [
    'cookieTitle' => __('Third-Party Cookies Required', 'beyondseo'),
    'cookieDescription' =>
        __('To access the dashboard, please enable third-party cookies in your browser. If you prefer not to enable them, you can still view the dashboard by clicking the', 'beyondseo')
        . ' "' . __('Open Dashboard in New Tab', 'beyondseo') . '" '
        . __('button below', 'beyondseo'),

    'browserInstructions' => [
        'safari' => [
            __('Open Safari Preferences (Safari → Settings)', 'beyondseo'),
            __('Go to the "Privacy" tab', 'beyondseo'),
            __('Uncheck "Prevent cross-site tracking"', 'beyondseo'),
            __('Refresh this page', 'beyondseo'),
        ],
        'chrome' => [
            __('Click the three dots menu (⋮) in the top right corner', 'beyondseo'),
            __('In the left sidebar, click Privacy and security.', 'beyondseo'),
            __('On the right click Third-party cookies', 'beyondseo'),
            __('Pick Allow third-party cookies', 'beyondseo'),
        ],
        'firefox' => [
            __('Click the menu button (☰) and select Settings', 'beyondseo'),
            __('Go to Privacy & Security panel', 'beyondseo'),
            __('Under "Enhanced Tracking Protection", select "Standard" or "Custom"', 'beyondseo'),
            __('If Custom, uncheck "Cookies" or select "Cross-site tracking cookies"', 'beyondseo'),
            __('Refresh this page', 'beyondseo'),
        ],
        'edge' => [
            __('Click the three dots menu (...) in the top right corner', 'beyondseo'),
            __('Go to Settings → Cookies and site permissions → Cookies and site data', 'beyondseo'),
            __('Turn off "Block third-party cookies"', 'beyondseo'),
            __('Refresh this page', 'beyondseo'),
        ],
        'default' => [
            __('Open your browser\'s Settings or Preferences', 'beyondseo'),
            __('Look for Privacy, Security, or Cookies settings', 'beyondseo'),
            __('Enable third-party cookies or disable tracking protection', 'beyondseo'),
            __('Refresh this page', 'beyondseo'),
        ],
    ],
];
