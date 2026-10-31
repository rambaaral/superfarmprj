<?php
declare(strict_types=1);

namespace RankingCoach;

// If this file is called directly, abort.
if ( !defined('ABSPATH') ) {
    exit('Direct access denied.');
}

/**
 * Professional WordPress SEO Plugin
 *
 * This is the main plugin file that initializes the SEO plugin.
 * It defines plugin metadata, sets up necessary constants, and bootstraps the plugin.
 *
 * @link              https://www.rankingcoach.com
 * @package           BeyondSEO
 *
 * @wordpress-plugin
 * Plugin Name:       BeyondSEO
 * Description:       Get found online with AI SEO, listings, reviews, social media, and Google Ads in one WordPress plugin. For SMBs & web professionals.
 * Version: 1.3.8
 * RcAPI Version:     v1
 * WpAPI Version:     v1
 * Requires at least: 6.5
 * Author:            rankingCoach
 * Author URI:        https://grow.rankingcoach.com/wordpress?utm_source=wordpress-plugin&utm_medium=plugin&utm_campaign=beyondseo&utm_content=by-rankingcoach
 * Tested up to:      7.1
 * Requires PHP:      8.0
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       beyondseo
 * Domain Path:       /languages/
 * Tags:              SEO, Google rankings, marketing, online visibility
 * Requires WP API:   true
 *
 * The plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * The plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License.
 * If not, see https://www.rankingcoach.com/en-us/wordpress.
 */

defined('RANKINGCOACH_FILE')            || define('RANKINGCOACH_FILE', __FILE__);
defined('RANKINGCOACH_PLUGIN_DIR')      || define('RANKINGCOACH_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Include the core plugin class file
 */
require_once RANKINGCOACH_PLUGIN_DIR . 'vendor/autoload.php';
require_once RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Plugin/constants.php';
require_once RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Plugin/functions.php';
require_once RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Plugin/safe-polyfills.php';

/**
 * Load the plugin's core functionality
 * This includes the main plugin class and any other necessary files.
*/

use Exception;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;

/**
 * Bootstrap and initialize the plugin
 * 
 * This self-executing anonymous function initializes the plugin and handles any
 * exceptions that might occur during the initialization process.
 */
(function() {
    try {
        // Initialize the plugin
        RankingCoachPlugin::instance()->initialize();
    } catch (Exception $e) {
        // Handle and render any exceptions that occur during initialization
        beyondseo_rcren($e);
    }
})();

