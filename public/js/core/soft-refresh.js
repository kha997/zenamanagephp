/**
 * Core Soft Refresh Module
 * Provides universal click interception and refresh functionality for admin pages
 * Usage: installSoftRefresh({ linkSelector, route, refreshFn })
 */

/**
 * Install soft refresh functionality for a specific page type
 * @param {Object} config Configuration object
 * @param {string} config.linkSelector CSS selector for the links to intercept
 * @param {string} config.route Base route path to check for same-route clicks
 * @param {Function} config.refreshFn Function to call for refresh
 * @param {Object} config.options Additional options
 * @param {number} config.options.debounceMs Debounce delay (default: 100ms)
 * @param {boolean} config.options.preventDefault Whether to prevent default (default: true)
 */
export function installSoftRefresh({
    linkSelector,
    route,
    refreshFn,
    options = {}
}) {
    const {
        debounceMs = 100,
        preventDefault = true
    } = options;

    if (!linkSelector || !route || !refreshFn) {
        console.warn('installSoftRefresh: Missing required parameters');
        return;
    }

    let refreshTimeout = null;

    // Add click listeners to all matching links
    function attachListeners() {
        const links = document.querySelectorAll(linkSelector);
        
        links.forEach(link => {
            // Remove existing listener if any
            link.removeEventListener('click', handleClick);
            
            // Add new listener
            link.addEventListener('click', handleClick.bind(null, link));
        });
        
        console.log(`Soft refresh installed for ${links.length} links matching "${linkSelector}"`);
    }

    // Handle click events
    function handleClick(link, event) {
        const currentPath = window.location.pathname;
        const linkPath = link.getAttribute('href') || '';
        
        // Check if clicking on same route
        const isSameRoute = currentPath === linkPath || 
                           currentPath.startsWith(route) && linkPath.startsWith(route);
        
        if (isSameRoute) {
            console.log(`Intercepted same-route click: ${currentPath}`);
            
            if (preventDefault) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Debounce the refresh call
            if (refreshTimeout) {
                clearTimeout(refreshTimeout);
            }
            
            refreshTimeout = setTimeout(() => {
                console.log(`Executing soft refresh for route: ${route}`);
                try {
                    if (typeof refreshFn === 'function') {
                        refreshFn();
                    } else {
                        console.warn('refreshFn is not a function:', refreshFn);
                    }
                } catch (error) {
                    console.error('Error during soft refresh:', error);
                }
            }, debounceMs);
            
            return false;
        }
    }

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachListeners);
    } else {
        attachListeners();
    }

    // Return control functions
    return {
        attach,
        detach: () => {
            const links<｜tool▁sep｜>selectorfor links matching "${linkSelector}"`);
        },
        
        refresh: () => {
            console.log(`Manual refresh call for route: ${route}`);
            refreshTimeout && clearTimeout(refreshTimeout);
            try {
                refreshFn();
            } catch (error) {
                console.error('Error during manual refresh:', error);
            }
        }
    };
}

/**
 * Install soft refresh for multiple page types at once
 * @param {Array<Object>} configs Array of installSoftRefresh configurations
 */
export function installMultipleSoftRefresh(configs) {
    const installations = [];
    
    configs.forEach(config => {
        try {
            const installation = installSoftRefresh(config);
            if (installation) {
                installations.push({
                    ...config,
                    installation
                });
            }
        } catch (error) {
            console.error(`Failed to install soft refresh for ${config.route}:`, error);
        }
    });
    
    console.log(`Installed soft refresh for ${installations.length} page types`);
    
    return installations;
}

/**
 * Global soft refresh state management
 */
window.SoftRefresh = window.SoftRefresh || {
    installations: [],
    
    // Track active refreshes
    activeRefreshes: new Set(),
    
    // Monitor refresh health
    getHealthStatus() {
        return {
            installations: this.installations.length,
            activeRefreshes: this.activeRefreshes.size,
            timestamp: new Date().toISOString()
        };
    },
    
    // Enable debug mode
    debug: false
};

// Export default for compatibility
export default { installSoftRefresh, installMultipleSoftRefresh };
