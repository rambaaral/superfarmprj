class RankingCoachElementorTab {

    constructor() {
        this.tabId = 'rankingcoach-seo-tab';
        this.tabTitle = 'Beyond SEO';
        this.panelId = 'panel/page-settings';
        this.initialize();
    }

    initialize() {
        // Hook into Elementor document load
        elementor.hooks.addAction('panel/open_editor/page_settings', this.registerTab.bind(this));

        // Handle Elementor routing for displaying our custom content
        $e.routes.on('run:after', (routeEvent, route) => {
            if (route === `${this.panelId}/${this.tabId}`) {
                this.loadCustomTabContent();
            }
        });

        // If Elementor's UI is ready, init immediately
        if (elementor.documents.getCurrent()) {
            this.registerTab();
        }
    }

    /**
     * Register a custom tab within Elementor's panel
     */
    registerTab() {
        const documentSettings = elementor.documents.getCurrent().config.settings;

        if (!documentSettings.tabs[this.tabId]) {
            documentSettings.tabs = Object.assign({}, documentSettings.tabs, {
                [this.tabId]: this.tabTitle
            });
        }

        const panelPageSettings = $e.components.get(this.panelId);

        if (panelPageSettings && !panelPageSettings.hasTab(this.tabId)) {
            panelPageSettings.addTab(this.tabId, {
                title: this.tabTitle
            });
        }

        // Add the menu item into Elementor panel (optional, in panel-menu top icon)
        this.addMenuItem();
    }

    /**
     * Adds RankingCoach tab/menu item to Elementor left sidebar menu (Optional)
     */
    addMenuItem() {
        try {
            const panelView = elementor.getPanelView();

            if (!panelView || !panelView.currentView) {
                // Panel not ready yet, skip silently
                return;
            }

            const menuPage = panelView.getPages('menu');
            if (!menuPage || !menuPage.view) {
                return; // Panel not ready yet
            }

            if (menuPage.view.collection.findWhere({ name: 'rankingcoach-seo-menu-item' })) {
                return; // Already exists
            }

            // Safe to add menu item
            menuPage.view.addItem({
                name: 'rankingcoach-seo-menu-item',
                icon: 'eicon-seo',
                title: this.tabTitle,
                type: 'page',
                callback: () => {
                    $e.route(`${this.panelId}/${this.tabId}`);
                }
            }, 'more');
        } catch (error) {
            // Silently fail if panel not ready
        }
    }

    /**
     * Inject your custom content dynamically in the Elementor container
     */
    loadCustomTabContent() {
        const container = document.getElementById('elementor-panel-page-settings-controls');

        if (!container) {
            console.error('Elementor page settings container not found.');
            return;
        }

        // Check if your element already injected
        if (document.getElementById('rankingcoach-elementor-content')) {
            return;
        }

        // Create your custom container and append it into Elementor DOM
        const contentDiv = document.createElement('div');
        contentDiv.id = 'rankingcoach-elementor-content';
        contentDiv.className = 'rankingcoach-elementor-tab';
        container.appendChild(contentDiv);

        window.initializeApp();
    }
}

// Initialization after Elementor editor DOM/UI is ready
jQuery(window).on('elementor:init', () => {
    // ---------------------------
    // Helper: mark Elementor as dirty (light up "Update" button)
    // ---------------------------
    window.rankingCoachMarkElementorDirty = function () {
        try {
            if (window.$e) {
                // Modern way (Elementor 3.6+)
                window.$e.internal?.("document/save/set-is-modified", { status: true });
            } else if (window.elementor) {
                // Legacy fallback
                const e = window.elementor;
                e.settings?.page?.setFlagEditorChange?.(true);
                e.saver?.setFlagSaveRequired?.(true);
                e.settings?.editor?.setFlagEditorChange?.(true);
            }
        } catch (err) {
            console.error('[RankingCoach] Error marking Elementor dirty:', err);
        }
    };

    // ---------------------------
    // Initialize the custom Elementor tab
    // ---------------------------
    try {
        new RankingCoachElementorTab();
    } catch (error) {
        console.error('[RankingCoach] Failed to initialize tab:', error);
        // Continue anyway - the save listeners don't depend on the tab
    }

    // ---------------------------
    // Helper: dispatch custom React event after save
    // ---------------------------
    const dispatchRankingCoachSaveEvent = (documentId) => {
        try {
            const saveEvent = new CustomEvent('rankingcoach-elementor-saved', {
                detail: {
                    timestamp: Date.now(),
                    documentId: documentId || null,
                    saveSuccess: true
                }
            });
            document.dispatchEvent(saveEvent);
        } catch (err) {
            console.error('[RankingCoach] Error dispatching custom save event:', err);
        }
    };

    // ---------------------------
    // Subscribe to Elementor save events
    // ---------------------------
    
    // Legacy API (Elementor ≤ 3.5) - Backup method
    if (window.elementor?.hooks?.addAction) {
        window.elementor.hooks.addAction('document/save/after', (documentInstance, data) => {
            dispatchRankingCoachSaveEvent(documentInstance?.id);
        });
    }
    
    // Rank Math's approach - Command system hooks (Primary method)
    // This works across modern Elementor versions and is the most reliable
    if (typeof window.$e !== 'undefined' && window.$e.hooks) {
        // Register After hook - fires when save completes successfully
        if (window.$e.hooks.registerUIAfter) {
            try {
                class RankingCoachAfterSaveHook extends window.$e.modules.hookUI.After {
                    getCommand() {
                        return 'document/save/save';
                    }
                    getId() {
                        return 'rankingcoach-after-save';
                    }
                    getConditions() {
                        return true;
                    }
                    apply(args) {
                        dispatchRankingCoachSaveEvent(args?.document?.id);
                    }
                }
                window.$e.hooks.registerUIAfter(new RankingCoachAfterSaveHook());
            } catch (err) {
                console.error('[RankingCoach] Failed to register After hook:', err);
            }
        }
    }
});