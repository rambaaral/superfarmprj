/**
 * RankingCoach Registration - WordPress Side Communication Handler
 *
 * Implements secure bi-directional communication between WordPress and
 * external registration page using Window.postMessage API.
 *
 * This script handles the registration window popup functionality for the
 * Direct Channel (DC) upsell page, managing secure cross-origin communication,
 * session management, and window lifecycle.
 *
 * @see Design Document Section 5.2 - WordPress Side Implementation
 */
window.BSEORegistration = (function () {
    'use strict';

    // Configuration callbacks (can be overridden via init)
    let callbacks = {
        onSuccess: null,
        onError: null,
        onCancel: null
    };

    // Configuration
    const CONFIG = {
        REGISTRATION_URL: 'https://rts.dev.rankingcoach.com/customer/registration/index',
        ALLOWED_ORIGINS: [
            'https://rts.dev.rankingcoach.com',
            'https://www.rankingcoach.com',
            'https://localhost',
        ],
        SESSION_TIMEOUT: 30 * 60 * 1000, // 30 minutes
        MESSAGE_MAX_AGE: 5 * 60 * 1000, // 5 minutes
        WINDOW_CHECK_INTERVAL: 500, // 500ms
        LOG_PREFIX: '[BeyondSEO]'
    };

    // State management
    let state = {
        registrationWindow: null,
        sessionId: null,
        windowCheckInterval: null,
        sessionTimeout: null,
        isRegistrationInProgress: false
    };

    /**
     * Open a placeholder window with loading state
     * @returns {boolean} True if window opened successfully, false if blocked
     */
    function openLoadingWindow() {
        if (state.isRegistrationInProgress) {
            console.log(CONFIG.LOG_PREFIX, 'Registration already in progress');
            if (state.registrationWindow && !state.registrationWindow.closed) {
                state.registrationWindow.focus();
                return true;
            }
            state.isRegistrationInProgress = false;
        }

        try {
            const windowFeatures = 'width=800,height=700,menubar=no,toolbar=no,location=no,status=no';
            // Open about:blank first
            state.registrationWindow = window.open('about:blank', 'rankingcoach_registration', windowFeatures);

            if (!state.registrationWindow || state.registrationWindow.closed || typeof state.registrationWindow.closed === 'undefined') {
                console.error(CONFIG.LOG_PREFIX, 'Popup blocked by browser');
                alert('Please allow popups for this site to complete registration.');
                cleanupRegistrationSession();
                return false;
            }

            state.isRegistrationInProgress = true;

            // Get localized strings or defaults
            const title = (window.rcWindowConfig && window.rcWindowConfig.loadingTitle) || 'Loading...';
            const message = (window.rcWindowConfig && window.rcWindowConfig.connectingMessage) || 'Connecting to rankingCoach...';
            const style = (window.rcWindowConfig && window.rcWindowConfig.loadingCss) || '';

            // Render loading state
            const loadingHtml = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>${title}</title>
                </head>
                <body>
                    <div class="spinner"></div>
                    <p>${message}</p>
                </body>
                </html>
            `;

            state.registrationWindow.document.write(loadingHtml);

            if (style) {
                const styleEl = state.registrationWindow.document.createElement('style');
                styleEl.textContent = style;
                state.registrationWindow.document.head.appendChild(styleEl);
            }

            state.registrationWindow.document.close();

            // Monitor for manual closure
            startWindowClosureMonitoring();

            return true;

        } catch (error) {
            cleanupRegistrationSession();
            return false;
        }
    }

    /**
     * Navigate the open window to the target URL
     * @param {string} targetUrl
     */
    function navigateWindow(targetUrl) {
        if (!state.registrationWindow || state.registrationWindow.closed) {
            return;
        }

        try {
            const url = new URL(targetUrl);

            state.registrationWindow.location.href = url.toString();

            // Setup message listener
            setupPostMessageListener();

            // Set session timeout
            state.sessionTimeout = setTimeout(function () {
                if (state.registrationWindow && !state.registrationWindow.closed) {
                    state.registrationWindow.close();
                }
                cleanupRegistrationSession();
            }, CONFIG.SESSION_TIMEOUT);

        } catch (error) {
            if (state.registrationWindow) state.registrationWindow.close();
            cleanupRegistrationSession();
        }
    }

    /**
     * Close the registration window programmatically
     */
    function closeWindow() {
        if (state.registrationWindow && !state.registrationWindow.closed) {
            state.registrationWindow.close();
        }
        cleanupRegistrationSession();
    }

    /**
     * Open registration window with proper parameters
     * @param {string} targetUrl - The dynamic URL to open
     */
    function openWindow(targetUrl) {
        if (openLoadingWindow()) {
            navigateWindow(targetUrl);
        }
    }

    /**
     * Legacy wrapper for backward compatibility
     * @param {Event} event
     */
    function openRegistrationWindow(event) {
        if (event) event.preventDefault();
        openWindow(CONFIG.REGISTRATION_URL);
    }

    /**
     * Setup postMessage listener for cross-origin communication
     */
    function setupPostMessageListener() {
        // Remove existing listener if any
        window.removeEventListener('message', handlePostMessage);

        // Add new listener
        window.addEventListener('message', handlePostMessage);
    }

    /**
     * Handle incoming postMessage events
     * @param {MessageEvent} event - PostMessage event
     */
    function handlePostMessage(event) {
        console.log(CONFIG.LOG_PREFIX, 'Received message:', {
            origin: event.origin,
            data: event.data
        });

        // Validate message
        const validation = validateMessage(event);
        if (!validation.valid) {
            console.warn(CONFIG.LOG_PREFIX, 'Message validation failed:', validation.reason);
            return;
        }

        // Route message by type
        handleRegistrationMessage(event.data);
    }

    /**
     * Validate incoming message structure and security
     * @param {MessageEvent} event - PostMessage event
     * @returns {Object} Validation result with valid flag and reason
     */
    function validateMessage(event) {
        // Validate origin
        if (!CONFIG.ALLOWED_ORIGINS.includes(event.origin)) {
            return {
                valid: false,
                reason: 'Origin not in whitelist: ' + event.origin
            };
        }

        const message = event.data;

        // Validate message structure
        if (!message || typeof message !== 'object') {
            return {
                valid: false,
                reason: 'Invalid message structure'
            };
        }

        // Validate required fields
        if (!message.source || message.source !== 'rankingcoach-registration') {
            return {
                valid: false,
                reason: 'Invalid or missing source'
            };
        }

        if (!message.type || typeof message.type !== 'string') {
            return {
                valid: false,
                reason: 'Invalid or missing type'
            };
        }

        if (!message.sessionId) {
            return {
                valid: false,
                reason: 'Missing sessionId'
            };
        }

        // Validate session ID matches
        if (message.sessionId !== state.sessionId) {
            return {
                valid: false,
                reason: 'Session ID mismatch'
            };
        }

        // Validate timestamp
        if (!message.timestamp || typeof message.timestamp !== 'number') {
            return {
                valid: false,
                reason: 'Invalid or missing timestamp'
            };
        }

        const messageAge = Date.now() - message.timestamp;
        if (messageAge > CONFIG.MESSAGE_MAX_AGE) {
            return {
                valid: false,
                reason: 'Message too old: ' + messageAge + 'ms'
            };
        }

        if (messageAge < 0) {
            return {
                valid: false,
                reason: 'Message timestamp in future'
            };
        }

        return { valid: true };
    }

    /**
     * Route message to appropriate handler based on type
     * @param {Object} message - Validated message object
     */
    function handleRegistrationMessage(message) {
        console.log(CONFIG.LOG_PREFIX, 'Handling message type:', message.type);

        switch (message.type) {
            case 'registration.ready':
                console.log(CONFIG.LOG_PREFIX, 'Registration page is ready');
                break;

            case 'registration.complete':
                handleRegistrationComplete(message);
                break;

            case 'registration.error':
                handleRegistrationError(message);
                break;

            case 'registration.cancelled':
                handleRegistrationCancelled(message);
                break;

            default:
                console.warn(CONFIG.LOG_PREFIX, 'Unknown message type:', message.type);
        }
    }

    /**
     * Handle successful registration completion
     * @param {Object} message - Registration complete message
     */
    function handleRegistrationComplete(message) {
        console.log(CONFIG.LOG_PREFIX, 'Registration completed successfully:', message.payload);

        // Close registration window
        if (state.registrationWindow && !state.registrationWindow.closed) {
            state.registrationWindow.close();
        }

        // Cleanup session
        cleanupRegistrationSession();

        // Execute custom success callback if provided, otherwise fallback to reload
        if (callbacks.onSuccess && typeof callbacks.onSuccess === 'function') {
            console.log(CONFIG.LOG_PREFIX, 'Executing custom success callback');
            callbacks.onSuccess(message.payload);
        } else {
            console.log(CONFIG.LOG_PREFIX, 'No custom callback provided, reloading page');
            window.location.reload();
        }
    }

    /**
     * Handle registration error
     * @param {Object} message - Registration error message
     */
    function handleRegistrationError(message) {
        const errorMessage = message.payload && message.payload.message
            ? message.payload.message
            : 'An error occurred during registration';

        console.error(CONFIG.LOG_PREFIX, 'Registration error:', errorMessage);

        // Close registration window
        if (state.registrationWindow && !state.registrationWindow.closed) {
            state.registrationWindow.close();
        }

        // Cleanup session
        cleanupRegistrationSession();

        // Execute custom error callback if provided, otherwise show alert
        if (callbacks.onError && typeof callbacks.onError === 'function') {
            console.log(CONFIG.LOG_PREFIX, 'Executing custom error callback');
            callbacks.onError(errorMessage, message.payload);
        } else {
            alert('Registration Error: ' + errorMessage);
        }
    }

    /**
     * Handle registration cancellation
     * @param {Object} message - Registration cancelled message
     */
    function handleRegistrationCancelled(message) {
        console.log(CONFIG.LOG_PREFIX, 'Registration cancelled by user');

        // Close registration window if still open
        if (state.registrationWindow && !state.registrationWindow.closed) {
            state.registrationWindow.close();
        }

        // Cleanup session
        cleanupRegistrationSession();

        // Execute custom cancel callback if provided
        if (callbacks.onCancel && typeof callbacks.onCancel === 'function') {
            console.log(CONFIG.LOG_PREFIX, 'Executing custom cancel callback');
            callbacks.onCancel(message.payload);
        } else {
            console.log(CONFIG.LOG_PREFIX, 'Registration process cancelled');
        }
    }

    /**
     * Monitor registration window for manual closure
     */
    function startWindowClosureMonitoring() {
        // Clear any existing interval
        if (state.windowCheckInterval) {
            clearInterval(state.windowCheckInterval);
        }

        state.windowCheckInterval = setInterval(function () {
            if (state.registrationWindow && state.registrationWindow.closed) {
                console.log(CONFIG.LOG_PREFIX, 'Registration window was closed manually');
                handleWindowClosed();
            }
        }, CONFIG.WINDOW_CHECK_INTERVAL);
    }

    /**
     * Handle manual window closure
     */
    function handleWindowClosed() {
        console.log(CONFIG.LOG_PREFIX, 'Handling window closure');
        cleanupRegistrationSession();
    }

    /**
     * Cleanup registration session and state
     */
    function cleanupRegistrationSession() {
        console.log(CONFIG.LOG_PREFIX, 'Cleaning up registration session');

        // Clear intervals
        if (state.windowCheckInterval) {
            clearInterval(state.windowCheckInterval);
            state.windowCheckInterval = null;
        }

        // Clear timeout
        if (state.sessionTimeout) {
            clearTimeout(state.sessionTimeout);
            state.sessionTimeout = null;
        }

        // Remove message listener
        window.removeEventListener('message', handlePostMessage);

        // Reset state
        state.registrationWindow = null;
        state.sessionId = null;
        state.isRegistrationInProgress = false;

        console.log(CONFIG.LOG_PREFIX, 'Cleanup complete');
    }

    /**
     * Initialize the registration handler
     * @param {Object} config - Configuration object
     * @param {Function} config.onSuccess - Callback for successful registration
     * @param {Function} config.onError - Callback for registration errors
     * @param {Function} config.onCancel - Callback for registration cancellation
     */
    function init(config) {
        console.log(CONFIG.LOG_PREFIX, 'Initializing registration handler with custom config');

        // Store callbacks if provided
        if (config) {
            if (config.onSuccess) callbacks.onSuccess = config.onSuccess;
            if (config.onError) callbacks.onError = config.onError;
            if (config.onCancel) callbacks.onCancel = config.onCancel;
        }

        // Setup DOM ready handler
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupUI);
        } else {
            setupUI();
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', function () {
            if (state.isRegistrationInProgress) {
                cleanupRegistrationSession();
            }
        });
    }

    /**
     * Setup UI elements and event listeners
     */
    function setupUI() {
        console.log(CONFIG.LOG_PREFIX, 'Setting up UI elements');

        const upgradeBtn = document.getElementById('dc-plan-upgrade-btn');
        if (upgradeBtn) {
            upgradeBtn.addEventListener('click', openRegistrationWindow);
            console.log(CONFIG.LOG_PREFIX, 'Upgrade button listener attached');
        } else {
            console.warn(CONFIG.LOG_PREFIX, 'Upgrade button not found');
        }
    }

    // Public API
    return {
        init: init,
        openRegistrationWindow: openRegistrationWindow,
        openWindow: openWindow,
        openLoadingWindow: openLoadingWindow,
        navigateWindow: navigateWindow,
        closeWindow: closeWindow
    };

})();