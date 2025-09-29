// Soft Refresh Manager - Prevent full page reloads for same route
class SoftRefreshManager {
    constructor() {
        this.refreshHandlers = new Map();
        this.activeSoftRefreshes = new Set();
        this.init();
    }

    init() {
        // Listen for clicks on soft refresh enabled links
        document.addEventListener('click', this.handleClick.bind(this));
        
        // Listen for programmatic navigation
        this.interceptProgrammaticNavigation();
        
        // Listen for visibility changes
        document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
        
        console.log('[SoftRefresh] Manager initialized');
    }

    handleClick(event) {
        const link = event.target.closest('a[data-soft-refresh]');
        if (!link) return;

        // Check if it's the same route
        const targetPath = new URL(link.href, location.origin).pathname;
        const currentPath = location.pathname;
        
        if (targetPath !== currentPath) {
            // Different route - allow normal navigation
            return;
        }

        // Same route - prevent default and trigger soft refresh
        event.preventDefault();
        
        const pageKey = link.dataset.softRefresh;
        const refreshHandler = this.refreshHandlers.get(pageKey);
        
        if (refreshHandler) {
            console.log(`[SoftRefresh] Triggering refresh for ${pageKey}`);
            
            // Track active refresh for cleanup
            const refreshId = `${pageKey}-${Date.now()}`;
            this.activeSoftRefreshes.add(refreshId);
            
            // Add loading indicator
            this.addLoadingIndicator(pageKey);
            
            // Call refresh handler
            const refreshPromise = Promise.resolve(refreshHandler()).finally(() => {
                this.activeSoftRefreshes.delete(refreshId);
                this.removeLoadingIndicator(pageKey);
                
                // If no more active refreshes, update URL
                if (this.activeSoftRefreshes.has(pageKey.split('-')[0])) {
                    this.updateUrlState(pageKey, link.href);
                }
            });
            
            return refreshPromise;
        } else {
            console.warn(`[SoftRefresh] No refresh handler found for ${pageKey}`);
            // Fallback to normal navigation
        }
    }

    addLoadingIndicator(pageKey) {
        // Add subtle loading state to the specific page
        const pageElement = document.querySelector(`[data-soft-refresh="${pageKey}"]`);
        if (pageElement) {
            pageElement.style.opacity = '0.7';
            pageElement.style.pointerEvents = 'none';
        }

        // Add global loading class to body for subtle effect
        document.body.classList.add('soft-refresh-loading');
        
        // Dispatch event
        document.dispatchEvent(new CustomEvent('soft-refresh:start', {
            detail: { pageKey }
        }));
    }

    removeLoadingIndicator(pageKey) {
        // Remove loading state
        const pageElement = document.querySelector(`[data-soft-refresh="${pageKey}"]`);
        if (pageElement) {
            pageElement.style.opacity = '';
            pageElement.style.pointerEvents = '';
        }

        // Remove global loading class
        document.body.classList.remove('soft-refresh-loading');
        
        // Dispatch event
        document.dispatchEvent(new CustomEvent('soft-refresh:end', {
            detail: { pageKey }
        }));
    }

    updateUrlState(pageKey, newUrl) {
        // Update URL without triggering navigation
        if (newUrl !== location.href) {
            history.replaceState({}, '', newUrl);
            
            // Dispatch URL change event
            document.dispatchEvent(new CustomEvent('soft-refresh:urlUpdate', {
                detail: { pageKey, newUrl }
            }));
        }
    }

    handleVisibilityChange() {
        // Pause/resume refresh handlers based on visibility
        if (document.hidden) {
            // Page hidden - pause refresh handlers
            this.activeSoftRefreshes.forEach(i => {
                // Implementation would pause any ongoing refreshes
            });
        } else {
            // Page visible - resume refresh handlers
            this.activeSoftRefreshes.forEach(i => {
                // Implementation would resume any paused refreshes
            });
        }
    }

    // Register refresh handler for a page
    registerRefreshHandler(pageKey, handler) {
        if (typeof handler !== 'function') {
            console.error(`[SoftRefresh] Handler must be a function for ${pageKey}`);
            return;
        }

        this.refreshHandlers.set(pageKey, handler);
        console.log(`[SoftRefresh] Registered handler for ${pageKey}`);
    }

    // Unregister refresh handler
    unregisterRefreshHandler(pageKey) {
        if (this.refreshHandlers.delete(pageKey)) {
            console.log(`[SoftRefresh] Unregistered handler for ${pageKey}`);
        }
    }

    // Intercept programmatic navigation
    interceptProgrammaticNavigation() {
        // Override pushState/replaceState to handle programmatic navigation
        const originalPushState = history.pushState;
        const originalReplaceState = history.replaceState;

        history.pushState = function(state, title, url) {
            originalPushState.call(this, state, title, url);
            
            // Check if this is soft refresh territory
            const currentPath = window.location.pathname;
            
            document.dispatchEvent(new CustomEvent('history:pushState', {
                detail: { state, title, url, currentPath }
            }));
        };

        history.replaceState = function(state, title, url) {
            originalReplaceState.call(this, state, title, url);
            
            document.dispatchEvent(new CustomEvent('history:replaceState', {
                detail: { state, title, url, currentPath: window.location.pathname }
            }));
        };
    }

    // Cancel active soft refreshes
    cancelActiveRefreshes(pageKey = null) {
        if (pageKey) {
            // Cancel specific page refreshes
            const keysToRemove = Array.from(this.activeSoftRefreshes)
                .filter(id => id.startsWith(pageKey));
            
            keysToRemove.forEach(id => this.activeSoftRefreshes.delete(id));
        } else {
            // Cancel all active refreshes
            this.activeSoftRefreshes.clear();
        }
        
        console.log(`[SoftRefresh] Cancelled active refreshes${pageKey ? ` for ${pageKey}` : ''}`);
    }

    // Get active refresh status
    getActiveRefreshes() {
        return Array.from(this.activeSoftRefreshes);
    }

    // Check if page is currently refreshing
    isPageRefreshing(pageKey) {
        return Array.from(this.activeSoftRefreshes)
            .some(id => id.startsWith(pageKey));
    }
}

// Global instance
const softRefresh = new SoftRefreshManager();

// Convenience functions
export function registerRefreshHandler(pageKey, handler) {
    return softRefresh.registerRefreshHandler(pageKey, handler);
}

export function unregisterRefreshHandler(pageKey) {
    return softRefresh.unregisterRefreshHandler(pageKey);
}

export function cancelActiveRefreshes(pageKey = null) {
    return softRefresh.cancelActiveRefreshes(pageKey);
}

// Helper to create refresh handler for standard page patterns
export function createStandardRefreshHandler(pageKey, options = {}) {
    const { 
        apiEndpoint, 
        dataSelector, 
        onBeforeRefresh, 
        onAfterRefresh 
    } = options;

    return async function refreshPage() {
        try {
            // Call before refresh hook
            if (onBeforeRefresh) {
                await onBeforeRefresh();
            }

            // Fetch updated data
            if (apiEndpoint && window.getWithETag) {
                const freshData = await window.getWithETag(
                    `${pageKey}:refresh`, 
                    apiEndpoint,
                    { forceRefresh: true }
                );

                // Update DOM with fresh data
                if (dataSelector && document.querySelector(dataSelector)) {
                    const dataElement = document.querySelector(dataSelector);
                    
                    // Dispatch data update event
                    dataElement.dispatchEvent(new CustomEvent('page:dataRefresh', {
                        detail: { pageKey, data: freshData }
                    }));
                }
            }

            // Call after refresh hook
            if (onAfterRefresh) {
                await onAfterRefresh();
            }

        } catch (error) {
            console.error(`[SoftRefresh] Error refreshing ${pageKey}:`, error);
            
            // Dispatch error event
            document.dispatchEvent(new CustomEvent('soft-refresh:error', {
                detail: { pageKey, error }
            }));
        }
    };
}

// Export for global access
window.SoftRefresh = softRefresh;
window.registerRefreshHandler = registerRefreshHandler;
window.unregisterRefreshHandler = unregisterRefreshHandler;

console.log('[SoftRefresh] Module loaded');
