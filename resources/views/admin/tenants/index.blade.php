{{-- Admin Tenant Management Page --}}
@extends('layouts.universal-frame')

@section('title', 'Tenant Management - Admin Dashboard')

@section('content')
<div class="tenant-management">
    <!-- Page Header -->
    <div class="page-header bg-white border-b border-gray-200 px-6 py-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tenant Management</h1>
                <p class="text-gray-600 mt-1">Manage system tenants and organizations</p>
            </div>
            <div class="flex items-center space-x-3">
                <button 
                    @click="exportTenants()"
                    class="btn-secondary flex items-center gap-2"
                >
                    <i class="fas fa-download"></i>
                    <span>Export</span>
                </button>
                <a href="{{ route('admin.tenants.create') }}" class="btn-primary flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    <span>Add Tenant</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Tenant Statistics -->
    <div class="stats-section bg-white border-b border-gray-200 px-6 py-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="stat-card bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-600">Total Tenants</p>
                        <p class="text-2xl font-bold text-blue-900" x-text="stats.totalTenants">89</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-building text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-600">Active Tenants</p>
                        <p class="text-2xl font-bold text-green-900" x-text="stats.activeTenants">82</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-yellow-600">Trial Tenants</p>
                        <p class="text-2xl font-bold text-yellow-900" x-text="stats.trialTenants">12</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-red-600">Suspended</p>
                        <p class="text-2xl font-bold text-red-900" x-text="stats.suspendedTenants">5</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-ban text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="filters-section bg-white border-b border-gray-200 px-6 py-4">
        <div class="flex flex-col md:flex-row gap-4">
            <!-- Search -->
            <div class="flex-1">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input 
                        type="text" 
                        x-model="searchQuery"
                        @input.debounce.300ms="searchTenants()"
                        placeholder="Search tenants by name, domain, or contact..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
            </div>
            
            <!-- Filters -->
            <div class="flex gap-3">
                <select 
                    x-model="statusFilter"
                    @change="filterTenants()"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="trial">Trial</option>
                    <option value="suspended">Suspended</option>
                    <option value="inactive">Inactive</option>
                </select>
                
                <select 
                    x-model="planFilter"
                    @change="filterTenants()"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Plans</option>
                    <option value="basic">Basic</option>
                    <option value="professional">Professional</option>
                    <option value="enterprise">Enterprise</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tenants Grid -->
    <div class="main-content p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <template x-for="tenant in filteredTenants" :key="tenant.id">
                <div class="tenant-card bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                    <!-- Card Header -->
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gray-300 rounded-full flex items-center justify-center">
                                    <i class="fas fa-building text-gray-600 text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900" x-text="tenant.name"></h3>
                                    <p class="text-sm text-gray-500" x-text="tenant.domain"></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span 
                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                    :class="getStatusColor(tenant.status)"
                                    x-text="tenant.status"
                                ></span>
                                <div class="relative">
                                    <button 
                                        @click="toggleTenantMenu(tenant.id)"
                                        class="p-1 text-gray-400 hover:text-gray-600"
                                    >
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div 
                                        x-show="activeTenantMenu === tenant.id"
                                        @click.away="activeTenantMenu = null"
                                        class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-md shadow-lg z-10"
                                    >
                                        <div class="py-1">
                                            <button @click="viewTenant(tenant.id)" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-eye mr-2"></i>View Details
                                            </button>
                                            <button @click="editTenant(tenant.id)" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-edit mr-2"></i>Edit Tenant
                                            </button>
                                            <button @click="manageUsers(tenant.id)" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-users mr-2"></i>Manage Users
                                            </button>
                                            <button @click="suspendTenant(tenant.id)" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i :class="tenant.status === 'suspended' ? 'fas fa-unlock' : 'fas fa-lock'" class="mr-2"></i>
                                                <span x-text="tenant.status === 'suspended' ? 'Activate' : 'Suspend'"></span>
                                            </button>
                                            <div class="border-t border-gray-200"></div>
                                            <button @click="deleteTenant(tenant.id)" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                <i class="fas fa-trash mr-2"></i>Delete Tenant
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="px-6 py-4">
                        <div class="space-y-3">
                            <!-- Plan -->
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Plan</span>
                                <span 
                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                    :class="getPlanColor(tenant.plan)"
                                    x-text="tenant.plan"
                                ></span>
                            </div>

                            <!-- Users Count -->
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Users</span>
                                <span class="text-sm font-medium text-gray-900" x-text="tenant.users_count"></span>
                            </div>

                            <!-- Projects Count -->
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Projects</span>
                                <span class="text-sm font-medium text-gray-900" x-text="tenant.projects_count"></span>
                            </div>

                            <!-- Storage Usage -->
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Storage</span>
                                <div class="flex items-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-blue-600 h-2 rounded-full" :style="`width: ${tenant.storage_usage}%`"></div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900" x-text="tenant.storage_usage + '%'"></span>
                                </div>
                            </div>

                            <!-- Contact -->
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Contact</span>
                                <span class="text-sm font-medium text-gray-900" x-text="tenant.contact_email"></span>
                            </div>

                            <!-- Created Date -->
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Created</span>
                                <span class="text-sm font-medium text-gray-900" x-text="formatDate(tenant.created_at)"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-xs text-gray-500">
                                Last activity: <span x-text="formatDate(tenant.last_activity)"></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button 
                                    @click="viewTenant(tenant.id)"
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                >
                                    View Details
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Empty State -->
        <div x-show="filteredTenants.length === 0" class="text-center py-12">
            <i class="fas fa-building text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No tenants found</h3>
            <p class="text-gray-500 mb-4">Try adjusting your search or filter criteria.</p>
            <a href="{{ route('admin.tenants.create') }}" class="btn-primary">
                <i class="fas fa-plus mr-2"></i>Add First Tenant
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('tenantManagement', () => ({
        searchQuery: '',
        statusFilter: '',
        planFilter: '',
        activeTenantMenu: null,
        
        stats: {
            totalTenants: 89,
            activeTenants: 82,
            trialTenants: 12,
            suspendedTenants: 5
        },
        
        tenants: [
            {
                id: 1,
                name: 'Acme Corporation',
                domain: 'acme.com',
                status: 'active',
                plan: 'enterprise',
                users_count: 45,
                projects_count: 12,
                storage_usage: 67,
                contact_email: 'admin@acme.com',
                created_at: '2025-01-15T08:00:00Z',
                last_activity: '2025-09-24T10:30:00Z'
            },
            {
                id: 2,
                name: 'Tech Solutions Inc',
                domain: 'techsolutions.com',
                status: 'active',
                plan: 'professional',
                users_count: 23,
                projects_count: 8,
                storage_usage: 45,
                contact_email: 'contact@techsolutions.com',
                created_at: '2025-02-20T10:30:00Z',
                last_activity: '2025-09-24T09:15:00Z'
            },
            {
                id: 3,
                name: 'Design Studio',
                domain: 'designstudio.com',
                status: 'trial',
                plan: 'basic',
                users_count: 8,
                projects_count: 3,
                storage_usage: 23,
                contact_email: 'hello@designstudio.com',
                created_at: '2025-03-10T12:00:00Z',
                last_activity: '2025-09-24T08:30:00Z'
            },
            {
                id: 4,
                name: 'Marketing Agency',
                domain: 'marketingagency.com',
                status: 'suspended',
                plan: 'professional',
                users_count: 15,
                projects_count: 6,
                storage_usage: 89,
                contact_email: 'info@marketingagency.com',
                created_at: '2025-04-05T15:20:00Z',
                last_activity: '2025-09-20T14:45:00Z'
            },
            {
                id: 5,
                name: 'StartupXYZ',
                domain: 'startupxyz.com',
                status: 'active',
                plan: 'basic',
                users_count: 5,
                projects_count: 2,
                storage_usage: 12,
                contact_email: 'team@startupxyz.com',
                created_at: '2025-05-12T09:45:00Z',
                last_activity: '2025-09-24T07:20:00Z'
            }
        ],

        get filteredTenants() {
            let filtered = this.tenants;
            
            // Search filter
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(tenant => 
                    tenant.name.toLowerCase().includes(query) ||
                    tenant.domain.toLowerCase().includes(query) ||
                    tenant.contact_email.toLowerCase().includes(query)
                );
            }
            
            // Status filter
            if (this.statusFilter) {
                filtered = filtered.filter(tenant => tenant.status === this.statusFilter);
            }
            
            // Plan filter
            if (this.planFilter) {
                filtered = filtered.filter(tenant => tenant.plan === this.planFilter);
            }
            
            return filtered;
        },

        searchTenants() {
            // Search logic is handled by the computed property
        },

        filterTenants() {
            // Filter logic is handled by the computed property
        },

        toggleTenantMenu(tenantId) {
            this.activeTenantMenu = this.activeTenantMenu === tenantId ? null : tenantId;
        },

        getStatusColor(status) {
            const colors = {
                'active': 'bg-green-100 text-green-800',
                'trial': 'bg-yellow-100 text-yellow-800',
                'suspended': 'bg-red-100 text-red-800',
                'inactive': 'bg-gray-100 text-gray-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        },

        getPlanColor(plan) {
            const colors = {
                'basic': 'bg-gray-100 text-gray-800',
                'professional': 'bg-blue-100 text-blue-800',
                'enterprise': 'bg-purple-100 text-purple-800'
            };
            return colors[plan] || 'bg-gray-100 text-gray-800';
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        },

        viewTenant(tenantId) {
            console.log('View tenant:', tenantId);
            // Implement view tenant logic
        },

        editTenant(tenantId) {
            console.log('Edit tenant:', tenantId);
            // Implement edit tenant logic
        },

        manageUsers(tenantId) {
            console.log('Manage users for tenant:', tenantId);
            // Implement manage users logic
        },

        suspendTenant(tenantId) {
            const tenant = this.tenants.find(t => t.id === tenantId);
            if (tenant) {
                tenant.status = tenant.status === 'suspended' ? 'active' : 'suspended';
            }
        },

        deleteTenant(tenantId) {
            if (confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) {
                this.tenants = this.tenants.filter(t => t.id !== tenantId);
            }
        },

        exportTenants() {
            console.log('Exporting tenants...');
            // Implement export logic
        }
    }));
});
</script>

<style>
.tenant-management {
    min-height: 100vh;
    background-color: #f9fafb;
}

.tenant-card {
    transition: transform 0.2s ease-in-out;
}

.tenant-card:hover {
    transform: translateY(-2px);
}

.btn-primary {
    background: #2563eb;
    color: white;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 500;
    transition: background-color 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 500;
    transition: background-color 0.2s;
    border: 1px solid #d1d5db;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.stat-card {
    transition: transform 0.2s ease-in-out;
}

.stat-card:hover {
    transform: translateY(-2px);
}

/* Responsive design */
@media (max-width: 768px) {
    .tenant-management .grid {
        grid-template-columns: 1fr;
    }
    
    .tenant-management .stats-section .grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .tenant-management .stats-section .grid {
        grid-template-columns: 1fr;
    }
}
</style>
@endsection
