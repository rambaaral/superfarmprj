/**
 * Gutenberg Availability Checker
 * Comprehensive validation for Gutenberg editor presence and functionality
 */
class GutenbergAvailabilityChecker {
    static isGutenbergAvailable() {
        // Check if wp object exists
        if (typeof wp === 'undefined') {
            return false;
        }

        // Check if wp.data exists
        if (!wp.data) {
            return false;
        }

        // Check if core/editor store exists
        try {
            const editor = wp.data.select('core/editor');
            if (!editor) {
                return false;
            }
        } catch (error) {
            return false;
        }

        // Check if we're in the block editor context
        if (!document.body.classList.contains('block-editor-page')) {
            return false;
        }

        // Check if Gutenberg editor is actually loaded and functional
        try {
            const editorSelect = wp.data.select('core/editor');
            const blockEditorSelect = wp.data.select('core/block-editor');
            
            // Verify essential editor methods exist
            if (!editorSelect.getCurrentPostId || 
                !editorSelect.getEditedPostAttribute ||
                !wp.data.subscribe) {
                return false;
            }

            // Check if we can get current post data
            const postId = editorSelect.getCurrentPostId();
            if (!postId || postId <= 0) {
                return false;
            }

        } catch (error) {
            return false;
        }

        // Check if classic editor is active (mutual exclusion)
        if (document.getElementById('wp-content-wrap') && 
            document.getElementById('wp-content-wrap').classList.contains('tmce-active')) {
            return false;
        }

        return true;
    }

    static waitForGutenberg(callback, maxAttempts = 10, interval = 500) {
        let attempts = 0;
        
        const checkAvailability = () => {
            attempts++;
            
            if (this.isGutenbergAvailable()) {
                callback();
                return;
            }
            
            if (attempts >= maxAttempts) {
                return;
            }
            
            setTimeout(checkAvailability, interval);
        };
        
        checkAvailability();
    }
}

/**
 * Advanced Gutenberg Content Change Handler
 * Implements intelligent debouncing with human-friendly UX patterns
 */
class RankingCoachGutenbergHandler {
    constructor() {
        // Final validation before initialization
        if (!GutenbergAvailabilityChecker.isGutenbergAvailable()) {
            throw new Error('Cannot initialize handler - Gutenberg is not available');
        }
        
        this.config = {
            // Debounce delays (ms)
            TYPING_DELAY: 3000,        // While user is actively typing
            IDLE_DELAY: 3000,          // After user stops typing
            MAJOR_CHANGE_DELAY: 500,   // For significant content changes
            
            // Thresholds
            MIN_CONTENT_LENGTH: 50,    // Minimum content length to process
            MAJOR_CHANGE_THRESHOLD: 0.3, // 30% content change threshold
            
            // Timing
            MAX_PROCESSING_INTERVAL: 30000, // Max 30s between processing
            ACTIVITY_TIMEOUT: 5000,    // Consider user inactive after 5s
        };
        
        this.state = {
            lastContent: '',
            lastProcessedContent: '',
            lastActivity: Date.now(),
            isProcessing: false,
            contentChangeCount: 0,
            userIsTyping: false,
            lastSignificantChange: Date.now(),
        };
        
        this.timers = {
            debounce: null,
            activityCheck: null,
            forceProcess: null,
        };
        
        this.init();
    }
    
    init() {
        try {
            // Verify Gutenberg is still available during initialization
            if (!wp.data || !wp.data.subscribe) {
                throw new Error('wp.data.subscribe not available');
            }
            
            // Subscribe to Gutenberg data changes
            this.unsubscribe = wp.data.subscribe(() => this.handleContentChange());
            
            // Set up activity monitoring
            this.setupActivityMonitoring();
            
            // Set up periodic processing fallback
            this.setupPeriodicProcessing();
            
            // Cleanup on page unload
            window.addEventListener('beforeunload', () => this.cleanup());
            
        } catch (error) {
            this.cleanup();
            throw error;
        }
    }
    
    handleContentChange() {
        const currentContent = this.getCurrentContent();
        
        if (!this.isValidContent(currentContent)) {
            return;
        }
        
        const changeMetrics = this.analyzeContentChange(currentContent);
        this.updateActivityState();
        
        // Clear existing timers
        this.clearTimers();
        
        // Determine processing strategy based on change type
        if (changeMetrics.isMajorChange) {
            this.scheduleProcessing(this.config.MAJOR_CHANGE_DELAY, 'major-change');
        } else if (this.state.userIsTyping) {
            this.scheduleProcessing(this.config.TYPING_DELAY, 'typing');
        } else {
            this.scheduleProcessing(this.config.IDLE_DELAY, 'idle');
        }
        
        this.state.lastContent = currentContent;
    }
    
    getCurrentContent() {
        try {
            // Runtime check for Gutenberg availability
            if (!wp || !wp.data || !wp.data.select) {
                return '';
            }
            
            const editor = wp.data.select('core/editor');
            if (!editor || !editor.getEditedPostAttribute) {
                return '';
            }
            
            return editor.getEditedPostAttribute('content') || '';
        } catch (error) {
            return '';
        }
    }
    
    isValidContent(content) {
        return content && 
               typeof content === 'string' && 
               content.length >= this.config.MIN_CONTENT_LENGTH &&
               content !== this.state.lastContent;
    }
    
    analyzeContentChange(currentContent) {
        const lastContent = this.state.lastContent;
        const contentLength = currentContent.length;
        const lastLength = lastContent.length;
        
        // Calculate change percentage
        const lengthDiff = Math.abs(contentLength - lastLength);
        const changePercentage = lastLength > 0 ? lengthDiff / lastLength : 1;
        
        // Detect content similarity using simple heuristics
        const similarity = this.calculateSimilarity(currentContent, lastContent);
        
        // Determine if this is a major change
        const isMajorChange = changePercentage > this.config.MAJOR_CHANGE_THRESHOLD || 
                             similarity < 0.7;
        
        // Check for structural changes (blocks added/removed)
        const hasStructuralChange = this.detectStructuralChange(currentContent, lastContent);
        
        return {
            isMajorChange: isMajorChange || hasStructuralChange,
            changePercentage,
            similarity,
            hasStructuralChange,
            lengthDiff
        };
    }
    
    calculateSimilarity(str1, str2) {
        if (!str1 || !str2) return 0;
        
        // Simple similarity calculation based on common substrings
        const shorter = str1.length < str2.length ? str1 : str2;
        const longer = str1.length >= str2.length ? str1 : str2;
        
        if (shorter.length === 0) return 0;
        
        let matches = 0;
        const chunkSize = Math.max(10, Math.floor(shorter.length / 10));
        
        for (let i = 0; i <= shorter.length - chunkSize; i += chunkSize) {
            const chunk = shorter.substring(i, i + chunkSize);
            if (longer.includes(chunk)) {
                matches++;
            }
        }
        
        return matches / Math.ceil(shorter.length / chunkSize);
    }
    
    detectStructuralChange(current, previous) {
        // Detect block-level changes by counting block markers
        const currentBlocks = (current.match(/<!-- wp:/g) || []).length;
        const previousBlocks = (previous.match(/<!-- wp:/g) || []).length;
        
        return Math.abs(currentBlocks - previousBlocks) > 0;
    }
    
    updateActivityState() {
        const now = Date.now();
        const timeSinceLastActivity = now - this.state.lastActivity;
        
        // Update typing state
        this.state.userIsTyping = timeSinceLastActivity < this.config.ACTIVITY_TIMEOUT;
        this.state.lastActivity = now;
        this.state.contentChangeCount++;
    }
    
    setupActivityMonitoring() {
        // Monitor user activity to detect typing patterns
        this.timers.activityCheck = setInterval(() => {
            const now = Date.now();
            const timeSinceActivity = now - this.state.lastActivity;
            
            if (timeSinceActivity > this.config.ACTIVITY_TIMEOUT) {
                this.state.userIsTyping = false;
            }
        }, 1000);
    }
    
    setupPeriodicProcessing() {
        // Fallback processing to ensure content is eventually processed
        this.timers.forceProcess = setInterval(() => {
            const now = Date.now();
            const timeSinceLastProcess = now - this.state.lastSignificantChange;
            
            if (timeSinceLastProcess > this.config.MAX_PROCESSING_INTERVAL && 
                this.state.lastContent !== this.state.lastProcessedContent &&
                !this.state.isProcessing) {
                
                this.processContent('force-timeout');
            }
        }, this.config.MAX_PROCESSING_INTERVAL / 2);
    }
    
    scheduleProcessing(delay, reason) {
        this.timers.debounce = setTimeout(() => {
            this.processContent(reason);
        }, delay);
        
    }
    
    async processContent(reason) {
        if (this.state.isProcessing) {
            return;
        }
        
        this.state.isProcessing = true;
        const content = this.state.lastContent;
        
        try {

            // Show processing feedback
            this.showProcessingFeedback();

            // Save the content before triggering calculation
            await this.savePostContent();
            
            // Trigger SEO Optimiser calculation via AJAX
            // await this.triggerSEOOptimiser();
            
            // Update state
            this.state.lastProcessedContent = content;
            this.state.lastSignificantChange = Date.now();
            
        } catch (error) {
        } finally {
            this.state.isProcessing = false;
        }
    }
    
    async triggerSEOOptimiser() {
        try {
            const postId = this.getCurrentPostId();
            if (!postId) {
                return;
            }


            // Build the API URL with query parameters
            const apiUrl = `/wp-json/rankingcoach/seo/optimiser/${postId}?ref=editor&noCache=1&debug=1`;
            
            // Create the fetch request
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.rankingCoachReactData?.restNonce || '',
                },
                body: JSON.stringify({
                    content: this.state.lastContent,
                    source: 'gutenberg-content-change',
                    timestamp: Date.now()
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const apiResponse = await response.json();

            return apiResponse;
            
        } catch (error) {
        }
    }
    
    async savePostContent() {
        try {
            const editor = wp.data.select('core/editor');
            const coreSelect = wp.data.select('core');
            const dispatch = wp.data.dispatch('core/editor');
            
            if (!editor || !dispatch) {
                return false;
            }
            
            // Check if post has unsaved changes using different methods based on availability
            let hasUnsavedChanges = false;
            
            try {
                const postType = editor.getCurrentPostType();
                const postId = editor.getCurrentPostId();
                
                // Try the newer core method first
                if (coreSelect && coreSelect.hasEditsForEntityRecord) {
                    hasUnsavedChanges = coreSelect.hasEditsForEntityRecord('postType', postType, postId);
                } else if (editor.isEditedPostDirty) {
                    // Fallback to older editor method
                    hasUnsavedChanges = editor.isEditedPostDirty();
                } else {
                    // Force save if we can't determine dirty state
                    hasUnsavedChanges = true;
                }
            } catch (checkError) {
                hasUnsavedChanges = true;
            }
            
            if (!hasUnsavedChanges) {
                return true;
            }
            

            // Save the post
            await dispatch.savePost();
            
            // Wait for save to complete
            let saveAttempts = 0;
            const maxAttempts = 10;
            
            while (saveAttempts < maxAttempts) {
                let isSaving = false;
                let hasUnsaved = false;
                
                try {
                    // Check if currently saving
                    if (editor.isSavingPost) {
                        isSaving = editor.isSavingPost();
                    }
                    
                    // Check if still has unsaved changes
                    const postType = editor.getCurrentPostType();
                    const postId = editor.getCurrentPostId();
                    
                    if (coreSelect && coreSelect.hasEditsForEntityRecord) {
                        hasUnsaved = coreSelect.hasEditsForEntityRecord('postType', postType, postId);
                    } else if (editor.isEditedPostDirty) {
                        hasUnsaved = editor.isEditedPostDirty();
                    }
                    
                    if (!isSaving && !hasUnsaved) {
                        return true;
                    }
                } catch (statusError) {
                    // If we can't check status, assume it's done after a reasonable time
                    if (saveAttempts >= 3) {
                        return true;
                    }
                }
                
                // Wait 500ms before checking again
                await new Promise(resolve => setTimeout(resolve, 500));
                saveAttempts++;
            }
            
            return false;
            
        } catch (error) {
            return false;
        }
    }
    
    getCurrentPostId() {
        try {
            // Runtime check for Gutenberg availability
            if (!wp || !wp.data || !wp.data.select) {
                return null;
            }
            
            const editor = wp.data.select('core/editor');
            if (!editor || !editor.getCurrentPostId) {
                return null;
            }
            
            return editor.getCurrentPostId();
        } catch (error) {
            return null;
        }
    }
    
    showProcessingFeedback() {
        // Optional: Show subtle visual feedback to user
        const indicator = document.createElement('div');
        indicator.id = 'rankingcoach-processing-indicator';
        indicator.style.cssText = `
            position: fixed;
            top: 50px;
            right: 20px;
            background: #0073aa;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 999999;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        indicator.textContent = 'Analyzing content...';
        
        document.body.appendChild(indicator);
        
        // Fade in
        setTimeout(() => indicator.style.opacity = '1', 10);
        
        // Remove after delay
        setTimeout(() => {
            indicator.style.opacity = '0';
            setTimeout(() => {
                if (indicator.parentNode) {
                    indicator.parentNode.removeChild(indicator);
                }
            }, 300);
        }, 2000);
    }
    
    clearTimers() {
        if (this.timers.debounce) {
            clearTimeout(this.timers.debounce);
            this.timers.debounce = null;
        }
    }
    
    cleanup() {
        this.clearTimers();
        
        if (this.timers.activityCheck) {
            clearInterval(this.timers.activityCheck);
        }
        
        if (this.timers.forceProcess) {
            clearInterval(this.timers.forceProcess);
        }
        
        if (this.unsubscribe) {
            this.unsubscribe();
        }
        
    }
    
    // Public API for debugging
    getState() {
        return { ...this.state };
    }
    
    getConfig() {
        return { ...this.config };
    }
    
    forceProcess() {
        this.processContent('manual-trigger');
    }
}

// Safe initialization with Gutenberg availability checks
function initializeRankingCoachHandler() {
    // Early exit if Gutenberg is not available
    if (!GutenbergAvailabilityChecker.isGutenbergAvailable()) {

        // Wait for Gutenberg to become available
        GutenbergAvailabilityChecker.waitForGutenberg(() => {
            try {
                window.rankingCoachGutenbergHandler = new RankingCoachGutenbergHandler();
            } catch (error) {
            }
        });
        return;
    }
    
    // Gutenberg is available, initialize immediately
    try {
        window.rankingCoachGutenbergHandler = new RankingCoachGutenbergHandler();
    } catch (error) {
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeRankingCoachHandler);
} else {
    initializeRankingCoachHandler();
}

// Expose classes for debugging and external access
window.RankingCoachGutenbergHandler = RankingCoachGutenbergHandler;
window.GutenbergAvailabilityChecker = GutenbergAvailabilityChecker;