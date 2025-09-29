/**
 * Tenants Page - Soft Refresh with SWR/ETag Support
 * Implements smooth UX with ETag caching and soft refresh
 */

class TenantsPage {
    constructor() {
        this.cache = new Map();
        this.cacheTTL = 30000; // 30 seconds
        this.debounceTimer = null;
        this.isLoading = false;
        
        this.init();
    }

    init() {
        console.log('TenantsPage initialized with soft refresh support');
        
        // Parse URL parameters first
        this.parseUrlParams();
        
        // Initialize soft refresh for tenants
        this.initSoftRefresh();
        
        // Initialize search with debounce
        this.initSearch();
        
        // Initialize filters
        this.initFilters();
        
        // Initialize pagination
        this.initPagination();
        
        // Initialize export button
        this.initExport();
        
        // Load initial data
        this.loadTenants();
    }

    /**
     * Initialize export button
     */
    initExport() {
        const exportBtn = document.querySelector('.export-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportTenants());
        }
    }

    /**
     * Initialize soft refresh functionality
     */
    initSoftRefresh() {
        // Add data-soft-refresh attribute to tenants link in sidebar
        const tenantsLink = document.querySelector('a[href*="/admin/tenants"]');
        if (tenantsLink) {
            tenantsLink.setAttribute('data-soft-refresh', 'tenants');
        }

        // Intercept clicks on tenants route
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[data-soft-refresh="tenants"]');
            if (link && link.href.includes('/admin/tenants')) {
                e.preventDefault();
                this.softRefresh();
            }
        });

        // Global refresh function
        window.tenants = {
            refresh: () => this.softRefresh()
        };
    }

    /**
     * Soft refresh - dim panel and refresh data without full page reload
     */
    async softRefresh() {
        const panel = document.querySelector('#tenants-table, .tenants-list');
        if (!panel) return;

        // Add soft dim effect
        panel.classList.add('soft-dim');
        
        try {
            // Clear cache to force fresh data
            this.cache.clear();
            
            // Reload tenants data
            await this.loadTenants();
            
            // Update URL without page reload
            this.updateUrl();
            
        } catch (error) {
            console.error('Soft refresh failed:', error);
        } finally {
            // Remove dim effect
            setTimeout(() => {
                panel.classList.remove('soft-dim');
            }, 300);
        }
    }

    /**
     * Load tenants with ETag support
     */
    async loadTenants() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        const url = this.buildApiUrl();
        const cacheKey = this.getCacheKey();
        
        try {
            // Check cache first
            const cached = this.getFromCache(cacheKey);
            if (cached) {
                this.updateUI(cached);
                return;
            }

            // Make request with ETag
            const response = await this.getWithETag(cacheKey, url);
            
            if (response.status === 304) {
                // Use cached data
                const cached = this.getFromCache(cacheKey);
                if (cached) {
                    this.updateUI(cached);
                }
                return;
            }

            const data = await response.json();
            
            // Cache the response
            this.setCache(cacheKey, data, response.headers.get('ETag'));
            
            // Update UI
            this.updateUI(data);
            
        } catch (error) {
            console.error('Failed to load tenants:', error);
            this.showError('Failed to load tenants data');
        } finally {
            this.isLoading = false;
        }
    }

    /**
     * Get data with ETag support
     */
    async getWithETag(cacheKey, url) {
        const cached = this.cache.get(cacheKey);
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };

        // Add ETag if we have cached data
        if (cached && cached.etag) {
            headers['If-None-Match'] = cached.etag;
        }

        return fetch(url, {
            method: 'GET',
            headers: headers,
            credentials: 'same-origin'
        });
    }

    /**
     * Initialize search with debounce
     */
    initSearch() {
        const searchInput = document.querySelector('#search-input, [name="search"]');
        if (!searchInput) return;

        searchInput.addEventListener('input', (e) => {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.searchQuery = e.target.value;
                this.page = 1; // Reset to first page
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
        const fromInput = document.querySelector('[data-filter="dateFrom"]');
        const toInput = document.querySelector('[data-filter="dateTo"]');
        
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
                
                this[filterType] = filterValue;
                this.page = 1; // Reset to first page
                
                // Validate date range before API call
                if (filterType === 'dateFrom' || filterType === 'dateTo') {
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
                this[filterType] = isActive ? '' : filterValue;
                this.page = 1;
                this.loadTenants();
                this.updateUrl();
            });
        });
    }

    /**
     * Validate date range
     */
    validateDateRange() {
        const fromInput = document.querySelector('[data-filter="dateFrom"]');
        const toInput = document.querySelector('[data-filter="dateTo"]');
        
        if (!fromInput || !toInput) return true;
        
        const fromDate = new Date(fromInput.value);
        const toDate = new Date(toInput.value);
        
        if (fromInput.value && toInput.value && fromDate > toDate) {
            // Show error tooltip
            this.showDateRangeError(fromInput, 'From date must be before To date');
            return false;
        }
        
        // Clear error
        this.clearDateRangeError(fromInput);
        return true;
    }

    /**
     * Show date range error
     */
    showDateRangeError(input, message) {
        // Remove existing error
        this.clearDateRangeError(input);
        
        // Add error class
        input.classList.add('error');
        
        // Create tooltip
        const tooltip = document.createElement('div');
        tooltip.className = 'error-tooltip';
        tooltip.textContent = message;
        input.parentNode.appendChild(tooltip);
    }

    /**
     * Clear date range error
     */
    clearDateRangeError(input) {
        input.classList.remove('error');
        const tooltip = input.parentNode.querySelector('.error-tooltip');
        if (tooltip) tooltip.remove();
    }

    /**
     * Initialize pagination
     */
    initPagination() {
        const paginationLinks = document.querySelectorAll('.pagination a');
        paginationLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(e.target.dataset.page || e.target.textContent);
                if (page && page !== this.page) {
                    this.page = page;
                    this.loadTenants();
                    this.updateUrl();
                }
            });
        });
    }

    /**
     * Build API URL with current filters
     */
    buildApiUrl() {
        const params = new URLSearchParams();
        
        if (this.searchQuery) params.set('q', this.searchQuery);
        if (this.statusFilter) params.set('status', this.statusFilter);
        if (this.planFilter) params.set('plan', this.planFilter);
        if (this.dateFrom) params.set('from', this.dateFrom);
        if (this.dateTo) params.set('to', this.dateTo);
        if (this.sortBy) params.set('sort', this.sortOrder === 'desc' ? `-${this.sortBy}` : this.sortBy);
        if (this.page > 1) params.set('page', this.page);
        if (this.perPage !== 20) params.set('per_page', this.perPage);
        
        return `/api/admin/tenants?${params.toString()}`;
    }

    /**
     * Get cache key for current state
     */
    getCacheKey() {
        return `tenants-${JSON.stringify({
            q: this.searchQuery,
            status: this.statusFilter,
            plan: this.planFilter,
            from: this.dateFrom,
            to: this.dateTo,
            sort: this.sortBy,
            order: this.sortOrder,
            page: this.page,
            per_page: this.perPage
        })}`;
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
        
        return cached.data;
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
     * Update UI with new data
     */
    updateUI(data) {
        // Update table
        this.updateTable(data.data);
        
        // Update pagination
        this.updatePagination(data.meta);
        
        // Update KPI cards
        this.updateKPIs(data.meta);
    }

    /**
     * Update table with new data
     */
    updateTable(tenants) {
        const tbody = document.querySelector('#tenants-table tbody');
        if (!tbody) return;

        if (tenants.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="empty-state">
                        <div class="empty-content">
                            <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No tenants found</h3>
                            <p class="text-gray-500 mb-4">Try adjusting your filters or search terms.</p>
                            <button onclick="window.tenants.clearFilters()" class="btn-primary">
                                Clear filters
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = tenants.map(tenant => `
            <tr>
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
                        <button onclick="window.tenants.edit('${tenant.id}')" class="btn-edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="window.tenants.delete('${tenant.id}')" class="btn-delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        // Update aria-live region
        const ariaLive = document.querySelector('[aria-live="polite"]');
        if (ariaLive) {
            ariaLive.textContent = `${tenants.length} results loaded`;
        }
    }

    /**
     * Update pagination
     */
    updatePagination(meta) {
        const pagination = document.querySelector('.pagination');
        if (!pagination) return;

        const pages = this.getVisiblePages(meta.current_page, meta.last_page);
        
        pagination.innerHTML = `
            <div class="pagination-info">
                <span class="text-sm text-gray-600">
                    Showing ${((meta.current_page - 1) * meta.per_page) + 1} to ${Math.min(meta.current_page * meta.per_page, meta.total)} of ${meta.total} results
                </span>
            </div>
            <div class="pagination-controls">
                <button ${meta.current_page <= 1 ? 'disabled' : ''} 
                        onclick="window.tenants.goToPage(${meta.current_page - 1})">
                    Previous
                </button>
                ${pages.map(page => `
                    <button ${page === meta.current_page ? 'class="active"' : ''} 
                            onclick="window.tenants.goToPage(${page})">
                        ${page}
                    </button>
                `).join('')}
                <button ${meta.current_page >= meta.last_page ? 'disabled' : ''} 
                        onclick="window.tenants.goToPage(${meta.current_page + 1})">
                    Next
                </button>
            </div>
        `;
    }

    /**
     * Update KPI cards
     */
    updateKPIs(meta) {
        // KPI data structure
        const kpis = {
            total: meta.total,
            active: meta.active || 0,
            disabled: meta.disabled || 0,
            new30d: meta.new30d || 0,
            trialExpiring: meta.trialExpiring || 0,
            deltas: {
                total: meta.deltaTotal || 0,
                active: meta.deltaActive || 0,
                disabled: meta.deltaDisabled || 0,
                new30d: meta.deltaNew30d || 0,
                trialExpiring: meta.deltaTrialExpiring || 0
            }
        };

        // Update KPI cards with values, deltas, and sparklines
        this.updateKPICard('total', kpis.total, kpis.deltas.total, kpis.total > 0);
        this.updateKPICard('active', kpis.active, kpis.deltas.active, kpis.active > 0);
        this.updateKPICard('disabled', kpis.disabled, kpis.deltas.disabled, kpis.disabled > 0);
        this.updateKPICard('new30d', kpis.new30d, kpis.deltas.new30d, kpis.new30d > 0);
        this.updateKPICard('trialExpiring', kpis.trialExpiring, kpis.deltas.trialExpiring, kpis.trialExpiring > 0);
    }

    /**
     * Update individual KPI card
     */
    updateKPICard(type, value, delta, hasData) {
        const card = document.querySelector(`[data-kpi="${type}"]`);
        if (!card) return;

        // Update value
        const valueEl = card.querySelector('.kpi-value');
        if (valueEl) valueEl.textContent = value.toLocaleString();

        // Update delta
        const deltaEl = card.querySelector('.kpi-delta');
        if (deltaEl) {
            const deltaText = delta > 0 ? `+${delta}%` : delta < 0 ? `${delta}%` : '0%';
            deltaEl.textContent = deltaText;
            deltaEl.className = `kpi-delta ${delta > 0 ? 'positive' : delta < 0 ? 'negative' : ''}`;
        }

        // Update sparkline
        const sparklineEl = card.querySelector('.sparkline-container');
        if (sparklineEl && hasData) {
            this.createSparkline(sparklineEl, this.generateSparklineData(value, delta), this.getKPIColor(type));
        }

        // Update aria-label
        const button = card.querySelector('button');
        if (button) {
            button.setAttribute('aria-label', this.getKPIAriaLabel(type, value, delta));
        }
    }

    /**
     * Generate sparkline data
     */
    generateSparklineData(value, delta) {
        const data = [];
        const baseValue = Math.max(1, value - delta);
        for (let i = 0; i < 5; i++) {
            data.push(baseValue + (delta * i / 4));
        }
        return data;
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
     * Update URL without page reload
     */
    updateUrl() {
        const params = new URLSearchParams();
        
        if (this.searchQuery) params.set('q', this.searchQuery);
        if (this.statusFilter) params.set('status', this.statusFilter);
        if (this.planFilter) params.set('plan', this.planFilter);
        if (this.dateFrom) params.set('from', this.dateFrom);
        if (this.dateTo) params.set('to', this.dateTo);
        if (this.sortBy) params.set('sort', this.sortOrder === 'desc' ? `-${this.sortBy}` : this.sortBy);
        if (this.page > 1) params.set('page', this.page);
        if (this.perPage !== 20) params.set('per_page', this.perPage);
        
        const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
        window.history.pushState({ per_page: this.perPage }, '', newUrl);
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
     * Show error message
     */
    showError(message) {
        const errorContainer = document.querySelector('.error-container');
        if (errorContainer) {
            errorContainer.textContent = message;
            errorContainer.style.display = 'block';
        }
    }

    /**
     * Export tenants with current filters
     */
    async exportTenants() {
        const exportBtn = document.querySelector('.export-btn');
        if (!exportBtn) return;

        // Disable button during loading
        exportBtn.disabled = true;
        exportBtn.textContent = 'Exporting...';

        try {
            const url = this.buildApiUrl().replace('/api/admin/tenants', '/api/admin/tenants/export.csv');
            
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
            // Re-enable button
            exportBtn.disabled = false;
            exportBtn.textContent = 'Export';
        }
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
        this.searchQuery = '';
        this.statusFilter = '';
        this.planFilter = '';
        this.dateFrom = '';
        this.dateTo = '';
        this.sortBy = 'name';
        this.sortOrder = 'asc';
        this.page = 1;
        
        // Update UI
        const searchInput = document.querySelector('#search-input');
        if (searchInput) searchInput.value = '';
        
        const filterSelects = document.querySelectorAll('[data-filter]');
        filterSelects.forEach(select => {
            if (select.type === 'select-one') {
                select.selectedIndex = 0;
            } else {
                select.value = '';
            }
        });
        
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
     * Parse URL parameters on page load
     */
    parseUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        this.searchQuery = urlParams.get('q') || '';
        this.statusFilter = urlParams.get('status') || '';
        this.planFilter = urlParams.get('plan') || '';
        this.dateFrom = urlParams.get('from') || '';
        this.dateTo = urlParams.get('to') || '';
        this.sortBy = urlParams.get('sort')?.replace('-', '') || 'name';
        this.sortOrder = urlParams.get('sort')?.startsWith('-') ? 'desc' : 'asc';
        this.page = parseInt(urlParams.get('page')) || 1;
        this.perPage = parseInt(urlParams.get('per_page') ?? '20', 10) || 20;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.tenantsPage = new TenantsPage();
});

// Export for global access
window.TenantsPage = TenantsPage;
