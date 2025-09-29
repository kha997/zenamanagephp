/**
 * Tenants Page Module - Real API Integration
 * Handles state management, API calls, and UI updates
 */

class TenantsPage {
    constructor() {
        this.state = {
            list: [],
            kpis: {},
            meta: {
                total: 0,
                page: 1,
                per_page: 20,
                last_page: 1
            },
            loading: false,
            error: null
        };
        
        this.cache = new Map();
        this.cacheTTL = 30000; // 30 seconds
        this.abortController = null;
        this.debounceTimer = null;
        
        this.init();
    }

    init() {
        console.log('TenantsPage initialized with real API integration');
        
        // Parse URL parameters
        this.parseUrlParams();
        
        // Initialize components
        this.initSoftRefresh();
        this.initSearch();
        this.initFilters();
        this.initPagination();
        this.initExport();
        this.initKPIButtons();
        this.initBulkActions();
        
        // Load initial data
        this.loadTenants();
    }

    /**
     * Parse URL parameters with proper defaults
     */
    parseUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        
        this.state.meta.per_page = Number(urlParams.get('per_page') ?? 20) || 20;
        this.state.meta.page = Number(urlParams.get('page') ?? 1) || 1;
        
        this.filters = {
            q: urlParams.get('q') || '',
            status: urlParams.get('status') || '',
            plan: urlParams.get('plan') || '',
            from: urlParams.get('from') || '',
            to: urlParams.get('to') || '',
            sort: urlParams.get('sort') || '-created_at'
        };
        
        // Update UI with parsed values
        this.updateUIFromState();
    }

    /**
     * Update UI elements from current state
     */
    updateUIFromState() {
        // Update search input
        const searchInput = document.querySelector('#search-input');
        if (searchInput) searchInput.value = this.filters.q;
        
        // Update filter selects
        const statusSelect = document.querySelector('[data-filter="status"]');
        if (statusSelect) statusSelect.value = this.filters.status;
        
        const planSelect = document.querySelector('[data-filter="plan"]');
        if (planSelect) planSelect.value = this.filters.plan;
        
        // Update date inputs
        const fromInput = document.querySelector('[data-filter="from"]');
        if (fromInput) fromInput.value = this.filters.from;
        
        const toInput = document.querySelector('[data-filter="to"]');
        if (toInput) toInput.value = this.filters.to;
        
        // Update per_page select
        const perPageSelect = document.querySelector('[data-filter="per_page"]');
        if (perPageSelect) perPageSelect.value = this.state.meta.per_page;
    }

    /**
     * Update URL with current state
     */
    updateUrl() {
        const params = new URLSearchParams();
        
        // Add filters
        if (this.filters.q) params.set('q', this.filters.q);
        if (this.filters.status) params.set('status', this.filters.status);
        if (this.filters.plan) params.set('plan', this.filters.plan);
        if (this.filters.from) params.set('from', this.filters.from);
        if (this.filters.to) params.set('to', this.filters.to);
        if (this.filters.sort) params.set('sort', this.filters.sort);
        
        // Add pagination
        if (this.state.meta.page > 1) params.set('page', this.state.meta.page);
        if (this.state.meta.per_page !== 20) params.set('per_page', this.state.meta.per_page);
        
        const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
        window.history.pushState({ per_page: this.state.meta.per_page }, '', newUrl);
    }

    /**
     * Fetch tenants from API
     */
    async fetchTenants(params = {}, options = {}) {
        // Cancel previous request
        if (this.abortController) {
            this.abortController.abort();
        }
        
        this.abortController = new AbortController();
        const signal = this.abortController.signal;
        
        // Build query parameters
        const queryParams = {
            q: this.filters.q,
            status: this.filters.status,
            plan: this.filters.plan,
            from: this.filters.from,
            to: this.filters.to,
            sort: this.filters.sort,
            page: this.state.meta.page,
            per_page: this.state.meta.per_page,
            ...params
        };
        
        // Remove empty parameters
        Object.keys(queryParams).forEach(key => {
            if (!queryParams[key]) delete queryParams[key];
        });
        
        const queryString = new URLSearchParams(queryParams).toString();
        const url = `/api/admin/tenants?${queryString}`;
        
        // Check cache first
        const cacheKey = this.getCacheKey(queryParams);
        const cached = this.getFromCache(cacheKey);
        
        // Prepare headers
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };
        
        // Add ETag if we have cached data
        if (cached && cached.etag) {
            headers['If-None-Match'] = cached.etag;
        }
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: headers,
                credentials: 'same-origin',
                signal: signal
            });
            
            if (response.status === 304) {
                // Use cached data
                if (cached) {
                    this.state.list = cached.data.list;
                    this.state.meta = cached.data.meta;
                    this.state.kpis = cached.data.kpis || {};
                    this.updateUI();
                }
                return;
            }
            
            if (response.status === 401 || response.status === 403) {
                this.showToast('Authentication required. Please login again.', 'error');
                setTimeout(() => window.location.href = '/login', 2000);
                return;
            }
            
            if (response.status === 422) {
                const errorData = await response.json();
                this.handleValidationError(errorData);
                return;
            }
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            // Update state
            this.state.list = data.data || [];
            this.state.meta = data.meta || {};
            this.state.kpis = data.kpis || {};
            this.state.error = null;
            
            // Cache the response
            const etag = response.headers.get('ETag');
            this.setCache(cacheKey, {
                list: this.state.list,
                meta: this.state.meta,
                kpis: this.state.kpis
            }, etag);
            
            // Update UI
            this.updateUI();
            
        } catch (error) {
            if (error.name === 'AbortError') return;
            
            console.error('Failed to fetch tenants:', error);
            this.state.error = error.message;
            this.showToast(`Failed to load tenants: ${error.message}`, 'error');
        }
    }

    /**
     * Load tenants (public method)
     */
    async loadTenants(params = {}) {
        this.state.loading = true;
        this.updateLoadingState();
        
        try {
            await this.fetchTenants(params);
        } finally {
            this.state.loading = false;
            this.updateLoadingState();
        }
    }

    /**
     * Get cache key for query parameters
     */
    getCacheKey(params) {
        return `tenants-${JSON.stringify(params)}`;
    }

    /**
     * Get data from cache
     */
    getFromCache(key) {
        const cached = this.cache.get(key);
        if (!cached) return null;
        
        // Check if cache is expired
        if (Date.now() - cached.timestamp > this.cacheTTL) {
            this.cache.delete(key);
            return null;
        }
        
        return cached;
    }

    /**
     * Set data in cache
     */
    setCache(key, data, etag) {
        this.cache.set(key, {
            data: data,
            etag: etag,
            timestamp: Date.now()
        });
    }

    /**
     * Handle validation errors
     */
    handleValidationError(errorData) {
        console.warn('Validation error:', errorData);
        
        // Highlight invalid inputs
        if (errorData.errors) {
            Object.keys(errorData.errors).forEach(field => {
                const input = document.querySelector(`[data-filter="${field}"]`);
                if (input) {
                    input.classList.add('error');
                    this.showFieldError(input, errorData.errors[field][0]);
                }
            });
        }
        
        this.showToast('Please check your input and try again.', 'warning');
    }

    /**
     * Show field error
     */
    showFieldError(input, message) {
        this.clearFieldError(input);
        
        const tooltip = document.createElement('div');
        tooltip.className = 'error-tooltip';
        tooltip.textContent = message;
        input.parentNode.appendChild(tooltip);
    }

    /**
     * Clear field error
     */
    clearFieldError(input) {
        input.classList.remove('error');
        const tooltip = input.parentNode.querySelector('.error-tooltip');
        if (tooltip) tooltip.remove();
    }

    /**
     * Update loading state
     */
    updateLoadingState() {
        const container = document.querySelector('.tenants-container');
        if (container) {
            container.classList.toggle('loading', this.state.loading);
        }
        
        const exportBtn = document.querySelector('.export-btn');
        if (exportBtn) {
            exportBtn.disabled = this.state.loading || this.state.meta.total === 0;
        }
    }

    /**
     * Update UI with current state
     */
    updateUI() {
        this.updateTable();
        this.updatePagination();
        this.updateKPIs();
        this.updateAriaLive();
    }

    /**
     * Update table with current data
     */
    updateTable() {
        const tbody = document.querySelector('#tenants-table tbody');
        if (!tbody) return;

        if (this.state.list.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="empty-state">
                        <div class="empty-content">
                            <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No tenants found</h3>
                            <p class="text-gray-500 mb-4">Try adjusting your filters or search terms.</p>
                            <button onclick="window.Tenants.clearFilters()" class="btn-primary">
                                Clear filters
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.list.map(tenant => `
            <tr>
                <td>
                    <input type="checkbox" class="tenant-checkbox" value="${tenant.id}">
                </td>
                <td>${tenant.name}</td>
                <td>${tenant.domain}</td>
                <td>
                    <span class="status-badge status-${tenant.status}">
                        ${tenant.status}
                    </span>
                </td>
                <td>${tenant.users_count || 0}</td>
                <td>${tenant.projects_count || 0}</td>
                <td>${this.formatDate(tenant.created_at)}</td>
                <td>
                    <div class="action-buttons">
                        <button onclick="window.Tenants.viewTenant('${tenant.id}')" class="btn-view" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="window.Tenants.toggleStatus('${tenant.id}')" class="btn-toggle" title="Toggle Status">
                            <i class="fas fa-${tenant.status === 'active' ? 'pause' : 'play'}"></i>
                        </button>
                        <button onclick="window.Tenants.changePlan('${tenant.id}')" class="btn-plan" title="Change Plan">
                            <i class="fas fa-cog"></i>
                        </button>
                        <button onclick="window.Tenants.deleteTenant('${tenant.id}')" class="btn-delete" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    /**
     * Update pagination
     */
    updatePagination() {
        const pagination = document.querySelector('.pagination');
        if (!pagination) return;

        const { page, per_page, last_page, total } = this.state.meta;
        const pages = this.getVisiblePages(page, last_page);
        
        pagination.innerHTML = `
            <div class="pagination-info">
                <span class="text-sm text-gray-600">
                    Showing ${((page - 1) * per_page) + 1} to ${Math.min(page * per_page, total)} of ${total} results
                </span>
            </div>
            <div class="pagination-controls">
                <button ${page <= 1 ? 'disabled' : ''} 
                        onclick="window.Tenants.goToPage(${page - 1})">
                    Previous
                </button>
                ${pages.map(p => `
                    <button ${p === page ? 'class="active"' : ''} 
                            onclick="window.Tenants.goToPage(${p})">
                        ${p}
                    </button>
                `).join('')}
                <button ${page >= last_page ? 'disabled' : ''} 
                        onclick="window.Tenants.goToPage(${page + 1})">
                    Next
                </button>
            </div>
        `;
    }

    /**
     * Update KPI cards
     */
    updateKPIs() {
        const kpis = this.state.kpis;
        if (!kpis) return;

        // Update each KPI card
        this.updateKPICard('total', kpis.total, kpis.deltas?.total, kpis.sparklines?.total);
        this.updateKPICard('active', kpis.active, kpis.deltas?.active, kpis.sparklines?.active);
        this.updateKPICard('disabled', kpis.disabled, kpis.deltas?.disabled, kpis.sparklines?.disabled);
        this.updateKPICard('new30d', kpis.new30d, kpis.deltas?.new30d, kpis.sparklines?.new30d);
        this.updateKPICard('trialExpiring', kpis.trialExpiring, kpis.deltas?.trialExpiring, kpis.sparklines?.trialExpiring);
    }

    /**
     * Update individual KPI card
     */
    updateKPICard(type, value, delta, sparklineData) {
        const card = document.querySelector(`[data-kpi="${type}"]`);
        if (!card) return;

        // Update value
        const valueEl = card.querySelector('.kpi-value');
        if (valueEl) valueEl.textContent = (value || 0).toLocaleString();

        // Update delta
        const deltaEl = card.querySelector('.kpi-delta');
        if (deltaEl && delta !== undefined) {
            const deltaText = delta > 0 ? `+${delta}%` : delta < 0 ? `${delta}%` : '0%';
            deltaEl.textContent = deltaText;
            deltaEl.className = `kpi-delta ${delta > 0 ? 'positive' : delta < 0 ? 'negative' : ''}`;
        }

        // Update sparkline
        const sparklineEl = card.querySelector('.sparkline-container');
        if (sparklineEl && sparklineData) {
            this.createSparkline(sparklineEl, sparklineData, this.getKPIColor(type));
        }

        // Update aria-label
        const button = card.querySelector('button');
        if (button) {
            button.setAttribute('aria-label', this.getKPIAriaLabel(type, value, delta));
        }
    }

    /**
     * Create sparkline chart
     */
    createSparkline(container, data, color) {
        if (!data || data.length === 0) return;
        
        const width = 100;
        const height = 40;
        const padding = 4;
        
        const max = Math.max(...data);
        const min = Math.min(...data);
        const range = max - min || 1;
        
        const points = data.map((value, index) => {
            const x = padding + (index / (data.length - 1)) * (width - 2 * padding);
            const y = padding + ((max - value) / range) * (height - 2 * padding);
            return `${x},${y}`;
        }).join(' ');
        
        container.innerHTML = `
            <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
                <polyline
                    points="${points}"
                    fill="none"
                    stroke="${color}"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        `;
    }

    /**
     * Get KPI color
     */
    getKPIColor(type) {
        const colors = {
            total: '#3B82F6',
            active: '#10B981',
            disabled: '#EF4444',
            new30d: '#8B5CF6',
            trialExpiring: '#F59E0B'
        };
        return colors[type] || '#6B7280';
    }

    /**
     * Get KPI aria-label
     */
    getKPIAriaLabel(type, value, delta) {
        const labels = {
            total: `View all tenants — ${value} total`,
            active: `Filter active tenants — ${value} active`,
            disabled: `Filter disabled tenants — ${value} disabled`,
            new30d: `Filter new tenants — ${value} new in 30 days`,
            trialExpiring: `Filter trial expiring — ${value} expiring`
        };
        return labels[type] || `View ${type} tenants — ${value} total`;
    }

    /**
     * Update aria-live region
     */
    updateAriaLive() {
        const ariaLive = document.querySelector('[aria-live="polite"]');
        if (ariaLive) {
            ariaLive.textContent = `${this.state.list.length} results loaded`;
        }
    }

    /**
     * Get visible page numbers
     */
    getVisiblePages(current, last) {
        const pages = [];
        const start = Math.max(1, current - 2);
        const end = Math.min(last, current + 2);
        
        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
        return pages;
    }

    /**
     * Format date for display
     */
    formatDate(dateString) {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 3000);
    }

    /**
     * Clear all filters
     */
    clearFilters() {
        this.filters = {
            q: '',
            status: '',
            plan: '',
            from: '',
            to: '',
            sort: '-created_at'
        };
        
        this.state.meta.page = 1;
        
        // Update UI
        this.updateUIFromState();
        
        // Clear filter chips
        const filterChips = document.querySelectorAll('[data-filter-chip]');
        filterChips.forEach(chip => {
            chip.classList.remove('active');
            chip.setAttribute('aria-pressed', 'false');
        });
        
        this.loadTenants();
        this.updateUrl();
    }

    /**
     * Go to specific page
     */
    goToPage(page) {
        if (page < 1 || page > this.state.meta.last_page) return;
        
        this.state.meta.page = page;
        this.loadTenants();
        this.updateUrl();
    }

    /**
     * Initialize soft refresh
     */
    initSoftRefresh() {
        // Global refresh function
        window.Tenants = {
            refresh: () => this.softRefresh(),
            clearFilters: () => this.clearFilters(),
            goToPage: (page) => this.goToPage(page),
            viewTenant: (id) => this.viewTenant(id),
            toggleStatus: (id) => this.toggleStatus(id),
            changePlan: (id) => this.changePlan(id),
            deleteTenant: (id) => this.deleteTenant(id)
        };
    }

    /**
     * Soft refresh
     */
    async softRefresh() {
        const panels = document.querySelectorAll('.tenants-table, .kpi-cards');
        panels.forEach(panel => panel.classList.add('soft-dim'));
        
        try {
            // Clear cache to force fresh data
            this.cache.clear();
            await this.loadTenants();
        } finally {
            setTimeout(() => {
                panels.forEach(panel => panel.classList.remove('soft-dim'));
            }, 300);
        }
    }

    /**
     * Initialize search
     */
    initSearch() {
        const searchInput = document.querySelector('#search-input');
        if (!searchInput) return;

        searchInput.addEventListener('input', (e) => {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.filters.q = e.target.value;
                this.state.meta.page = 1;
                this.loadTenants();
                this.updateUrl();
            }, 300);
        });
    }

    /**
     * Initialize filters
     */
    initFilters() {
        // Date range validation
        const fromInput = document.querySelector('[data-filter="from"]');
        const toInput = document.querySelector('[data-filter="to"]');
        
        if (fromInput && toInput) {
            fromInput.addEventListener('change', () => this.validateDateRange());
            toInput.addEventListener('change', () => this.validateDateRange());
        }

        // Filter elements
        const filterElements = document.querySelectorAll('[data-filter]');
        filterElements.forEach(element => {
            element.addEventListener('change', (e) => {
                const filterType = e.target.dataset.filter;
                const filterValue = e.target.value;
                
                this.filters[filterType] = filterValue;
                this.state.meta.page = 1;
                
                // Validate date range before API call
                if (filterType === 'from' || filterType === 'to') {
                    if (!this.validateDateRange()) return;
                }
                
                this.loadTenants();
                this.updateUrl();
            });
        });

        // Filter chips
        const filterChips = document.querySelectorAll('[data-filter-chip]');
        filterChips.forEach(chip => {
            chip.addEventListener('click', (e) => {
                e.preventDefault();
                const filterType = chip.dataset.filterChip;
                const filterValue = chip.dataset.filterValue;
                
                // Toggle chip state
                const isActive = chip.classList.contains('active');
                chip.classList.toggle('active');
                chip.setAttribute('aria-pressed', !isActive);
                
                // Apply filter
                this.filters[filterType] = isActive ? '' : filterValue;
                this.state.meta.page = 1;
                this.loadTenants();
                this.updateUrl();
            });
        });
    }

    /**
     * Validate date range
     */
    validateDateRange() {
        const fromInput = document.querySelector('[data-filter="from"]');
        const toInput = document.querySelector('[data-filter="to"]');
        
        if (!fromInput || !toInput) return true;
        
        const fromDate = new Date(fromInput.value);
        const toDate = new Date(toInput.value);
        
        if (fromInput.value && toInput.value && fromDate > toDate) {
            this.showFieldError(fromInput, 'From date must be before To date');
            return false;
        }
        
        this.clearFieldError(fromInput);
        return true;
    }

    /**
     * Initialize pagination
     */
    initPagination() {
        // Pagination is handled by updatePagination method
    }

    /**
     * Initialize export
     */
    initExport() {
        const exportBtn = document.querySelector('.export-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportTenants());
        }
    }

    /**
     * Export tenants
     */
    async exportTenants() {
        const exportBtn = document.querySelector('.export-btn');
        if (!exportBtn || exportBtn.disabled) return;

        exportBtn.disabled = true;
        exportBtn.textContent = 'Exporting...';

        try {
            const queryParams = {
                q: this.filters.q,
                status: this.filters.status,
                plan: this.filters.plan,
                from: this.filters.from,
                to: this.filters.to,
                sort: this.filters.sort
            };
            
            Object.keys(queryParams).forEach(key => {
                if (!queryParams[key]) delete queryParams[key];
            });
            
            const queryString = new URLSearchParams(queryParams).toString();
            const url = `/api/admin/tenants/export.csv?${queryString}`;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'text/csv',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });

            if (response.status === 429) {
                const retryAfter = response.headers.get('Retry-After');
                this.showToast(`Export rate limited. Please try again in ${retryAfter} seconds.`, 'warning');
                return;
            }

            if (!response.ok) {
                throw new Error(`Export failed: ${response.statusText}`);
            }

            // Download the CSV
            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = `tenants_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(downloadUrl);

            this.showToast('Export completed successfully', 'success');

        } catch (error) {
            console.error('Export failed:', error);
            this.showToast(`Export failed: ${error.message}`, 'error');
        } finally {
            exportBtn.disabled = false;
            exportBtn.textContent = 'Export';
        }
    }

    /**
     * Initialize KPI buttons
     */
    initKPIButtons() {
        const kpiButtons = document.querySelectorAll('[data-kpi-action]');
        kpiButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const action = button.dataset.kpiAction;
                this.handleKPIAction(action);
            });
        });
    }

    /**
     * Handle KPI button actions
     */
    handleKPIAction(action) {
        // Clear all filters first
        this.filters = {
            q: '',
            status: '',
            plan: '',
            from: '',
            to: '',
            sort: '-created_at'
        };
        
        switch (action) {
            case 'view-all':
                // No additional filters
                break;
            case 'filter-active':
                this.filters.status = 'active';
                break;
            case 'filter-disabled':
                this.filters.status = 'disabled';
                break;
            case 'view-recent':
                this.filters.status = 'new30d';
                this.filters.sort = '-created_at';
                break;
            case 'view-expiring':
                this.filters.status = 'trialExpiring';
                break;
        }
        
        this.state.meta.page = 1;
        this.updateUIFromState();
        this.loadTenants();
        this.updateUrl();
    }

    /**
     * Initialize bulk actions
     */
    initBulkActions() {
        // Bulk actions will be implemented when checkboxes are added
    }

    /**
     * View tenant details
     */
    viewTenant(id) {
        window.location.href = `/admin/tenants/${id}`;
    }

    /**
     * Toggle tenant status
     */
    async toggleStatus(id) {
        try {
            // Stub implementation
            this.showToast('Status toggle not enabled in this environment', 'info');
        } catch (error) {
            this.showToast('Failed to toggle status', 'error');
        }
    }

    /**
     * Change tenant plan
     */
    async changePlan(id) {
        try {
            // Stub implementation
            this.showToast('Plan change not enabled in this environment', 'info');
        } catch (error) {
            this.showToast('Failed to change plan', 'error');
        }
    }

    /**
     * Delete tenant
     */
    async deleteTenant(id) {
        if (!confirm('Are you sure you want to delete this tenant?')) return;
        
        try {
            // Stub implementation
            this.showToast('Delete not enabled in this environment', 'info');
        } catch (error) {
            this.showToast('Failed to delete tenant', 'error');
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.tenantsPage = new TenantsPage();
});

// Export for global access
window.TenantsPage = TenantsPage;
