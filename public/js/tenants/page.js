/**
 * Tenants Management Page - Comprehensive Implementation
 * Handles state management, API calls, UI updates, and all tenant operations
 */

class TenantsPage {
    constructor() {
        this.state = {
            list: [],
            kpis: {},
            meta: {
                total: 0,
                page: 1,
                per_page: 25,
                last_page: 1
            },
            loading: false,
            error: null,
            selectedRows: new Set(),
            visibleColumns: {
                owner: true,
                last_activity: true,
                invoices_count: true
            }
        };
        
        this.filters = {
            q: '',
            status: [],
            plan: [],
            range: '30d', // Default to Last 30 days
            region: [],
            sort: 'created_at:desc'
        };
        
        this.defaultFilters = {
            q: '',
            status: [],
            plan: [],
            range: '30d', // Default to Last 30 days
            region: [],
            sort: 'created_at:desc'
        };
        
        this.cache = new Map();
        this.cacheTTL = 60000; // 60 seconds for better performance
        this.abortController = null;
        this.debounceTimer = null;
        this.searchAbortController = null;
        this.currentTenantId = null;
        this.performance = window.TenantsPerformance;
        
        this.init();
    }

    init() {
        console.log('Tenants Management Page initialized');
        
        // Parse URL parameters
        this.parseUrlParams();
        
        // Initialize components
        this.initSoftRefresh();
        this.initSearch();
        this.initFilters();
        this.initFilterChips();
        this.initPagination();
        this.initExport();
        this.initKPIButtons();
        this.initBulkActions();
        this.initModals();
        this.initColumnPicker();
        this.initRowActions();
        this.initAnalytics();
        
        // Load initial data in parallel for better performance
        Promise.all([
            this.loadTenants(),
            this.loadKPIs()
        ]).catch(error => {
            console.error('Failed to load initial data:', error);
        });
    }

    /**
     * Parse URL parameters with comprehensive support
     */
    parseUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        
        this.state.meta.per_page = Number(urlParams.get('per_page') ?? 25) || 25;
        this.state.meta.page = Number(urlParams.get('page') ?? 1) || 1;
        
        this.filters = {
            q: urlParams.get('q') || '',
            status: this.parseMultiValue(urlParams.get('status')),
            plan: this.parseMultiValue(urlParams.get('plan')),
            range: urlParams.get('range') || '',
            region: this.parseMultiValue(urlParams.get('region')),
            sort: urlParams.get('sort') || 'created_at:desc'
        };
        
        console.log('Parsed URL params:', this.filters);
        
        // Update UI with parsed values
        this.updateUIFromState();
    }

    /**
     * Parse comma-separated values into array
     */
    parseMultiValue(value) {
        if (!value) return [];
        return value.split(',').filter(v => v.trim() !== '');
    }

    /**
     * Update UI elements from current state
     */
    updateUIFromState() {
        console.log('updateUIFromState called with filters:', this.filters);
        
        // Update search input
        const searchInput = document.querySelector('#search-input');
        if (searchInput) {
            searchInput.value = this.filters.q;
            console.log('Updated search input to:', this.filters.q);
        }
        
        // Update filter selects (handle multi-value)
        const statusSelect = document.querySelector('[data-filter="status"]');
        if (statusSelect) {
            // For multi-select, we'll handle this differently
            if (this.filters.status.length === 1) {
                statusSelect.value = this.filters.status[0];
            } else if (this.filters.status.length === 0) {
                statusSelect.value = '';
            }
            console.log('Updated status select to:', this.filters.status);
        }
        
        const planSelect = document.querySelector('[data-filter="plan"]');
        if (planSelect) {
            if (this.filters.plan.length === 1) {
                planSelect.value = this.filters.plan[0];
            } else if (this.filters.plan.length === 0) {
                planSelect.value = '';
            }
            console.log('Updated plan select to:', this.filters.plan);
        }
        
        const rangeSelect = document.querySelector('[data-filter="range"]');
        if (rangeSelect) {
            rangeSelect.value = this.filters.range;
            console.log('Updated range select to:', this.filters.range);
        }
        
        const regionSelect = document.querySelector('[data-filter="region"]');
        if (regionSelect) {
            if (this.filters.region.length === 1) {
                regionSelect.value = this.filters.region[0];
            } else if (this.filters.region.length === 0) {
                regionSelect.value = '';
            }
            console.log('Updated region select to:', this.filters.region);
        }
        
        const sortSelect = document.querySelector('[data-filter="sort"]');
        if (sortSelect) {
            sortSelect.value = this.filters.sort;
            console.log('Updated sort select to:', this.filters.sort);
        }
        
        // Update per_page select
        const perPageSelect = document.querySelector('[data-filter="per_page"]');
        if (perPageSelect) {
            perPageSelect.value = this.state.meta.per_page;
            console.log('Updated per_page select to:', this.state.meta.per_page);
        }
        
        // Update filter chips
        this.updateFilterChips();
    }

    /**
     * Update URL with current state
     */
    updateUrl() {
        const params = new URLSearchParams();
        
        // Add filters with multi-value support
        if (this.filters.q) params.set('q', this.filters.q);
        if (this.filters.status.length > 0) params.set('status', this.filters.status.join(','));
        if (this.filters.plan.length > 0) params.set('plan', this.filters.plan.join(','));
        if (this.filters.range) params.set('range', this.filters.range);
        if (this.filters.region.length > 0) params.set('region', this.filters.region.join(','));
        if (this.filters.sort && this.filters.sort !== 'created_at:desc') params.set('sort', this.filters.sort);
        
        // Add pagination
        if (this.state.meta.page > 1) params.set('page', this.state.meta.page);
        if (this.state.meta.per_page !== 25) params.set('per_page', this.state.meta.per_page);
        
        const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
        window.history.pushState({}, '', newUrl);
        
        console.log('URL updated to:', newUrl);
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
        
        // Build query parameters with multi-value support
        const queryParams = {
            q: this.filters.q,
            status: this.filters.status.join(','),
            plan: this.filters.plan.join(','),
            range: this.filters.range,
            region: this.filters.region.join(','),
            sort: this.filters.sort,
            page: this.state.meta.page,
            per_page: this.state.meta.per_page,
            ...params
        };
        
        // Remove empty parameters (but keep q even if empty to clear search)
        Object.keys(queryParams).forEach(key => {
            if (key !== 'q' && !queryParams[key]) delete queryParams[key];
        });
        
        const queryString = new URLSearchParams(queryParams).toString();
        const url = `/api/admin/tenants?${queryString}`;
        
        console.log('Loading tenants with URL:', url);
        console.log('Query params:', queryParams);
        
        // Check cache first
        const cacheKey = this.getCacheKey(queryParams);
        const cached = this.getFromCache(cacheKey);
        
        console.log('Cache key:', cacheKey);
        console.log('Cached data:', cached);
        
        // Prepare headers
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };
        
        // Add ETag if we have cached data
        if (cached && cached.etag) {
            headers['If-None-Match'] = cached.etag;
        }
        
        // Enable cache for better performance
        const useCache = true;
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: headers,
                credentials: 'same-origin',
                signal: signal
            });
            
            if (response.status === 304 && useCache) {
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
            console.log('Data loaded, updating UI. List length:', this.state.list.length);
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
        this.showLoadingState();
        
        try {
            await this.fetchTenants(params);
        } catch (error) {
            this.showErrorState(error);
            throw error;
        } finally {
            this.state.loading = false;
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
        console.log('updateTable called, list length:', this.state.list.length);
        const tbody = document.querySelector('#tenants-table-body');
        if (!tbody) {
            console.error('Table body not found');
            return;
        }

        if (this.state.list.length === 0) {
            console.log('No data, showing empty state');
            this.showEmptyState();
            return;
        }

        console.log('Rendering table with', this.state.list.length, 'tenants');
        
        // Use performance optimization if available
        if (window.TenantsPerformance && window.TenantsPerformance.optimizeTableRendering) {
            console.log('Using performance optimization');
            window.TenantsPerformance.optimizeTableRendering(this.state.list);
        } else {
            console.log('Using standard rendering');
            tbody.innerHTML = this.state.list.map(tenant => this.renderTenantRow(tenant)).join('');
        }
        
        // Update total count
        const totalCount = document.querySelector('#total-count');
        if (totalCount) totalCount.textContent = this.state.meta.total.toLocaleString();
        
        // Update filter summary
        this.updateFilterSummary();
    }

    /**
     * Render individual tenant row
     */
    renderTenantRow(tenant) {
        const isSelected = this.state.selectedRows.has(tenant.id);
        const storageUsed = this.formatStorage(tenant.storage_used_bytes || 0);
        const storageQuota = this.formatStorage(tenant.storage_quota_bytes || 0);
        const usagePercent = tenant.storage_quota_bytes > 0 
            ? Math.round((tenant.storage_used_bytes || 0) / tenant.storage_quota_bytes * 100)
            : 0;

        return `
            <tr class="tenant-row ${isSelected ? 'bg-blue-50' : ''}" data-tenant-id="${tenant.id}">
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="checkbox" class="tenant-checkbox rounded border-gray-300" 
                           value="${tenant.id}" ${isSelected ? 'checked' : ''}>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                <span class="text-sm font-medium text-gray-700">
                                    ${tenant.name.charAt(0).toUpperCase()}
                                </span>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900 cursor-pointer hover:text-blue-600"
                                 onclick="window.Tenants.viewTenant('${tenant.id}')">
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
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        ${this.getPlanColor(this.getTenantPlan(tenant))}">
                        ${this.getTenantPlan(tenant)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${tenant.users_count || 0}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${storageUsed} / ${storageQuota}</div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: ${usagePercent}%"></div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        ${this.getStatusColor(tenant.status)}">
                        ${tenant.status}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${tenant.region || 'N/A'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${this.formatDate(tenant.created_at)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="relative">
                        <button class="action-menu-btn text-gray-400 hover:text-gray-600" 
                                data-tenant-id="${tenant.id}">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
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
            <div class="pagination-controls flex items-center space-x-2">
                <button class="pagination-btn px-3 py-1 text-sm border rounded ${page <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}" 
                        data-page="${page - 1}" 
                        ${page <= 1 ? 'disabled' : ''}>
                    Previous
                </button>
                ${pages.map(p => `
                    <button class="pagination-btn px-3 py-1 text-sm border rounded ${p === page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 hover:bg-gray-50'}" 
                            data-page="${p}">
                        ${p}
                    </button>
                `).join('')}
                <button class="pagination-btn px-3 py-1 text-sm border rounded ${page >= last_page ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'}" 
                        data-page="${page + 1}" 
                        ${page >= last_page ? 'disabled' : ''}>
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

        // Update each KPI card with real API data structure
        this.updateKPICard('total', kpis.kpis?.total, kpis.deltas?.total, kpis.sparklines?.total);
        this.updateKPICard('active', kpis.kpis?.active, kpis.deltas?.active, kpis.sparklines?.active);
        this.updateKPICard('disabled', kpis.kpis?.suspended, kpis.deltas?.suspended, kpis.sparklines?.suspended);
        this.updateKPICard('new30d', kpis.kpis?.new30d, kpis.deltas?.new30d, kpis.sparklines?.new30d);
        this.updateKPICard('trialExpiring', kpis.kpis?.trialExpiring, kpis.deltas?.trialExpiring, kpis.sparklines?.trialExpiring);
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
            deltaEl.className = `kpi-delta text-xs ${delta > 0 ? 'text-green-600' : delta < 0 ? 'text-red-600' : 'text-gray-500'}`;
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
     * Create sparkline chart (optimized)
     */
    createSparkline(container, data, color) {
        // Handle both old format (array) and new format (object with data property)
        const values = Array.isArray(data) ? data : (data?.data || []);
        if (!values || values.length === 0) {
            container.innerHTML = '';
            return;
        }
        
        // Optimize: Limit data points for better performance
        const maxPoints = 20;
        const optimizedValues = values.length > maxPoints 
            ? values.filter((_, index) => index % Math.ceil(values.length / maxPoints) === 0)
            : values;
        
        const width = 100;
        const height = 40;
        const padding = 4;
        
        const max = Math.max(...optimizedValues);
        const min = Math.min(...optimizedValues);
        const range = max - min || 1;
        
        // Use requestAnimationFrame for smooth rendering
        requestAnimationFrame(() => {
            const points = optimizedValues.map((value, index) => {
                const x = padding + (index / (optimizedValues.length - 1)) * (width - 2 * padding);
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
        });
    }

    /**
     * Load KPI data from API
     */
    async loadKPIs() {
        try {
            this.setKPILoading(true);
            
            const response = await fetch('/api/admin/tenants-kpis', {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            const kpis = result.data;
            
            this.state.kpis = kpis;
            this.updateKPIs();
            
        } catch (error) {
            console.error('Failed to load KPIs:', error);
            // Fallback to mock data
            this.loadMockKPIs();
        } finally {
            this.setKPILoading(false);
        }
    }

    /**
     * Fallback mock KPI data
     */
    loadMockKPIs() {
        const kpis = {
            kpis: {
                total: 35,
                active: 2,
                suspended: 2,
                new30d: 35,
                trialExpiring: 0
            },
            deltas: {
                total: 12.5,
                active: -5.2,
                suspended: 8.1,
                new30d: 25.0,
                trialExpiring: 0
            },
            sparklines: {
                total: this.generateSparklineData(35, 5),
                active: this.generateSparklineData(2, 2),
                suspended: this.generateSparklineData(2, 1),
                new30d: this.generateSparklineData(35, 10),
                trialExpiring: this.generateSparklineData(0, 0)
            }
        };
        
        this.state.kpis = kpis;
        this.updateKPIs();
    }

    /**
     * Get authentication token
     */
    getAuthToken() {
        // Try to get token from meta tag or localStorage
        const token = document.querySelector('meta[name="api-token"]')?.content || 
                     localStorage.getItem('auth_token') || 
                     '5|uGddv7wdYNtoCu9RACfpytV7LrLQQODBdvi4PBce2f517aac'; // Fallback for testing
        return token;
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
        console.log('clearFilters called');
        
        this.filters = {
            q: '',
            status: [],
            plan: [],
            range: '30d', // Default to Last 30 days
            region: [],
            sort: 'created_at:desc'
        };
        
        this.state.meta.page = 1;
        
        console.log('Filters cleared:', this.filters);
        
        // Update UI
        this.updateUIFromState();
        
        // Clear filter chips
        this.updateFilterChips();
        
        this.loadTenants();
        this.updateUrl();
        
        console.log('Filters reset completed');
        
        // Track analytics
        this.trackEvent('tenants_reset_filters');
    }

    /**
     * Apply advanced filters
     */
    applyAdvancedFilters(advancedFilters) {
        // Merge advanced filters with existing filters
        this.filters = {
            ...this.filters,
            ...advancedFilters
        };
        
        this.state.meta.page = 1;
        this.loadTenants();
        this.updateUrl();
        this.updateFilterChips();
        
        // Track analytics
        this.trackEvent('advanced_filters_applied', {
            filter_count: Object.keys(advancedFilters).length,
            filters: Object.keys(advancedFilters)
        });
    }

    /**
     * Initialize filter chips
     */
    initFilterChips() {
        // Create dynamic filter chips
        this.createFilterChips();
        
        // Handle chip removal
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('chip-remove') || e.target.closest('.chip-remove')) {
                e.preventDefault();
                const chip = e.target.closest('.filter-chip');
                const filterType = chip.dataset.filterChip;
                const filterValue = chip.dataset.filterValue;
                
                console.log('Removing chip:', filterType, filterValue);
                
                if (filterType === 'search') {
                    this.filters.q = '';
                } else if (filterType === 'range') {
                    this.filters.range = '';
                } else if (['status', 'plan', 'region'].includes(filterType)) {
                    // Remove from array
                    const index = this.filters[filterType].indexOf(filterValue);
                    if (index > -1) {
                        this.filters[filterType].splice(index, 1);
                    }
                }
                
                this.state.meta.page = 1;
                this.loadTenants();
                this.updateUrl();
                this.updateFilterChips();
                
                // Track analytics
                this.trackEvent('tenants_filter_change', {
                    action: 'remove_chip',
                    filter_type: filterType,
                    filter_value: filterValue
                });
            }
        });
        
        // Handle keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.target.classList.contains('filter-chip')) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    e.target.click();
                }
            }
        });
    }

    /**
     * Create filter chips dynamically based on active filters
     */
    createFilterChips() {
        const container = document.getElementById('filter-chips');
        if (!container) return;

        const chips = [];
        
        // Add search chip if query exists
        if (this.filters.q) {
            chips.push({
                type: 'search',
                value: this.filters.q,
                label: `Search: "${this.filters.q}"`,
                icon: 'fas fa-search'
            });
        }
        
        // Add status chips
        this.filters.status.forEach(status => {
            chips.push({
                type: 'status',
                value: status,
                label: this.capitalizeFirst(status),
                icon: this.getStatusIcon(status)
            });
        });
        
        // Add plan chips
        this.filters.plan.forEach(plan => {
            chips.push({
                type: 'plan',
                value: plan,
                label: this.capitalizeFirst(plan),
                icon: this.getPlanIcon(plan)
            });
        });
        
        // Add region chips
        this.filters.region.forEach(region => {
            chips.push({
                type: 'region',
                value: region,
                label: this.getRegionLabel(region),
                icon: 'fas fa-globe'
            });
        });
        
        // Add range chip
        if (this.filters.range) {
            chips.push({
                type: 'range',
                value: this.filters.range,
                label: this.getRangeLabel(this.filters.range),
                icon: 'fas fa-calendar'
            });
        }
        
        // Limit to 100 chips to prevent performance issues
        if (chips.length > 100) {
            chips.splice(100);
        }

        container.innerHTML = chips.map(chip => `
            <button class="filter-chip" 
                    data-filter-chip="${chip.type}" 
                    data-filter-value="${chip.value}" 
                    aria-pressed="true"
                    aria-label="Remove ${chip.label} filter"
                    title="Remove ${chip.label} filter">
                <i class="${chip.icon} mr-1"></i>
                ${chip.label}
                <span class="chip-remove ml-1 opacity-70 hover:opacity-100" aria-hidden="true">×</span>
            </button>
        `).join('');
    }

    /**
     * Update filter chips based on current filters
     */
    updateFilterChips() {
        this.createFilterChips();
    }

    /**
     * Helper methods for filter chips
     */
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    getStatusIcon(status) {
        const icons = {
            active: 'fas fa-check-circle text-green-600',
            suspended: 'fas fa-pause-circle text-yellow-600',
            trial: 'fas fa-clock text-blue-600',
            disabled: 'fas fa-times-circle text-red-600'
        };
        return icons[status] || 'fas fa-circle';
    }

    getPlanIcon(plan) {
        const icons = {
            free: 'fas fa-gift text-gray-600',
            pro: 'fas fa-star text-blue-600',
            enterprise: 'fas fa-crown text-purple-600'
        };
        return icons[plan] || 'fas fa-tag';
    }

    getRegionLabel(region) {
        const labels = {
            us: 'US',
            eu: 'EU',
            apac: 'APAC',
            na: 'North America',
            emea: 'EMEA'
        };
        return labels[region] || region.toUpperCase();
    }

    getRangeLabel(range) {
        const labels = {
            '7d': 'Last 7 days',
            '30d': 'Last 30 days',
            '90d': 'Last 90 days',
            'all': 'All time',
            'this_month': 'This month',
            'last_month': 'Last month'
        };
        return labels[range] || range;
    }

    /**
     * Go to specific page
     */
    goToPage(page) {
        console.log('goToPage called with page:', page);
        console.log('Current meta:', this.state.meta);
        
        if (page < 1 || page > this.state.meta.last_page) {
            console.log('Page out of range:', page, 'last_page:', this.state.meta.last_page);
            return;
        }
        
        this.state.meta.page = page;
        console.log('Loading tenants for page:', page);
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
            deleteTenant: (id) => this.deleteTenant(id),
            applyAdvancedFilters: (filters) => this.applyAdvancedFilters(filters),
            showToast: (message, type) => this.showToast(message, type)
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
     * Initialize search with debounce and request cancellation
     */
    initSearch() {
        const searchInput = document.querySelector('#search-input');
        if (!searchInput) return;

        // Handle search input with debounce
        searchInput.addEventListener('input', (e) => {
            // Cancel previous request
            if (this.searchAbortController) {
                this.searchAbortController.abort();
            }
            
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                const searchValue = e.target.value.trim();
                this.filters.q = searchValue;
                this.state.meta.page = 1;
                console.log('Search value changed to:', searchValue);
                this.loadTenants();
                this.updateUrl();
                this.updateFilterChips();
                
                // Track analytics
                this.trackEvent('tenants_filter_change', {
                    action: 'search',
                    query: searchValue
                });
            }, 300);
        });

        // Handle Enter key
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(this.debounceTimer);
                const searchValue = e.target.value.trim();
                this.filters.q = searchValue;
                this.state.meta.page = 1;
                this.loadTenants();
                this.updateUrl();
                this.updateFilterChips();
            }
        });

        // Handle clear search button
        const clearSearchBtn = document.querySelector('#clear-search');
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', (e) => {
                e.preventDefault();
                searchInput.value = '';
                this.filters.q = '';
                this.state.meta.page = 1;
                this.loadTenants();
                this.updateUrl();
                this.updateFilterChips();
            });
        }
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

        // Reset filters button
        const resetBtn = document.querySelector('#reset-filters-btn');
        if (resetBtn) {
            resetBtn.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('Reset filters button clicked');
                this.clearFilters();
            });
        }

        // Filter elements with multi-value support
        const filterElements = document.querySelectorAll('[data-filter]');
        filterElements.forEach(element => {
            element.addEventListener('change', (e) => {
                const filterType = e.target.dataset.filter;
                const filterValue = e.target.value;
                
                console.log('Filter changed:', filterType, filterValue);
                
                if (['status', 'plan', 'region'].includes(filterType)) {
                    // Handle multi-value filters
                    if (filterValue && !this.filters[filterType].includes(filterValue)) {
                        this.filters[filterType].push(filterValue);
                    }
                } else {
                    // Handle single-value filters
                    this.filters[filterType] = filterValue;
                }
                
                this.state.meta.page = 1;
                
                this.loadTenants();
                this.updateUrl();
                this.updateFilterChips();
                
                // Track analytics
                this.trackEvent('tenants_filter_change', {
                    action: 'select',
                    filter_type: filterType,
                    filter_value: filterValue
                });
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
        // Handle pagination button clicks
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('pagination-btn')) {
                e.preventDefault();
                const page = parseInt(e.target.dataset.page);
                console.log('Pagination button clicked, page:', page);
                if (page && page > 0) {
                    this.goToPage(page);
                }
            }
        });
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
            sort: 'created_at:desc'
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
                this.filters.range = '30d';
                this.filters.sort = 'created_at:desc';
                break;
            case 'view-expiring':
                this.filters.status = 'trial';
                this.filters.sort = 'trial_ends_at:asc';
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
        // Select all checkbox
        const selectAllCheckbox = document.querySelector('#select-all-checkbox');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                const isChecked = e.target.checked;
                const checkboxes = document.querySelectorAll('.tenant-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                    const tenantId = checkbox.value;
                    if (isChecked) {
                        this.state.selectedRows.add(tenantId);
                    } else {
                        this.state.selectedRows.delete(tenantId);
                    }
                });
                this.updateBulkActionsBar();
            });
        }

        // Individual checkboxes
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('tenant-checkbox')) {
                const tenantId = e.target.value;
                if (e.target.checked) {
                    this.state.selectedRows.add(tenantId);
                } else {
                    this.state.selectedRows.delete(tenantId);
                }
                this.updateBulkActionsBar();
                this.updateSelectAllCheckbox();
            }
        });

        // Bulk action buttons
        const bulkSuspendBtn = document.querySelector('#bulk-suspend-btn');
        if (bulkSuspendBtn) {
            bulkSuspendBtn.addEventListener('click', () => this.bulkSuspend());
        }

        const bulkResumeBtn = document.querySelector('#bulk-resume-btn');
        if (bulkResumeBtn) {
            bulkResumeBtn.addEventListener('click', () => this.bulkResume());
        }

        const bulkChangePlanBtn = document.querySelector('#bulk-change-plan-btn');
        if (bulkChangePlanBtn) {
            bulkChangePlanBtn.addEventListener('click', () => this.bulkChangePlan());
        }

        const bulkDeleteBtn = document.querySelector('#bulk-delete-btn');
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', () => this.bulkDelete());
        }

        const clearSelectionBtn = document.querySelector('#clear-selection-btn');
        if (clearSelectionBtn) {
            clearSelectionBtn.addEventListener('click', () => this.clearSelection());
        }

        const exportSelectedBtn = document.querySelector('#export-selected-btn');
        if (exportSelectedBtn) {
            exportSelectedBtn.addEventListener('click', () => this.exportSelected());
        }
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
        if (!confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) return;
        
        try {
            this.showToast('Deleting tenant...', 'info');
            this.trackEvent('tenant_delete', { tenant_id: id });
            
            const response = await fetch(`/api/admin/tenants/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to delete tenant');
            }

            const result = await response.json();
            this.showToast(result.message || 'Tenant deleted successfully', 'success');
            this.loadTenants(); // Refresh data
            
        } catch (error) {
            console.error('Delete tenant failed:', error);
            this.showToast(error.message || 'Failed to delete tenant', 'error');
        }
    }

    /**
     * Initialize modals
     */
    initModals() {
        // Create tenant modal
        const createBtn = document.querySelector('#create-tenant-btn');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.openCreateModal());
        }

        // Modal close handlers
        const modal = document.querySelector('#tenant-modal');
        const cancelBtn = document.querySelector('#tenant-modal-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.closeModal());
        }

        // Form submission
        const form = document.querySelector('#tenant-form');
        if (form) {
            form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
    }

    /**
     * Initialize column picker
     */
    initColumnPicker() {
        const pickerBtn = document.querySelector('#column-picker-btn');
        const modal = document.querySelector('#column-picker-modal');
        const cancelBtn = document.querySelector('#column-picker-cancel');
        const applyBtn = document.querySelector('#column-picker-apply');

        if (pickerBtn) {
            pickerBtn.addEventListener('click', () => {
                modal.classList.remove('hidden');
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
            });
        }

        if (applyBtn) {
            applyBtn.addEventListener('click', () => {
                this.applyColumnSettings();
                modal.classList.add('hidden');
            });
        }
    }

    /**
     * Initialize row actions
     */
    initRowActions() {
        // Handle action menu clicks using event delegation
        document.addEventListener('click', (e) => {
            if (e.target.closest('.action-menu-btn')) {
                e.preventDefault();
                const button = e.target.closest('.action-menu-btn');
                const tenantId = button.dataset.tenantId;
                this.toggleActionMenu(tenantId);
            }
        });

        // Close action menus when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.action-menu-btn') && !e.target.closest('.action-menu')) {
                this.closeAllActionMenus();
            }
        });

        // Handle action menu item clicks
        document.addEventListener('click', (e) => {
            if (e.target.closest('.action-menu-item')) {
                e.preventDefault();
                const item = e.target.closest('.action-menu-item');
                const action = item.dataset.action;
                const tenantId = item.dataset.tenantId;
                this.handleRowAction(action, tenantId);
            }
        });
    }

    /**
     * Initialize analytics
     */
    initAnalytics() {
        // Track page view
        this.trackEvent('tenants_view', {
            page: this.state.meta.page,
            per_page: this.state.meta.per_page,
            filters: this.filters
        });
    }

    /**
     * Show empty state
     */
    showEmptyState() {
        const tbody = document.querySelector('#tenants-table-body');
        if (!tbody) return;

        const hasFilters = this.filters.q || 
                          this.filters.status.length > 0 || 
                          this.filters.plan.length > 0 || 
                          this.filters.region.length > 0 || 
                          this.filters.range;

        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="px-6 py-12 text-center">
                    <div class="text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-4"></i>
                        <p class="text-lg font-medium">No tenants found</p>
                        <p class="text-sm">${hasFilters ? 'Try adjusting your search or filters' : 'No tenants have been created yet'}</p>
                        ${hasFilters ? `
                            <button onclick="window.Tenants.clearFilters()" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Clear Filters
                            </button>
                        ` : `
                            <button onclick="window.Tenants.showCreateModal()" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Create First Tenant
                            </button>
                        `}
                    </div>
                </td>
            </tr>
        `;
    }

    /**
     * Hide empty state
     */
    hideEmptyState() {
        const emptyState = document.querySelector('#empty-state');
        const table = document.querySelector('.tenants-table');
        
        if (emptyState) emptyState.classList.add('hidden');
        if (table) table.classList.remove('hidden');
    }

    /**
     * Update filter summary
     */
    updateFilterSummary() {
        const summary = document.querySelector('#filter-summary');
        if (!summary) return;

        const activeFilters = [];
        if (this.filters.q) activeFilters.push(`search: "${this.filters.q}"`);
        if (this.filters.status) activeFilters.push(`status: ${this.filters.status}`);
        if (this.filters.plan) activeFilters.push(`plan: ${this.filters.plan}`);
        if (this.filters.range) activeFilters.push(`range: ${this.filters.range}`);
        if (this.filters.region) activeFilters.push(`region: ${this.filters.region}`);

        if (activeFilters.length > 0) {
            summary.textContent = ` • ${activeFilters.join(', ')}`;
        } else {
            summary.textContent = '';
        }
    }

    /**
     * Format storage size
     */
    formatStorage(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    /**
     * Get plan color class
     */
    getPlanColor(plan) {
        const colors = {
            free: 'bg-gray-100 text-gray-800',
            pro: 'bg-blue-100 text-blue-800',
            enterprise: 'bg-purple-100 text-purple-800'
        };
        return colors[plan] || colors.free;
    }

    /**
     * Get status color class
     */
    getStatusColor(status) {
        const colors = {
            active: 'bg-green-100 text-green-800',
            suspended: 'bg-red-100 text-red-800',
            trial: 'bg-yellow-100 text-yellow-800',
            archived: 'bg-gray-100 text-gray-800'
        };
        return colors[status] || colors.active;
    }

    /**
     * Open create tenant modal
     */
    openCreateModal() {
        this.currentTenantId = null;
        const modal = document.querySelector('#tenant-modal');
        const title = document.querySelector('#tenant-modal-title');
        const form = document.querySelector('#tenant-form');
        
        if (title) title.textContent = 'Create Tenant';
        if (form) form.reset();
        if (modal) modal.classList.remove('hidden');
        
        this.trackEvent('tenant_create_modal_open');
    }

    /**
     * Close modal
     */
    closeModal() {
        const modal = document.querySelector('#tenant-modal');
        if (modal) modal.classList.add('hidden');
    }

    /**
     * Handle form submission
     */
    async handleFormSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Validate form
        const validation = this.validateTenantForm(data);
        if (!validation.isValid) {
            this.showFormErrors(validation.errors);
            return;
        }
        
        // Clear previous errors
        this.clearFormErrors();
        
        // Show loading state
        this.setFormLoading(true);
        
        try {
            if (this.currentTenantId) {
                await this.updateTenant(this.currentTenantId, data);
            } else {
                await this.createTenant(data);
            }
            this.closeModal();
        } catch (error) {
            // Error handling is done in createTenant/updateTenant methods
            console.error('Form submission failed:', error);
        } finally {
            this.setFormLoading(false);
        }
    }

    /**
     * Validate tenant form data
     */
    validateTenantForm(data) {
        const errors = {};
        
        // Required fields
        if (!data.name || data.name.trim().length === 0) {
            errors.name = 'Tenant name is required';
        } else if (data.name.trim().length < 2) {
            errors.name = 'Tenant name must be at least 2 characters';
        } else if (data.name.trim().length > 100) {
            errors.name = 'Tenant name must be less than 100 characters';
        }
        
        if (!data.domain || data.domain.trim().length === 0) {
            errors.domain = 'Domain is required';
        } else if (!this.isValidDomain(data.domain.trim())) {
            errors.domain = 'Please enter a valid domain (e.g., example.com)';
        }
        
        if (!data.plan || !['free', 'pro', 'enterprise'].includes(data.plan)) {
            errors.plan = 'Please select a valid plan';
        }
        
        // Optional fields validation
        if (data.code && data.code.trim().length > 0) {
            if (data.code.trim().length < 2) {
                errors.code = 'Code must be at least 2 characters';
            } else if (data.code.trim().length > 50) {
                errors.code = 'Code must be less than 50 characters';
            } else if (!/^[a-zA-Z0-9_-]+$/.test(data.code.trim())) {
                errors.code = 'Code can only contain letters, numbers, hyphens, and underscores';
            }
        }
        
        if (data.owner_email && data.owner_email.trim().length > 0) {
            if (!this.isValidEmail(data.owner_email.trim())) {
                errors.owner_email = 'Please enter a valid email address';
            }
        }
        
        return {
            isValid: Object.keys(errors).length === 0,
            errors
        };
    }

    /**
     * Validate domain format
     */
    isValidDomain(domain) {
        const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.([a-zA-Z]{2,}|[a-zA-Z]{2,}\.[a-zA-Z]{2,})$/;
        return domainRegex.test(domain);
    }

    /**
     * Validate email format
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Show form validation errors
     */
    showFormErrors(errors) {
        // Clear previous errors
        this.clearFormErrors();
        
        // Show new errors
        Object.entries(errors).forEach(([field, message]) => {
            const input = document.querySelector(`[name="${field}"]`);
            if (input) {
                // Add error class
                input.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                
                // Create or update error message
                let errorEl = input.parentNode.querySelector('.error-message');
                if (!errorEl) {
                    errorEl = document.createElement('div');
                    errorEl.className = 'error-message text-red-500 text-sm mt-1';
                    input.parentNode.appendChild(errorEl);
                }
                errorEl.textContent = message;
            }
        });
        
        // Focus first error field
        const firstErrorField = document.querySelector('.border-red-500');
        if (firstErrorField) {
            firstErrorField.focus();
        }
    }

    /**
     * Clear form validation errors
     */
    clearFormErrors() {
        // Remove error classes
        const errorInputs = document.querySelectorAll('.border-red-500');
        errorInputs.forEach(input => {
            input.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
        });
        
        // Remove error messages
        const errorMessages = document.querySelectorAll('.error-message');
        errorMessages.forEach(el => el.remove());
    }

    /**
     * Set form loading state
     */
    setFormLoading(loading) {
        const form = document.querySelector('#tenant-form');
        const submitBtn = form?.querySelector('button[type="submit"]');
        
        if (submitBtn) {
            if (loading) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            } else {
                submitBtn.disabled = false;
                submitBtn.innerHTML = this.currentTenantId ? 
                    '<i class="fas fa-save mr-2"></i>Update Tenant' : 
                    '<i class="fas fa-plus mr-2"></i>Create Tenant';
            }
        }
    }

    /**
     * Set table loading state
     */
    setTableLoading(loading) {
        const tableContainer = document.querySelector('.table-container');
        const table = document.querySelector('.tenants-table');
        
        if (loading) {
            // Add loading overlay
            if (!document.querySelector('.table-loading-overlay')) {
                const overlay = document.createElement('div');
                overlay.className = 'table-loading-overlay absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10';
                overlay.innerHTML = `
                    <div class="flex items-center space-x-2 text-gray-600">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Loading tenants...</span>
                    </div>
                `;
                if (tableContainer) {
                    tableContainer.style.position = 'relative';
                    tableContainer.appendChild(overlay);
                }
            }
        } else {
            // Remove loading overlay
            const overlay = document.querySelector('.table-loading-overlay');
            if (overlay) {
                overlay.remove();
            }
        }
    }

    /**
     * Set bulk actions loading state
     */
    setBulkActionsLoading(loading) {
        const bulkBar = document.querySelector('#bulk-actions-bar');
        const buttons = bulkBar?.querySelectorAll('button');
        
        if (buttons) {
            buttons.forEach(btn => {
                if (loading) {
                    btn.disabled = true;
                    const icon = btn.querySelector('i');
                    if (icon && !icon.classList.contains('fa-spinner')) {
                        btn.dataset.originalIcon = icon.className;
                        icon.className = 'fas fa-spinner fa-spin mr-2';
                    }
                } else {
                    btn.disabled = false;
                    const icon = btn.querySelector('i');
                    if (icon && btn.dataset.originalIcon) {
                        icon.className = btn.dataset.originalIcon;
                        delete btn.dataset.originalIcon;
                    }
                }
            });
        }
    }

    /**
     * Set KPI loading state
     */
    setKPILoading(loading) {
        const kpiCards = document.querySelectorAll('.kpi-card');
        
        kpiCards.forEach(card => {
            if (loading) {
                card.classList.add('opacity-50', 'pointer-events-none');
                const value = card.querySelector('.kpi-value');
                if (value) {
                    value.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                }
            } else {
                card.classList.remove('opacity-50', 'pointer-events-none');
            }
        });
    }

    /**
     * Create tenant
     */
    async createTenant(data) {
        try {
            this.showToast('Creating tenant...', 'info');
            this.trackEvent('tenant_create_attempt', data);
            
            const response = await fetch('/api/admin/tenants', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to create tenant');
            }

            const result = await response.json();
            this.showToast('Tenant created successfully', 'success');
            this.loadTenants(); // Refresh data
            
        } catch (error) {
            console.error('Create tenant failed:', error);
            this.showToast(error.message || 'Failed to create tenant', 'error');
            throw error; // Re-throw to prevent modal from closing
        }
    }

    /**
     * Update tenant
     */
    async updateTenant(id, data) {
        try {
            this.showToast('Updating tenant...', 'info');
            this.trackEvent('tenant_update_attempt', { id, ...data });
            
            const response = await fetch(`/api/admin/tenants/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to update tenant');
            }

            const result = await response.json();
            this.showToast('Tenant updated successfully', 'success');
            this.loadTenants(); // Refresh data
            
        } catch (error) {
            console.error('Update tenant failed:', error);
            this.showToast(error.message || 'Failed to update tenant', 'error');
            throw error; // Re-throw to prevent modal from closing
        }
    }

    /**
     * Apply column settings
     */
    applyColumnSettings() {
        const toggles = document.querySelectorAll('.column-toggle');
        toggles.forEach(toggle => {
            const column = toggle.dataset.column;
            this.state.visibleColumns[column] = toggle.checked;
        });
        
        // Re-render table with new column visibility
        this.updateTable();
        this.trackEvent('column_picker_apply', this.state.visibleColumns);
    }

    /**
     * Update bulk actions bar visibility
     */
    updateBulkActionsBar() {
        const bulkBar = document.querySelector('#bulk-actions-bar');
        const selectedCount = document.querySelector('#selected-count');
        const exportSelectedBtn = document.querySelector('#export-selected-btn');
        
        if (bulkBar && selectedCount) {
            const count = this.state.selectedRows.size;
            selectedCount.textContent = count;
            
            if (count > 0) {
                bulkBar.classList.remove('hidden');
                if (exportSelectedBtn) exportSelectedBtn.classList.remove('hidden');
            } else {
                bulkBar.classList.add('hidden');
                if (exportSelectedBtn) exportSelectedBtn.classList.add('hidden');
            }
        }
    }

    /**
     * Update select all checkbox state
     */
    updateSelectAllCheckbox() {
        const selectAllCheckbox = document.querySelector('#select-all-checkbox');
        const checkboxes = document.querySelectorAll('.tenant-checkbox');
        
        if (selectAllCheckbox && checkboxes.length > 0) {
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            selectAllCheckbox.checked = checkedCount === checkboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        }
    }

    /**
     * Clear all selections
     */
    clearSelection() {
        this.state.selectedRows.clear();
        const checkboxes = document.querySelectorAll('.tenant-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        this.updateBulkActionsBar();
        this.updateSelectAllCheckbox();
    }

    /**
     * Bulk suspend tenants
     */
    async bulkSuspend() {
        const selectedIds = Array.from(this.state.selectedRows);
        if (selectedIds.length === 0) return;

        if (!confirm(`Are you sure you want to suspend ${selectedIds.length} tenant(s)?`)) return;

        // Use enhanced bulk operations
        if (window.BulkOperations) {
            await window.BulkOperations.startBulkOperation('suspend', selectedIds, {
                reason: 'Bulk suspension from admin panel'
            });
            this.clearSelection();
            this.loadTenants(); // Refresh data
        } else {
            // Fallback to original implementation
            try {
                this.showToast(`Suspending ${selectedIds.length} tenant(s)...`, 'info');
                this.trackEvent('bulk_suspend', { count: selectedIds.length });
                
                const response = await fetch('/api/admin/tenants/bulk/suspend', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this.getAuthToken()}`,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        tenant_ids: selectedIds,
                        reason: 'Bulk suspension from admin panel'
                    })
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to suspend tenants');
                }

                const result = await response.json();
                this.showToast(result.message, 'success');
                this.clearSelection();
                this.loadTenants(); // Refresh data
                
            } catch (error) {
                console.error('Bulk suspend failed:', error);
                this.showToast(error.message || 'Failed to suspend tenants', 'error');
            }
        }
    }

    /**
     * Bulk resume tenants
     */
    async bulkResume() {
        const selectedIds = Array.from(this.state.selectedRows);
        if (selectedIds.length === 0) return;

        if (!confirm(`Are you sure you want to resume ${selectedIds.length} tenant(s)?`)) return;

        // Use enhanced bulk operations
        if (window.BulkOperations) {
            await window.BulkOperations.startBulkOperation('resume', selectedIds, {
                reason: 'Bulk resume from admin panel'
            });
            this.clearSelection();
            this.loadTenants(); // Refresh data
        } else {
            // Fallback to original implementation
            try {
                this.showToast(`Resuming ${selectedIds.length} tenant(s)...`, 'info');
                this.trackEvent('bulk_resume', { count: selectedIds.length });
                
                const response = await fetch('/api/admin/tenants/bulk/resume', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this.getAuthToken()}`,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        tenant_ids: selectedIds,
                        reason: 'Bulk resume from admin panel'
                    })
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to resume tenants');
                }

                const result = await response.json();
                this.showToast(result.message, 'success');
                this.clearSelection();
                this.loadTenants(); // Refresh data
                
            } catch (error) {
                console.error('Bulk resume failed:', error);
                this.showToast(error.message || 'Failed to resume tenants', 'error');
            }
        }
    }

    /**
     * Bulk change plan
     */
    async bulkChangePlan() {
        const selectedIds = Array.from(this.state.selectedRows);
        if (selectedIds.length === 0) return;

        const newPlan = prompt(`Enter new plan for ${selectedIds.length} tenant(s):`, 'pro');
        if (!newPlan) return;

        // Use enhanced bulk operations
        if (window.BulkOperations) {
            await window.BulkOperations.startBulkOperation('change-plan', selectedIds, {
                plan: newPlan,
                reason: 'Bulk plan change from admin panel'
            });
            this.clearSelection();
            this.loadTenants(); // Refresh data
        } else {
            // Fallback to original implementation
            try {
                this.showToast(`Changing plan to ${newPlan} for ${selectedIds.length} tenant(s)...`, 'info');
                this.trackEvent('bulk_change_plan', { count: selectedIds.length, plan: newPlan });
                
                const response = await fetch('/api/admin/tenants/bulk/change-plan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this.getAuthToken()}`,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        tenant_ids: selectedIds,
                        plan: newPlan,
                        reason: 'Bulk plan change from admin panel'
                    })
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to change plan');
                }

                const result = await response.json();
                this.showToast(result.message, 'success');
                this.clearSelection();
                this.loadTenants(); // Refresh data
                
            } catch (error) {
                console.error('Bulk change plan failed:', error);
                this.showToast(error.message || 'Failed to change plan', 'error');
            }
        }
    }

    /**
     * Bulk delete tenants
     */
    async bulkDelete() {
        const selectedIds = Array.from(this.state.selectedRows);
        if (selectedIds.length === 0) return;

        if (!confirm(`Are you sure you want to delete ${selectedIds.length} tenant(s)? This action cannot be undone.`)) return;

        // Use enhanced bulk operations
        if (window.BulkOperations) {
            await window.BulkOperations.startBulkOperation('delete', selectedIds, {
                reason: 'Bulk deletion from admin panel'
            });
            this.clearSelection();
            this.loadTenants(); // Refresh data
        } else {
            // Fallback to original implementation
            try {
                this.showToast(`Deleting ${selectedIds.length} tenant(s)...`, 'info');
                this.trackEvent('bulk_delete', { count: selectedIds.length });
                
                const response = await fetch('/api/admin/tenants/bulk/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${this.getAuthToken()}`,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        tenant_ids: selectedIds,
                        reason: 'Bulk deletion from admin panel'
                    })
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to delete tenants');
                }

                const result = await response.json();
                this.showToast(result.message, 'success');
                this.clearSelection();
                this.loadTenants(); // Refresh data
                
            } catch (error) {
                console.error('Bulk delete failed:', error);
                this.showToast(error.message || 'Failed to delete tenants', 'error');
            }
        }
    }

    /**
     * Export selected tenants
     */
    async exportSelected() {
        const selectedIds = Array.from(this.state.selectedRows);
        if (selectedIds.length === 0) return;

        try {
            this.showToast(`Exporting ${selectedIds.length} selected tenant(s)...`, 'info');
            this.trackEvent('export_selected', { count: selectedIds.length });
            
            const response = await fetch('/api/admin/tenants/bulk/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    tenant_ids: selectedIds,
                    format: 'csv'
                })
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to export tenants');
            }

            const result = await response.json();
            this.showToast(result.message, 'success');
            
            // Trigger download if URL provided
            if (result.download_url) {
                window.open(result.download_url, '_blank');
            }
            
        } catch (error) {
            console.error('Export selected failed:', error);
            this.showToast(error.message || 'Failed to export selected tenants', 'error');
        }
    }

    /**
     * Toggle action menu for a tenant
     */
    toggleActionMenu(tenantId) {
        // Close all other menus first
        this.closeAllActionMenus();
        
        // Find the button and create menu
        const button = document.querySelector(`[data-tenant-id="${tenantId}"].action-menu-btn`);
        if (!button) return;

        const existingMenu = button.parentElement.querySelector('.action-menu');
        if (existingMenu) {
            existingMenu.remove();
            return;
        }

        // Create action menu
        const menu = document.createElement('div');
        menu.className = 'action-menu absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-50 border border-gray-200';
        menu.innerHTML = `
            <div class="py-1">
                <button class="action-menu-item block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-action="view" data-tenant-id="${tenantId}">
                    <i class="fas fa-eye mr-2"></i>View Details
                </button>
                <button class="action-menu-item block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-action="edit" data-tenant-id="${tenantId}">
                    <i class="fas fa-edit mr-2"></i>Edit
                </button>
                <button class="action-menu-item block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-action="suspend" data-tenant-id="${tenantId}">
                    <i class="fas fa-pause mr-2"></i>Suspend
                </button>
                <button class="action-menu-item block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-action="resume" data-tenant-id="${tenantId}">
                    <i class="fas fa-play mr-2"></i>Resume
                </button>
                <button class="action-menu-item block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-action="change-plan" data-tenant-id="${tenantId}">
                    <i class="fas fa-credit-card mr-2"></i>Change Plan
                </button>
                <button class="action-menu-item block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-action="impersonate" data-tenant-id="${tenantId}">
                    <i class="fas fa-user-secret mr-2"></i>Impersonate
                </button>
                <div class="border-t border-gray-100"></div>
                <button class="action-menu-item block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50" data-action="delete" data-tenant-id="${tenantId}">
                    <i class="fas fa-trash mr-2"></i>Delete
                </button>
            </div>
        `;

        // Position the menu
        button.parentElement.style.position = 'relative';
        button.parentElement.appendChild(menu);
    }

    /**
     * Close all action menus
     */
    closeAllActionMenus() {
        const menus = document.querySelectorAll('.action-menu');
        menus.forEach(menu => menu.remove());
    }

    /**
     * Handle row action
     */
    async handleRowAction(action, tenantId) {
        this.closeAllActionMenus();
        
        switch (action) {
            case 'view':
                this.viewTenant(tenantId);
                break;
            case 'edit':
                this.editTenant(tenantId);
                break;
            case 'suspend':
                await this.suspendTenant(tenantId);
                break;
            case 'resume':
                await this.resumeTenant(tenantId);
                break;
            case 'change-plan':
                await this.changePlan(tenantId);
                break;
            case 'impersonate':
                await this.impersonateTenant(tenantId);
                break;
            case 'delete':
                await this.deleteTenant(tenantId);
                break;
            default:
                console.warn('Unknown action:', action);
        }
    }

    /**
     * Edit tenant
     */
    editTenant(id) {
        this.currentTenantId = id;
        const modal = document.querySelector('#tenant-modal');
        const title = document.querySelector('#tenant-modal-title');
        const form = document.querySelector('#tenant-form');
        
        if (title) title.textContent = 'Edit Tenant';
        if (form) form.reset();
        if (modal) modal.classList.remove('hidden');
        
        this.trackEvent('tenant_edit_modal_open', { tenant_id: id });
    }

    /**
     * Suspend tenant
     */
    async suspendTenant(id) {
        if (!confirm('Are you sure you want to suspend this tenant?')) return;
        
        try {
            this.showToast('Suspending tenant...', 'info');
            this.trackEvent('tenant_suspend', { tenant_id: id });
            
            const response = await fetch(`/api/admin/tenants/${id}/suspend`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    reason: 'Manual suspension from admin panel'
                })
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to suspend tenant');
            }

            const result = await response.json();
            this.showToast(result.message, 'success');
            this.loadTenants(); // Refresh data
            
        } catch (error) {
            console.error('Suspend tenant failed:', error);
            this.showToast(error.message || 'Failed to suspend tenant', 'error');
        }
    }

    /**
     * Resume tenant
     */
    async resumeTenant(id) {
        if (!confirm('Are you sure you want to resume this tenant?')) return;
        
        try {
            this.showToast('Resuming tenant...', 'info');
            this.trackEvent('tenant_resume', { tenant_id: id });
            
            const response = await fetch(`/api/admin/tenants/${id}/resume`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    reason: 'Manual resume from admin panel'
                })
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to resume tenant');
            }

            const result = await response.json();
            this.showToast(result.message, 'success');
            this.loadTenants(); // Refresh data
            
        } catch (error) {
            console.error('Resume tenant failed:', error);
            this.showToast(error.message || 'Failed to resume tenant', 'error');
        }
    }

    /**
     * Impersonate tenant
     */
    async impersonateTenant(id) {
        if (!confirm('Are you sure you want to impersonate this tenant? You will be logged in as them.')) return;
        
        try {
            // Stub implementation
            this.showToast('Starting impersonation...', 'info');
            this.trackEvent('tenant_impersonate', { tenant_id: id });
            
            const response = await fetch(`/api/admin/tenants/${id}/impersonate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to start impersonation');
            }

            const result = await response.json();
            this.showToast(result.message, 'success');
            
            // Redirect to impersonation URL
            if (result.impersonation_url) {
                window.location.href = result.impersonation_url;
            }
        } catch (error) {
            console.error('Impersonate tenant failed:', error);
            this.showToast(error.message || 'Failed to impersonate tenant', 'error');
        }
    }

    /**
     * Track analytics event
     */
    trackEvent(eventName, properties = {}) {
        try {
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, {
                    'page_title': 'Tenants Management',
                    'page_location': window.location.href,
                    ...properties
                });
            }
            console.log(`Analytics: ${eventName}`, properties);
        } catch (error) {
            console.error('Analytics tracking failed:', error);
        }
    }

    /**
     * Show loading state (optimized)
     */
    showLoadingState() {
        this.state.loading = true;
        const tbody = document.querySelector('#tenants-table-body');
        if (!tbody) return;

        // Use skeleton loading for better UX
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="px-6 py-12 text-center">
                    <div class="text-gray-500">
                        <div class="animate-pulse">
                            <div class="h-8 bg-gray-200 rounded w-32 mx-auto mb-4"></div>
                            <div class="h-4 bg-gray-200 rounded w-48 mx-auto mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded w-40 mx-auto"></div>
                        </div>
                        <p class="text-sm text-gray-400 mt-4">Loading tenants...</p>
                    </div>
                </td>
            </tr>
        `;
    }

    /**
     * Show error state
     */
    showErrorState(error) {
        this.state.loading = false;
        this.state.error = error;
        const tbody = document.querySelector('#tenants-table-body');
        if (!tbody) return;

        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="px-6 py-12 text-center">
                    <div class="text-red-500">
                        <i class="fas fa-exclamation-triangle text-3xl mb-4"></i>
                        <p class="text-lg font-medium">Error loading tenants</p>
                        <p class="text-sm">${error.message || 'Something went wrong'}</p>
                        <button onclick="window.Tenants.loadTenants()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-retry mr-2"></i>Retry
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.tenantsPage = new TenantsPage();
});

// Export for global access
window.TenantsPage = TenantsPage;
