/**
 * Performance optimization utilities for Tenants page
 */
class TenantsPerformance {
    constructor() {
        this.debounceTimers = new Map();
        this.intersectionObserver = null;
        this.performanceMetrics = {
            pageLoad: 0,
            apiCalls: [],
            renderTime: 0
        };
        
        this.init();
    }

    init() {
        this.measurePageLoad();
        this.setupIntersectionObserver();
        this.setupPerformanceMonitoring();
    }

    /**
     * Measure page load performance
     */
    measurePageLoad() {
        if (window.performance && window.performance.timing) {
            const timing = window.performance.timing;
            this.performanceMetrics.pageLoad = timing.loadEventEnd - timing.navigationStart;
            
            console.log(`Page load time: ${this.performanceMetrics.pageLoad}ms`);
        }
    }

    /**
     * Setup intersection observer for lazy loading
     */
    setupIntersectionObserver() {
        if ('IntersectionObserver' in window) {
            this.intersectionObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        const action = element.dataset.lazyAction;
                        
                        if (action && typeof window.Tenants[action] === 'function') {
                            window.Tenants[action]();
                            this.intersectionObserver.unobserve(element);
                        }
                    }
                });
            }, {
                rootMargin: '50px'
            });
        }
    }

    /**
     * Setup performance monitoring
     */
    setupPerformanceMonitoring() {
        // Monitor API call performance
        const originalFetch = window.fetch;
        window.fetch = async (...args) => {
            const startTime = performance.now();
            const response = await originalFetch(...args);
            const endTime = performance.now();
            
            this.performanceMetrics.apiCalls.push({
                url: args[0],
                duration: endTime - startTime,
                timestamp: Date.now()
            });
            
            return response;
        };

        // Monitor render performance
        this.observeRenderPerformance();
    }

    /**
     * Observe render performance
     */
    observeRenderPerformance() {
        if ('PerformanceObserver' in window) {
            const observer = new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    if (entry.entryType === 'measure') {
                        this.performanceMetrics.renderTime = entry.duration;
                        console.log(`Render time: ${entry.duration}ms`);
                    }
                }
            });
            
            observer.observe({ entryTypes: ['measure'] });
        }
    }

    /**
     * Debounce function calls
     */
    debounce(key, func, delay = 300) {
        if (this.debounceTimers.has(key)) {
            clearTimeout(this.debounceTimers.get(key));
        }
        
        const timer = setTimeout(() => {
            func();
            this.debounceTimers.delete(key);
        }, delay);
        
        this.debounceTimers.set(key, timer);
    }

    /**
     * Throttle function calls
     */
    throttle(func, delay = 100) {
        let lastCall = 0;
        return (...args) => {
            const now = Date.now();
            if (now - lastCall >= delay) {
                lastCall = now;
                return func(...args);
            }
        };
    }

    /**
     * Optimize table rendering
     */
    optimizeTableRendering(tenants) {
        const startTime = performance.now();
        
        // Use document fragment for batch DOM updates
        const fragment = document.createDocumentFragment();
        const tableBody = document.querySelector('#tenants-table-body');
        
        if (!tableBody) return;
        
        // Clear existing rows
        tableBody.innerHTML = '';
        
        // Create rows in batches
        const batchSize = 20;
        const batches = this.chunkArray(tenants, batchSize);
        
        let currentBatch = 0;
        
        const renderBatch = () => {
            if (currentBatch >= batches.length) {
                tableBody.appendChild(fragment);
                const endTime = performance.now();
                console.log(`Table rendered in ${endTime - startTime}ms`);
                return;
            }
            
            const batch = batches[currentBatch];
            batch.forEach(tenant => {
                const row = this.createTenantRow(tenant);
                fragment.appendChild(row);
            });
            
            currentBatch++;
            
            // Use requestAnimationFrame for smooth rendering
            requestAnimationFrame(renderBatch);
        };
        
        renderBatch();
    }

    /**
     * Create optimized tenant row
     */
    createTenantRow(tenant) {
        const row = document.createElement('tr');
        row.className = 'tenant-row hover:bg-gray-50';
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <input type="checkbox" class="tenant-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500" value="${tenant.id}">
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10">
                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                            <span class="text-sm font-medium text-gray-700">${tenant.name.charAt(0).toUpperCase()}</span>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900 cursor-pointer hover:text-blue-600" onclick="window.Tenants.viewTenant('${tenant.id}')">
                            ${tenant.name}
                        </div>
                        <div class="text-sm text-gray-500">${tenant.code || tenant.slug || ''}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${tenant.domain}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getPlanBadgeClass(this.getTenantPlan(tenant))}">
                    ${this.capitalizeFirst(this.getTenantPlan(tenant))}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                ${tenant.users_count || 0}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                ${this.formatStorage(tenant.storage_used || 0)}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getStatusBadgeClass(tenant.status)}">
                    ${this.capitalizeFirst(tenant.status || 'unknown')}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                ${tenant.region || 'N/A'}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                ${this.formatDate(tenant.created_at)}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button class="action-menu-btn text-gray-400 hover:text-gray-600" data-tenant-id="${tenant.id}">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </td>
        `;
        
        return row;
    }

    /**
     * Chunk array into smaller batches
     */
    chunkArray(array, size) {
        const chunks = [];
        for (let i = 0; i < array.length; i += size) {
            chunks.push(array.slice(i, i + size));
        }
        return chunks;
    }

    /**
     * Get tenant plan from settings
     */
    getTenantPlan(tenant) {
        if (typeof tenant.settings === 'string') {
            try {
                const settings = JSON.parse(tenant.settings);
                return settings.plan || 'free';
            } catch (e) {
                return 'free';
            }
        }
        return tenant.settings?.plan || 'free';
    }

    /**
     * Get plan badge class
     */
    getPlanBadgeClass(plan) {
        const classes = {
            free: 'bg-gray-100 text-gray-800',
            pro: 'bg-blue-100 text-blue-800',
            enterprise: 'bg-purple-100 text-purple-800'
        };
        return classes[plan] || 'bg-gray-100 text-gray-800';
    }

    /**
     * Get status badge class
     */
    getStatusBadgeClass(status) {
        const classes = {
            active: 'bg-green-100 text-green-800',
            suspended: 'bg-yellow-100 text-yellow-800',
            trial: 'bg-blue-100 text-blue-800',
            archived: 'bg-gray-100 text-gray-800'
        };
        return classes[status] || 'bg-gray-100 text-gray-800';
    }

    /**
     * Format storage size
     */
    formatStorage(bytes) {
        if (!bytes) return '0 MB';
        const mb = bytes / (1024 * 1024);
        return `${mb.toFixed(1)} MB`;
    }

    /**
     * Format date
     */
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    /**
     * Capitalize first letter
     */
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /**
     * Get performance metrics
     */
    getPerformanceMetrics() {
        return {
            ...this.performanceMetrics,
            averageApiCallTime: this.performanceMetrics.apiCalls.length > 0 
                ? this.performanceMetrics.apiCalls.reduce((sum, call) => sum + call.duration, 0) / this.performanceMetrics.apiCalls.length
                : 0,
            totalApiCalls: this.performanceMetrics.apiCalls.length
        };
    }

    /**
     * Report performance metrics
     */
    reportPerformanceMetrics() {
        const metrics = this.getPerformanceMetrics();
        
        // Send to analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'performance_metrics', {
                page_load_time: metrics.pageLoad,
                render_time: metrics.renderTime,
                average_api_call_time: metrics.averageApiCallTime,
                total_api_calls: metrics.totalApiCalls
            });
        }
        
        console.log('Performance Metrics:', metrics);
        return metrics;
    }

    /**
     * Cleanup
     */
    destroy() {
        // Clear all debounce timers
        this.debounceTimers.forEach(timer => clearTimeout(timer));
        this.debounceTimers.clear();
        
        // Disconnect intersection observer
        if (this.intersectionObserver) {
            this.intersectionObserver.disconnect();
        }
    }
}

// Initialize performance monitoring
window.TenantsPerformance = new TenantsPerformance();
