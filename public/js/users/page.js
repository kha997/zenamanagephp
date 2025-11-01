/**
 * Users Management Page Module
 * Handles users list, filtering, search, bulk actions, and export
 */

class UsersPage {
    constructor() {
        this.state = {
            loading: false,
            error: null,
            data: [],
            meta: {
                total: 0,
                page: 1,
                per_page: 25,
                last_page: 1
            }
        };
        
        this.filters = {
            q: '',
            tenant_id: '',
            role: [],
            status: [],
            range: '30d',
            last_login: '',
            mfa: '',
            sort: 'created_at:desc'
        };
        
        this.defaultFilters = {
            q: '',
            tenant_id: '',
            role: [],
            status: [],
            range: '30d',
            last_login: '',
            mfa: '',
            sort: 'created_at:desc'
        };
        
        this.selectedUsers = new Set();
        this.searchAbortController = null;
        this.cache = new Map();
        this.cacheTTL = 60000; // 1 minute
        
        this.init();
    }
    
    init() {
        console.log('UsersPage initialized');
        this.parseUrlParams();
        this.loadUsers();
        this.initEventListeners();
        this.initFilterChips();
        this.initSearch();
        this.initFilters();
        this.loadTenants();
    }
    
    parseUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Parse pagination
        this.state.meta.page = parseInt(urlParams.get('page')) || 1;
        this.state.meta.per_page = parseInt(urlParams.get('per_page')) || 25;
        
        // Parse filters
        this.filters.q = urlParams.get('q') || '';
        this.filters.tenant_id = urlParams.get('tenant_id') || '';
        this.filters.role = this.parseMultiValue(urlParams.get('role'));
        this.filters.status = this.parseMultiValue(urlParams.get('status'));
        this.filters.range = urlParams.get('range') || '30d';
        this.filters.last_login = urlParams.get('last_login') || '';
        this.filters.mfa = urlParams.get('mfa') || '';
        this.filters.sort = urlParams.get('sort') || 'created_at:desc';
        
        console.log('Parsed URL params:', this.filters);
        this.updateUIFromState();
    }
    
    parseMultiValue(value) {
        if (!value) return [];
        return value.split(',').map(v => v.trim()).filter(v => v);
    }
    
    updateUIFromState() {
        console.log('updateUIFromState called with filters:', this.filters);
        
        // Update search input
        const searchInput = document.querySelector('#search-input');
        if (searchInput) {
            searchInput.value = this.filters.q;
        }
        
        // Update filter selects
        const tenantSelect = document.querySelector('[data-filter="tenant_id"]');
        if (tenantSelect) {
            tenantSelect.value = this.filters.tenant_id;
        }
        
        const roleSelect = document.querySelector('[data-filter="role"]');
        if (roleSelect) {
            if (this.filters.role.length === 1) {
                roleSelect.value = this.filters.role[0];
            } else if (this.filters.role.length === 0) {
                roleSelect.value = '';
            }
        }
        
        const statusSelect = document.querySelector('[data-filter="status"]');
        if (statusSelect) {
            if (this.filters.status.length === 1) {
                statusSelect.value = this.filters.status[0];
            } else if (this.filters.status.length === 0) {
                statusSelect.value = '';
            }
        }
        
        const rangeSelect = document.querySelector('[data-filter="range"]');
        if (rangeSelect) {
            rangeSelect.value = this.filters.range;
        }
        
        const lastLoginSelect = document.querySelector('[data-filter="last_login"]');
        if (lastLoginSelect) {
            lastLoginSelect.value = this.filters.last_login;
        }
        
        const mfaSelect = document.querySelector('[data-filter="mfa"]');
        if (mfaSelect) {
            mfaSelect.value = this.filters.mfa;
        }
        
        const sortSelect = document.querySelector('[data-filter="sort"]');
        if (sortSelect) {
            sortSelect.value = this.filters.sort;
        }
        
        const perPageSelect = document.querySelector('[data-filter="per_page"]');
        if (perPageSelect) {
            perPageSelect.value = this.state.meta.per_page;
        }
        
        this.updateFilterChips();
    }
    
    updateUrl() {
        const params = new URLSearchParams();
        
        // Add filters
        if (this.filters.q) params.set('q', this.filters.q);
        if (this.filters.tenant_id) params.set('tenant_id', this.filters.tenant_id);
        if (this.filters.role.length > 0) params.set('role', this.filters.role.join(','));
        if (this.filters.status.length > 0) params.set('status', this.filters.status.join(','));
        if (this.filters.range) params.set('range', this.filters.range);
        if (this.filters.last_login) params.set('last_login', this.filters.last_login);
        if (this.filters.mfa) params.set('mfa', this.filters.mfa);
        if (this.filters.sort && this.filters.sort !== 'created_at:desc') params.set('sort', this.filters.sort);
        
        // Add pagination
        if (this.state.meta.page > 1) params.set('page', this.state.meta.page);
        if (this.state.meta.per_page !== 25) params.set('per_page', this.state.meta.per_page);
        
        const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
        window.history.pushState({}, '', newUrl);
        
        console.log('URL updated to:', newUrl);
    }
    
    async fetchUsers(useCache = true) {
        const cacheKey = JSON.stringify({ filters: this.filters, meta: this.state.meta });
        
        if (useCache && this.cache.has(cacheKey)) {
            const cached = this.cache.get(cacheKey);
            if (Date.now() - cached.timestamp < this.cacheTTL) {
                console.log('Using cached users data');
                return cached.data;
            }
        }
        
        const queryParams = new URLSearchParams();
        
        // Add filters
        if (this.filters.q) queryParams.set('q', this.filters.q);
        if (this.filters.tenant_id) queryParams.set('tenant_id', this.filters.tenant_id);
        if (this.filters.role.length > 0) queryParams.set('role', this.filters.role.join(','));
        if (this.filters.status.length > 0) queryParams.set('status', this.filters.status.join(','));
        if (this.filters.range) queryParams.set('range', this.filters.range);
        if (this.filters.last_login) queryParams.set('last_login', this.filters.last_login);
        if (this.filters.mfa) queryParams.set('mfa', this.filters.mfa);
        if (this.filters.sort) queryParams.set('sort', this.filters.sort);
        
        // Add pagination
        queryParams.set('page', this.state.meta.page);
        queryParams.set('per_page', this.state.meta.per_page);
        
        const url = `/api/admin/users?${queryParams.toString()}`;
        
        // Cancel previous request
        if (this.searchAbortController) {
            this.searchAbortController.abort();
        }
        this.searchAbortController = new AbortController();
        
        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`
                },
                signal: this.searchAbortController.signal
            });
            
            if (response.status === 304) {
                console.log('Users data not modified, using cache');
                return this.cache.get(cacheKey)?.data;
            }
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            // Cache the response
            this.cache.set(cacheKey, {
                data: data,
                timestamp: Date.now()
            });
            
            return data;
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('Users request aborted');
                return null;
            }
            throw error;
        }
    }
    
    async loadUsers() {
        this.state.loading = true;
        this.showLoadingState();
        
        try {
            const data = await this.fetchUsers();
            if (data) {
                this.state.data = data.data || [];
                this.state.meta = data.meta || this.state.meta;
                this.state.error = null;
                
                this.renderUsers();
                this.updateUsersCount();
                this.renderPagination();
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.state.error = error;
            this.showErrorState(error);
        } finally {
            this.state.loading = false;
        }
    }
    
    renderUsers() {
        const tbody = document.querySelector('#users-table-body');
        if (!tbody) return;
        
        if (this.state.data.length === 0) {
            this.showEmptyState();
            return;
        }
        
        tbody.innerHTML = this.state.data.map(user => `
            <tr class="hover:bg-gray-50" data-user-id="${user.id}">
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="checkbox" class="user-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500" 
                           value="${user.id}" ${this.selectedUsers.has(user.id) ? 'checked' : ''}>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                <span class="text-sm font-medium text-gray-700">${this.getInitials(user.name)}</span>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900 cursor-pointer hover:text-blue-600" 
                                 onclick="window.Users.showUserDetail('${user.id}')">${user.name}</div>
                            <div class="text-sm text-gray-500">${user.email}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${user.tenant?.name || 'N/A'}</div>
                    <div class="text-sm text-gray-500">${user.tenant?.slug || ''}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getRoleBadgeClass(user.role)}">
                        ${this.capitalizeFirst(user.role)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${user.last_login_at ? this.formatRelativeTime(user.last_login_at) : 'Never'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${user.mfa_enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                        ${user.mfa_enabled ? 'On' : 'Off'}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getStatusBadgeClass(user.status)}">
                        ${this.capitalizeFirst(user.status)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${this.formatDate(user.created_at)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="relative inline-block text-left">
                        <button class="text-gray-400 hover:text-gray-600 focus:outline-none" onclick="window.Users.showUserActions('${user.id}')">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }
    
    showLoadingState() {
        const tbody = document.querySelector('#users-table-body');
        if (!tbody) return;
        
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-12 text-center">
                    <div class="text-gray-500">
                        <div class="animate-pulse">
                            <div class="h-8 bg-gray-200 rounded w-32 mx-auto mb-4"></div>
                            <div class="h-4 bg-gray-200 rounded w-48 mx-auto mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded w-40 mx-auto"></div>
                        </div>
                        <p class="text-sm text-gray-400 mt-4">Loading users...</p>
                    </div>
                </td>
            </tr>
        `;
    }
    
    showEmptyState() {
        const tbody = document.querySelector('#users-table-body');
        if (!tbody) return;
        
        const hasFilters = this.filters.q || 
                          this.filters.tenant_id || 
                          this.filters.role.length > 0 || 
                          this.filters.status.length > 0 || 
                          this.filters.range ||
                          this.filters.last_login ||
                          this.filters.mfa;
        
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-12 text-center">
                    <div class="text-gray-500">
                        <i class="fas fa-users text-4xl mb-4"></i>
                        <p class="text-lg font-medium">No users found</p>
                        <p class="text-sm">${hasFilters ? 'Try adjusting your search or filters' : 'No users have been created yet'}</p>
                        ${hasFilters ? `
                            <button onclick="window.Users.clearFilters()" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Clear Filters
                            </button>
                        ` : `
                            <button onclick="window.Users.showInviteModal()" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Invite First User
                            </button>
                        `}
                    </div>
                </td>
            </tr>
        `;
    }
    
    showErrorState(error) {
        const tbody = document.querySelector('#users-table-body');
        if (!tbody) return;
        
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-12 text-center">
                    <div class="text-red-500">
                        <i class="fas fa-exclamation-triangle text-3xl mb-4"></i>
                        <p class="text-lg font-medium">Error loading users</p>
                        <p class="text-sm">${error.message || 'Something went wrong'}</p>
                        <button onclick="window.Users.loadUsers()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-retry mr-2"></i>Retry
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }
    
    updateUsersCount() {
        const countElement = document.querySelector('#users-count');
        if (countElement) {
            countElement.textContent = `${this.state.meta.total} users found`;
        }
    }
    
    renderPagination() {
        const container = document.querySelector('#pagination-container');
        if (!container) return;
        
        const { page, last_page, total } = this.state.meta;
        
        if (last_page <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let paginationHTML = '<nav class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">';
        paginationHTML += '<div class="flex flex-1 justify-between sm:hidden">';
        
        if (page > 1) {
            paginationHTML += `<button onclick="window.Users.goToPage(${page - 1})" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</button>`;
        }
        
        if (page < last_page) {
            paginationHTML += `<button onclick="window.Users.goToPage(${page + 1})" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</button>`;
        }
        
        paginationHTML += '</div>';
        paginationHTML += '<div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">';
        paginationHTML += `<div><p class="text-sm text-gray-700">Showing <span class="font-medium">${((page - 1) * this.state.meta.per_page) + 1}</span> to <span class="font-medium">${Math.min(page * this.state.meta.per_page, total)}</span> of <span class="font-medium">${total}</span> results</p></div>`;
        paginationHTML += '<div>';
        paginationHTML += '<nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">';
        
        // Previous button
        if (page > 1) {
            paginationHTML += `<button onclick="window.Users.goToPage(${page - 1})" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0"><i class="fas fa-chevron-left"></i></button>`;
        }
        
        // Page numbers
        const startPage = Math.max(1, page - 2);
        const endPage = Math.min(last_page, page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === page;
            paginationHTML += `<button onclick="window.Users.goToPage(${i})" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold ${isActive ? 'bg-blue-600 text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'}">${i}</button>`;
        }
        
        // Next button
        if (page < last_page) {
            paginationHTML += `<button onclick="window.Users.goToPage(${page + 1})" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0"><i class="fas fa-chevron-right"></i></button>`;
        }
        
        paginationHTML += '</nav></div></div></nav>';
        
        container.innerHTML = paginationHTML;
    }
    
    goToPage(page) {
        this.state.meta.page = page;
        this.loadUsers();
        this.updateUrl();
    }
    
    clearFilters() {
        console.log('clearFilters called');
        
        this.filters = { ...this.defaultFilters };
        this.state.meta.page = 1;
        
        console.log('Filters cleared:', this.filters);
        
        this.updateUIFromState();
        this.updateFilterChips();
        this.loadUsers();
        this.updateUrl();
        
        this.trackEvent('users_reset_filters');
    }
    
    initFilterChips() {
        this.createFilterChips();
        
        // Handle chip removal
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('chip-remove') || e.target.closest('.chip-remove')) {
                const chip = e.target.closest('.filter-chip');
                if (chip) {
                    const filterType = chip.dataset.filterChip;
                    const filterValue = chip.dataset.filterValue;
                    
                    this.removeFilterChip(filterType, filterValue);
                }
            }
        });
    }
    
    createFilterChips() {
        const container = document.getElementById('filter-chips');
        if (!container) return;
        
        const chips = [];
        
        // Add search chip
        if (this.filters.q) {
            chips.push({
                type: 'search',
                value: this.filters.q,
                label: `Search: "${this.filters.q}"`,
                icon: 'fas fa-search'
            });
        }
        
        // Add tenant chip
        if (this.filters.tenant_id) {
            chips.push({
                type: 'tenant_id',
                value: this.filters.tenant_id,
                label: `Tenant: ${this.filters.tenant_id}`,
                icon: 'fas fa-building'
            });
        }
        
        // Add role chips
        this.filters.role.forEach(role => {
            chips.push({
                type: 'role',
                value: role,
                label: `Role: ${this.capitalizeFirst(role)}`,
                icon: 'fas fa-user-tag'
            });
        });
        
        // Add status chips
        this.filters.status.forEach(status => {
            chips.push({
                type: 'status',
                value: status,
                label: `Status: ${this.capitalizeFirst(status)}`,
                icon: 'fas fa-circle'
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
        
        // Add last login chip
        if (this.filters.last_login) {
            chips.push({
                type: 'last_login',
                value: this.filters.last_login,
                label: this.getLastLoginLabel(this.filters.last_login),
                icon: 'fas fa-sign-in-alt'
            });
        }
        
        // Add MFA chip
        if (this.filters.mfa) {
            chips.push({
                type: 'mfa',
                value: this.filters.mfa,
                label: `MFA: ${this.capitalizeFirst(this.filters.mfa)}`,
                icon: 'fas fa-shield-alt'
            });
        }
        
        container.innerHTML = chips.map(chip => `
            <button class="filter-chip inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors" 
                    data-filter-chip="${chip.type}" 
                    data-filter-value="${chip.value}">
                <i class="${chip.icon} mr-1"></i>
                ${chip.label}
                <span class="chip-remove ml-1 opacity-70 hover:opacity-100">×</span>
            </button>
        `).join('');
    }
    
    updateFilterChips() {
        this.createFilterChips();
    }
    
    removeFilterChip(filterType, filterValue) {
        console.log('Removing filter chip:', filterType, filterValue);
        
        switch (filterType) {
            case 'search':
                this.filters.q = '';
                break;
            case 'tenant_id':
                this.filters.tenant_id = '';
                break;
            case 'role':
                this.filters.role = this.filters.role.filter(r => r !== filterValue);
                break;
            case 'status':
                this.filters.status = this.filters.status.filter(s => s !== filterValue);
                break;
            case 'range':
                this.filters.range = '';
                break;
            case 'last_login':
                this.filters.last_login = '';
                break;
            case 'mfa':
                this.filters.mfa = '';
                break;
        }
        
        this.state.meta.page = 1;
        this.updateUIFromState();
        this.updateFilterChips();
        this.loadUsers();
        this.updateUrl();
        
        this.trackEvent('users_filter_change', {
            filter_type: filterType,
            filter_value: filterValue,
            action: 'remove'
        });
    }
    
    initSearch() {
        const searchInput = document.querySelector('#search-input');
        const clearSearchBtn = document.querySelector('#clear-search');
        
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.filters.q = e.target.value;
                    this.state.meta.page = 1;
                    this.updateFilterChips();
                    this.loadUsers();
                    this.updateUrl();
                    
                    this.trackEvent('users_filter_change', {
                        filter_type: 'search',
                        filter_value: this.filters.q
                    });
                }, 300);
            });
            
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    this.filters.q = e.target.value;
                    this.state.meta.page = 1;
                    this.updateFilterChips();
                    this.loadUsers();
                    this.updateUrl();
                }
            });
        }
        
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', () => {
                this.filters.q = '';
                if (searchInput) searchInput.value = '';
                this.state.meta.page = 1;
                this.updateFilterChips();
                this.loadUsers();
                this.updateUrl();
            });
        }
    }
    
    initFilters() {
        // Reset filters button
        const resetBtn = document.querySelector('#reset-filters-btn');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.clearFilters();
            });
        }
        
        // Filter selects
        document.addEventListener('change', (e) => {
            if (e.target.hasAttribute('data-filter')) {
                const filterType = e.target.getAttribute('data-filter');
                const filterValue = e.target.value;
                
                console.log('Filter changed:', filterType, filterValue);
                
                switch (filterType) {
                    case 'tenant_id':
                        this.filters.tenant_id = filterValue;
                        break;
                    case 'role':
                        if (filterValue) {
                            this.filters.role = [filterValue];
                        } else {
                            this.filters.role = [];
                        }
                        break;
                    case 'status':
                        if (filterValue) {
                            this.filters.status = [filterValue];
                        } else {
                            this.filters.status = [];
                        }
                        break;
                    case 'range':
                        this.filters.range = filterValue;
                        break;
                    case 'last_login':
                        this.filters.last_login = filterValue;
                        break;
                    case 'mfa':
                        this.filters.mfa = filterValue;
                        break;
                    case 'sort':
                        this.filters.sort = filterValue;
                        break;
                    case 'per_page':
                        this.state.meta.per_page = parseInt(filterValue);
                        this.state.meta.page = 1;
                        break;
                }
                
                this.state.meta.page = 1;
                this.updateFilterChips();
                this.loadUsers();
                this.updateUrl();
                
                this.trackEvent('users_filter_change', {
                    filter_type: filterType,
                    filter_value: filterValue
                });
            }
        });
    }
    
    initEventListeners() {
        // Export buttons
        const exportAllBtn = document.querySelector('#export-all-btn');
        if (exportAllBtn) {
            exportAllBtn.addEventListener('click', () => {
                this.exportUsers();
            });
        }
        
        const exportSelectedBtn = document.querySelector('#export-selected-btn');
        if (exportSelectedBtn) {
            exportSelectedBtn.addEventListener('click', () => {
                this.exportSelectedUsers();
            });
        }
        
        // Invite user button
        const inviteBtn = document.querySelector('#invite-user-btn');
        if (inviteBtn) {
            inviteBtn.addEventListener('click', () => {
                this.showInviteModal();
            });
        }
        
        // Select all checkbox
        const selectAllCheckbox = document.querySelector('#select-all-checkbox');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                this.toggleSelectAll(e.target.checked);
            });
        }
        
        // User checkboxes
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('user-checkbox')) {
                this.toggleUserSelection(e.target.value, e.target.checked);
            }
        });
    }
    
    async loadTenants() {
        try {
            const response = await fetch('/api/admin/tenants', {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                const tenantSelect = document.querySelector('[data-filter="tenant_id"]');
                const inviteTenantSelect = document.querySelector('#invite-tenant');
                
                if (tenantSelect && data.data) {
                    const currentValue = tenantSelect.value;
                    tenantSelect.innerHTML = '<option value="">All Tenants</option>' + 
                        data.data.map(tenant => `<option value="${tenant.id}">${tenant.name}</option>`).join('');
                    tenantSelect.value = currentValue;
                }
                
                if (inviteTenantSelect && data.data) {
                    inviteTenantSelect.innerHTML = '<option value="">Select a tenant</option>' + 
                        data.data.map(tenant => `<option value="${tenant.id}">${tenant.name}</option>`).join('');
                }
            }
        } catch (error) {
            console.error('Error loading tenants:', error);
        }
    }
    
    showInviteModal() {
        const modal = document.querySelector('#invite-user-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }
    
    hideInviteModal() {
        const modal = document.querySelector('#invite-user-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    
    showUserDetail(userId) {
        window.location.href = `/admin/users/${userId}`;
    }
    
    showUserActions(userId) {
        // TODO: Implement user actions dropdown
        console.log('Show actions for user:', userId);
    }
    
    toggleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.user-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
            this.toggleUserSelection(checkbox.value, checked);
        });
    }
    
    toggleUserSelection(userId, checked) {
        if (checked) {
            this.selectedUsers.add(userId);
        } else {
            this.selectedUsers.delete(userId);
        }
        
        this.updateBulkActionsBar();
    }
    
    updateBulkActionsBar() {
        const bar = document.querySelector('#bulk-actions-bar');
        const countElement = document.querySelector('#selected-count');
        const exportSelectedBtn = document.querySelector('#export-selected-btn');
        
        if (this.selectedUsers.size > 0) {
            if (bar) bar.classList.remove('hidden');
            if (countElement) countElement.textContent = `${this.selectedUsers.size} users selected`;
            if (exportSelectedBtn) exportSelectedBtn.classList.remove('hidden');
        } else {
            if (bar) bar.classList.add('hidden');
            if (exportSelectedBtn) exportSelectedBtn.classList.add('hidden');
        }
    }
    
    async exportUsers() {
        try {
            const queryParams = new URLSearchParams();
            
            // Add current filters
            if (this.filters.q) queryParams.set('q', this.filters.q);
            if (this.filters.tenant_id) queryParams.set('tenant_id', this.filters.tenant_id);
            if (this.filters.role.length > 0) queryParams.set('role', this.filters.role.join(','));
            if (this.filters.status.length > 0) queryParams.set('status', this.filters.status.join(','));
            if (this.filters.range) queryParams.set('range', this.filters.range);
            if (this.filters.last_login) queryParams.set('last_login', this.filters.last_login);
            if (this.filters.mfa) queryParams.set('mfa', this.filters.mfa);
            if (this.filters.sort) queryParams.set('sort', this.filters.sort);
            
            const url = `/api/admin/users/export?${queryParams.toString()}`;
            
            const response = await fetch(url, {
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`
                }
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const downloadUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = `users_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(downloadUrl);
                
                this.showToast('Users exported successfully', 'success');
                this.trackEvent('users_export', { type: 'all' });
            } else {
                throw new Error('Export failed');
            }
        } catch (error) {
            console.error('Export error:', error);
            this.showToast('Export failed', 'error');
        }
    }
    
    async exportSelectedUsers() {
        if (this.selectedUsers.size === 0) {
            this.showToast('No users selected', 'warning');
            return;
        }
        
        try {
            const response = await fetch('/api/admin/users/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`
                },
                body: JSON.stringify({
                    user_ids: Array.from(this.selectedUsers)
                })
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const downloadUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = `users_selected_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(downloadUrl);
                
                this.showToast('Selected users exported successfully', 'success');
                this.trackEvent('users_export', { type: 'selected', count: this.selectedUsers.size });
            } else {
                throw new Error('Export failed');
            }
        } catch (error) {
            console.error('Export error:', error);
            this.showToast('Export failed', 'error');
        }
    }
    
    // Utility methods
    getAuthToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }
    
    getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    }
    
    getRoleBadgeClass(role) {
        const classes = {
            admin: 'bg-red-100 text-red-800',
            manager: 'bg-blue-100 text-blue-800',
            member: 'bg-gray-100 text-gray-800'
        };
        return classes[role] || 'bg-gray-100 text-gray-800';
    }
    
    getStatusBadgeClass(status) {
        const classes = {
            active: 'bg-green-100 text-green-800',
            inactive: 'bg-gray-100 text-gray-800',
            suspended: 'bg-red-100 text-red-800',
            invited: 'bg-yellow-100 text-yellow-800',
            locked: 'bg-red-100 text-red-800'
        };
        return classes[status] || 'bg-gray-100 text-gray-800';
    }
    
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    }
    
    formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        
        return date.toLocaleDateString();
    }
    
    getRangeLabel(range) {
        const labels = {
            '7d': 'Last 7 days',
            '30d': 'Last 30 days',
            '90d': 'Last 90 days',
            'all': 'All time'
        };
        return labels[range] || range;
    }
    
    getLastLoginLabel(lastLogin) {
        const labels = {
            '7d': 'Last 7 days',
            '30d': 'Last 30 days',
            'never': 'Never logged in'
        };
        return labels[lastLogin] || lastLogin;
    }
    
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    showToast(message, type = 'info') {
        // Simple toast implementation
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg text-white z-50 ${
            type === 'success' ? 'bg-green-500' : 
            type === 'error' ? 'bg-red-500' : 
            type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
        }`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 3000);
    }
    
    trackEvent(eventName, properties = {}) {
        if (typeof gtag !== 'undefined') {
            gtag('event', eventName, {
                page_title: document.title,
                page_location: window.location.href,
                ...properties
            });
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.Users = new UsersPage();
});

// Export for global access
window.UsersPage = UsersPage;
