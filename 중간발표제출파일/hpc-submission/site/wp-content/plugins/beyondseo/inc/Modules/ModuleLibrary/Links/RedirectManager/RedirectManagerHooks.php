<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Links\RedirectManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleBase\BaseSubmoduleHooks;
use ReflectionException;

/**
 * Class RedirectManagerHooks
 * Handles WordPress hooks for the RedirectManager module
 */
class RedirectManagerHooks extends BaseSubmoduleHooks {
    
    /**
     * @var RedirectManager
     */
    protected RedirectManager $module;

    /**
     * @param BaseModule $module
     * @param array|null $params
     * @throws ReflectionException
     */
    public function __construct(BaseModule $module, ?array $params = null) {
        // Implement constructor
        $this->module = $module instanceof RedirectManager ? $module : new RedirectManager($module, $params);
        parent::__construct($module, $params);
    }

    /**
     * Initialize hooks for the RedirectManager module
     * 
     * @return void
     */
    public function initializeHooks(): void {
        // Register the redirect hook
        add_action('template_redirect', [$this, 'handleRedirects'], 1);
    }

    /**
     * Handle redirects based on the current URL
     *
     * @return void
     */
    public function handleRedirects(): void {
        // Get REQUEST_URI safely
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $request_uri_raw = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        
        // Skip admin pages and REST API requests
        if (defined('REST_REQUEST') || is_admin() || wp_doing_ajax() || str_contains($request_uri_raw, '/wp-json/')) {
            return;
        }

        // Sanitize REQUEST_URI to prevent XSS
        $request_uri = sanitize_text_field($request_uri_raw);

        if (str_contains($request_uri, '/wp-json/')) {
            return;
        }

        // Skip programmatic requests from admin with specific header
        $bypass_header = isset($_SERVER['HTTP_X_RANKINGCOACH_BYPASS_REDIRECT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_RANKINGCOACH_BYPASS_REDIRECT']))
            : '';

        if ($bypass_header === '1') {
            return;
        }

        // Get current URL path
        $current_url = rtrim($request_uri, '/');

        // Parse the current URL to extract query parameters and fragments
        $parsed_url = wp_parse_url($request_uri);
        $query = isset($parsed_url['query']) ? sanitize_text_field($parsed_url['query']) : '';

        // Use the RedirectManager to find a matching redirect
        $redirect = $this->module->findRedirectByUrl($current_url);

        // If a redirect is found, append query parameters and fragments to the destination URL and perform the redirect
        if ($redirect) {
            $this->module->incrementHitCount((int) $redirect->id);
            $destination_url = $redirect->destination_url;
            $redirectCode = (int) $redirect->redirect_code;
            if ($query) {
                $destination_url .= (!str_contains($destination_url, '?') ? '?' : '&') . $query;
            }
            // Ensure the redirect code is valid (301 or 302)
            $code = in_array($redirectCode, [301, 302])
                ? $redirectCode
                : 302;

            // Perform the redirect
            // phpcs:ignore
            wp_redirect(esc_url_raw($destination_url), $code);
            exit;
        }
    }
}
