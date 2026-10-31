<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

/**
 * Class SeoOptimiserAjax
 * 
 * Handles Ajax requests for the SEO Optimiser functionality
 */
class SeoOptimiserAjax {
    
    /**
     * Constructor
     */
    public function __construct() {
        
        // Register Ajax handlers
        add_action('wp_ajax_rankingcoach_retrieve_seo_optimiser', [$this, 'retrieveSeoOptimiser']);
        add_action('wp_ajax_rankingcoach_proceed_seo_optimiser', [$this, 'proceedSeoOptimiser']);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }
    
    /**
     * Enqueue necessary scripts
     */
    public function enqueueScripts(): void
    {
        // Only enqueue on post edit screens
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, ['post', 'post-new'])) {
            return;
        }
        
        wp_enqueue_script(
            'rankingcoach-seo-optimiser-script',
            RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/js/seo-optimiser-client.js',
            ['jquery'],
            time(),
            true
        );
        
        // Localize the script with the nonce
        wp_localize_script('rankingcoach-seo-optimiser-script', 'RankingCoachSeoOptimiserData', [
                'nonce' => wp_create_nonce('rankingcoach_seo_optimiser_nonce')
            ]
        );
        
        // Initialize the client
        wp_add_inline_script(
            'rankingcoach-seo-optimiser-script',
            'jQuery(document).ready(function($) { RankingCoach.initSeoOptimiser({ nonce: RankingCoachSeoOptimiserData.nonce }); });'
        );
    }
    
    /**
     * Handle the retrieve SEO optimiser Ajax request
     */
    public function retrieveSeoOptimiser(): void
    {
        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'beyondseo')));
            return;
        }
        
        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'rankingcoach_seo_optimiser_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed. Please try again.', 'beyondseo')));
            return;
        }
        
        // Check if post ID is provided and sanitize
        if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
            wp_send_json_error(array('message' => __('Invalid post ID provided.', 'beyondseo')));
            return;
        }

        $postId = absint($_POST['post_id']);
        
        // Verify post exists and user can edit it
        if (!get_post($postId) || !current_user_can('edit_post', $postId)) {
            wp_send_json_error(array('message' => __('Post not found or insufficient permissions.', 'beyondseo')));
            return;
        }
        
        try {

            $result = 'Here logic for retrieve SEO optimiser for ' . $postId;
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle the proceed SEO optimiser Ajax request
     */
    public function proceedSeoOptimiser(): void
    {
        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'beyondseo')));
            return;
        }
        
        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'rankingcoach_seo_optimiser_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed. Please try again.', 'beyondseo')));
            return;
        }
        
        // Check if post ID is provided and sanitize
        if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
            wp_send_json_error(array('message' => __('Invalid post ID provided.', 'beyondseo')));
            return;
        }
        
        $postId = absint($_POST['post_id']);
        
        // Verify post exists and user can edit it
        if (!get_post($postId) || !current_user_can('edit_post', $postId)) {
            wp_send_json_error(array('message' => __('Post not found or insufficient permissions.', 'beyondseo')));
            return;
        }
        
        // Sanitize parameters
        $params = WordpressHelpers::sanitize_input('POST', 'params', [FILTER_SANITIZE_FULL_SPECIAL_CHARS], null, 'array', []);
        // $params = isset($_POST['params']) && is_array($_POST['params']) ? wp_unslash($_POST['params']) : [];
        
        try {
            $requestDto = '';

            // Set additional parameters if provided (sanitized)
            if (isset($params['context'])) {
                $requestDto .= ' Context: ' . sanitize_text_field($params['context']);
            }
            
            if (isset($params['factor'])) {
                $requestDto .= ' Factor: ' . sanitize_text_field($params['factor']);
            }
            
            if (isset($params['operation'])) {
                $requestDto .= ' Operation: ' . sanitize_text_field($params['operation']);
            }

            $result = 'Here logic for proceed SEO optimiser for ' . $postId . ' with params: ' . $requestDto;
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
