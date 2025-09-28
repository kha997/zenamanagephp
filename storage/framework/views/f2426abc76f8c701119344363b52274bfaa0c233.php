<?php $__env->startSection('title', 'Tenants'); ?>

<?php $__env->startSection('breadcrumb'); ?>
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Tenants</span>
</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6" x-data="tenantsPage()">
    
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tenants</h1>
            <p class="text-gray-600">Manage all tenant organizations</p>
        </div>
        <div class="flex items-center space-x-3">
            <!-- Mock Data Badge -->
            <div x-show="mockData" class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">
                <i class="fas fa-flask mr-1"></i>Mock Data
            </div>
            <button @click="exportTenants" 
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button @click="openCreateModal" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-plus mr-2"></i>New Tenant
            </button>
        </div>
    </div>
    
    
    <?php echo $__env->make('admin.tenants._kpis', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    
    <?php echo $__env->make('admin.tenants._filters', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    
    <?php echo $__env->make('admin.tenants._table', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    
    <?php echo $__env->make('admin.tenants._pagination', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="/js/tenantsApi.js"></script>
<script>
    function tenantsPage() {
        return {
            // Feature flag for mock data
            mockData: false, // Set to false to use real BE API
            
            // KPI Data Contract v2
            kpis: {
                totalTenants: { 
                    value: 89, 
                    deltaPct: 5.2, 
                    period: '30d',
                    series: [82, 83, 84, 86, 89]
                },
                activeTenants: { 
                    value: 76, 
                    deltaPct: 3.1, 
                    period: '30d',
                    series: [70, 72, 74, 75, 76]
                },
                disabledTenants: { 
                    value: 8, 
                    deltaAbs: 2, 
                    period: '7d',
                    series: [5, 6, 7, 8, 8]
                },
                newTenants: { 
                    value: 12, 
                    deltaPct: 20.0, 
                    period: '30d',
                    series: [8, 9, 10, 11, 12]
                },
                trialExpiring: { 
                    value: 3, 
                    deltaAbs: 3, 
                    period: '7d',
                    series: [1, 2, 2, 3, 3]
                }
            },
            
            // Data
            tenants: [
                {
                    id: 1,
                    name: 'TechCorp',
                    domain: 'techcorp.com',
                    owner: 'John Doe',
                    ownerEmail: 'john@techcorp.com',
                    plan: 'Professional',
                    status: 'active',
                    users: 25,
                    createdAt: '2024-01-15',
                    lastActive: '2024-09-27'
                },
                {
                    id: 2,
                    name: 'DesignStudio',
                    domain: 'designstudio.com',
                    owner: 'Jane Smith',
                    ownerEmail: 'jane@designstudio.com',
                    plan: 'Basic',
                    status: 'active',
                    users: 8,
                    createdAt: '2024-02-20',
                    lastActive: '2024-09-26'
                },
                {
                    id: 3,
                    name: 'StartupXYZ',
                    domain: 'startupxyz.com',
                    owner: 'Mike Johnson',
                    ownerEmail: 'mike@startupxyz.com',
                    plan: 'Enterprise',
                    status: 'suspended',
                    users: 45,
                    createdAt: '2024-03-10',
                    lastActive: '2024-09-20'
                }
            ],
            
            // Server-side state
            filteredTenants: [],
            searchQuery: '',
            statusFilter: '',
            planFilter: '',
            dateFrom: '',
            dateTo: '',
            sortBy: 'name',
            sortOrder: 'asc',
            page: 1,
            perPage: 20,
            total: 0,
            lastPage: 1,
            isLoading: false,
            error: null,
            abortController: null,
            
            // UI state
            selectedTenants: [],
            activePreset: '',
            showCreateModal: false,
            showEditModal: false,
            showDeleteModal: false,
            currentTenant: null,
            chartInstances: {},
            
            init() {
                this.parseUrlParams();
                this.loadTenants();
                this.initCharts();
                this.logEvent('tenants_view_loaded', { 
                    query: this.getCurrentQuery(), 
                    page: this.page, 
                    per_page: this.perPage 
                });
            },
            
            // URL state management
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
            },
            
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
            },
            
            // Server-side API calls
            async loadTenants() {
                if (this.abortController) {
                    this.abortController.abort();
                }
                
                this.abortController = new AbortController();
                this.isLoading = true;
                this.error = null;
                
                try {
                    if (this.mockData) {
                        // Mock API response
                        await new Promise(resolve => setTimeout(resolve, 300));
                        this.filteredTenants = [...this.tenants];
                        this.total = this.tenants.length;
                        this.lastPage = Math.ceil(this.total / this.perPage);
                    } else {
                        // Real API call using service layer
                        const params = {
                            q: this.searchQuery,
                            status: this.statusFilter,
                            plan: this.planFilter,
                            from: this.dateFrom,
                            to: this.dateTo,
                            sort: this.sortOrder === 'desc' ? `-${this.sortBy}` : this.sortBy,
                            page: this.page,
                            per_page: this.perPage
                        };
                        
                        const data = await window.tenantsApi.getTenants(params);
                        this.filteredTenants = data.data;
                        this.total = data.meta.total;
                        this.lastPage = data.meta.last_page;
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        this.error = error.message;
                        console.error('Failed to load tenants:', error);
                        
                        // Debug log for 422 errors
                        if (error.message.includes('422')) {
                            console.warn('[tenants] 422 params', {
                                searchQuery: this.searchQuery,
                                statusFilter: this.statusFilter,
                                planFilter: this.planFilter,
                                dateFrom: this.dateFrom,
                                dateTo: this.dateTo,
                                sortBy: this.sortBy,
                                sortOrder: this.sortOrder,
                                page: this.page,
                                perPage: this.perPage
                            });
                        }
                    }
                } finally {
                    this.isLoading = false;
                    this.abortController = null;
                }
            },
            
            // Search and filter functions
            async performServerSearch() {
                this.page = 1;
                this.updateUrl();
                await this.loadTenants();
            },
            
            async applyFilters() {
                this.page = 1;
                this.activePreset = '';
                this.updateUrl();
                await this.loadTenants();
            },
            
            async applyPreset(preset) {
                this.activePreset = preset;
                this.page = 1;
                
                // Clear existing filters first
                this.searchQuery = '';
                this.statusFilter = '';
                this.planFilter = '';
                this.dateFrom = '';
                this.dateTo = '';
                this.sortBy = 'name';
                this.sortOrder = 'asc';
                
                switch (preset) {
                    case 'active':
                        this.statusFilter = 'active';
                        this.sortBy = 'lastActiveAt';
                        this.sortOrder = 'desc';
                        break;
                    case 'disabled':
                        this.statusFilter = 'suspended';
                        break;
                    case 'new':
                        const thirtyDaysAgo = new Date();
                        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                        this.dateFrom = thirtyDaysAgo.toISOString().split('T')[0];
                        this.sortBy = 'createdAt';
                        this.sortOrder = 'desc';
                        break;
                    case 'trial':
                        this.statusFilter = 'trial';
                        const sevenDaysFromNow = new Date();
                        sevenDaysFromNow.setDate(sevenDaysFromNow.getDate() + 7);
                        this.dateTo = sevenDaysFromNow.toISOString().split('T')[0];
                        break;
                }
                
                this.updateUrl();
                await this.loadTenants();
                this.logEvent('tenants_preset_click', { preset });
            },
            
            clearFilters() {
                this.searchQuery = '';
                this.statusFilter = '';
                this.planFilter = '';
                this.dateFrom = '';
                this.dateTo = '';
                this.sortBy = 'name';
                this.sortOrder = 'asc';
                this.activePreset = '';
                this.page = 1;
                this.updateUrl();
                this.loadTenants();
            },
            
            get hasActiveFilters() {
                return this.searchQuery || this.statusFilter || this.planFilter || this.dateFrom || this.dateTo;
            },
            
            // Server-side sorting
            async setSort(column) {
                if (this.sortBy === column) {
                    this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortBy = column;
                    this.sortOrder = 'asc';
                }
                this.page = 1;
                this.updateUrl();
                await this.loadTenants();
            },
            
            // Pagination
            async changePage(newPage) {
                if (newPage >= 1 && newPage <= this.lastPage) {
                    this.page = newPage;
                    this.updateUrl();
                    await this.loadTenants();
                }
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
            
            // Per-row actions
            viewTenant(tenant) {
                window.location.href = `/admin/tenants/${tenant.id}`;
                this.logEvent('tenant_row_action', { action: 'view', tenantId: tenant.id });
            },
            
            async toggleTenantStatus(tenant) {
                const newStatus = tenant.status === 'active' ? 'suspended' : 'active';
                const action = newStatus === 'active' ? 'enable' : 'disable';
                
                try {
                    if (this.mockData) {
                        // Mock API call
                        await new Promise(resolve => setTimeout(resolve, 500));
                        tenant.status = newStatus;
                    } else {
                        // Real API call using service layer
                        const data = await window.tenantsApi[`${action}Tenant`](tenant.id);
                        tenant.status = data.data.status;
                    }
                    
                    this.logEvent('tenant_row_action', { action, tenantId: tenant.id });
                } catch (error) {
                    console.error('Failed to toggle tenant status:', error);
                    this.error = error.message;
                }
            },
            
            // Chart initialization
            initCharts() {
                this.$nextTick(() => {
                    // Destroy existing charts
                    Object.values(this.chartInstances).forEach(chart => {
                        if (chart) chart.destroy();
                    });
                    this.chartInstances = {};
                    
                    // Initialize sparkline charts
                    this.createSparkline('totalTenantsSparkline', this.kpis.totalTenants.series, '#3B82F6');
                    this.createSparkline('activeTenantsSparkline', this.kpis.activeTenants.series, '#10B981');
                    this.createSparkline('disabledTenantsSparkline', this.kpis.disabledTenants.series, '#EF4444');
                    this.createSparkline('newTenantsSparkline', this.kpis.newTenants.series, '#8B5CF6');
                    this.createSparkline('trialExpiringSparkline', this.kpis.trialExpiring.series, '#F59E0B');
                });
            },
            
            createSparkline(canvasId, data, color) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;
                
                const ctx = canvas.getContext('2d');
                this.chartInstances[canvasId] = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map((_, i) => ''),
                        datasets: [{
                            data: data,
                            borderColor: color,
                            backgroundColor: color + '20',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { enabled: false }
                        },
                        scales: {
                            x: { display: false },
                            y: { display: false }
                        },
                        interaction: {
                            intersect: false
                        }
                    }
                });
            },
            
            // Drill-down functions
            drillDownTotal() {
                window.location.href = '/admin/tenants?sort=-created_at';
                this.logEvent('kpi_drilldown', { kpi: 'total', target: 'tenants_list' });
            },
            
            drillDownActive() {
                window.location.href = '/admin/tenants?status=active&sort=-lastActiveAt';
                this.logEvent('kpi_drilldown', { kpi: 'active', target: 'tenants_list' });
            },
            
            drillDownDisabled() {
                window.location.href = '/admin/tenants?status=suspended';
                this.logEvent('kpi_drilldown', { kpi: 'disabled', target: 'tenants_list' });
            },
            
            drillDownNew() {
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                const from = thirtyDaysAgo.toISOString().split('T')[0];
                window.location.href = `/admin/tenants?from=${from}&sort=-createdAt`;
                this.logEvent('kpi_drilldown', { kpi: 'new', target: 'tenants_list' });
            },
            
            drillDownTrialExpiring() {
                const sevenDaysFromNow = new Date();
                sevenDaysFromNow.setDate(sevenDaysFromNow.getDate() + 7);
                const to = sevenDaysFromNow.toISOString().split('T')[0];
                window.location.href = `/admin/tenants?status=trial&to=${to}`;
                this.logEvent('kpi_drilldown', { kpi: 'trial', target: 'tenants_list' });
            },
            
            // Utility functions
            getAriaLabel(type, value, delta, period) {
                const deltaText = typeof delta === 'number' ? 
                    (delta > 0 ? `up ${delta}${type === 'total' || type === 'active' || type === 'new' ? '%' : ''}` : 
                     delta < 0 ? `down ${Math.abs(delta)}${type === 'total' || type === 'active' || type === 'new' ? '%' : ''}` : 'no change') : 
                    `change ${delta}`;
                return `View ${type} tenants — ${value} total, ${deltaText} in ${period}`;
            },
            
            getCurrentQuery() {
                return {
                    q: this.searchQuery,
                    status: this.statusFilter,
                    plan: this.planFilter,
                    sort: this.sortBy,
                    order: this.sortOrder
                };
            },
            
            // Analytics
            logEvent(eventName, meta = {}) {
                const event = {
                    event: eventName,
                    timestamp: new Date().toISOString(),
                    meta: {
                        view: 'tenants',
                        ...meta
                    }
                };
                console.log('Analytics Event:', event);
                // In real implementation, send to analytics service
            },
            
            filterTenants() {
                this.filteredTenants = this.tenants.filter(tenant => {
                    const matchesSearch = tenant.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                                        tenant.domain.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                                        tenant.owner.toLowerCase().includes(this.searchQuery.toLowerCase());
                    
                    const matchesStatus = this.statusFilter === 'all' || tenant.status === this.statusFilter;
                    const matchesPlan = this.planFilter === 'all' || tenant.plan === this.planFilter;
                    
                    return matchesSearch && matchesStatus && matchesPlan;
                });
                
                this.sortTenants();
            },
            
            sortTenants() {
                this.filteredTenants.sort((a, b) => {
                    let aValue = a[this.sortBy];
                    let bValue = b[this.sortBy];
                    
                    if (typeof aValue === 'string') {
                        aValue = aValue.toLowerCase();
                        bValue = bValue.toLowerCase();
                    }
                    
                    if (this.sortOrder === 'asc') {
                        return aValue > bValue ? 1 : -1;
                    } else {
                        return aValue < bValue ? 1 : -1;
                    }
                });
            },
            
            setSort(column) {
                if (this.sortBy === column) {
                    this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortBy = column;
                    this.sortOrder = 'asc';
                }
                this.sortTenants();
            },
            
            selectTenant(tenant) {
                const index = this.selectedTenants.findIndex(t => t.id === tenant.id);
                if (index > -1) {
                    this.selectedTenants.splice(index, 1);
                } else {
                    this.selectedTenants.push(tenant);
                }
            },
            
            selectAllTenants() {
                if (this.selectedTenants.length === this.filteredTenants.length) {
                    this.selectedTenants = [];
                } else {
                    this.selectedTenants = [...this.filteredTenants];
                }
            },
            
            openCreateModal() {
                this.showCreateModal = true;
                this.currentTenant = {
                    name: '',
                    domain: '',
                    owner: '',
                    ownerEmail: '',
                    plan: 'Basic'
                };
            },
            
            openEditModal(tenant) {
                this.showEditModal = true;
                this.currentTenant = { ...tenant };
            },
            
            openDeleteModal(tenant) {
                this.showDeleteModal = true;
                this.currentTenant = tenant;
            },
            
            closeModals() {
                this.showCreateModal = false;
                this.showEditModal = false;
                this.showDeleteModal = false;
                this.currentTenant = null;
            },
            
            saveTenant() {
                if (this.showCreateModal) {
                    // Create new tenant
                    const newTenant = {
                        ...this.currentTenant,
                        id: this.tenants.length + 1,
                        status: 'active',
                        users: 0,
                        createdAt: new Date().toISOString().split('T')[0],
                        lastActive: new Date().toISOString().split('T')[0]
                    };
                    this.tenants.push(newTenant);
                } else if (this.showEditModal) {
                    // Update existing tenant
                    const index = this.tenants.findIndex(t => t.id === this.currentTenant.id);
                    if (index > -1) {
                        this.tenants[index] = { ...this.currentTenant };
                    }
                }
                
                this.closeModals();
                this.filterTenants();
            },
            
            deleteTenant() {
                const index = this.tenants.findIndex(t => t.id === this.currentTenant.id);
                if (index > -1) {
                    this.tenants.splice(index, 1);
                }
                this.closeModals();
                this.filterTenants();
            },
            
            async bulkAction(action) {
                if (this.selectedTenants.length === 0) return;
                
                const count = this.selectedTenants.length;
                let confirmMessage = '';
                let successMessage = '';
                
                switch(action) {
                    case 'activate':
                        confirmMessage = `Are you sure you want to activate ${count} tenant(s)?`;
                        successMessage = `${count} tenant(s) activated successfully`;
                        break;
                    case 'suspend':
                        confirmMessage = `Are you sure you want to suspend ${count} tenant(s)?`;
                        successMessage = `${count} tenant(s) suspended successfully`;
                        break;
                    case 'delete':
                        confirmMessage = `Are you sure you want to delete ${count} tenant(s)? This action cannot be undone.`;
                        successMessage = `${count} tenant(s) deleted successfully`;
                        break;
                    case 'change-plan':
                        confirmMessage = `Are you sure you want to change plan for ${count} tenant(s)?`;
                        successMessage = `${count} tenant(s) plan changed successfully`;
                        break;
                    case 'export':
                        await this.exportSelectedTenants();
                        return;
                }
                
                if (!confirm(confirmMessage)) return;
                
                try {
                    if (this.mockData) {
                        // Mock bulk action
                        let successCount = 0;
                        let errorCount = 0;
                        
                        for (const tenant of this.selectedTenants) {
                            try {
                                await new Promise(resolve => setTimeout(resolve, 200));
                                
                                switch(action) {
                                    case 'activate':
                                        tenant.status = 'active';
                                        break;
                                    case 'suspend':
                                        tenant.status = 'suspended';
                                        break;
                                    case 'delete':
                                        const index = this.tenants.findIndex(t => t.id === tenant.id);
                                        if (index > -1) {
                                            this.tenants.splice(index, 1);
                                        }
                                        break;
                                    case 'change-plan':
                                        tenant.plan = 'Professional'; // Mock plan change
                                        break;
                                }
                                successCount++;
                            } catch (error) {
                                console.error(`Failed to ${action} tenant ${tenant.id}:`, error);
                                errorCount++;
                            }
                        }
                        
                        // Show results
                        if (errorCount === 0) {
                            alert(successMessage);
                        } else {
                            alert(`${successCount} tenant(s) processed successfully, ${errorCount} failed`);
                        }
                    } else {
                        // Real API call using service layer
                        const ids = this.selectedTenants.map(t => t.id);
                        const result = await window.tenantsApi.bulkAction(action, ids, 'Professional');
                        
                        // Show results
                        const successCount = result.ok.length;
                        const errorCount = result.failed.length;
                        
                        if (errorCount === 0) {
                            alert(successMessage);
                        } else {
                            alert(`${successCount} tenant(s) processed successfully, ${errorCount} failed`);
                        }
                    }
                    
                    this.selectedTenants = [];
                    await this.loadTenants();
                    this.logEvent('tenants_bulk_action', { action, count, successCount: this.mockData ? 0 : result.ok.length, errorCount: this.mockData ? 0 : result.failed.length });
                    
                } catch (error) {
                    console.error('Bulk action failed:', error);
                    alert('Bulk action failed. Please try again.');
                }
            },
            
            async exportSelectedTenants() {
                if (this.selectedTenants.length === 0) return;
                
                try {
                    const tenantIds = this.selectedTenants.map(t => t.id);
                    
                    if (this.mockData) {
                        // Mock export
                        await new Promise(resolve => setTimeout(resolve, 1000));
                        alert(`Exporting ${tenantIds.length} selected tenants...`);
                    } else {
                        // Real API call using service layer
                        await window.tenantsApi.exportSelectedTenants(tenantIds);
                    }
                    
                    this.logEvent('tenants_export', { format: 'csv', filtered: false, count: tenantIds.length });
                    
                } catch (error) {
                    console.error('Export failed:', error);
                    alert(`Export failed: ${error.message}`);
                }
            },
            
            async exportTenants() {
                try {
                    const params = {
                        q: this.searchQuery,
                        status: this.statusFilter,
                        plan: this.planFilter,
                        from: this.dateFrom,
                        to: this.dateTo,
                        sort: this.sortOrder === 'desc' ? `-${this.sortBy}` : this.sortBy
                    };
                    
                    if (this.mockData) {
                        // Mock export
                        await new Promise(resolve => setTimeout(resolve, 1000));
                        alert('Exporting all tenants with current filters...');
                    } else {
                        // Real API call using service layer
                        await window.tenantsApi.exportTenants(params);
                    }
                    
                    this.logEvent('tenants_export', { format: 'csv', filtered: this.hasActiveFilters, count: this.total });
                    
                } catch (error) {
                    console.error('Export failed:', error);
                    alert(`Export failed: ${error.message}`);
                }
            }
        }
    }
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/tenants/index.blade.php ENDPATH**/ ?>