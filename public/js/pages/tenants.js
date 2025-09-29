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
        
        // Initialize soft refresh for tenants
        this.initSoftRefresh();
        
        // Initialize search with debounce
        this.initSearch();
        
        // Initialize filters
        this.initFilters();
        
        // Initialize pagination
        this.initPagination();
        
        // Load initial data
        this.loadTenants();
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
        const filterElements = document.querySelectorAll('[data-filter]');
        filterElements.forEach(element => {
            element.addEventListener('change', (e) => {
                const filterType = e.target.dataset.filter;
                const filterValue = e.target.value;
                
                this[filterType] = filterValue;
                this.page = 1; // Reset to first page
                this.loadTenants();
                this.updateUrl();
            });
        });
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
    }

    /**
     * Update pagination
     */
    updatePagination(meta) {
        const pagination = document.querySelector('.pagination');
        if (!pagination) return;

        const pages = this.getVisiblePages(meta.current_page, meta.last_page);
        
        pagination.innerHTML = `
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
        `;
    }

    /**
     * Update KPI cards
     */
    updateKPIs(meta) {
        // Update total tenants
        const totalElement = document.querySelector('[data-kpi="total"]');
        if (totalElement) {
            totalElement.textContent = meta.total;
        }

        // Update other KPIs based on filtered data
        // This would need to be calculated from the actual data
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
        window.history.replaceState({}, '', newUrl);
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
        this.perPage = parseInt(urlParams.get('per_page')) || 20;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.tenantsPage = new TenantsPage();
});

// Export for global access
window.TenantsPage = TenantsPage;
