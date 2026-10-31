((wp, React) => {
  const el = React.createElement;
  const registerPlugin = wp.plugins.registerPlugin;
  const PluginSidebar = wp.editor.PluginSidebar;
  const dispatch = wp.data.dispatch;
  const select = wp.data.select;
  const subscribe = wp.data.subscribe;

  // Track sidebar state
  const sidebarState = {
    isInitialized: false,
    isOpen: false,
  };

  // Function to initialize the sidebar content
  const initializeSidebarContent = () => {
    if (
        window.initializeGutenbergSidebar &&
        typeof window.initializeGutenbergSidebar === "function"
    ) {
      window.initializeGutenbergSidebar();
      sidebarState.isInitialized = true;
    } else {
    }
  };

  // Function to toggle sidebar
  const toggleSidebar = () => {
    const interfaceStore = select("core/interface");
    const editPostStore = dispatch("core/edit-post");

    // Check if sidebar is currently open
    const isOpen =
        interfaceStore.getActiveComplementaryArea("core/edit-post") ===
        "rankingcoach-sidebar/rankingcoach-sidebar";

    if (isOpen) {
      // Close the sidebar
      editPostStore.closeGeneralSidebar();
    } else {
      // Open the sidebar
      editPostStore.openGeneralSidebar(
          "rankingcoach-sidebar/rankingcoach-sidebar"
      );
    }
  };

  // Wait for DOM to be ready and attach event listener
  const attachEventListener = () => {
    const scoreButton = document.querySelector("#score-button-header");
    if (scoreButton) {
      scoreButton.addEventListener("click", toggleSidebar);
    } else {
      // If button not found, try again after a short delay
      setTimeout(attachEventListener, 1000);
    }
  };

  // Initialize when DOM is ready
  const initialize = () => {
    attachEventListener();

    // Auto-open sidebar when page loads
    setTimeout(() => {
      // Initialize sidebar content after opening
      setTimeout(initializeSidebarContent, 300);
    }, 500);
  };

  // Get sidebar title from localized data or use fallback
  const getSidebarTitle = () => {
    return window.rankingCoachReactData?.brandName || "rankingCoach";
  };

  // Register the sidebar plugin
  registerPlugin("rankingcoach-sidebar", {
    render: () =>
        el(
            PluginSidebar,
            {
              name: "rankingcoach-sidebar",
              icon: "admin-post",
              title: getSidebarTitle(),
            },
            el("div", { id: "rankingcoach-sidebar-content" })
        ),
  });

  // Subscribe to sidebar state changes
  const unsubscribe = subscribe(() => {
    const interfaceStore = select("core/interface");
    const isOpen =
        interfaceStore.getActiveComplementaryArea("core/edit-post") ===
        "rankingcoach-sidebar/rankingcoach-sidebar";

    // If sidebar was closed and is now open
    if (!sidebarState.isOpen && isOpen) {

      // Initialize content if needed
      if (!sidebarState.isInitialized) {
        initializeSidebarContent();
      } else {
        // Re-render the sidebar content when reopened
        setTimeout(() => {
          const sidebarContent = document.getElementById(
              "rankingcoach-sidebar-content"
          );
          if (sidebarContent && window.initializeGutenbergSidebar) {
            window.initializeGutenbergSidebar();
          }
        }, 100);
      }
    }

    // Update state
    sidebarState.isOpen = isOpen;
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initialize);
  } else {
    initialize();
  }

  // Also try to attach after a delay to ensure all elements are loaded
  setTimeout(attachEventListener, 2000);

  // Expose functions to window for external access
  window.rankingCoachSidebar = {
    toggle: toggleSidebar,
    initialize: initializeSidebarContent,
  };
})(window.wp, window.React);