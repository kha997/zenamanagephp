<!-- Admin Tenants Content -->
<div x-data="adminTenants()" x-init="init()" class="space-y-6">
    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-2 text-gray-600">Loading tenants...</span>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="ml-3">
                <p class="font-bold">Error loading tenants</p>
                <p x-text="error"></p>
                <button @click="init()" class="mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                    Retry
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div x-show="!loading && !error" class="space-y-6">
        
        <!-- Header Section -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Tenant Management</h1>
                    <p class="text-gray-600 mt-1">Manage all tenants and their configurations</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button @click="refreshData()" 
                            :disabled="refreshing"
                            class="flex items-center space-x-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 disabled:opacity-50">
                        <i class="fas fa-sync-alt" :class="{'animate-spin': refreshing}"></i>
                        <span>Refresh</span>
                    </button>
                    <button @click="showAddTenantModal = true" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Tenant
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-building text-blue-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-600">Total Tenants</p>
                            <p class="text-2xl font-bold text-blue-900" x-text="stats.totalTenants || 0"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-600">Active Tenants</p>
                            <p class="text-2xl font-bold text-green-900" x-text="stats.activeTenants || 0"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-600">At Risk</p>
                            <p class="text-2xl font-bold text-yellow-900" x-text="stats.atRiskTenants || 0"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-red-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-pause-circle text-red-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-600">Suspended</p>
                            <p class="text-2xl font-bold text-red-900" x-text="stats.suspendedTenants || 0"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <input type="text" 
                               x-model="searchQuery" 
                               @input="filterTenants()"
                               placeholder="Search tenants..." 
                               class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                    <select x-model="statusFilter" @change="filterTenants()" 
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                        <option value="at_risk">At Risk</option>
                    </select>
                    <select x-model="planFilter" @change="filterTenants()" 
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Plans</option>
                        <option value="basic">Basic</option>
                        <option value="professional">Professional</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500" x-text="`Showing ${filteredTenants.length} of ${tenants.length} tenants`"></span>
                </div>
            </div>
        </div>

        <!-- Tenants Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <template x-for="tenant in filteredTenants" :key="tenant.id">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow"
                     :class="getTenantCardClass(tenant.status)">
                    <!-- Tenant Header -->
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 rounded-lg flex items-center justify-center"
                                     :class="getTenantIconBg(tenant.status)">
                                    <i class="fas fa-building text-xl"
                                       :class="getTenantIconColor(tenant.status)"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900" x-text="tenant.name"></h3>
                                    <p class="text-sm text-gray-500" x-text="tenant.domain"></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 text-xs font-medium rounded-full"
                                      :class="getStatusBadgeColor(tenant.status)"
                                      x-text="tenant.status"></span>
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div x-show="open" @click.away="open = false" 
                                         class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                        <div class="py-1">
                                            <button @click="viewTenant(tenant)" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-eye mr-3 text-gray-400"></i>
                                                View Details
                                            </button>
                                            <button @click="editTenant(tenant)" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-edit mr-3 text-gray-400"></i>
                                                Edit Tenant
                                            </button>
                                            <button @click="manageUsers(tenant)" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-users mr-3 text-gray-400"></i>
                                                Manage Users
                                            </button>
                                            <div class="border-t border-gray-200 my-1"></div>
                                            <button @click="suspendTenant(tenant)" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-ban mr-3 text-gray-400"></i>
                                                Suspend
                                            </button>
                                            <button @click="deleteTenant(tenant)" class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                <i class="fas fa-trash mr-3 text-red-400"></i>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tenant Info -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Plan:</span>
                                <span class="font-medium" x-text="tenant.plan"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Users:</span>
                                <span class="font-medium" x-text="`${tenant.userCount}/${tenant.userLimit}`"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Storage:</span>
                                <span class="font-medium" x-text="`${tenant.storageUsed}/${tenant.storageLimit}`"></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Created:</span>
                                <span class="font-medium" x-text="tenant.createdAt"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bars -->
                    <div class="p-6 pt-0">
                        <!-- User Usage -->
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-600">User Usage</span>
                                <span class="text-xs text-gray-500" x-text="`${Math.round(tenant.userCount / tenant.userLimit * 100)}%`"></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" 
                                     :style="`width: ${Math.min(tenant.userCount / tenant.userLimit * 100, 100)}%`"></div>
                            </div>
                        </div>

                        <!-- Storage Usage -->
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-600">Storage Usage</span>
                                <span class="text-xs text-gray-500" x-text="`${Math.round(tenant.storageUsed / tenant.storageLimit * 100)}%`"></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full transition-all duration-500" 
                                     :style="`width: ${Math.min(tenant.storageUsed / tenant.storageLimit * 100, 100)}%`"></div>
                            </div>
                        </div>

                        <!-- Risk Indicators -->
                        <div x-show="tenant.status === 'at_risk'" class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                                <span class="text-sm text-yellow-800" x-text="tenant.riskReason"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Empty State -->
        <div x-show="filteredTenants.length === 0" class="text-center py-12">
            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-building text-3xl text-gray-400"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No tenants found</h3>
            <p class="text-gray-500 mb-4">No tenants match your current filters</p>
            <button @click="clearFilters()" class="text-blue-600 hover:text-blue-800 font-medium">
                Clear filters
            </button>
        </div>
    </div>

    <!-- Add Tenant Modal -->
    <div x-show="showAddTenantModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showAddTenantModal = false"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form @submit.prevent="addTenant()">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-building text-blue-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Add New Tenant</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                                        <input type="text" x-model="newTenant.name" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Domain</label>
                                        <input type="text" x-model="newTenant.domain" required
                                               placeholder="company.zena.com"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                                        <select x-model="newTenant.plan" required
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Plan</option>
                                            <option value="basic">Basic</option>
                                            <option value="professional">Professional</option>
                                            <option value="enterprise">Enterprise</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                                        <input type="email" x-model="newTenant.contactEmail" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" 
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Add Tenant
                        </button>
                        <button type="button" @click="showAddTenantModal = false"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function adminTenants() {
    return {
        loading: true,
        error: null,
        refreshing: false,
        
        // Data
        tenants: [],
        filteredTenants: [],
        stats: {
            totalTenants: 0,
            activeTenants: 0,
            atRiskTenants: 0,
            suspendedTenants: 0
        },
        
        // Filters
        searchQuery: '',
        statusFilter: '',
        planFilter: '',
        
        // Modal
        showAddTenantModal: false,
        newTenant: {
            name: '',
            domain: '',
            plan: '',
            contactEmail: ''
        },

        async init() {
            await this.loadTenants();
            this.calculateStats();
            this.filterTenants();
        },

        async loadTenants() {
            try {
                this.loading = true;
                this.error = null;
                
                // Mock data for demonstration
                this.tenants = [
                    {
                        id: 1,
                        name: 'Acme Corporation',
                        domain: 'acme.zena.com',
                        status: 'active',
                        plan: 'enterprise',
                        userCount: 45,
                        userLimit: 100,
                        storageUsed: '2.4 GB',
                        storageLimit: '10 GB',
                        createdAt: 'Jan 15, 2024',
                        contactEmail: 'admin@acme.com',
                        riskReason: ''
                    },
                    {
                        id: 2,
                        name: 'TechStart Inc',
                        domain: 'techstart.zena.com',
                        status: 'active',
                        plan: 'professional',
                        userCount: 12,
                        userLimit: 25,
                        storageUsed: '1.2 GB',
                        storageLimit: '5 GB',
                        createdAt: 'Feb 20, 2024',
                        contactEmail: 'admin@techstart.com',
                        riskReason: ''
                    },
                    {
                        id: 3,
                        name: 'Global Solutions',
                        domain: 'global.zena.com',
                        status: 'at_risk',
                        plan: 'basic',
                        userCount: 8,
                        userLimit: 10,
                        storageUsed: '4.8 GB',
                        storageLimit: '2 GB',
                        createdAt: 'Mar 10, 2024',
                        contactEmail: 'admin@global.com',
                        riskReason: 'Storage limit exceeded'
                    },
                    {
                        id: 4,
                        name: 'OldCorp Ltd',
                        domain: 'oldcorp.zena.com',
                        status: 'suspended',
                        plan: 'professional',
                        userCount: 0,
                        userLimit: 25,
                        storageUsed: '0 GB',
                        storageLimit: '5 GB',
                        createdAt: 'Dec 5, 2023',
                        contactEmail: 'admin@oldcorp.com',
                        riskReason: ''
                    },
                    {
                        id: 5,
                        name: 'New Ventures',
                        domain: 'newventures.zena.com',
                        status: 'active',
                        plan: 'basic',
                        userCount: 3,
                        userLimit: 10,
                        storageUsed: '0.5 GB',
                        storageLimit: '2 GB',
                        createdAt: 'Apr 1, 2024',
                        contactEmail: 'admin@newventures.com',
                        riskReason: ''
                    }
                ];
                
                this.loading = false;
                
            } catch (error) {
                console.error('Error loading tenants:', error);
                this.error = error.message;
                this.loading = false;
            }
        },

        calculateStats() {
            this.stats = {
                totalTenants: this.tenants.length,
                activeTenants: this.tenants.filter(t => t.status === 'active').length,
                atRiskTenants: this.tenants.filter(t => t.status === 'at_risk').length,
                suspendedTenants: this.tenants.filter(t => t.status === 'suspended').length
            };
        },

        filterTenants() {
            let filtered = this.tenants;
            
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(tenant => 
                    tenant.name.toLowerCase().includes(query) ||
                    tenant.domain.toLowerCase().includes(query) ||
                    tenant.contactEmail.toLowerCase().includes(query)
                );
            }
            
            if (this.statusFilter) {
                filtered = filtered.filter(tenant => tenant.status === this.statusFilter);
            }
            
            if (this.planFilter) {
                filtered = filtered.filter(tenant => tenant.plan === this.planFilter);
            }
            
            this.filteredTenants = filtered;
        },

        getTenantCardClass(status) {
            const classes = {
                'active': 'border-l-4 border-green-500',
                'inactive': 'border-l-4 border-gray-400',
                'suspended': 'border-l-4 border-red-500',
                'at_risk': 'border-l-4 border-yellow-500'
            };
            return classes[status] || 'border-l-4 border-gray-400';
        },

        getTenantIconBg(status) {
            const classes = {
                'active': 'bg-green-100',
                'inactive': 'bg-gray-100',
                'suspended': 'bg-red-100',
                'at_risk': 'bg-yellow-100'
            };
            return classes[status] || 'bg-gray-100';
        },

        getTenantIconColor(status) {
            const classes = {
                'active': 'text-green-600',
                'inactive': 'text-gray-600',
                'suspended': 'text-red-600',
                'at_risk': 'text-yellow-600'
            };
            return classes[status] || 'text-gray-600';
        },

        getStatusBadgeColor(status) {
            const colors = {
                'active': 'bg-green-100 text-green-800',
                'inactive': 'bg-gray-100 text-gray-800',
                'suspended': 'bg-red-100 text-red-800',
                'at_risk': 'bg-yellow-100 text-yellow-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        },

        viewTenant(tenant) {
            console.log('View tenant:', tenant);
            // Implement view functionality
        },

        editTenant(tenant) {
            console.log('Edit tenant:', tenant);
            // Implement edit functionality
        },

        manageUsers(tenant) {
            console.log('Manage users for tenant:', tenant);
            // Implement user management functionality
        },

        suspendTenant(tenant) {
            if (confirm(`Are you sure you want to suspend ${tenant.name}?`)) {
                console.log('Suspend tenant:', tenant);
                // Implement suspend functionality
            }
        },

        deleteTenant(tenant) {
            if (confirm(`Are you sure you want to delete ${tenant.name}? This action cannot be undone.`)) {
                console.log('Delete tenant:', tenant);
                // Implement delete functionality
            }
        },

        async addTenant() {
            try {
                console.log('Adding tenant:', this.newTenant);
                // Implement add tenant functionality
                
                // Reset form
                this.newTenant = { name: '', domain: '', plan: '', contactEmail: '' };
                this.showAddTenantModal = false;
                
                // Refresh data
                await this.loadTenants();
                this.calculateStats();
                this.filterTenants();
                
            } catch (error) {
                console.error('Error adding tenant:', error);
            }
        },

        refreshData() {
            this.refreshing = true;
            setTimeout(() => {
                this.init();
                this.refreshing = false;
            }, 1000);
        },

        clearFilters() {
            this.searchQuery = '';
            this.statusFilter = '';
            this.planFilter = '';
            this.filterTenants();
        }
    }
}
</script>