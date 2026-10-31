/**
 * SEO Optimiser Client
 * 
 * This JavaScript module provides a client for interacting with the WPSeoOptimiserController
 * endpoints. It handles authentication through WordPress's Ajax system.
 */

(function($) {
    'use strict';

    // Create the namespace if it doesn't exist
    window.RankingCoach = window.RankingCoach || {};
    
    /**
     * SEO Optimiser Client
     */
    RankingCoach.SeoOptimiser = {
        /**
         * Retrieve SEO Optimiser data for a post
         */
        retrieveSeoOptimiser: function(postId, options) {
            options = options || {};
            
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'rankingcoach_retrieve_seo_optimiser',
                    post_id: postId,
                    security: RankingCoach.nonce
                },
                success: function(response) {
                    if (response.success && options.success) {
                        options.success(response.data);
                    } else if (!response.success && options.error) {
                        options.error(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    if (options.error) {
                        options.error(error);
                    }
                }
            });
        },
        
        /**
         * Process SEO Optimiser for a post with specific parameters
         */
        proceedSeoOptimiser: function(postId, params, options) {
            options = options || {};
            params = params || {};
            
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'rankingcoach_proceed_seo_optimiser',
                    post_id: postId,
                    params: params,
                    security: RankingCoach.nonce
                },
                success: function(response) {
                    if (response.success && options.success) {
                        options.success(response.data);
                    } else if (!response.success && options.error) {
                        options.error(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    if (options.error) {
                        options.error(error);
                    }
                }
            });
        }
    };
    
    /**
     * Initialize the SEO Optimiser Client
     */
    RankingCoach.initSeoOptimiser = function(options) {
        options = options || {};
        
        // Store the nonce for later use
        RankingCoach.nonce = options.nonce || '';
    };
    
})(jQuery);