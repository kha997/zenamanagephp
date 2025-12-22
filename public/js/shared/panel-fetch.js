// Panel Fetch Manager - Non-blocking data loading with consistent patterns
// import { getWithETag } from './swr.js'; // Converted to regular script

class PanelFetchManager {
    constructor(config = {}) {
        this.defaultConfig = {
            ttl: 30000,
            loadingClass: 'soft-dim',
            spinnerClass: 'abs-spinner',
            abortOnNewRequest: true,
            ...config
        };
        this.activeRequests = new Map();
    }

    // Main panel fetch method
    async fetchPanel(panelSelector, url, options = {}) {
        const {
            key,
            panelId,
            onStart,
            onEnd,
            onData,
            onError,
            loadingClass = this.defaultConfig.loadingClass,
            abortPrevious = this.defaultConfig.abortOnNewRequest,
            ...swrOptions
        } = options;

        const cacheKey = key || `${panelId || panelSelector}:${url}`;
        
        // Find panel element
        const panelElement = typeof panelSelector === 'string' 
            ? document.querySelector(panelSelector)
            : panelSelector;
            
        if (!panelElement) {
            console.warn('[PanelFetch] Panel not found:', panelSelector);
            return;
        }

        // Abort previous request if needed
        if (abortPrevious && this.activeRequests.has(cacheKey)) {
            this.activeRequests.get(cacheKey).abort();
            this.activeRequests.delete(cacheKey);
        }

        // Set loading state
        this.setPanelLoading(panelElement, true, { loadingClass });

        // Call onStart callback
        if (onStart) onStart(panelElement);

        try {
            // Create abort controller for this request
            const abortController = new AbortController();
            this.activeRequests.set(cacheKey, abortController);

            // Make request with SWR
            const result = await window.SWRCache.getWithETag(cacheKey, url, {
                ...swrOptions,
                signal: abortController.signal,
                onStart: () => {}, // Handled above
                onEnd: () => {
                    this.setPanelLoading(panelElement, false, { loadingClass });
                    if (onEnd) onEnd(panelElement);
                }
            });

            // Call onData callback
            if (onData) onData(result, panelElement);

            // Dispatch success event
            this.dispatchPanelEvent(panelElement, 'dataUpdated', { data: result });
            
            return result;

        } catch (error) {
            this.setPanelLoading(panelElement, false, { loadingClass });
            
            // Call onError callback
            if (onError) onError(error, panelElement);
            
            // Dispatch error event
            this.dispatchPanelEvent(panelElement, 'error', { error });
            
            if (error.name !== 'AbortError') {
                console.error(`[PanelFetch] Error fetching ${url}:`, error);
            }
            
            throw error;
        } finally {
            this.activeRequests.delete(cacheKey);
        }
    }

    // Set panel loading state
    setPanelLoading(element, loading, options = {}) {
        const { loadingClass } = { ...this.defaultConfig, ...options };
        
        if (loading) {
            element.classList.add(loadingClass);
            element.setAttribute('aria-busy', 'true');
            
            // Add iframe spinner if not exists
            if (!element.querySelector('.panel-spinner')) {
                const spinner = document.createElement('div');
                spinner.className = 'panel-spinner';
                spinner.innerHTML = '<div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>';
                element.appendChild(spinner);
            }
        } else {
            element.classList.remove(loadingClass);
            element.removeAttribute('aria-busy');
            
            // Remove spinner
            const spinner = element.querySelector('.panel-spinner');
            if (spinner) {
                spinner.remove();
            }
        }
    }

    // Dispatch panel events
    dispatchPanelEvent(element, event, detail = {}) {
        const customEvent = new CustomEvent(`panel:${event}`, {
            detail: { panelElement: element, ...detail },
            bubbles: true,
            cancelable: true
        });
        
        element.dispatchEvent(customEvent);
        document.dispatchEvent(customEvent);
    }

    // Cancel active request
    cancelRequest(key) {
        const request = this.activeRequests.get(key);
        if (request && request.abort) {
            request.abort();
            this.activeRequests.delete(key);
            return true;
        }
        return false;
    }

    overridePanelState(panelSelector, state) {
        const element = typeof panelSelector === 'string' 
            ? document.querySelector(panelSelector)
            : panelSelector;
            
        if (!element) return;

        Object.entries(state).forEach(([key, value]) => {
            if (key === 'loading') {
                this.setPanelLoading(element, value);
            } else {
                // Set other custom states
                const dataAttr = `data-${key}`;
                if (value) {
                    element.setAttribute(dataAttr, String(value));
                } else {
                    element.removeAttribute(dataAttr);
                }
            }
        });
    }

    overrideStyles(panelSelector, styles) {
        const element = typeof panelSelector === 'string' 
            ? document.querySelector(panelSelector)
            : panelSelector;
            
        if (!element) return;

        Object.assign(element.style, styles);
    }
}

// Global instance
const panelFetch = new PanelFetchManager();

// Convenience functions
window. fetchPanel(panelSelector, url, options = {}) {
    return panelFetch.fetchPanel(panelSelector, url, options);
}

window. setPanelLoading(panelSelector, loading, options = {}) {
    return panelFetch.setPanelLoading(panelSelector, loading, options);
}

window. dispatchPanelEvent(panelSelector, event, detail = {}) {
    return panelFetch.dispatchPanelEvent(panelSelector, event, detail);
}

// Utility functions
window. createPanelWrapper(id, cssClass = 'panel-wrapper') {
    const wrapper = document.createElement('section');
    wrapper.id = id;
    wrapper.className = cssClass;
    wrapper.setAttribute('data-panel-id', id);
    return wrapper;
}

window. wrapPanel(panelSelector, options = {}) {
    const panel = typeof panelSelector === 'string' 
        ? document.querySelector(panelSelector)
        : panelSelector;
        
    if (!panel) return null;

    const wrapper = createPanelWrapper(options.id || `panel-${Date.now()}`, options.className);
    panel.parentNode.insertBefore(wrapper, panel);
    wrapper.appendChild(panel);
    
    return wrapper;
}

// Export for global access
window.PanelFetch = panelFetch;
window.fetchPanel = fetchPanel;
window.setPanelLoading = setPanelLoading;

console.log('[PanelFetch] Manager initialized');
