// noinspection JSUnresolvedReference,JSUnusedLocalSymbols

(function ($) {
    "use strict";

    window.rankingcoach_data_site_url = RankingCoachGeneralData.site_url;
    window.rankingcoach_data_api_base_url = RankingCoachGeneralData.api_base_url;
    window.rankingcoach_data_ajax_url = RankingCoachGeneralData.ajax_url;
    window.rankingcoach_data_nonce_ts = RankingCoachGeneralData.nonce_ts;
    window.rankingcoach_data_nonce = RankingCoachGeneralData.nonce;

    // Initialize toggle text for meta box
    function initToggleText() {
        var $postbox = $("#rankingcoach-seo-analysis");
        if ($postbox.length) {
            var $handle = $postbox.find(".hndle");

            // Only add if not already added
            if ($handle.find("#rankingcoach-toggle-text").length === 0) {
                var $toggleText = $('<div id="rankingcoach-toggle-text" style="cursor: pointer; display: inline-block; margin-left: 8px;"></div>');
                $handle.append($toggleText);
                updateToggleText();
            }
        }
    }

    function updateToggleText() {
        var $postbox = $("#rankingcoach-seo-analysis");
        var $toggleText = $("#rankingcoach-toggle-text");

        if ($postbox.length && $toggleText.length) {
            if ($postbox.hasClass("closed")) {
                $toggleText.text(RankingCoachGeneralData.i18n.showMore);
            } else {
                $toggleText.text(RankingCoachGeneralData.i18n.showLess);
            }
        }
    }

    /**
     * Generate a cryptographically secure session ID
     * @returns {string} UUID v4 session identifier
     */
    window.generateSessionId = function () {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        // Fallback for older browsers
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Get current origin for parent identification
     * @returns {string} Current window origin
     */
    window.getCurrentOrigin = function() {
        return window.location.origin;
    }

    // Potentially used for the upsell redirect
    window.addEventListener("message", function (event) {
        let data = event.data;
        if (!data) {
            return;
        }

        if (typeof data === "string") {
            try {
                data = JSON.parse(data);
            } catch (e) {
                return;
            }
        }

        if (!data || typeof data !== "object") {
            return;
        }

        switch (data.type) {
            case 'registration.ready':
                //
                break;

            case 'registration.complete':
                window.location.href = (RankingCoachGeneralData.admin_url || 'admin.php') + '?page=rankingcoach-main&ref=account_sync';
                break;

            case 'registration.error':
                //
                break;

            case 'registration.cancelled':
                //
                break;

            default:
                //
                //console.warn('Unknown message type:', message.type);
        }

        if (data.action === "redirect_upsell" || data.type === "redirect_upsell") {
            const rawUrl = data.relativeUrl || data.url;
            if (rawUrl && typeof rawUrl === "string") {
                const rewrittenUrl = rawUrl.replace(/page=rankingcoach-upsell/g, "page=rankingcoach-connect");
                if (rewrittenUrl.startsWith("http://") || rewrittenUrl.startsWith("https://") || rewrittenUrl.startsWith("//")) {
                    window.location.href = rewrittenUrl;
                } else {
                    window.location.href = window.location.origin + (rewrittenUrl.startsWith("/") ? "" : "/") + rewrittenUrl;
                }
            }
        }
    });

    /**
     * Called when the iframe has loaded
     */
    window.handleIframeLoaded = function () {
        // Hide loader and show iframe when it's loaded
        const loader = document.getElementById("rc-seo-iframe-loader");
        if (loader) {
            loader.style.display = "none";
        }

        const iframe = document.getElementById("rc-seo-iframe");
        if (iframe) {
            iframe.style.visibility = "visible";
        }

        // Apply the proper iframe sizing
        window.loadIframeWrap();
    };

    /**
     * Load the iframe wrap within the window context.
     */
    window.loadIframeWrap = function () {
        let iframeWrapId = "rc-seo-iframe-wrap";
        let iframeWrapDom = document.querySelector("#" + iframeWrapId);
        if (iframeWrapDom) {
            let wpContentDom = document.querySelector("#wpwrap #wpcontent");
            let wpBodyContentDom = document.querySelector("#wpbody-content");
            wpBodyContentDom.style.padding = "0";
            wpContentDom.style.paddingLeft = "0";
            iframeWrapDom.style.height = window.innerHeight - 32 + "px";
        }
    };
    window.addEventListener("resize", function () {
        let iframeWrapDom = document.querySelector("#rc-seo-iframe-wrap");
        if (iframeWrapDom) {
            iframeWrapDom.style.height = window.innerHeight - 32 + "px";
        }
    });

    /**
     * Load the iframe within the window context.
     */
    window.onload = function () {
        let wpFooterDom = document.querySelector("#wpfooter");
        if (wpFooterDom) wpFooterDom.style.display = "none";

        // Set initial sizing and make sure the iframe wrapper is visible
        let iframeWrapDom = document.querySelector("#rc-seo-iframe-wrap");
        if (iframeWrapDom) {
            let wpContentDom = document.querySelector("#wpwrap #wpcontent");
            let wpBodyContentDom = document.querySelector("#wpbody-content");
            wpBodyContentDom.style.padding = "0";
            wpContentDom.style.paddingLeft = "0";
            iframeWrapDom.style.height = window.innerHeight - 32 + "px";
        }

        // Initialize toggle text for meta box
        initToggleText();
    };

    // Re-initialize after postbox toggles
    $(document).ready(function () {
        // Initialize on page load
        setTimeout(initToggleText, 100);

        // Listen for postbox toggle events
        $(document).on("click", "#rankingcoach-seo-analysis .hndle, #rankingcoach-seo-analysis .handlediv", function () {
            setTimeout(updateToggleText, 100);
        });

        // Listen for postbox state changes
        $(document).on("postbox-toggled", function () {
            setTimeout(updateToggleText, 100);
        });
    });
})(jQuery);
