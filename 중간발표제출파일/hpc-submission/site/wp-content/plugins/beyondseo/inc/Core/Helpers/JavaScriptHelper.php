<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * JavaScript utility helper for common client-side functionality
 */
class JavaScriptHelper {

    /**
     * Generates the WordPress login session expiration handler script
     * 
     * This script monitors the wp-auth-check-wrap modal and refreshes the page
     * when the login modal is closed, ensuring proper session state management.
     * 
     * @return string The JavaScript code for login session handling
     */
    public static function getLoginSessionExpirationScript(): string {
        return '// Login form handler when the user session is expired.
        document.addEventListener("DOMContentLoaded", function() {
            const targetId = "wp-auth-check-wrap";
            let wasLoginModalOpen = false;
            let checkInterval = null;

            function isLoginModalOpen() {
                const modal = document.getElementById(targetId);
                const bodyHasClass = document.body.classList.contains("modal-open");
                const modalIsVisible = modal && modal.offsetParent !== null; // true if not hidden
                return modalIsVisible && bodyHasClass;
            }

            function checkModalState() {
                const isNowOpen = isLoginModalOpen();

                if (isNowOpen && !wasLoginModalOpen) {
                    // Login modal just opened
                    wasLoginModalOpen = true;
                    console.log("[Login Modal] Opened");
                } else if (!isNowOpen && wasLoginModalOpen) {
                    // Login modal just closed
                    wasLoginModalOpen = false;
                    console.log("[Login Modal] Closed - refreshing page...");

                    // Clear the interval before refreshing
                    if (checkInterval) {
                        clearInterval(checkInterval);
                    }

                    window.location.reload(); // Refresh the page
                }
            }

            // Use a MutationObserver to monitor changes
            const observer = new MutationObserver(checkModalState);

            const config = {
                attributes: true,
                childList: true,
                subtree: true,
                attributeFilter: ["class", "style"]
            };

            // Observe body for class changes
            observer.observe(document.body, config);

            // Try to find and observe the modal element if it exists
            const modalElement = document.getElementById(targetId);
            if (modalElement) {
                observer.observe(modalElement, config);
            }

            // Fallback: poll every 1s to catch edge cases
            checkInterval = setInterval(checkModalState, 1000);

            // Clean up on page unload to prevent memory leaks
            window.addEventListener("beforeunload", function() {
                if (checkInterval) {
                    clearInterval(checkInterval);
                }
                observer.disconnect();
            });
        });
        ';
    }

    /**
     * Enqueues the login session expiration handler as an inline script
     * using the WordPress script API (wp_add_inline_script).
     * 
     * @return void
     */
    public static function enqueueLoginSessionExpirationScript(): void {
        $handle = 'rc-login-session-expiration';

        if ( ! wp_script_is( $handle, 'registered' ) ) {
            wp_register_script( $handle, false, [], RANKINGCOACH_VERSION, true );
        }

        wp_enqueue_script( $handle );
        wp_add_inline_script( $handle, self::getLoginSessionExpirationScript() );
    }

    /**
     * @deprecated Use enqueueLoginSessionExpirationScript() instead.
     * @return void
     */
    public static function renderLoginSessionExpirationScript(): void {
        self::enqueueLoginSessionExpirationScript();
    }
}