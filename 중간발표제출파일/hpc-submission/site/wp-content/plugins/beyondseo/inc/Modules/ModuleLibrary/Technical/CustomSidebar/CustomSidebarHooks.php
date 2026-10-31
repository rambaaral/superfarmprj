<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Technical\CustomSidebar;

use RankingCoach\Inc\Core\Base\BaseConstants;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CustomSidebarHooks
 * 
 * Handles the hooks for the CustomSidebar module
 * 
 * @package RankingCoach\Inc\Modules\ModuleLibrary\Technical\CustomSidebar
 */
class CustomSidebarHooks
{
    /**
     * @var CustomSidebar The parent module instance
     */
    private CustomSidebar $module;

    /**
     * CustomSidebarHooks constructor.
     * 
     * @param CustomSidebar $module The parent module instance
     */
    public function __construct(CustomSidebar $module)
    {
        $this->module = $module;
    }

    /**
     * Initialize hooks for the module
     * 
     * @return void
     */
    public function initializeHooks(): void
    {
        // Register the Gutenberg sidebar assets
        add_action('init', [$this, 'registerGutenbergAssets']);
        
        // Enqueue the Gutenberg sidebar assets
        add_action('enqueue_block_editor_assets', [$this, 'enqueueGutenbergAssets']);
        
        // Register meta fields
        add_action('init', [$this, 'registerMetaFields']);
    }

    /**
     * Register the Gutenberg sidebar assets
     * 
     * @return void
     */
    public function registerGutenbergAssets(): void
    {
        wp_register_script(
            'rankingcoach-sidebar-js',
            RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/js/gutenberg_sidebar.js',
            [
                'wp-plugins',
                'wp-editor',
                'wp-element',
                'wp-components',
                'wp-data',
                'wp-i18n'
            ],
            RANKINGCOACH_VERSION,
            false
        );
    }

    /**
     * Enqueue the Gutenberg sidebar assets
     * 
     * @return void
     */
    public function enqueueGutenbergAssets(): void
    {
        // Enqueue the sidebar script
        wp_enqueue_script('rankingcoach-sidebar-js');
    }

    /**
     * Register meta fields for storing SEO data
     * 
     * @return void
     */
    public function registerMetaFields(): void
    {
        // Register a meta field for SEO title
    }
}