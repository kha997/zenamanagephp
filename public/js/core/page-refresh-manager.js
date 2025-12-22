// Page Refresh Manager - Unified approach for all admin pages
class PageRefreshManager {
    constructor(pageName, config = {}) {
        this.pageName = pageName;
        this.config = {
            apiEndpoint: config.apiEndpoint || `/api/admin/${pageName}`,
            refreshInterval: config.refreshInterval || 30000, // 30s
            cacheKey: config.cacheKey || `${pageName}_data`,
            kpiKey: config.kpiKey || 'kpis',
            tableKey: config.tableKey || 'items',
            ...config
        };
        
        this.currentData = null;
        this.lastETag = null;
        this.abortController = null;
        this.refreshIntervalId = null;
        this.isInitialized = false;
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadInitialData();
        this.setupPeriodicRefresh();
        
        // Register global refresh function
        window[this.pageName.charAt(0).toUpperCase() + this.pageName.slice(1)] = {
            refresh: () => this.refreshPage(),
            destroy: () => this.destroy()
        };
        
        console.log(`${this.pageName} page refresh manager initialized`);
    }

    bindEvents() {
        // Listen for soft refresh trigger
        document.addEventListener(`${this.pageName}:refresh`, () => {
            console.log(`${this.pageName}: soft refresh triggered`);
            this.refreshPage();
        });

        // Export handlers
        document.addEventListener(`${this.pageName}:export`, (e) => {
            this.exportData(e.detail.format);
        });

        // Filter/table interactions that need refresh
        this.bindTableEvents();
        
        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseRefresh();
            } else {
                this.resumeRefresh();
            }
        });
    }

    bindTableEvents() {
        // Refresh on filter changes
        const filterButtons = document.querySelectorAll(`[data-${this.pageName}-filter]`);
        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                setTimeout(() => this.refreshPage(), 100);
            });
        });

        // Refresh after pagination
        const paginationButtons = document.querySelectorAll(`[data-${this.pageName}-pagination]`);
        paginationButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                setTimeout(() => this.refreshPage(), 100);
            });
        });

        // Refresh after bulk actions
        const actionButtons = document.querySelectorAll(`[data-${this.pageName}-action]`);
        actionButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                setTimeout(() => this.refreshPage(), 200);
            });
        });
    }

    async loadInitialData() {
        try {
            const cachedData = this.getCachedData();
            if (cachedData) {
                console.log(`${this.pageName}: loading cached data`);
                this.updateUI(cachedData);
            }

            const freshData = await this.fetchData();
            this.currentData = freshData;
            this.updateUI(freshData);
            this.cacheData(freshData);
            this.isInitialized = true;
            
        } catch (error) {
            console.error(`${this.pageName}: initial data load failed:`, error);
            // Don't show error toast during navigation to prevent flash
            // Only log the error silently
        }
    }

    async refreshPage() {
        try {
            // Cancel previous request
            if (this.abortController) {
                this.abortController.abort();
            }

            const data = await this.fetchData();
            this.currentData = data;
            this.updateUI(data);
            this.cacheData(data);

            // Dispatch refresh complete event
            document.dispatchEvent(new CustomEvent(`${this.pageName}:refreshComplete`, {
                detail: { data }
            }));

        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error(`${this.pageName}: refresh failed:`, error);
            }
        }
    }

    async fetchData(params = {}) {
        this.abortController = new AbortController();
        
        const queryParams = new URLSearchParams({
            ...params,
            page: this.getCurrentPage(),
            per_page: this.getCurrentPerPage(),
            sort_by: this.getCurrentSortBy(),
            sort_order: this.getCurrentSortOrder(),
            ...this.getActiveFilters()
        });

        const url = `${this.config.apiEndpoint}?${queryParams}`;
        
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'If-None-Match': this.lastETag || undefined
            },
            signal: this.abortController.signal
        });

        if (response.status === 304) {
            console.log(`${this.pageName}: data unchanged (304)`);
            return this.currentData;
        }

        if (!response.isLoading) {
            throw new Error(`HTTP ${response.status}`);
        }

        this.lastETag = response.headers.get('ETag');
        const data = await response.json();

        // Set cache headers for subsequent requests
        this.handleCacheHeaders(response);

        return data;
    }

    updateUI(data) {
        this.updateKPIs(data[this.config.kpiKey]);
        this.updateTable(data[this.config.tableKey]);
        this.updatePagination(data.meta);
        this.updateFooter(data.meta);
    }

    updateKPIs(kpiData) {
        if (!kpiData) return;

        this.pageName === 'users' ? this.updateUsersKPIs(kpiData) :
        this.pageName === 'tenants' ? this.updateTenantsKPIs(kpiData) :
        this.pageName === 'alerts' ? this.updateAlertsKPIs(kpiData) :
        this.updateGenericKPIs(kpiData);

        // Update sparklines
        this.updateSparklines(kpiData);
    }

    updateUsersKPIs(kpis) {
        const updateKPI = (id, data) => {
            const valueEl = document.querySelector(`[data-kpi="${id}"] .value`);
            const deltaEl = document.querySelector(`[data-kpi="${id}"] .delta`);
            const sparklineEl = document.querySelector(`#sparkline-${id}`);
            
            if (valueEl) valueEl.textContent = data.value?.toLocaleString() || '0';
            if (deltaEl) deltaEl.textContent = this.formatDelta(data);
            if (sparklineEl && data.series) this.drawSparkline(sparklineEl, data.series);
        };

        updateKPI('totalUsers', kpis.totalUsers);
        updateKPI('activeUsers', kpis.activeUsers);
        updateKPI('lockedUsers', kpis.lockedUsers);
        updateKPI('noMfaUsers', kpis.noMfaUsers);
        updateKPI('pendingInvites', kpis.pendingInvites);
    }

    updateTenantsKPIs(kpis) {
        const updateKPI = (id, data) => {
            const valueEl = document.querySelector(`[data-kpi="${id}"] .value`);
            const deltaEl = document.querySelector(`[data-kpi="${id}"] .delta`);
            const sparklineEl = document.querySelector(`#sparkline-${id}`);
            
            if (valueEl) valueEl.textContent = data.value?.toLocaleString() || '0';
            if (deltaEl) deltaEl.textContent = this.formatDelta(data);
            if (sparklineEl && data.series) this.drawSparkline(sparklineEl, data.series);
        };

        updateKPI('totalTenants', kpis.totalTenants);
        updateKPI('activeTenants', kpis.activeTenants);
        updateKPI('disabledTenants', kpis.disabledTenants);
        updateKPI('newTenants', kpis.newTenants);
        updateKPI('trialExpiring', kpis.trialExpiring);
    }

    updateAlertsKPIs(kpis) {
        const updateKPI = (id, data) => {
            const valueEl = document.querySelector(`[data-kpi="${id}"] .value`);
            const deltaEl = document.querySelector(`[data-kpi="${id}"] .delta`);
            const sparklineEl = document.querySelector(`#sparkline-${id}`);
            
            if (valueEl) valueEl.textContent = data.value?.toLocaleString() || '0';
            if (deltaEl) deltaEl.textContent = this.formatDelta(data);
            if (sparklineEl && data.series) this.drawSparkline(sparklineEl, data.series);
        };

        updateKPI('criticalAlerts', kpis.criticalAlerts || {value: 0, deltaAbs: 0});
        updateKPI('warningAlerts', kpis.warningAlerts || {value: 0, deltaPct: 0});
        updateKPI('resolvedAlerts', kpis.resolvedAlerts || {value: 0, deltaPct: 0});
        updateKPI('systemHealth', kpis.systemHealth || {value: 95, deltaPct: -2});
    }

    updateGenericKPIs(kpis) {
        // Generic KPI update for pages without specific handlers
        Object.keys(kpis).forEach(key => {
            const kpiData = kpis[key];
            const valueEl = document.querySelector(`[data-kpi="${key}"] .value`);
            const deltaEl = document.querySelector(`[data-kpi="${key}"] .delta`);
            
            if (valueEl) valueEl.textContent = kpiData.value?.toLocaleString() || '0';
            if (deltaEl) deltaEl.textContent = this.formatDelta(kpiData);
        });
    }

    updateTable(tableData) {
        if (!tableData) return;

        // Get table container
        const tableContainer = document.querySelector(`[data-${this.pageName}-table]`);
        if (!tableContainer) return;

        // Update table rows
        const tbody = tableContainer.querySelector('tbody');
        if (tbody) {
            tbody.innerHTML = this.generateTableRows(tableData);
        }

        // Dispatch table update event
        document.dispatchEvent(new CustomEvent(`${this.pageName}:tableUpdated`, {
            detail: { data: tableData }
        }));
    }

    generateTableRows(items) {
        const formatRow = (item, type = this.pageName) => {
            switch (type) {
                case 'users':
                    return this.generateUserRow(item);
                case 'tenants':
                    return this.generateTenantRow(item);
                case 'alerts':
                    return this.generateAlertRow(item);
                default:
                    return `<tr><td colspan="6">Unknown item type</td></tr>`;
            }
        };

        return items.map(item => formatRow(item)).join('');
    }

    generateUserRow(user) {
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-gray-600"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">${user.name || user.email}</div>
                            <div class="text-sm text-gray-500">${user.email}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 py-1 text-xs font-medium rounded-full ${this.getRoleBadgeClass(user.role)}">
                        ${user.role || 'N/A'}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${user.tenant?.name || 'N/A'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 py-1 text-xs font-medium rounded-full ${this.getStatusBadgeClass(user.status)}">
                        ${user.status || 'Unknown'}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${this.formatDate(user.last_login_at)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <button class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                    <button class="text-red-600 hover:text-red-900">Delete</button>
                </td>
            </tr>
        `;
    }

    generateTenantRow(tenant) {
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="text-sm font-medium text-gray-900">${tenant.name}</div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${tenant.users_count || 0} users
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 py-1 text-xs font-medium rounded-full ${this.getStatusBadgeClass(tenant.status)}">
                        ${tenant.status || 'Unknown'}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${this.formatDate(tenant.created_at)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${this.formatDate(tenant.last_login_at)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                    <button class="text-green-600 hover:text-green-900 mr-3">Edit</button>
                    <button class="text-red-600 hover:text-red-900">Delete</button>
                </td>
            </tr>
        `;
    }

    generateAlertRow(alert) {
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="text-sm font-medium text-gray-900">${alert.title}</div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 py-1 text-xs font-medium rounded-full ${this.getSeverityBadgeClass(alert.severity)}">
                        ${alert.severity || 'Unknown'}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${alert.source || 'System'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${this.formatDate(alert.created_at)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 py-1 text-xs font-medium rounded-full ${this.getStatusBadgeClass(alert.status)}">
                        ${alert.status || 'Unknown'}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                    <button class="text-green-600 hover:text-green-900">Resolve</button>
                </td>
            </tr>
        `;
    }

    updatePagination(meta) {
        if (!meta) return;

        const paginationEl = document.querySelector(`[data-${this.pageName}-pagination-container]`);
        if (!paginationEl) return;

        const paginationHTML = this.generatePaginationHTML(meta);
        paginationEl.innerHTML = paginationHTML;

        // Re-bind pagination events
        this.bindTableEvents();
    }

    generatePaginationHTML(meta) {
        const { current_page, per_page, total, last_page } = meta;
        const showing = `${(current_page - 1) * per_page + 1}-${Math.min(current_page * per_page, total)}`;
        
        return `
            <div class="flex items-center justify-between px-6 py-3 bg-white border-t border-gray-200">
                <div class="flex items-center text-sm text-gray-700">
                    Showing <span class="font-medium">${showing}</span> of <span class="font-medium">${total}</span> results
                </div>
                <div class="flex items-center space-x-2">
                    ${current_page > 1 ? `<button data-${this.pageName}-pagination="${current_page - 1}" class="px-3 py-1 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Previous</button>` : ''}
                    
                    ${Array.from({length: Math.min(5, last_page)}, (_, i) => {
                        const page = Math.max(1, Math.min(last_page, current_page - 2 + i));
                        const active = page === current_page ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border-gray-300';
                        return `<button data-${this.pageName}-pagination="${page}" class="px-3 py-1 text-sm ${active} border rounded-md hover:bg-gray-50">${page}</button>`;
                    }).join('')}
                    
                    ${current_page < last_page ? `<button data-${this.pageName}-pagination="${current_page + 1}" class="px-3 py-1 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Next</button>` : ''}
                </div>
            </div>
        `;
    }

    // Utility methods
    formatDelta(data) {
        if (data.deltaPct !== undefined) {
            const sign = data.deltaPct >= 0 ? '+' : '';
            const type = data.deltaPct >= 0 ? 'text-green-600' : 'text-red-600';
            return `<span class="${type}">${sign}${data.deltaPct.toFixed(1)}%</span>`;
        }
        if (data.deltaAbs !== undefined) {
            const sign = data.deltaAbs >= 0 ? '+' : '';
            const type = data.deltaAbs >= 0 ? 'text-green-600' : 'text-red-600';
            return `<span class="${type}">${sign}${data.deltaAbs}</span>`;
        }
        return '';
    }

    formatDate(dateStr) {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('en-GB', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    }

    getRoleBadgeClass(role) {
        const classes = {
            'super_admin': 'bg-purple-100 text-purple-800',
            'admin': 'bg-blue-100 text-blue-800',
            'user': 'bg-green-100 text-green-800',
            'viewer': 'bg-gray-100 text-gray-800'
        };
        return classes[role?.toLowerCase()] || 'bg-gray-100 text-gray-800';
    }

    getStatusBadgeClass(status) {
        const classes = {
            'active': 'bg-green-100 text-green-800',
            'inactive': 'bg-red-100 text-red-800',
            'pending': 'bg-yellow-100 text-yellow-800',
            'disabled': 'bg-gray-100 text-gray-800'
        };
        return classes[status?.toLowerCase()] || 'bg-gray-100 text-gray-800';
    }

    getSeverityBadgeClass(severity) {
        const classes = {
            'critical': 'bg-red-100 text-red-800',
            'high': 'bg-orange-100 text-orange-800',
            'medium': 'bg-yellow-100 text-yellow-800',
            'low': 'bg-blue-100 text-blue-800',
            'info': 'bg-gray-100 text-gray-800'
        };
        return classes[severity?.toLowerCase()] || 'bg-gray-100 text-gray-800';
    }

    drawSparkline(canvas, values) {
        if (!values || !values.length) return;
        
        const ctx = canvas.getContext('2d');
        const width = canvas.width;
        const height = canvas.height;
        
        const min = Math.min(...values);
        const max = Math.max(...values);
        const range = max - min || 1;
        
        ctx.clearRect(0, 0, width, height);
        ctx.strokeStyle = '#3B82F6';
        ctx.lineWidth = 2;
        ctx.beginPath();
        
        values.forEach((value, index) => {
            const x = (index / (values.length - 1)) * width;
            const y = height - ((value - min) / range) * height;
            
            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        
        ctx.stroke();
    }

    updateSparklines(kpiData) {
        Object.keys(kpiData).forEach(key => {
            const sparklineEl = document.querySelector(`#sparkline-${key}`);
            if (sparklineEl && kpiData[key]?.series) {
                this.drawSparkline(sparklineEl, kpiData[key].series);
            }
        });
    }

    // Cache management
    getCachedData() {
        try {
            const cached = localStorage.getItem(this.config.cacheKey);
            if (cached) {
                const { data, timestamp } = JSON.parse(cached);
                const age = Date.now() - timestamp;
                const maxAge = this.config.refreshInterval || 30000;
                
                if (age < maxAge) {
                    return data;
                }
            }
        } catch (error) {
            console.warn('Cache read failed:', error);
        }
        return null;
    }

    cacheData(data) {
        try {
            const cacheEntry = {
                data,
                timestamp: Date.now()
            };
            localStorage.setItem(this.config.cacheKey, JSON.stringify(cacheEntry));
        } catch (error) {
            console.warn('Cache write failed:', error);
        }
    }

    // Current page state helpers
    getCurrentPage() {
        return new URLSearchParams(window.location.search).get('page') || '1';
    }

    getCurrentPerPage() {
        return new URLSearchParams(window.location.search).get('per_page') || '20';
    }

    getCurrentSortBy() {
        return new URLSearchParams(window.location.search).get('sort_by') || 'id';
    }

    getCurrentSortOrder() {
        return new URLSearchParams(window.location.search).get('sort_order') || 'desc';
    }

    getActiveFilters() {
        const url = new URLSearchParams(window.location.search);
        const filters = {};
        
        ['q', 'status', 'role', 'tenant_id'].forEach(param => {
            if (url.has(param)) filters[param] = url.get(param);
        });
        
        return filters;
    };

    handleCacheHeaders(response) {
        // Set browser cache controls for subsequent requests
        // This helps with 304 responses
        const cacheControl = response.headers.get('Cache-Control');
        const etag = response.headers.get('ETag');
        
        if (cacheControl && etag) {
            // Store cache hints for next request
            this.lastETag = etag;
        }
    }

    setupPeriodicRefresh() {
        this.refreshIntervalId = setInterval(() => {
            if (!document.hidden && this.isInitialized) {
                this.refreshPage();
            }
        }, this.config.refreshInterval);
    }

    pauseRefresh() {
        if (this.refreshIntervalId) {
            clearInterval(this.refreshIntervalId);
        }
    }

    resumeRefresh() {
        if (this.isInitialized) {
            this.setupPeriodicRefresh();
        }
    }

    showError(message) {
        // Remove error banner to prevent flash during navigation
        // Only log to console
        console.warn(`${this.pageName}: ${message}`);
    }

    destroy() {
        if (this.abortController) {
            this.abortController.abort();
        }
        
        if (this.refreshIntervalId) {
            clearInterval(this.refreshIntervalId);
        }
        
        // Remove event listeners
        const events = [`${this.pageName}:refresh`, `${this.pageName}:export`];
        events.forEach(eventType => {
            // Note: For cleanup, you'd need to store event handler references
            // This is a simplified cleanup
        });
        
        console.log(`${this.pageName} page refresh manager destroyed`);
    }
}

// Export for global access
window.PageRefreshManager = PageRefreshManager;
