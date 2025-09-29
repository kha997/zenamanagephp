{{-- 
    ZenaManage Blade Component Template
    
    This template ensures consistent component structure and prevents conflicts
    
    Usage:
    1. Copy this template
    2. Replace [ComponentName] with your component name
    3. Replace [component-name] with kebab-case version
    4. Implement your functionality
    5. Follow the patterns below
--}}

{{-- Component Container --}}
<div id="[component-name]-container" 
     class="[component-name]-component"
     data-component="[component-name]"
     x-data="[componentName]Component()"
     x-init="init()"
     @[component-name]:refresh.window="refresh()"
     @[component-name]:error.window="handleError($event.detail.error)">
     
    {{-- Loading State --}}
    <div data-loading 
         class="loading-state"
         x-show="loading"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-center p-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <span class="ml-2 text-gray-600">Loading...</span>
        </div>
    </div>

    {{-- Error State --}}
    <div data-error 
         class="error-state"
         x-show="error"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Error</h3>
                    <div class="mt-2 text-sm text-red-700" x-text="error"></div>
                    <div class="mt-4">
                        <button @click="refresh()" 
                                class="bg-red-100 px-3 py-2 rounded-md text-sm font-medium text-red-800 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500">
                            Try Again
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Content State --}}
    <div data-content 
         class="content-state"
         x-show="!loading && !error"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        {{-- Component Header --}}
        <div class="component-header mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">[ComponentName]</h2>
                    <p class="text-sm text-gray-600">Component description</p>
                </div>
                <div class="flex items-center space-x-2">
                    <button data-refresh 
                            @click="refresh()"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            :disabled="loading">
                        <i class="fas fa-sync-alt mr-2" :class="{ 'animate-spin': loading }"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </div>

        {{-- Component Content --}}
        <div class="component-content">
            {{-- Implement your content here --}}
            <div class="bg-white shadow rounded-lg p-6">
                <div class="text-center text-gray-500">
                    <i class="fas fa-cog text-4xl mb-4"></i>
                    <p>Component content goes here</p>
                </div>
            </div>
        </div>

        {{-- Component Footer --}}
        <div class="component-footer mt-6 text-xs text-gray-500">
            <div class="flex items-center justify-between">
                <span>Last updated: <span x-text="lastUpdate ? new Date(lastUpdate).toLocaleString() : 'Never'"></span></span>
                <span>Component: [component-name]</span>
            </div>
        </div>
    </div>
</div>

{{-- Component JavaScript --}}
<script>
function [componentName]Component() {
    return {
        // State
        loading: false,
        error: null,
        data: null,
        lastUpdate: null,
        
        // Configuration
        config: {
            apiEndpoint: '/api/[component-name]',
            refreshInterval: 30000, // 30 seconds
            autoRefresh: true
        },
        
        // Polling
        pollingInterval: null,
        
        /**
         * Initialize component
         */
        init() {
            console.log('[ComponentName] Component initializing...');
            this.loadData();
            this.startPolling();
        },
        
        /**
         * Load data from API
         */
        async loadData() {
            if (this.loading) {
                console.log('[ComponentName] Already loading, skipping...');
                return;
            }
            
            this.loading = true;
            this.error = null;
            
            try {
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
                
                this.data = result.data;
                this.lastUpdate = new Date();
                this.error = null;
                
                console.log('[ComponentName] Data loaded successfully');
                
            } catch (error) {
                console.error('[ComponentName] Load failed:', error);
                this.error = error.message;
                this.data = null;
            } finally {
                this.loading = false;
            }
        },
        
        /**
         * Refresh data
         */
        async refresh() {
            console.log('[ComponentName] Refreshing...');
            await this.loadData();
        },
        
        /**
         * Start polling for updates
         */
        startPolling() {
            if (this.config.autoRefresh && this.config.refreshInterval > 0) {
                this.pollingInterval = setInterval(() => {
                    this.loadData();
                }, this.config.refreshInterval);
            }
        },
        
        /**
         * Stop polling
         */
        stopPolling() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
            }
        },
        
        /**
         * Handle errors
         */
        handleError(error) {
            console.error('[ComponentName] Error:', error);
            this.error = error.message || 'An error occurred';
        },
        
        /**
         * Cleanup on component destroy
         */
        destroy() {
            this.stopPolling();
        }
    };
}

// Global component management
window.ZenaManage = window.ZenaManage || {};
window.ZenaManage.[ComponentName] = window.ZenaManage.[ComponentName] || {};

// Component utilities
window.ZenaManage.[ComponentName].refresh = function() {
    document.dispatchEvent(new CustomEvent('[component-name]:refresh'));
};

window.ZenaManage.[ComponentName].getState = function() {
    const container = document.querySelector('#[component-name]-container');
    if (container && container._x_dataStack) {
        return container._x_dataStack[0];
    }
    return null;
};

console.log('[ComponentName] Component template loaded');
</script>

{{-- Component Styles --}}
<style>
.[component-name]-component {
    @apply relative;
}

.[component-name]-component .loading-state,
.[component-name]-component .error-state,
.[component-name]-component .content-state {
    @apply min-h-32;
}

.[component-name]-component .component-header {
    @apply border-b border-gray-200 pb-4;
}

.[component-name]-component .component-content {
    @apply space-y-4;
}

.[component-name]-component .component-footer {
    @apply border-t border-gray-200 pt-4;
}

/* Responsive design */
@media (max-width: 640px) {
    .[component-name]-component .component-header {
        @apply flex-col space-y-4;
    }
    
    .[component-name]-component .component-header > div {
        @apply w-full;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .[component-name]-component {
        @apply text-gray-100;
    }
    
    .[component-name]-component .component-header {
        @apply border-gray-700;
    }
    
    .[component-name]-component .component-footer {
        @apply border-gray-700;
    }
}
</style>
