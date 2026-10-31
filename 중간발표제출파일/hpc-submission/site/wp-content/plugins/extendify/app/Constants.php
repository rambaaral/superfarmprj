<?php

/**
 * Build-time URL rewrites (.github/workflows/build-*-zip.yml) target this file,
 * so any new PHP code that consumes these constants inherits the override.
 */

namespace Extendify;

defined('ABSPATH') || die('No direct access.');

// A site points a backend elsewhere by defining EXTENDIFY_<NAME> in wp-config.
$extendifyOverridden = [];
foreach (
    [
        'AI_HOST' => 'https://ai.extendify.com',
        'PATTERNS_HOST' => 'https://patterns.extendify.com',
        'KB_HOST' => 'https://kb.extendify.com',
        'INSIGHTS_HOST' => 'https://insights.extendify.com',
        'IMAGES_HOST' => 'https://images-resource.extendify.com',
        'DASHBOARD_HOST' => 'https://dashboard.extendify.com',
    ] as $extendifyHost => $extendifyDefault
) {
    if (!defined('EXTENDIFY_' . $extendifyHost)) {
        define('EXTENDIFY_' . $extendifyHost, $extendifyDefault);
    } elseif (constant('EXTENDIFY_' . $extendifyHost) !== $extendifyDefault) {
        $extendifyOverridden[$extendifyHost] = constant('EXTENDIFY_' . $extendifyHost);
    }
}

// Only once every constant exists: touching the class evaluates all of its constants.
Constants::recordOverridden($extendifyOverridden);

class Constants
{
    // Plugin requires PHP 7.0; constant visibility modifiers are PHP 7.1+.
    // phpcs:disable PSR12.Properties.ConstantVisibility.NotFound
    const AI_HOST = EXTENDIFY_AI_HOST;
    const PATTERNS_HOST = EXTENDIFY_PATTERNS_HOST;
    const KB_HOST = EXTENDIFY_KB_HOST;
    const INSIGHTS_HOST = EXTENDIFY_INSIGHTS_HOST;
    const IMAGES_HOST = EXTENDIFY_IMAGES_HOST;
    const DASHBOARD_HOST = EXTENDIFY_DASHBOARD_HOST;
    // phpcs:enable PSR12.Properties.ConstantVisibility.NotFound

    private static $overridden = [];

    public static function recordOverridden($hosts)
    {
        self::$overridden = $hosts;
    }

    /**
     * Prints the overridden hosts before any plugin script, which reads them as it loads.
     *
     * @return void
     */
    public static function printOverriddenHosts()
    {
        if (!self::$overridden) {
            return;
        }

        echo '<script>window.extendifyHosts = ' . \wp_json_encode(self::$overridden) . ';</script>' . PHP_EOL;
    }

    /**
     * All Extendify service base URLs.
     *
     * @return string[]
     */
    public static function serviceUrls()
    {
        return [
            self::AI_HOST,
            self::PATTERNS_HOST,
            self::KB_HOST,
            self::INSIGHTS_HOST,
            self::IMAGES_HOST,
            self::DASHBOARD_HOST,
        ];
    }
}
