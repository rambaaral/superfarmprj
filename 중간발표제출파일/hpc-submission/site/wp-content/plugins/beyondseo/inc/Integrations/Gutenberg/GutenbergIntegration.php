<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Integrations\Gutenberg;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;

/**
 * Class GutenbergIntegration
 *
 * This class integrates the RankingCoach plugin with the Gutenberg block editor.
 * It prepares the foundation for custom panels, blocks, or enhancements in the editor.
 * Future implementations can extend this for advanced SEO features like block-specific meta tags,
 * AI-assisted content optimization, or dynamic schema generation within blocks.
 *
 * @package RankingCoach\Inc\Integrations\Gutenberg
 */
class GutenbergIntegration
{
    public function __construct() {
        if ( ! self::isAllowedToLoad() ) {
            return;
        }

        add_action( 'init', [ $this, 'init' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
    }

    /**
     * Check if the integration is allowed to load based on comprehensive rules.
     *
     * This method performs multiple checks to ensure Gutenberg is truly available
     * and not disabled by plugins or configurations, preventing false positives.
     *
     * @return bool True if Gutenberg is available and enabled, false otherwise.
     */
    public static function isAllowedToLoad(): bool
    {
        // Rule 1: Minimum WordPress version check (Gutenberg core since 5.0)
        if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
            return false;
        }

        // Rule 2: Core Gutenberg functions existence
        if ( ! function_exists( 'register_block_type' ) ||
             ! function_exists( 'use_block_editor_for_post' ) ||
             ! function_exists( 'use_block_editor_for_post_type' ) ) {
            return false;
        }

        // Rule 3: Check if Classic Editor is active and overriding Gutenberg
        if ( class_exists( 'Classic_Editor' ) ) {
            // Classic Editor settings: 'classic' means fully disabled, 'replace' means optional but default to classic
            $replace_option = get_option( 'classic-editor-replace' );
            $allow_users = get_option( 'classic-editor-allow-users' );

            if ( $replace_option === 'classic' ||
                 ( $replace_option === 'replace' && $allow_users !== 'allow' ) ) {
                return false;
            }
        }

        // Rule 4: Ensure at least one post type actively uses the block editor
        $has_block_editor = false;
        foreach ( get_post_types() as $post_type ) {
            if ( use_block_editor_for_post_type( $post_type ) ) {
                $has_block_editor = true;
                break;
            }
        }
        if ( ! $has_block_editor ) {
            return false;
        }

        // Rule 5: Check for REST API support (required for Gutenberg)
        $post_types = get_post_types( [ 'show_in_rest' => true ] );
        if ( empty( $post_types ) ) {
            return false;
        }

        // Rule 6: Check if Gutenberg is explicitly disabled via constant
        if ( defined( 'GUTENBERG_DEVELOPMENT_MODE' ) && ! GUTENBERG_DEVELOPMENT_MODE ) {
            return false;
        }

        // All checks passed
        return true;
    }

    /**
     * Initialize the Gutenberg integration.
     *
     * This function sets up core integration components.
     */
    public function init(): void
    {
        if ( ! self::isAllowedToLoad() ) {
            return;
        }

        ReactApp::get([
            'gutenberg'
        ]);
    }

    /**
     * Enqueue the custom JavaScript and styles for the Gutenberg editor.
     *
     * This function is called when block editor assets are enqueued.
     */
    public function enqueue_editor_assets(): void
    {
        if ( ! self::isAllowedToLoad() ) {
            return;
        }

        // Add REST API nonce for authentication
        wp_localize_script(
            'wp-blocks',
            'rankingCoachGutenbergRestData',
            [
                'nonce'          => wp_create_nonce('wp_rest'),
                'restUrl'        => esc_url_raw(rest_url()),
                'ajaxUrl'        => admin_url('admin-ajax.php'),
            ]
        );
        
        // Enqueue the main Gutenberg integration script with auto cache-busting
        $gutenberg_script_path = plugin_dir_path( __FILE__ ) . 'assets/js/gutenberg.js';
        $gutenberg_script_version = file_exists($gutenberg_script_path) ? filemtime($gutenberg_script_path) : '1.0';
        
        wp_enqueue_script(
            'rankingcoach-gutenberg-section',
            plugin_dir_url( __FILE__ ) . 'assets/js/gutenberg.js',
            [ 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-data' ],
            $gutenberg_script_version,
            true
        );

        // Enqueue the custom CSS for the Gutenberg editor
        wp_enqueue_style(
            'rankingcoach-gutenberg-style',
            plugin_dir_url( __FILE__ ) . 'assets/css/gutenberg.css',
            [ 'wp-edit-blocks' ],
            '1.0'
        );
    }
}