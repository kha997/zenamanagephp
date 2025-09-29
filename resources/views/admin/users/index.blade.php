@extends('layouts.admin')

@section('title', 'Users Management')

@section('content')
<div class="container mx-auto p-6" x-data="usersPage()">
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Users Management</h1>
            <p class="text-gray-600 mt-1">Manage all users across the system</p>
        </div>
        <div class="flex items-center space-x-3">
            <button @click="exportUsers" 
                    class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button @click="openInviteModal" 
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-user-plus mr-2"></i>Invite User
            </button>
        </div>
    </div>

    <!-- Mock Data Badge -->
    <div x-show="mockData" class="mb-4">
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
            <i class="fas fa-flask mr-1"></i>Mock Data
        </span>
    </div>

    <!-- KPI Strip -->
    @include('admin.users._kpis')

    <!-- Search & Filters -->
    @include('admin.users._filters')

    <!-- Users Table -->
    @include('admin.users._table')

    <!-- Pagination -->
    @include('admin.users._pagination')
    
    <!-- Modals -->
    @include('admin.users._edit_modal')
    @include('admin.users._change_role_modal')
    @include('admin.users._force_mfa_modal')
    @include('admin.users._invite_modal')
</div>
@endsection

@push('scripts')
<script src="/js/usersApi.js"></script>
<script>
    function usersPage() {
        return {
            // Feature flag for mock data
            mockData: false,
            
            // KPI Data Contract v2
            kpis: {
                totalUsers: { 
                    value: 1247, 
                    deltaPct: 12.1, 
                    period: '30d',
                    series: [1050, 1080, 1120, 1180, 1247]
                },
                activeUsers: { 
                    value: 892, 
                    deltaPct: 8.3, 
                    period: '7d',
                    series: [820, 835, 845, 860, 892]
                },
                lockedUsers: { 
                    value: 23, 
                    deltaAbs: -5, 
                    period: '7d',
                    series: [28, 26, 25, 24, 23]
                },
                noMfaUsers: { 
                    value: 156, 
                    deltaPct: -15.2, 
                    period: '30d',
                    series: [180, 175, 170, 162, 156]
                },
                pendingInvites: { 
                    value: 8, 
                    deltaAbs: 2, 
                    period: '7d',
                    series: [6, 6, 7, 7, 8]
                }
            },
            
            // Mock users data
            users: [
                {
                    id: '1',
                    name: 'John Doe',
                    email: 'john@acme.com',
                    tenantId: '1',
                    tenantName: 'Acme Corp',
                    role: 'TenantAdmin',
                    status: 'active',
                    mfaEnabled: true,
                    lastLoginAt: '2024-09-27T10:30:00Z',
                    createdAt: '2024-08-15T09:00:00Z'
                },
                {
                    id: '2',
                    name: 'Jane Smith',
                    email: 'jane@techstart.com',
                    tenantId: '2',
                    tenantName: 'TechStart',
                    role: 'PM',
                    status: 'active',
                    mfaEnabled: false,
                    lastLoginAt: '2024-09-26T14:20:00Z',
                    createdAt: '2024-09-01T11:30:00Z'
                },
                {
                    id: '3',
                    name: 'Bob Wilson',
                    email: 'bob@enterprise.com',
                    tenantId: '3',
                    tenantName: 'Enterprise Inc',
                    role: 'Staff',
                    status: 'locked',
                    mfaEnabled: true,
                    lastLoginAt: '2024-09-20T16:45:00Z',
                    createdAt: '2024-07-10T08:15:00Z'
                }
            ],
            
            // Server-side state
            filteredUsers: [],
            searchQuery: '',
            tenantFilter: '',
            roleFilter: '',
            statusFilter: '',
            mfaFilter: '',
            activeWithinFilter: '',
            lastLoginFrom: '',
            lastLoginTo: '',
            createdFrom: '',
            createdTo: '',
            sortBy: 'name',
            sortOrder: 'asc',
            page: 1,
            perPage: 20,
            total: 0,
            lastPage: 1,
            isLoading: false,
            usersLoading: false,
            error: null,
            abortController: null,
            
            // UI state - Non-blocking loading
            selectedUsers: [],
            activePreset: '',
            showEditModal: false,
            
            // Modal management with proper cloaking
            modalOpen: false,
            showChangeRoleModal: false,
            showForceMfaModal: false,
            showInviteModal: false,
            currentUser: null,
            newUser: {
                name: '',
                email: '',
                tenantId: '',
                role: 'Staff'
            },
            chartInstances: {},
            
            async init() {
                console.log('UsersPage init, mockData:', this.mockData);
                this.parseUrlParams();
                this.loadUsers();
                this.initCharts();
                this.logEvent('users_view_loaded', { 
                    query: this.getCurrentQuery(), 
                    page: this.page, 
                    per_page: this.perPage 
                });
            },
            
            // URL state management
            parseUrlParams() {
                const urlParams = new URLSearchParams(window.location.search);
                this.searchQuery = urlParams.get('q') || '';
                this.tenantFilter = urlParams.get('tenant') || '';
                this.roleFilter = urlParams.get('role') || '';
                this.statusFilter = urlParams.get('status') || '';
                this.mfaFilter = urlParams.get('mfa') || '';
                this.activeWithinFilter = urlParams.get('activeWithin') || '';
                this.lastLoginFrom = urlParams.get('lastLoginFrom') || '';
                this.lastLoginTo = urlParams.get('lastLoginTo') || '';
                this.createdFrom = urlParams.get('createdFrom') || '';
                this.createdTo = urlParams.get('createdTo') || '';
                this.sortBy = urlParams.get('sort')?.replace('-', '') || 'name';
                this.sortOrder = urlParams.get('sort')?.startsWith('-') ? 'desc' : 'asc';
                this.page = parseInt(urlParams.get('page')) || 1;
                this.perPage = parseInt(urlParams.get('per_page')) || 20;
            },
            
            updateUrl() {
                const params = new URLSearchParams();
                if (this.searchQuery) params.set('q', this.searchQuery);
                if (this.tenantFilter) params.set('tenant', this.tenantFilter);
                if (this.roleFilter) params.set('role', this.roleFilter);
                if (this.statusFilter) params.set('status', this.statusFilter);
                if (this.mfaFilter) params.set('mfa', this.mfaFilter);
                if (this.activeWithinFilter) params.set('activeWithin', this.activeWithinFilter);
                if (this.lastLoginFrom) params.set('lastLoginFrom', this.lastLoginFrom);
                if (this.lastLoginTo) params.set('lastLoginTo', this.lastLoginTo);
                if (this.createdFrom) params.set('createdFrom', this.createdFrom);
                if (this.createdTo) params.set('createdTo', this.createdTo);
                if (this.sortBy) params.set('sort', this.sortOrder === 'desc' ? `-${this.sortBy}` : this.sortBy);
                if (this.page > 1) params.set('page', this.page);
                if (this.perPage !== 20) params.set('per_page', this.perPage);
                
                const newUrl = `${window.location.pathname}?${params.toString()}`;
                window.history.replaceState({}, '', newUrl);
            },
            
            // Data loading - Non-blocking with Panel Fetch
            async loadUsers() {
                try {
                    this.error = null;
                    
                    // Use Panel Fetch for non-blocking load
                    const result = await window.panelFetch('/api/admin/users?' + this.buildApiParams(), {
                        onStart: () => { this.usersLoading = true; },
                        onEnd: () => { this.usersLoading = false; },
                        panelId: 'users-table',
                        cacheKey: 'users-list'
                    });
                    
                    // Handle response data
                    if (this.mockData) {
                        // Mock API response
                        await new Promise(resolve => setTimeout(resolve, 300));
                        this.filteredUsers = [...this.users];
                        this.total = this.users.length;
                        this.lastPage = Math.ceil(this.total / this.perPage);
                    } else {
                        // Real API call using service layer - map camelCase to snake_case
                        const params = this.buildApiParams();
                        
                        if (!window.usersApi) {
                            console.error('window.usersApi is not available, falling back to mock data');
                            this.filteredUsers = [...this.users];
                            this.total = this.users.length;
                            this.lastPage = Math.ceil(this.total / this.perPage);
                            return;
                        }
                        
                        const data = await window.usersApi.getUsers(params);
                        this.filteredUsers = data.data;
                        this.total = data.meta.total;
                        this.lastPage = data.meta.last_page;
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        console.error('Users loading error:', error);
                        this.error = error.message;
                    }
                }
            },
            
            // Build API params with snake_case mapping and sanitization
            buildApiParams() {
                const params = {};
                
                // Map camelCase to snake_case and sanitize
                if (this.searchQuery) params.q = this.searchQuery;
                if (this.tenantFilter) params.tenant = this.tenantFilter;
                if (this.roleFilter) params.role = this.roleFilter;
                if (this.statusFilter) params.status = this.statusFilter;
                if (this.mfaFilter) params.mfa = this.mfaFilter;
                if (this.activeWithinFilter) params.active_within = this.activeWithinFilter;
                if (this.lastLoginFrom) params.last_login_from = this.lastLoginFrom;
                if (this.lastLoginTo) params.last_login_to = this.lastLoginTo;
                if (this.createdFrom) params.created_from = this.createdFrom;
                if (this.createdTo) params.created_to = this.createdTo;
                
                // Sort field mapping
                let sortField = this.sortBy;
                if (sortField === 'lastLoginAt') sortField = 'last_login_at';
                if (sortField === 'createdAt') sortField = 'created_at';
                if (sortField === 'tenantName') sortField = 'tenant';
                if (sortField === 'mfaEnabled') sortField = 'mfa';
                
                params.sort = this.sortOrder === 'desc' ? `-${sortField}` : sortField;
                params.page = this.page;
                params.per_page = this.perPage;
                
                return params;
            },
            
            // Search and filters
            performServerSearch: debounce(function() {
                this.page = 1;
                this.updateUrl();
                this.loadUsers();
            }, 250),
            
            applyFilters() {
                this.page = 1;
                this.updateUrl();
                this.loadUsers();
            },
            
            applyPreset(preset) {
                // Clear existing filters first
                this.searchQuery = '';
                this.tenantFilter = '';
                this.roleFilter = '';
                this.statusFilter = '';
                this.mfaFilter = '';
                this.activeWithinFilter = '';
                this.lastLoginFrom = '';
                this.lastLoginTo = '';
                this.createdFrom = '';
                this.createdTo = '';
                this.sortBy = 'name';
                this.sortOrder = 'asc';
                
                switch (preset) {
                    case 'active':
                        this.statusFilter = 'active';
                        this.activeWithinFilter = '7d';
                        this.sortBy = 'lastLoginAt';
                        this.sortOrder = 'desc';
                        break;
                    case 'locked':
                        this.statusFilter = 'locked';
                        break;
                    case 'no-mfa':
                        this.mfaFilter = 'false';
                        break;
                    case 'invited':
                        this.statusFilter = 'invited';
                        break;
                    case 'disabled':
                        this.statusFilter = 'disabled';
                        break;
                }
                
                this.activePreset = preset;
                this.page = 1;
                this.updateUrl();
                this.loadUsers();
                this.logEvent('users_preset_click', { preset });
            },
            
            clearFilters() {
                this.searchQuery = '';
                this.tenantFilter = '';
                this.roleFilter = '';
                this.statusFilter = '';
                this.mfaFilter = '';
                this.activeWithinFilter = '';
                this.lastLoginFrom = '';
                this.lastLoginTo = '';
                this.createdFrom = '';
                this.createdTo = '';
                this.sortBy = 'name';
                this.sortOrder = 'asc';
                this.activePreset = '';
                this.page = 1;
                this.updateUrl();
                this.loadUsers();
            },
            
            get hasActiveFilters() {
                return this.searchQuery || this.tenantFilter || this.roleFilter || 
                       this.statusFilter || this.mfaFilter || this.activeWithinFilter ||
                       this.lastLoginFrom || this.lastLoginTo || this.createdFrom || this.createdTo;
            },
            
            // Server-side sorting
            async setSort(column) {
                if (this.sortBy === column) {
                    this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortBy = column;
                    this.sortOrder = 'asc';
                }
                
                this.updateUrl();
                this.loadUsers();
            },
            
            // Pagination
            changePage(newPage) {
                this.page = newPage;
                this.updateUrl();
                this.loadUsers();
            },
            
            getVisiblePages() {
                const pages = [];
                const start = Math.max(1, this.page - 2);
                const end = Math.min(this.lastPage, this.page + 2);
                
                for (let i = start; i <= end; i++) {
                    pages.push(i);
                }
                return pages;
            },
            
            // Utility functions
            formatDate(dateString) {
                if (!dateString) return 'Never';
                const date = new Date(dateString);
                return date.toLocaleDateString();
            },
            
            formatTimeAgo(dateString) {
                if (!dateString) return 'Never';
                const date = new Date(dateString);
                const now = new Date();
                const diffMs = now - date;
                const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                
                if (diffDays === 0) return 'Today';
                if (diffDays === 1) return 'Yesterday';
                if (diffDays < 7) return `${diffDays} days ago`;
                if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
                return `${Math.floor(diffDays / 30)} months ago`;
            },
            
            // User actions
            viewUser(user) {
                window.location.href = `/admin/users/${user.id}`;
                this.logEvent('user_row_action', { action: 'view', userId: user.id });
            },
            
            async toggleUserStatus(user) {
                const newStatus = user.status === 'active' ? 'disabled' : 'active';
                const action = newStatus === 'active' ? 'enable' : 'disable';
                
                try {
                    if (this.mockData) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                        user.status = newStatus;
                    } else {
                        const data = await window.usersApi[`${action}User`](user.id);
                        user.status = data.data.status;
                    }
                    
                    this.logEvent('user_row_action', { action, userId: user.id });
                } catch (error) {
                    console.error('Failed to toggle user status:', error);
                    this.error = error.message;
                }
            },
            
            async unlockUser(user) {
                try {
                    if (this.mockData) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                        user.status = 'active';
                    } else {
                        const data = await window.usersApi.unlockUser(user.id);
                        user.status = data.data.status;
                    }
                    
                    this.logEvent('user_row_action', { action: 'unlock', userId: user.id });
                } catch (error) {
                    console.error('Failed to unlock user:', error);
                    this.error = error.message;
                }
            },
            
            async sendResetLink(user) {
                try {
                    if (this.mockData) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                        alert(`Reset link sent to ${user.email}`);
                    } else {
                        await window.usersApi.sendResetLink(user.id);
                        alert(`Reset link sent to ${user.email}`);
                    }
                    
                    this.logEvent('user_row_action', { action: 'send_reset_link', userId: user.id });
                } catch (error) {
                    console.error('Failed to send reset link:', error);
                    alert(`Failed to send reset link: ${error.message}`);
                }
            },
            
            // Selection
            selectUser(user) {
                if (this.selectedUsers.some(u => u.id === user.id)) {
                    this.selectedUsers = this.selectedUsers.filter(u => u.id !== user.id);
                } else {
                    this.selectedUsers.push(user);
                }
            },
            
            selectAllUsers() {
                if (this.selectedUsers.length === this.filteredUsers.length) {
                    this.selectedUsers = [];
                } else {
                    this.selectedUsers = [...this.filteredUsers];
                }
            },
            
            // Modal functions
            openEditModal(user) {
                this.showEditModal = true;
                this.currentUser = { ...user };
            },
            
            openChangeRoleModal(user) {
                this.showChangeRoleModal = true;
                this.currentUser = user;
            },
            
            openForceMfaModal(user) {
                this.showForceMfaModal = true;
                this.currentUser = user;
            },
            
            openInviteModal() {
                this.showInviteModal = true;
                this.newUser = {
                    name: '',
                    email: '',
                    tenantId: '',
                    role: 'Staff'
                };
            },
            
            closeModals() {
                this.showEditModal = false;
                this.showChangeRoleModal = false;
                this.showForceMfaModal = false;
                this.showInviteModal = false;
                this.currentUser = null;
                this.newUser = {
                    name: '',
                    email: '',
                    tenantId: '',
                    role: 'Staff'
                };
            },
            
            async updateUser() {
                try {
                    if (this.mockData) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                        const index = this.filteredUsers.findIndex(u => u.id === this.currentUser.id);
                        if (index !== -1) {
                            this.filteredUsers[index] = { ...this.currentUser };
                        }
                    } else {
                        const data = await window.usersApi.updateUser(this.currentUser.id, this.currentUser);
                        const index = this.filteredUsers.findIndex(u => u.id === this.currentUser.id);
                        if (index !== -1) {
                            this.filteredUsers[index] = data.data;
                        }
                    }
                    
                    this.closeModals();
                    this.logEvent('user_updated', { userId: this.currentUser.id });
                    alert('User updated successfully!');
                } catch (error) {
                    console.error('Failed to update user:', error);
                    alert(`Failed to update user: ${error.message}`);
                }
            },
            
            async changeUserRole() {
                try {
                    if (this.mockData) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                        this.currentUser.role = this.currentUser.newRole;
                    } else {
                        const data = await window.usersApi.changeUserRole(this.currentUser.id, this.currentUser.newRole);
                        this.currentUser.role = data.data.role;
                    }
                    
                    this.closeModals();
                    this.logEvent('user_role_changed', { userId: this.currentUser.id, newRole: this.currentUser.newRole });
                    alert('User role changed successfully!');
                } catch (error) {
                    console.error('Failed to change user role:', error);
                    alert(`Failed to change user role: ${error.message}`);
                }
            },
            
            async forceMfa() {
                try {
                    if (this.mockData) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                        this.currentUser.mfaEnabled = true;
                    } else {
                        const data = await window.usersApi.forceMfa(this.currentUser.id);
                        this.currentUser.mfaEnabled = data.data.mfaEnabled;
                    }
                    
                    this.closeModals();
                    this.logEvent('user_mfa_forced', { userId: this.currentUser.id });
                    alert('MFA forced successfully!');
                } catch (error) {
                    console.error('Failed to force MFA:', error);
                    alert(`Failed to force MFA: ${error.message}`);
                }
            },
            
            async inviteUser() {
                try {
                    if (this.mockData) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                        const newUser = {
                            id: Date.now().toString(),
                            ...this.newUser,
                            status: 'invited',
                            mfaEnabled: false,
                            lastLoginAt: null,
                            createdAt: new Date().toISOString()
                        };
                        this.filteredUsers.unshift(newUser);
                        this.total++;
                    } else {
                        const data = await window.usersApi.createUser(this.newUser);
                        this.filteredUsers.unshift(data.data);
                        this.total++;
                    }
                    
                    this.closeModals();
                    this.logEvent('user_invited', { email: this.newUser.email });
                    alert('User invited successfully!');
                } catch (error) {
                    console.error('Failed to invite user:', error);
                    alert(`Failed to invite user: ${error.message}`);
                }
            },
            
            // Bulk actions
            async bulkAction(action) {
                if (this.selectedUsers.length === 0) return;
                
                const count = this.selectedUsers.length;
                let confirmMessage = '';
                let successMessage = '';
                
                switch (action) {
                    case 'enable':
                        confirmMessage = `Enable ${count} user(s)?`;
                        successMessage = `${count} user(s) enabled successfully`;
                        break;
                    case 'disable':
                        confirmMessage = `Disable ${count} user(s)?`;
                        successMessage = `${count} user(s) disabled successfully`;
                        break;
                    case 'unlock':
                        confirmMessage = `Unlock ${count} user(s)?`;
                        successMessage = `${count} user(s) unlocked successfully`;
                        break;
                    case 'change-role':
                        confirmMessage = `Change role for ${count} user(s)?`;
                        successMessage = `${count} user(s) role changed successfully`;
                        break;
                    case 'force-mfa':
                        confirmMessage = `Force MFA for ${count} user(s)?`;
                        successMessage = `${count} user(s) MFA forced successfully`;
                        break;
                    case 'send-reset':
                        confirmMessage = `Send reset links to ${count} user(s)?`;
                        successMessage = `Reset links sent to ${count} user(s)`;
                        break;
                    case 'delete':
                        confirmMessage = `Delete ${count} user(s)? This action cannot be undone.`;
                        successMessage = `${count} user(s) deleted successfully`;
                        break;
                }
                
                if (!confirm(confirmMessage)) return;
                
                try {
                    const ids = this.selectedUsers.map(u => u.id);
                    
                    if (this.mockData) {
                        await new Promise(resolve => setTimeout(resolve, 1000));
                        const successCount = Math.floor(count * 0.8);
                        const errorCount = count - successCount;
                        
                        if (errorCount === 0) {
                            alert(successMessage);
                        } else {
                            alert(`${successCount} user(s) processed successfully, ${errorCount} failed`);
                        }
                    } else {
                        const result = await window.usersApi.bulkAction(action, ids);
                        const successCount = result.ok.length;
                        const errorCount = result.failed.length;
                        
                        if (errorCount === 0) {
                            alert(successMessage);
                        } else {
                            alert(`${successCount} user(s) processed successfully, ${errorCount} failed`);
                        }
                    }
                    
                    this.selectedUsers = [];
                    this.loadUsers();
                    this.logEvent('users_bulk_action', { action, count });
                    
                } catch (error) {
                    console.error('Bulk action failed:', error);
                    alert(`Bulk action failed: ${error.message}`);
                }
            },
            
            // Export
            async exportUsers() {
                try {
                    const params = this.buildApiParams();
                    delete params.page;
                    delete params.per_page;
                    
                    if (this.mockData) {
                        await new Promise(resolve => setTimeout(resolve, 1000));
                        alert('Exporting all users with current filters...');
                    } else {
                        await window.usersApi.exportUsers(params);
                    }
                    
                    this.logEvent('users_export', { format: 'csv', filtered: this.hasActiveFilters, count: this.total });
                    
                } catch (error) {
                    console.error('Export failed:', error);
                    alert(`Export failed: ${error.message}`);
                }
            },
            
            // KPI drill-down
            drillDownTotal() {
                window.location.href = '/admin/users?sort=-created_at';
                this.logEvent('kpi_drilldown', { kpi: 'total', target: 'users_list' });
            },
            
            drillDownActive() {
                window.location.href = '/admin/users?status=active&active_within=7d&sort=-last_login_at';
                this.logEvent('kpi_drilldown', { kpi: 'active', target: 'users_list' });
            },
            
            drillDownLocked() {
                window.location.href = '/admin/users?status=locked,disabled';
                this.logEvent('kpi_drilldown', { kpi: 'locked', target: 'users_list' });
            },
            
            drillDownNoMfa() {
                window.location.href = '/admin/users?mfa=false';
                this.logEvent('kpi_drilldown', { kpi: 'no-mfa', target: 'users_list' });
            },
            
            drillDownInvites() {
                window.location.href = '/admin/users?status=invited';
                this.logEvent('kpi_drilldown', { kpi: 'invites', target: 'users_list' });
            },
            
            // Charts
            initCharts() {
                Object.keys(this.kpis).forEach(key => {
                    if (this.kpis[key].series) {
                        this.createSparkline(key, this.kpis[key].series);
                    }
                });
            },
            
            createSparkline(kpiKey, series) {
                const canvas = document.getElementById(`sparkline-${kpiKey}`);
                if (!canvas) return;
                
                const ctx = canvas.getContext('2d');
                const width = canvas.width;
                const height = canvas.height;
                
                if (this.chartInstances[kpiKey]) {
                    this.chartInstances[kpiKey].destroy();
                }
                
                const max = Math.max(...series);
                const min = Math.min(...series);
                const range = max - min || 1;
                
                ctx.clearRect(0, 0, width, height);
                ctx.strokeStyle = this.getSparklineColor(kpiKey);
                ctx.lineWidth = 2;
                ctx.beginPath();
                
                series.forEach((value, index) => {
                    const x = (index / (series.length - 1)) * width;
                    const y = height - ((value - min) / range) * height;
                    
                    if (index === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                });
                
                ctx.stroke();
            },
            
            getSparklineColor(kpiKey) {
                const colors = {
                    totalUsers: '#3B82F6',
                    activeUsers: '#10B981',
                    lockedUsers: '#EF4444',
                    noMfaUsers: '#F59E0B',
                    pendingInvites: '#8B5CF6'
                };
                return colors[kpiKey] || '#6B7280';
            },
            
            // Analytics
            getCurrentQuery() {
                const params = new URLSearchParams();
                if (this.searchQuery) params.set('q', this.searchQuery);
                if (this.statusFilter) params.set('status', this.statusFilter);
                if (this.roleFilter) params.set('role', this.roleFilter);
                return params.toString();
            },
            
            logEvent(eventName, meta = {}) {
                const event = {
                    name: eventName,
                    timestamp: new Date().toISOString(),
                    meta: {
                        view: 'users',
                        ...meta
                    }
                };
                console.log('Analytics Event:', event);
            },
            
            // Accessibility
            getAriaLabel(action, user) {
                const labels = {
                    view: `View user ${user.name}`,
                    edit: `Edit user ${user.name}`,
                    enable: `Enable user ${user.name}`,
                    disable: `Disable user ${user.name}`,
                    unlock: `Unlock user ${user.name}`,
                    'change-role': `Change role for ${user.name}`,
                    'force-mfa': `Force MFA for ${user.name}`,
                    'send-reset': `Send reset link to ${user.email}`,
                    delete: `Delete user ${user.name}`
                };
                return labels[action] || action;
            }
        }
    }
    
    // Debounce utility
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
</script>
@endpush