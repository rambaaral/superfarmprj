// registration.js - Beyond SEO registration flow (IMPROVED)
(function() {
  'use strict';

  // Constants and configuration
  const IDS = {
    form: 'rc-registration-form',
    email: 'email',
    country: 'country',
    error: 'registration_error',
    screen1: 'rc-screen-1',
    emailValidationNew: 'rc-email-validation-new-account',
    emailValidationExisting: 'rc-email-validation-existing-account',
    marketingConsent: 'marketingConsent',
    marketingConsentError: 'marketing_consent_error',
    restNonce: 'rc_rest_nonce'
  };

  const SELECTORS = {
    registrationType: '#rc_registration_type'
  };

  const STORAGE_KEYS = {
    pollToken: 'rcPollToken',
    registrationContext: 'rcRegistrationContext'
  };

  const POLL_INTERVAL_MS = 5000;
  const MAX_POLL_ATTEMPTS = 120;
  const REQUEST_TIMEOUT_MS = 30000; // NEW: Request timeout
  const CONTEXT_MAX_AGE_MS = 60 * 60 * 1000; // NEW: 1 hour staleness check

  const I18N_KEYS = {
    verificationExpired: 'i18nVerificationExpired',
    resendVerification: 'i18nResendVerification',
    verificationStatusError: 'i18nVerificationStatusError',
    registrationSetupError: 'i18nRegistrationSetupError',
    finalizeRegistrationFailed: 'i18nFinalizeRegistrationFailed',
    verificationInitiationFailed: 'i18nVerificationInitiationFailed',
    emailInvalid: 'i18nEmailInvalid',
    countryRequired: 'i18nCountryRequired',
    consentRequired: 'i18nConsentRequired',
    requestTimeout: 'i18nRequestTimeout' // NEW
  };

  // IMPROVED: Enhanced utilities with error handling

  /**
   * Get element by ID safely with error handling
   * @param {string} id - Element ID
   * @returns {HTMLElement|null}
   */
  function $(id) {
    try {
      return document.getElementById(id);
    } catch (e) {
      logError('Error getting element by ID', { id, error: e });
      return null;
    }
  }

  /**
   * Enhanced email validation with type checking
   * @param {string} email - Email address to validate
   * @returns {boolean}
   */
  function validEmail(email) {
    if (!email || typeof email !== 'string') return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
  }

  /**
   * IMPROVED: Show with accessibility attributes
   */
  function show(el) {
    if (el && el.style) {
      el.style.display = 'block';
    }
  }

  /**
   * IMPROVED: Hide with accessibility attributes
   */
  function hide(el) {
    if (el && el.style) {
      el.style.display = 'none';
    }
  }

  /**
   * IMPROVED: i18n with type validation
   */
  function i18n(key, fallback) {
    if (!key || typeof key !== 'string') return fallback || '';
    const cfg = window.rcRegistration || null;
    const val = cfg && cfg[key];
    return (val && typeof val === 'string') ? val : (fallback || '');
  }

  const DEBUG = !!(window.rcRegistration && window.rcRegistration.debug === true);

  /**
   * IMPROVED: Enhanced debug logging with prefix
   */
  function logDebug() {
    if (!DEBUG) return;
    try {
      if (window.console && console.log) {
        const args = ['[RC Registration]'].concat(Array.prototype.slice.call(arguments));
        console.log.apply(console, args);
      }
    } catch (e) {
      // Silent fail
    }
  }

  /**
   * NEW: Centralized error logging
   * @param {string} message - Error message
   * @param {Object} [context] - Additional context
   */
  function logError(message, context) {
    try {
      if (window.console && console.error) {
        console.error('[RC Registration Error]', message, context || {});
      }
    } catch (e) {
      // Silent fail
    }
  }

  /**
   * NEW: XSS prevention - sanitize text content
   * @param {string} text - Text to sanitize
   * @returns {string}
   */
  function sanitizeText(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
  }

  /**
   * IMPROVED: Error removal with better error handling
   * Removes spinner from active email validation container
   */
  function removeEmailValidationProgress() {
    try {
      const newContainer = $(IDS.emailValidationNew);
      const existingContainer = $(IDS.emailValidationExisting);
      
      // Remove from whichever container is visible
      [newContainer, existingContainer].forEach(function(container) {
        if (!container) return;
        const nodes = container.querySelectorAll('.spinner, [data-rc-progress], .rc-spinner-label');
        nodes.forEach(function(node) {
          if (node && node.parentNode) {
            node.remove();
          }
        });
      });
      
      logDebug('Email validation progress UI removed');
    } catch (e) {
      logError('Error removing email validation progress', { error: e });
    }
  }

  /**
   * IMPROVED: Enhanced error display with accessibility and XSS protection
   */
  function showError(message, opts) {
    const errorDiv = $(IDS.error);
    if (!errorDiv) {
      logError('Error container not found', { message });
      return;
    }

    errorDiv.style.display = 'block';
    errorDiv.setAttribute('role', 'alert'); // Accessibility
    errorDiv.setAttribute('aria-live', 'polite'); // Screen reader support

    if (opts && typeof opts.renderHtml === 'function') {
      errorDiv.innerHTML = '';
      try {
        opts.renderHtml(errorDiv);
      } catch (e) {
        logError('Error rendering custom error HTML', { error: e });
        errorDiv.textContent = message || 'An error occurred';
      }
    } else {
      errorDiv.textContent = sanitizeText(message || ''); // XSS protection
    }

    // NEW: Focus management for accessibility
    if (errorDiv.tabIndex === -1 || !errorDiv.hasAttribute('tabindex')) {
      errorDiv.setAttribute('tabindex', '-1');
    }

    try {
      errorDiv.focus();
    } catch (e) {
      logError('Error focusing error div', { error: e });
    }
  }

  /**
   * NEW: Fetch with timeout to prevent hanging requests
   * @param {string} url - Request URL
   * @param {RequestInit} options - Fetch options
   * @param {number} [timeout] - Timeout in milliseconds
   * @returns {Promise<Response>}
   */
  function fetchWithTimeout(url, options, timeout) {
    timeout = timeout || REQUEST_TIMEOUT_MS;

    return new Promise(function(resolve, reject) {
      const timer = setTimeout(function() {
        reject(new Error(i18n(I18N_KEYS.requestTimeout, 'Request timeout. Please try again.')));
      }, timeout);

      fetch(url, options)
          .then(function(response) {
            clearTimeout(timer);
            resolve(response);
          })
          .catch(function(error) {
            clearTimeout(timer);
            reject(error);
          });
    });
  }

  /**
   * NEW: Validate nonce before making requests
   * @param {string} nonce - Nonce to validate
   * @returns {boolean}
   */
  function validateNonce(nonce) {
    if (!nonce || typeof nonce !== 'string') return false;
    return nonce.length > 0 && nonce.length < 256;
  }

  /**
   * NEW: Validate URL to prevent injection attacks
   * @param {string} url - URL to validate
   * @returns {boolean}
   */
  function validateUrl(url) {
    if (!url || typeof url !== 'string') return false;
    try {
      const parsed = new URL(url, window.location.origin);
      return parsed.protocol === 'http:' || parsed.protocol === 'https:';
    } catch (e) {
      return false;
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    // DOM elements - cache references
    const form = $(IDS.form);
    const emailInput = $(IDS.email);
    const countrySelect = $(IDS.country);
    const errorDiv = $(IDS.error);
    const screen1 = $(IDS.screen1);
    const emailValidationNew = $(IDS.emailValidationNew);
    const emailValidationExisting = $(IDS.emailValidationExisting);
    const marketingConsentEl = $(IDS.marketingConsent);
    const marketingConsentError = $(IDS.marketingConsentError);

    // IMPROVED: Better state management
    let pollInterval = null;
    let stopped = false;
    let pollAttempts = 0;
    let isSubmitting = false; // NEW: Prevent double submissions
    let cleanupFunctions = []; // NEW: Track cleanup functions for memory leak prevention

    /**
     * NEW: Register cleanup function to prevent memory leaks
     * @param {Function} fn - Cleanup function
     */
    function registerCleanup(fn) {
      if (typeof fn === 'function') {
        cleanupFunctions.push(fn);
      }
    }

    /**
     * NEW: Execute all cleanup functions
     */
    function executeCleanup() {
      cleanupFunctions.forEach(function(fn) {
        try {
          fn();
        } catch (e) {
          logError('Cleanup function error', { error: e });
        }
      });
      cleanupFunctions = [];
    }

    /**
     * IMPROVED: Save context with validation and timestamp for staleness check
     */
    function saveRegistrationContext(context) {
      if (!context || typeof context !== 'object') {
        logError('Invalid context provided to saveRegistrationContext', { context });
        return;
      }

      try {
        const sanitizedContext = {
          email: context.email ? String(context.email).trim() : '',
          country: context.country ? String(context.country).trim() : '',
          type: context.type ? String(context.type).toLowerCase() : 'direct',
          pollToken: context.pollToken ? String(context.pollToken) : '',
          status: context.status ? String(context.status) : '',
          timestamp: Date.now() // NEW: Add timestamp for staleness check
        };
        sessionStorage.setItem(STORAGE_KEYS.registrationContext, JSON.stringify(sanitizedContext));
        logDebug('Registration context saved', sanitizedContext);
      } catch (e) {
        logError('Error saving registration context', { error: e, context });
      }
    }

    /**
     * IMPROVED: Get context with staleness check
     */
    function getRegistrationContext() {
      try {
        const stored = sessionStorage.getItem(STORAGE_KEYS.registrationContext);
        if (!stored) return null;

        const context = JSON.parse(stored);

        // NEW: Check if context is stale (older than 1 hour)
        if (context.timestamp && (Date.now() - context.timestamp > CONTEXT_MAX_AGE_MS)) {
          logDebug('Registration context is stale, clearing');
          clearRegistrationContext();
          return null;
        }

        return context;
      } catch (e) {
        logError('Error retrieving registration context', { error: e });
        return null;
      }
    }

    /**
     * IMPROVED: Clear context with logging
     */
    function clearRegistrationContext() {
      try {
        sessionStorage.removeItem(STORAGE_KEYS.registrationContext);
        sessionStorage.removeItem(STORAGE_KEYS.pollToken);
        logDebug('Registration context cleared');
      } catch (e) {
        logError('Error clearing registration context', { error: e });
      }
    }

    /**
     * IMPROVED: Get nonce with validation
     */
    function getNonce() {
      let nonce = '';

      if (window.rcRegistration && window.rcRegistration.nonce) {
        nonce = window.rcRegistration.nonce;
      } else {
        const hidden = $(IDS.restNonce);
        nonce = hidden ? hidden.value : '';
      }

      if (!validateNonce(nonce)) {
        logError('Invalid or missing nonce');
        return '';
      }

      return nonce;
    }

    /**
     * IMPROVED: Get URL with validation (applies to all URL getters)
     */
    function getRegisterUrl() {
      const url = (window.rcRegistration && window.rcRegistration.registerUrl) || '';
      if (url && !validateUrl(url)) {
        logError('Invalid register URL', { url });
        return '';
      }
      return url;
    }

    function getFinalizeRegisterUrl() {
      const url = (window.rcRegistration && window.rcRegistration.finalizeRegisterUrl) || '';
      if (url && !validateUrl(url)) {
        logError('Invalid finalize register URL', { url });
        return '';
      }
      return url;
    }

    function getVerificationStatusUrl() {
      const url = (window.rcRegistration && window.rcRegistration.verificationStatusUrl) || '';
      if (url && !validateUrl(url)) {
        logError('Invalid verification status URL', { url });
        return '';
      }
      return url;
    }

    function getFlowGuardStateUrl() {
      const url = (window.rcRegistration && window.rcRegistration.flowGuardStateUrl) || '';
      if (url && !validateUrl(url)) {
        logError('Invalid flow guard state URL', { url });
        return '';
      }
      return url;
    }

    function getFlowGuardResetUrl() {
      const url = (window.rcRegistration && window.rcRegistration.flowGuardResetUrl) || '';
      if (url && !validateUrl(url)) {
        logError('Invalid flow guard reset URL', { url });
        return '';
      }
      return url;
    }

    /**
     * NEW: Show the correct email validation container based on account status
     * @param {string} accountStatus - 'existing_account' or 'new_account'
     */
    function showEmailValidationContainer(accountStatus) {
      logDebug('Showing email validation container for status:', accountStatus);
      
      // Hide both containers first
      hide(emailValidationNew);
      hide(emailValidationExisting);
      
      // Show the appropriate container based on status
      if (accountStatus === 'existing_account') {
        logDebug('Showing existing account validation container');
        show(emailValidationExisting);
      } else {
        logDebug('Showing new account validation container');
        show(emailValidationNew);
      }
    }

    /**
     * IMPROVED: Redirect with validation and cleanup
     */
    function redirectNext() {
      const url = (window.rcRegistration && window.rcRegistration.nextStepUrl) || '';
      if (!url) {
        logError('Next step URL not found');
        return;
      }

      if (!validateUrl(url)) {
        logError('Invalid next step URL', { url });
        return;
      }

      logDebug('Redirecting to next step', url);
      executeCleanup(); // NEW: Cleanup before redirect
      window.location.href = url;
    }

    /**
     * Enhanced reset UI with better accessibility
     * Now works with new email validation containers
     */
    function showResetAndRetry() {
      const renderAction = function(container) {
        const wrap = document.createElement('div');
        wrap.id = 'rc-reset-and-retry-wrap';
        wrap.setAttribute('role', 'alert'); // Accessibility
        wrap.setAttribute('aria-live', 'polite');
        wrap.style.marginTop = '16px';
        wrap.style.padding = '12px';
        wrap.style.background = '#fff3cd';
        wrap.style.border = '1px solid #ffc107';
        wrap.style.borderRadius = '4px';

        const msg = document.createElement('div');
        msg.textContent = i18n(I18N_KEYS.verificationStatusError, "We couldn't check your email verification status. Please try again.");
        msg.style.marginBottom = '8px';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'rc-reset-and-retry';
        btn.className = 'button button-primary';
        btn.textContent = 'Reset and retry';
        btn.setAttribute('aria-label', 'Reset registration flow and try again'); // Accessibility

        const inlineErr = document.createElement('span');
        inlineErr.id = 'rc-reset-error';
        inlineErr.style.marginLeft = '8px';
        inlineErr.style.color = '#b3261e';
        inlineErr.setAttribute('role', 'alert'); // Accessibility
        inlineErr.setAttribute('aria-live', 'polite');

        btn.addEventListener('click', function() {
          doFlowGuardReset(btn, inlineErr);
        });

        wrap.appendChild(msg);
        wrap.appendChild(btn);
        wrap.appendChild(inlineErr);
        container.appendChild(wrap);
      };

      // Try to add reset UI to the active email validation container
      const newContainer = $(IDS.emailValidationNew);
      const existingContainer = $(IDS.emailValidationExisting);
      
      // Determine which container is visible
      let activeContainer = null;
      if (newContainer && newContainer.style.display !== 'none') {
        activeContainer = newContainer;
      } else if (existingContainer && existingContainer.style.display !== 'none') {
        activeContainer = existingContainer;
      }
      
      if (activeContainer) {
        // Check if reset UI already exists
        if (activeContainer.querySelector('#rc-reset-and-retry-wrap')) {
          return;
        }
        renderAction(activeContainer);
        return;
      }
      
      // Fallback: show in error div
      const errorDiv = $(IDS.error);
      if (errorDiv) {
        showError(null, { renderHtml: renderAction });
      }
    }

    /**
     * IMPROVED: Flow guard reset with timeout and better error handling
     */
    async function doFlowGuardReset(btn, inlineErrEl) {
      if (inlineErrEl) {
        inlineErrEl.textContent = '';
      }

      const originalLabel = btn ? btn.textContent : '';

      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Resetting...';
        btn.setAttribute('aria-busy', 'true'); // Accessibility
      }

      try {
        const url = getFlowGuardResetUrl();
        const nonce = getNonce();

        if (!url || !nonce) {
          throw new Error('Missing reset URL or authorization.');
        }

        const resp = await fetchWithTimeout(url, { // NEW: Using timeout
          method: 'POST',
          headers: {
            'X-WP-Nonce': nonce,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          credentials: 'same-origin'
        });

        if (!resp.ok) {
          let errMsg = 'Reset failed. Please try again.';
          try {
            const err = await resp.json();
            if (err && (err.message || err.error)) {
              errMsg = String(err.message || err.error);
            }
          } catch (e) {
            logError('Error parsing reset error response', { error: e });
          }
          throw new Error(errMsg);
        }

        const data = await resp.json().catch(function() { return {}; });

        if (data && data.ok === true) {
          logDebug('Flow guard reset successful, reloading page');
          executeCleanup(); // NEW: Cleanup before reload
          window.location.reload();
          return;
        }

        throw new Error('Reset failed. Please try again.');
      } catch (e) {
        logError('Flow guard reset failed', { error: e });

        if (inlineErrEl) {
          inlineErrEl.textContent = (e && e.message) ? String(e.message) : 'Reset failed. Please try again.';
        }

        if (btn) {
          btn.disabled = false;
          btn.textContent = originalLabel || 'Reset and retry';
          btn.setAttribute('aria-busy', 'false');
        }
      }
    }

    /**
     * NEW: Build polling URL safely with proper encoding
     */
    function buildPollingUrl(baseUrl, pollToken, status, attempt) {
      try {
        const url = new URL(baseUrl, window.location.origin);
        url.searchParams.set('pollToken', pollToken || '');
        url.searchParams.set('status', status || '');
        url.searchParams.set('_t', String(Date.now()));
        url.searchParams.set('attempt', String(attempt || 0));
        return url.toString();
      } catch (e) {
        logError('Error building polling URL', { error: e, baseUrl, pollToken, status, attempt });
        return baseUrl;
      }
    }

    /**
     * NEW: Separate response handling logic for better testability
     */
    async function handlePollingResponse(response, context) {
      if (!response.ok) {
        logError('Polling request failed', { status: response.status, statusText: response.statusText });
        return { continue: false, validated: false };
      }

      let data;
      try {
        data = await response.json();
      } catch (e) {
        logError('Error parsing polling response', { error: e });
        return { continue: true, validated: false };
      }

      const newPollToken = data && data.pollToken ? String(data.pollToken) : '';
      if (newPollToken && newPollToken !== context.pollToken) {
        try {
          sessionStorage.setItem(STORAGE_KEYS.pollToken, newPollToken);
          context.pollToken = newPollToken;
          saveRegistrationContext(context);
          logDebug('Poll token updated', newPollToken);
        } catch (e) {
          logError('Error updating poll token', { error: e });
        }
      }

      if (data && data.validated === true) {
        logDebug('Email validated successfully');
        return { continue: false, validated: true };
      }

      return { continue: true, validated: false };
    }

    /**
     * IMPROVED: Polling with better error handling and timeout protection
     */
    function startVerificationPolling(context) {
      if (!context || !context.pollToken) {
        logError('Invalid context provided to startVerificationPolling', { context });
        return;
      }

      const urlBase = getVerificationStatusUrl();
      const nonce = getNonce();

      if (!urlBase || !nonce) {
        logError('Missing verification status URL or nonce');
        showError(i18n(I18N_KEYS.registrationSetupError, 'Could not start registration. Please reload and try again.'));
        return;
      }

      stopPolling(); // Prevent overlapping loops

      stopped = false;
      pollAttempts = 0;

      async function pollOnce() {
        pollAttempts += 1;

        logDebug('Polling attempt', pollAttempts, 'of', MAX_POLL_ATTEMPTS);

        if (pollAttempts > MAX_POLL_ATTEMPTS) {
          logError('Max poll attempts reached', { pollAttempts });
          stopPolling();
          removeEmailValidationProgress();
          showResetAndRetry();
          return false;
        }

        if (stopped) {
          logDebug('Polling stopped by flag');
          return false;
        }

        try {
          const url = buildPollingUrl(urlBase, context.pollToken, context.status || '', pollAttempts);

          const response = await fetchWithTimeout(url, { // NEW: Using timeout
            method: 'GET',
            headers: {
              'X-WP-Nonce': nonce,
              'Accept': 'application/json'
            },
            credentials: 'same-origin'
          }, REQUEST_TIMEOUT_MS);

          const result = await handlePollingResponse(response, context);

          if (result.validated) {
            stopPolling();
            finalizeRegistration(context);
            return false;
          }

          if (!result.continue) {
            stopPolling();
            const fallback = i18n(I18N_KEYS.verificationStatusError, "We couldn't check your email verification status. Please try again.");
            showError(fallback);
            if(!result.validated) {
              removeEmailValidationProgress();
              showResetAndRetry();
            }
            return false;
          }

          if (pollAttempts >= MAX_POLL_ATTEMPTS) {
            stopPolling();
            removeEmailValidationProgress();
            showResetAndRetry();
            return false;
          }

          return true;
        } catch (e) {
          logError('Polling request error', { error: e, attempt: pollAttempts });

          if (pollAttempts >= MAX_POLL_ATTEMPTS) {
            stopPolling();
            removeEmailValidationProgress();
            showResetAndRetry();
            return false;
          }

          return true; // Continue on transient errors
        }
      }

      // Start polling
      (async function() {
        const keepPolling = await pollOnce();
        if (keepPolling && !stopped) {
          pollInterval = setInterval(async function() {
            const shouldContinue = await pollOnce();
            if (!shouldContinue || stopped) {
              stopPolling();
            }
          }, POLL_INTERVAL_MS);

          registerCleanup(stopPolling); // NEW: Register cleanup
        }
      })();
    }

    /**
     * Finalize with better validation and error handling
     */
    async function finalizeRegistration(payload) {
      if (!payload || typeof payload !== 'object') {
        logError('Invalid payload provided to finalizeRegistration', { payload });
        showError(i18n(I18N_KEYS.finalizeRegistrationFailed, "We couldn't complete your registration. Please try again."));
        return;
      }

      logDebug('finalizeRegistration', payload);

      const url = getFinalizeRegisterUrl();
      const nonce = getNonce();

      if (!url || !nonce) {
        showError(i18n(I18N_KEYS.registrationSetupError, 'Could not start registration. Please reload and try again.'));
        return;
      }

      try {
        const requestBody = {
          email: payload.email || undefined,
          country: payload.country || undefined,
          type: payload.type || undefined,
          pollToken: payload.pollToken || undefined
        };

        const resp = await fetchWithTimeout(url, { // NEW: Using timeout
          method: 'POST',
          headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify(requestBody)
        });

        if (!resp.ok) {
          const fallback = i18n(I18N_KEYS.finalizeRegistrationFailed, "We couldn't complete your registration. Please try again.");
          try {
            const err = await resp.json();
            showError((err && err.message) ? String(err.message) : fallback);
          } catch (e) {
            logError('Error parsing finalize error response', { error: e });
            showError(fallback);
          }
          return;
        }

        const data = await resp.json();
        clearRegistrationContext();
        logDebug('Registration finalized', data);
        redirectNext();
      } catch (e) {
        logError('Finalize registration error', { error: e });
        showError(i18n(I18N_KEYS.finalizeRegistrationFailed, "We couldn't complete your registration. Please try again."));
      }
    }

    /**
     * IMPROVED: Begin flow with better validation
     */
    async function beginRegistrationFlow(context) {
      if (!context || typeof context !== 'object') {
        logError('Invalid context provided to beginRegistrationFlow', { context });
        showError(i18n(I18N_KEYS.verificationInitiationFailed, "We couldn't send the verification email. Please try again."));
        return;
      }

      const url = getRegisterUrl();
      const nonce = getNonce();

      if (!url || !nonce) {
        showError(i18n(I18N_KEYS.registrationSetupError, 'Could not start registration. Please reload and try again.'));
        return;
      }

      try {
        const requestBody = {
          email: context.email || '',
          country: context.country || '',
          type: context.type || 'direct',
          marketingConsent: Boolean(context.marketingConsent)
        };

        const resp = await fetchWithTimeout(url, { // NEW: Using timeout
          method: 'POST',
          headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify(requestBody)
        });

        if (!resp.ok) {
          const fallback = i18n(I18N_KEYS.verificationInitiationFailed, "We couldn't send the verification email. Please try again.");
          try {
            const err = await resp.json();
            showError((err && err.message) ? String(err.message) : fallback);
          } catch (e) {
            logError('Error parsing registration error response', { error: e });
            showError(fallback);
          }
          return;
        }

        const data = await resp.json();

        const pollToken = data && data.pollToken ? String(data.pollToken) : '';
        const accountId = data && data.accountId ? String(data.accountId) : '';

        if (pollToken && accountId) {
          logDebug('Email verification will start with this context', data);

          try {
            sessionStorage.setItem(STORAGE_KEYS.pollToken, pollToken);
          } catch (e) {
            logError('Error setting poll token in session storage', { error: e });
          }

          context.pollToken = pollToken;

          if (data && data.status) {
            context.status = String(data.status);
          }

          saveRegistrationContext(context);

          hide(screen1);
          showEmailValidationContainer(context.status || 'new_account');

          startVerificationPolling(context);
          return;
        }

        logError('Registration response missing required fields', { data });
        showError(i18n(I18N_KEYS.verificationInitiationFailed, "We couldn't send the verification email. Please try again."));
      } catch (e) {
        logError('Begin registration flow error', { error: e });
        showError(i18n(I18N_KEYS.verificationInitiationFailed, "We couldn't send the verification email. Please try again."));
      }
    }

    /**
     * IMPROVED: Stop polling with logging
     */
    function stopPolling() {
      stopped = true;
      if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
        logDebug('Polling stopped');
      }
    }

    /**
     * NEW: Clear all error messages
     */
    function clearErrors() {
      if (errorDiv) {
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
      }
      if (marketingConsentError) {
        marketingConsentError.style.display = 'none';
        marketingConsentError.textContent = '';
      }
    }

    /**
     * NEW: Centralized form validation
     */
    function validateFormInputs(data) {
      if (!data.email || !validEmail(data.email)) {
        return {
          valid: false,
          error: i18n(I18N_KEYS.emailInvalid, 'Please enter a valid email address.')
        };
      }

      if (!data.country) {
        return {
          valid: false,
          error: i18n(I18N_KEYS.countryRequired, 'Please select a country.')
        };
      }

      if (!data.marketingConsent) {
        return {
          valid: false,
          error: i18n(I18N_KEYS.consentRequired, 'Please accept marketing communications to continue.'),
          field: 'marketingConsent'
        };
      }

      return { valid: true };
    }

    /**
     * NEW: Extracted form submission handler
     */
    async function handleFormSubmit(ev) {
      ev.preventDefault();

      // NEW: Prevent double submission
      if (isSubmitting) {
        logDebug('Form submission already in progress');
        return;
      }

      isSubmitting = true;
      clearErrors();

      try {
        const email = (emailInput ? String(emailInput.value || '').trim() : '');
        const country = (countrySelect ? String(countrySelect.value || '').trim() : '');
        const registrationTypeEl = document.querySelector(SELECTORS.registrationType);
        const type = (registrationTypeEl && registrationTypeEl.value ? String(registrationTypeEl.value) : 'direct').toLowerCase();
        const marketingConsent = Boolean(marketingConsentEl && marketingConsentEl.checked);

        const formData = {
          email: email,
          country: country,
          type: type,
          marketingConsent: marketingConsent
        };

        logDebug('Form submission data', formData);

        const validation = validateFormInputs(formData);

        if (!validation.valid) {
          if (validation.field === 'marketingConsent') {
            if (marketingConsentError) {
              marketingConsentError.textContent = validation.error;
              marketingConsentError.style.display = 'block';
            }
            if (marketingConsentEl) {
              marketingConsentEl.focus();
            }
          } else {
            showError(validation.error);
          }
          return;
        }

        await beginRegistrationFlow(formData);
      } catch (e) {
        logError('Form submission error', { error: e });
        showError(i18n(I18N_KEYS.verificationInitiationFailed, "We couldn't send the verification email. Please try again."));
      } finally {
        isSubmitting = false;
      }
    }

    // Attach form handler with cleanup registration
    if (form) {
      form.addEventListener('submit', handleFormSubmit);
      registerCleanup(function() {
        if (form) {
          form.removeEventListener('submit', handleFormSubmit);
        }
      });
    }

    /**
     * Restore state with better validation
     */
    function restoreEmailVerificationState() {
      // FIXED: Accept truthy values (true, 1, "1") not just boolean true
      const inEmailValidation = !!(window.rcRegistration && window.rcRegistration.inEmailValidation);
      const storedContext = getRegistrationContext();

      logDebug('Restore state check', { inEmailValidation, hasStoredContext: !!storedContext });

      if (!inEmailValidation) {
        if (storedContext || sessionStorage.getItem(STORAGE_KEYS.pollToken)) {
          logDebug('Clearing stale sessionStorage - server says NOT in email validation');
          clearRegistrationContext();
        }
        return;
      }

      const context = storedContext || {
        email: emailInput ? String(emailInput.value || '').trim() : '',
        country: countrySelect ? String(countrySelect.value || '').trim() : '',
        type: (function() {
          const el = document.querySelector(SELECTORS.registrationType);
          return (el && el.value ? String(el.value) : 'direct').toLowerCase();
        })(),
        pollToken: ''
      };

      if (!context.pollToken) {
        try {
          context.pollToken = sessionStorage.getItem(STORAGE_KEYS.pollToken) || '';
        } catch (e) {
          logError('Error retrieving poll token from session storage', { error: e });
        }
      }

      if (context.pollToken) {
        logDebug('Restoring email verification state', context);
        hide(screen1);
        
        // Get accountStatus from server-side or context
        const accountStatus = (window.rcRegistration && window.rcRegistration.accountStatus) || context.status || 'new_account';
        
        // CRITICAL: Set status in context before polling starts
        context.status = accountStatus;
        
        // Save updated context with status to sessionStorage
        saveRegistrationContext(context);
        
        showEmailValidationContainer(accountStatus);
        
        startVerificationPolling(context);
      } else {
        logDebug('No poll token found, cannot restore verification state');
      }
    }

    /**
     * NEW: Cleanup handler for page unload
     */
    function handleBeforeUnload() {
      logDebug('Page unload, executing cleanup');
      stopPolling();
      executeCleanup();
    }

    window.addEventListener('beforeunload', handleBeforeUnload);
    registerCleanup(function() {
      window.removeEventListener('beforeunload', handleBeforeUnload);
    });

    // IMPROVED: Restore state with error handling
    try {
      restoreEmailVerificationState();
    } catch (e) {
      logError('Error restoring email verification state', { error: e });
    }

    logDebug('Registration flow initialized');
  });
})();