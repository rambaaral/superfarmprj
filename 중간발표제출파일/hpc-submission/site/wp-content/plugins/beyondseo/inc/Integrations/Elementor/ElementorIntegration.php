<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;

/**
 * Class ElementorIntegration
 *
 * This class integrates the RankingCoach plugin with Elementor.
 * It adds a custom tab to the Elementor editor for the RankingCoach integration.
 *
 * @package RankingCoach\Inc\Integrations\Elementor
 */
class ElementorIntegration
{
    public function __construct() {
        add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor_assets' ] );
        add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'enqueue_editor_styles' ] );
        add_action( 'elementor/editor/init', [ $this, 'init' ] );
    }

    /**
     * Initialize the Elementor integration.
     *
     * This function is called when the Elementor editor is initialized.
     */
    public function init(): void
    {
        // Check if Elementor is active
        if ( ! class_exists( 'Elementor\Plugin' ) ) {
            return;
        }

        ReactApp::get([
            'elementor'
        ]);
    }

    /**
     * Enqueue the custom JavaScript file for the Elementor editor.
     *
     * This function is called when the Elementor editor is loaded.
     */
    public function enqueue_editor_assets(): void
    {
        // Add REST API nonce for authentication
        wp_localize_script(
            'jquery',
            'rankingCoachRestData',
            [
                'nonce'          => wp_create_nonce('wp_rest'),
                'restUrl'        => esc_url_raw(rest_url()),
                'ajaxUrl'        => admin_url('admin-ajax.php'),
            ]
        );
        
        // Enqueue the main Elementor integration script with auto cache-busting
        $elementor_script_path = plugin_dir_path( __FILE__ ) . 'assets/js/elementor.js';
        $elementor_script_version = file_exists($elementor_script_path) ? filemtime($elementor_script_path) : '1.0';
        
        wp_enqueue_script(
            'rankingcoach-elementor-section',
            plugin_dir_url( __FILE__ ) . 'assets/js/elementor.js',
            [ 'jquery', 'elementor-editor' ],
            $elementor_script_version,
            true
        );

        add_action('elementor/editor/after_enqueue_scripts', function() {});
    }

    /**
     * Enqueue the custom CSS file for the Elementor editor.
     *
     * This function is called when the Elementor editor is loaded.
     */
    public function enqueue_editor_styles(): void
    {
        wp_enqueue_style(
            'rankingcoach-elementor-style',
            plugin_dir_url( __FILE__ ) . 'assets/css/elementor.css',
            [],
            '1.0'
        );
    }
}
