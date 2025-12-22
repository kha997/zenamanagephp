/**
 * ZenaManage JavaScript Component Template
 * 
 * This template ensures consistent component structure and prevents conflicts
 * 
 * Usage:
 * 1. Copy this template
 * 2. Replace [ComponentName] with your component name
 * 3. Replace [component-name] with kebab-case version
 * 4. Implement your functionality
 * 5. Follow the patterns below
 */

// Global namespace check
window.ZenaManage = window.ZenaManage || {};

// Component namespace
window.ZenaManage.[ComponentName] = window.ZenaManage.[ComponentName] || {};

/**
 * [ComponentName] Component Class
 * 
 * @description Brief description of what this component does
 * @author Your Name
 * @version 1.0.0
 * @since 2025-01-01
 */
class [ComponentName]Component {
    constructor(options = {}) {
        // Default configuration
        this.config = {
            container: '[component-name]-container',
            apiEndpoint: '/api/[component-name]',
            refreshInterval: 30000, // 30 seconds
            ...options
        };

        // State management
        this.state = {
            loading: false,
            data: null,
            error: null,
            lastUpdate: null
        };

        // DOM elements
        this.elements = {};

        // Event handlers
        this.handlers = {};

        // Initialize component
        this.init();
    }

    /**
     * Initialize the component
     */
    init() {
        console.log(`[${this.constructor.name}] Initializing...`);
        
        try {
            this.cacheElements();
            this.bindEvents();
            this.loadData();
            this.startPolling();
            
            console.log(`[${this.constructor.name}] Initialized successfully`);
        } catch (error) {
            console.error(`[${this.constructor.name}] Initialization failed:`, error);
            this.handleError(error);
        }
    }

    /**
     * Cache DOM elements
     */
    cacheElements() {
        const container = document.querySelector(`#${this.config.container}`);
        if (!container) {
            throw new Error(`Container #${this.config.container} not found`);
        }

        this.elements = {
            container,
            loading: container.querySelector('[data-loading]'),
            content: container.querySelector('[data-content]'),
            error: container.querySelector('[data-error]'),
            refresh: container.querySelector('[data-refresh]')
        };
    }

    /**
     * Bind event handlers
     */
    bindEvents() {
        // Refresh button
        if (this.elements.refresh) {
            this.elements.refresh.addEventListener('click', () => {
                this.refresh();
            });
        }

        // Custom events
        document.addEventListener(`[component-name]:refresh`, () => {
            this.refresh();
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });
    }

    /**
     * Load data from API
     */
    async loadData() {
        if (this.state.loading) {
            console.log(`[${this.constructor.name}] Already loading, skipping...`);
            return;
        }

        this.setState({ loading: true, error: null });

        try {
            const data = await this.fetchData();
            this.setState({ 
                data, 
                loading: false, 
                lastUpdate: new Date(),
                error: null 
            });
            
            this.render();
            this.dispatchEvent('loaded', { data });
            
        } catch (error) {
            console.error(`[${this.constructor.name}] Load failed:`, error);
            this.setState({ 
                loading: false, 
                error: error.message 
            });
            this.handleError(error);
        }
    }

    /**
     * Fetch data from API
     */
    async fetchData() {
        const response = await fetch(this.config.apiEndpoint, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'API request failed');
        }

        return result.data;
    }

    /**
     * Render the component
     */
    render() {
        if (this.state.loading) {
            this.showLoading();
            return;
        }

        if (this.state.error) {
            this.showError();
            return;
        }

        this.showContent();
        this.updateContent();
    }

    /**
     * Show loading state
     */
    showLoading() {
        this.hideAll();
        if (this.elements.loading) {
            this.elements.loading.style.display = 'block';
        }
    }

    /**
     * Show error state
     */
    showError() {
        this.hideAll();
        if (this.elements.error) {
            this.elements.error.style.display = 'block';
            this.elements.error.textContent = this.state.error;
        }
    }

    /**
     * Show content state
     */
    showContent() {
        this.hideAll();
        if (this.elements.content) {
            this.elements.content.style.display = 'block';
        }
    }

    /**
     * Hide all states
     */
    hideAll() {
        Object.values(this.elements).forEach(element => {
            if (element && element.style) {
                element.style.display = 'none';
            }
        });
    }

    /**
     * Update content with data
     */
    updateContent() {
        if (!this.state.data || !this.elements.content) {
            return;
        }

        // Implement your content update logic here
        // Example:
        // this.elements.content.innerHTML = this.generateHTML(this.state.data);
    }

    /**
     * Generate HTML from data
     */
    generateHTML(data) {
        // Implement your HTML generation logic here
        // Example:
        // return data.map(item => `<div class="item">${item.name}</div>`).join('');
        return '';
    }

    /**
     * Refresh data
     */
    async refresh() {
        console.log(`[${this.constructor.name}] Refreshing...`);
        await this.loadData();
    }

    /**
     * Start polling for updates
     */
    startPolling() {
        if (this.config.refreshInterval > 0) {
            this.pollingInterval = setInterval(() => {
                this.loadData();
            }, this.config.refreshInterval);
        }
    }

    /**
     * Stop polling
     */
    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    /**
     * Update component state
     */
    setState(newState) {
        this.state = { ...this.state, ...newState };
        this.dispatchEvent('stateChanged', { state: this.state });
    }

    /**
     * Dispatch custom events
     */
    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`[component-name]:${eventName}`, {
            detail: { ...detail, component: this }
        });
        document.dispatchEvent(event);
    }

    /**
     * Handle errors
     */
    handleError(error) {
        console.error(`[${this.constructor.name}] Error:`, error);
        
        // Dispatch error event
        this.dispatchEvent('error', { error });
        
        // Show user-friendly error message
        if (this.elements.error) {
            this.elements.error.textContent = 'An error occurred. Please try again.';
        }
    }

    /**
     * Cleanup resources
     */
    cleanup() {
        console.log(`[${this.constructor.name}] Cleaning up...`);
        
        this.stopPolling();
        
        // Remove event listeners
        if (this.elements.refresh) {
            this.elements.refresh.removeEventListener('click', this.handlers.refresh);
        }
        
        // Clear references
        this.elements = {};
        this.state = {};
        this.config = {};
    }

    /**
     * Get component state
     */
    getState() {
        return { ...this.state };
    }

    /**
     * Get component configuration
     */
    getConfig() {
        return { ...this.config };
    }
}

// Global instance management
window.ZenaManage.[ComponentName].instances = window.ZenaManage.[ComponentName].instances || new Map();

/**
 * Initialize component instance
 */
window.ZenaManage.[ComponentName].init = function(containerId, options = {}) {
    const instance = new [ComponentName]Component({
        container: containerId,
        ...options
    });
    
    window.ZenaManage.[ComponentName].instances.set(containerId, instance);
    return instance;
};

/**
 * Get component instance
 */
window.ZenaManage.[ComponentName].getInstance = function(containerId) {
    return window.ZenaManage.[ComponentName].instances.get(containerId);
};

/**
 * Destroy component instance
 */
window.ZenaManage.[ComponentName].destroy = function(containerId) {
    const instance = window.ZenaManage.[ComponentName].instances.get(containerId);
    if (instance) {
        instance.cleanup();
        window.ZenaManage.[ComponentName].instances.delete(containerId);
    }
};

/**
 * Refresh all instances
 */
window.ZenaManage.[ComponentName].refreshAll = function() {
    window.ZenaManage.[ComponentName].instances.forEach(instance => {
        instance.refresh();
    });
};

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Auto-initialize if container exists
        const container = document.querySelector('#[component-name]-container');
        if (container) {
            window.ZenaManage.[ComponentName].init('[component-name]-container');
        }
    });
} else {
    // DOM is already ready
    const container = document.querySelector('#[component-name]-container');
    if (container) {
        window.ZenaManage.[ComponentName].init('[component-name]-container');
    }
}

// Export for module systems (if needed)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = [ComponentName]Component;
}

console.log(`[${[ComponentName]Component.name}] Component loaded successfully`);
