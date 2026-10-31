<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Builders\Elementor;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Elementor\Plugin as ElementorPlugin;
use Elementor\Frontend;
use WP_Post;

/**
 * Handles Elementor content rendering for SEO purposes.
 */
class ElementorContentRenderer
{
    /**
     * Cache for Elementor availability check
     *
     * @var bool|null
     */
    private static ?bool $elementor_available = null;

    /**
     * Flag to track if we've initialized Elementor frontend
     *
     * @var bool
     */
    private static bool $frontend_initialized = false;

    /**
     * Render Elementor content for a given post ID
     *
     * @param int  $post_id  The ID of the post to render.
     * @param bool $with_css Whether to include inline CSS (default: true).
     *
     * @return string The rendered HTML content, or empty string on failure.
     */
    public static function render(int $post_id, bool $with_css = true): string {
        // Validate post ID
        if ($post_id <= 0) {
            return '';
        }

        // Check if post exists
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return '';
        }

        // Check if Elementor is available
        if (!self::is_elementor_available()) {
            return self::get_fallback_content($post);
        }

        // Check if the post is built with Elementor
        if (!self::is_built_with_elementor($post_id)) {
            return self::get_fallback_content($post);
        }

        // Ensure Elementor frontend is properly initialized
        self::ensure_frontend_initialized();

        // Get the rendered content
        return self::get_elementor_content($post_id, $with_css);
    }

    /**
     * Check if Elementor plugin is active and fully loaded
     *
     * @return bool True if Elementor is available, false otherwise.
     */
    public static function is_elementor_available(): bool {
        if (self::$elementor_available !== null) {
            return self::$elementor_available;
        }

        self::$elementor_available = (
            class_exists('\Elementor\Plugin') &&
            did_action('elementor/loaded') &&
            self::get_elementor_instance() !== null
        );

        return self::$elementor_available;
    }

    /**
     * Check if a post is built with Elementor
     *
     * @param int $post_id The post ID to check.
     *
     * @return bool True if the post is built with Elementor.
     */
    public static function is_built_with_elementor(int $post_id): bool {
        // First, try using Elementor's native method
        if (self::is_elementor_available()) {
            $document = self::get_document($post_id);

            if ($document && method_exists($document, 'is_built_with_elementor')) {
                return $document->is_built_with_elementor();
            }
        }

        // Fallback: Check post meta directly
        return self::check_elementor_meta($post_id);
    }

    /**
     * Check Elementor meta data directly
     *
     * @param int $post_id The post ID.
     *
     * @return bool True if Elementor data exists.
     */
    private static function check_elementor_meta(int $post_id): bool {
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        $edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);

        // Check if data exists and is not empty
        if (empty($elementor_data) || $elementor_data === '[]') {
            return false;
        }

        // Additional check for edit mode
        return $edit_mode === 'builder';
    }

    /**
     * Get Elementor plugin instance safely
     *
     * @return ElementorPlugin|null The Elementor instance or null.
     */
    private static function get_elementor_instance(): ?ElementorPlugin {
        if (!class_exists('\Elementor\Plugin')) {
            return null;
        }

        try {
            $instance = ElementorPlugin::instance();
            return ($instance instanceof ElementorPlugin) ? $instance : null;
        } catch (\Exception $e) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[BeyondSEO] DEBUG: Elementor Content Renderer: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Get Elementor document for a post
     *
     * @param int $post_id The post ID.
     *
     * @return \Elementor\Core\Base\Document|null The document or null.
     */
    private static function get_document(int $post_id) {
        $elementor = self::get_elementor_instance();

        if (
            !$elementor ||
            !isset($elementor->documents) ||
            !is_object($elementor->documents) ||
            !method_exists($elementor->documents, 'get')
        ) {
            return null;
        }

        return $elementor->documents->get($post_id);
    }

    /**
     * Ensure Elementor frontend is properly initialized for programmatic use
     *
     * @return void
     */
    private static function ensure_frontend_initialized(): void {
        if (self::$frontend_initialized) {
            return;
        }

        $elementor = self::get_elementor_instance();

        if (!$elementor) {
            return;
        }

        // Check if we're not in editor or preview mode
        $is_edit_mode = (
            (isset($elementor->editor) && $elementor->editor->is_edit_mode()) ||
            (isset($elementor->preview) && $elementor->preview->is_preview_mode())
        );

        if ($is_edit_mode) {
            self::$frontend_initialized = true;
            return;
        }

        // Initialize frontend if not done
        if (isset($elementor->frontend) && $elementor->frontend instanceof Frontend) {
            self::init_frontend_components($elementor->frontend);
        }

        self::$frontend_initialized = true;
    }

    /**
     * Initialize frontend components
     *
     * @param Frontend $frontend The Elementor frontend instance.
     *
     * @return void
     */
    private static function init_frontend_components(Frontend $frontend): void {
        // Trigger init if not already done
        if (!did_action('elementor/frontend/init')) {
            if (method_exists($frontend, 'init')) {
                $frontend->init();
            }
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            do_action('elementor/frontend/init');
        }

        // Register styles if needed
        if (!did_action('elementor/frontend/after_register_styles')) {
            if (method_exists($frontend, 'register_styles')) {
                $frontend->register_styles();
            }
        }

        // Register scripts if needed
        if (!did_action('elementor/frontend/after_register_scripts')) {
            if (method_exists($frontend, 'register_scripts')) {
                $frontend->register_scripts();
            }
        }
    }

    /**
     * Get the actual Elementor rendered content
     *
     * @param int  $post_id  The post ID.
     * @param bool $with_css Include inline CSS.
     *
     * @return string The rendered content.
     */
    private static function get_elementor_content(int $post_id, bool $with_css): string {
        $elementor = self::get_elementor_instance();

        if (
            !$elementor ||
            !isset($elementor->frontend) ||
            !method_exists($elementor->frontend, 'get_builder_content_for_display')
        ) {
            return '';
        }

        try {
            // Start output buffering to capture any unexpected output
            ob_start();

            $content = $elementor->frontend->get_builder_content_for_display(
                $post_id,
                $with_css
            );

            // Clean any unexpected output
            ob_end_clean();

            return is_string($content) ? $content : '';

        } catch (\Exception $e) {
            ob_end_clean();
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[BeyondSEO] DEBUG: Elementor Content Renderer Error: ' . $e->getMessage());
            }
            return '';
        }
    }

    /**
     * Get fallback content when Elementor is not available
     *
     * @param WP_Post $post The post object.
     *
     * @return string The fallback content.
     */
    private static function get_fallback_content(WP_Post $post): string {
        // Apply the_content filters for shortcodes, embeds, etc.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        $content = apply_filters('the_content', $post->post_content);

        return is_string($content) ? $content : '';
    }

    /**
     * Get Elementor content with CSS extracted separately
     *
     * Useful when you need to place CSS in <head> and content in <body>
     *
     * @param int $post_id The post ID.
     *
     * @return array{content: string, css: string} Array with content and CSS.
     */
    public static function render_with_separated_css(int $post_id): array {
        $result = [
            'content' => '',
            'css'     => '',
        ];

        if (!self::is_elementor_available() || !self::is_built_with_elementor($post_id)) {
            $post = get_post($post_id);
            if ($post instanceof WP_Post) {
                $result['content'] = self::get_fallback_content($post);
            }
            return $result;
        }

        self::ensure_frontend_initialized();

        // Get content without inline CSS
        $result['content'] = self::get_elementor_content($post_id, false);

        // Get CSS separately
        $result['css'] = self::get_elementor_css($post_id);

        return $result;
    }

    /**
     * Get Elementor CSS for a post
     *
     * @param int $post_id The post ID.
     *
     * @return string The CSS content.
     */
    public static function get_elementor_css(int $post_id): string {
        if (!self::is_elementor_available()) {
            return '';
        }

        // Try to get the CSS file content
        $css_file = \Elementor\Core\Files\CSS\Post::create($post_id);

        if (!$css_file) {
            return '';
        }

        // Ensure CSS is generated
        $css_file->enqueue();

        $css_path = $css_file->get_path();

        if (file_exists($css_path)) {
            return file_get_contents($css_path) ?: '';
        }

        return '';
    }

    /**
     * Reset internal state (useful for testing or batch processing)
     *
     * @return void
     */
    public static function reset(): void {
        self::$elementor_available = null;
        self::$frontend_initialized = false;
    }
}
