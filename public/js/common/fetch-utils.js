// Panel Fetch Utilities - Non-blocking data loading with SWR
export class PanelFetchManager {
    constructor(config = {}) {
        this.cache = new Map();
        this.cacheTTL = config.cacheTTL || 30000; // 30s default
        this.activeRequests = new Map();
        this.abortControllers = new Map();
    }

    // Main panel fetch method
    async panelFetch(url, options = {}) {
        const { 
            signal, 
            onStart, 
            onEnd, 
            disableCache = false,
            cacheKey = url,
            panelId = 'default'
        } = options;

        // Dispatch loading start event
        this.dispatchPanelEvent(panelId, 'loading');

        // Set busy cursor
        document.documentElement.classList.add('busy');

        // Call onStart callback
        if (onStart && typeof onStart === 'function') {
            onStart();
        }

        try {
            // Check cache first
            if (!disableCache && this.hasValidCache(cacheKey)) {
                console.log(`[PanelFetch] Using cached data for ${cacheKey}`);
                const cachedData = this.getCachedData(cacheKey);
                
                // Call onEnd callback
                if (onEnd && typeof onEnd === 'function') {
                    onEnd();
                }
                
                // Dispatch ready event
                this.dispatchPanelEvent(panelId, 'ready');
                
                // Remove busy cursor
                document.documentElement.classList.remove('busy');
                
                return { data: cachedData, fromCache: true };
            }

            // Cancel previous request for this panel
            this.cancelPreviousRequest(panelId);

            // Create new AbortController for this request
            const abortController = new AbortController();
            this.abortControllers.set(panelId, abortController);

            // Merge signal with abort controller
            const finalSignal = this.createCombinedSignal(signal, abortController.signal);

            // Make request
            const response = await fetch(url, {
                ...options,
                signal: finalSignal,
                headers: {
                    'Accept': 'application/json',
                    'If-None-Match': this.getETag(cacheKey) || '',
                    ...options.headers
                }
            });

            // Handle 304 Not Modified
            if (response.status === 304) {
                console.log(`[PanelFetch] Data not modified (304) for ${cacheKey}`);
                const cachedData = this.getCachedData(cacheKey);
                
                if (onEnd) onEnd();
                this.dispatchPanelEvent(panelId, 'ready');
                document.documentElement.classList.remove('busy');
                
                return { data: cachedData, fromCache: true };
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            // Parse response
            const data = await response.json();

            // Cache the response
            if (!disableCache) {
                this.cacheResponse(cacheKey, data, response.headers.get('ETag'));
            }

            // Clean up controllers
            this.cleanupRequest(panelId);

            // Call onEnd callback
            if (onEnd) onEnd();

            // Dispatch ready event
            this.dispatchPanelEvent(panelId, 'ready');

            // Remove busy cursor
            document.documentElement.classList.remove('busy');

            return { data, fromCache: false };

        } catch (error) {
            // Clean up controllers
            this.cleanupRequest(panelId);

            // Call onEnd callback even on error
            if (onEnd) onEnd();

            // Dispatch error event
            this.dispatchPanelEvent(panelId, 'error', error);

            // Remove busy cursor
            document.documentElement.classList.remove('busy');

            if (error.name === 'AbortError') {
                console.log(`[PanelFetch] Request aborted for ${panelId}`);
                throw error;
            }

            console.error(`[PanelFetch] Error fetching ${url}:`, error);
            throw error;
        }
    }

    // Cache management
    hasValidCache(key) {
        const cached = this.cache.get(key);
        if (!cached) return false;
        
        const now = Date.now();
        return (now - cached.timestamp) < this.cacheTTL;
    }

    getCachedData(key) {
        const cached = this.cache.get(key);
        return cached ? cached.data : null;
    }

    cacheResponse(key, data, etag) {
        this.cache.set(key, {
            data,
            etag,
            timestamp: Date.now()
        });
    }

    getETag(key) {
        const cached = this.cache.get(key);
        return cached ? cached.etag : null;
    }

    // Request management
    cancelPreviousRequest(panelId) {
        const controller = this.abortControllers.get(panelId);
        if (controller) {
            controller.abort();
            this.abortControllers.delete(panelId);
        }
        
        // Also cancel any active requests
        const activeRequest = this.activeRequests.get(panelId);
        if (activeRequest) {
            activeRequest.abort();
            this.activeRequests.delete(panelId);
        }
    }

    cleanupRequest(panelId) {
        this.abortControllers.delete(panelId);
        this.activeRequests.delete(panelId);
    }

    createCombinedSignal(signal1, signal2) {
        if (!signal1 && !signal2) return null;
        if (!signal1) return signal2;
        if (!signal2) return signal1;

        // Create combined signal that aborts when either signal aborts
        const controller = new AbortController();
        
        const abort1 = () => controller.abort();
        const abort2 = () => controller.abort();
        
        signal1.addEventListener('abort', abort1);
        signal2.addEventListener('abort', abort2);
        
        controller.signal.addEventListener('abort', () => {
            signal1.removeEventListener('abort', abort1);
            signal2.removeEventListener('abort', abort2);
        });
        
        return controller.signal;
    }

    // Event system for panel loading states
    dispatchPanelEvent(panelId, event, data = null) {
        const customEvent = new CustomEvent(`panel:${event}`, {
            detail: { panelId, data },
            bubbles: true,
            cancelable: true
        });
        
        document.dispatchEvent(customEvent);
        
        // Also dispatch on panel element if it exists
        const panelElement = document.getElementById(panelId) || 
                           document.querySelector(`[data-panel-id="${panelId}"]`);
        
        if (panelElement) {
            panelElement.dispatchEvent(customEvent);
        }
    }

    // Clear cache
    clearCache() {
        this.cache.clear();
    }

    // Clear cache for specific panel
    clearPanelCache(panelId) {
        for (const [key, value] of this.cache.entries()) {
            if (key.includes(panelId)) {
                this.cache.delete(key);
            }
        }
    }
}

// Global instance
window.PanelFetchManager = new PanelFetchManager();

// Convenience functions
export function panelFetch(url, options = {}) {
    return window.PanelFetchManager.panelFetch(url, options);
}

export function createPanelFetchManager(config) {
    return new PanelFetchManager(config);
}

// Event listeners for hard navigation (different routes)
document.addEventListener('DOMContentLoaded', () => {
    let isHardNavigation = false;
    let navigationStartTime = 0;
    
    // Detect hard navigation by monitoring URL changes
    let currentUrl = window.location.href;
    
    const detectNavigation = () => {
        if (window.location.href !== currentUrl) {
            isHardNavigation = true;
            navigationStartTime = Date.now();
            currentUrl = window.location.href;
            
            // Start NProgress for hard navigation
            if (window.NProgress && isHardNavigation) {
                window.NProgress.start();
            }
        }
    };
    
    // Monitor for navigation
    window.addEventListener('popstate', detectNavigation);
    window.addEventListener('pushstate', detectNavigation);
    window.addEventListener('replacestate', detectNavigation);
    
    // End NProgress on page load
    window.addEventListener('load', () => {
        if (isHardNavigation && window.NProgress) {
            window.NProgress.done();
            isHardNavigation = false;
        }
    });
});

// Export for global access
window.panelFetch = panelFetch;

console.log('Panel Fetch Utils loaded');
