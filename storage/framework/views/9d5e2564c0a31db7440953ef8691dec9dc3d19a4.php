<?php $__env->startSection('title', 'Tenant Management'); ?>

<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-gray-50">
    <!-- Header with Logo -->
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <?php echo $__env->make('components.zena-logo', ['subtitle' => 'Tenant Management'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                
                <!-- Header Actions -->
                <div class="flex items-center space-x-4">
                    <a href="/admin" class="zena-btn zena-btn-outline zena-btn-sm">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Admin
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div x-data="tenantsDashboard()" x-init="init()">
            <!-- Tenants Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="dashboard-card metric-card blue p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Total Tenants</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.totalTenants || 0"></p>
                            <p class="text-white/80 text-sm">
                                <span x-text="stats?.activeTenants || 0"></span> active
                            </p>
                        </div>
                        <i class="fas fa-building text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card green p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Monthly Revenue</p>
                            <p class="text-3xl font-bold text-white" x-text="formatCurrency(stats?.monthlyRevenue || 0)"></p>
                            <p class="text-white/80 text-sm">
                                <span x-text="stats?.growthRate || 0"></span>% growth
                            </p>
                        </div>
                        <i class="fas fa-dollar-sign text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card orange p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Storage Used</p>
                            <p class="text-3xl font-bold text-white" x-text="formatBytes(stats?.storageUsed || 0)"></p>
                            <p class="text-white/80 text-sm">
                                of <span x-text="formatBytes(stats?.totalStorage || 0)"></span>
                            </p>
                        </div>
                        <i class="fas fa-hdd text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card purple p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">New This Month</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.newTenants || 0"></p>
                            <p class="text-white/80 text-sm">
                                New signups
                            </p>
                        </div>
                        <i class="fas fa-plus-circle text-4xl text-white/60"></i>
                    </div>
                </div>
            </div>

            <!-- Tenants Overview Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Recent Tenants -->
                <div class="dashboard-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Tenants</h3>
                        <div class="flex items-center gap-2">
                            <button class="zena-btn zena-btn-primary zena-btn-sm" @click="createTenant()">
                                <i class="fas fa-plus mr-2"></i>
                                Add Tenant
                            </button>
                            <a href="/admin" class="zena-btn zena-btn-outline zena-btn-sm">
                                <i class="fas fa-cog mr-2"></i>
                                Admin Panel
                            </a>
                            <div class="relative group">
                                <button class="zena-btn zena-btn-outline zena-btn-sm">
                                    <i class="fas fa-download mr-2"></i>
                                    Export
                                    <i class="fas fa-chevron-down ml-2 text-xs"></i>
                                </button>
                                <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-10">
                                    <div class="py-1">
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click.prevent="exportTenants('excel')">
                                            <i class="fas fa-file-excel mr-2 text-green-600"></i>
                                            Excel (.xlsx)
                                        </a>
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click.prevent="exportTenants('pdf')">
                                            <i class="fas fa-file-pdf mr-2 text-red-600"></i>
                                            PDF (.pdf)
                                        </a>
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click.prevent="exportTenants('csv')">
                                            <i class="fas fa-file-csv mr-2 text-blue-600"></i>
                                            CSV (.csv)
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <template x-for="tenant in recentTenants.slice(0, 4)" :key="tenant.id">
                            <li class="py-3 flex items-center justify-between hover:bg-gray-50 rounded-lg px-2 -mx-2 transition-colors">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="getTenantAvatarColor(tenant.plan)">
                                        <span class="text-white text-sm font-medium" x-text="tenant.initials"></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900" x-text="tenant.name"></p>
                                        <p class="text-sm text-gray-600">
                                            <span x-text="tenant.email"></span> • <span x-text="tenant.plan"></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="zena-badge" :class="{'zena-badge-success': tenant.status === 'active', 'zena-badge-warning': tenant.status === 'trial', 'zena-badge-danger': tenant.status === 'suspended'}" x-text="tenant.status"></span>
                                    <p class="text-sm text-gray-600" x-text="tenant.createdAt"></p>
                                </div>
                            </li>
                        </template>
                        <template x-if="recentTenants.length === 0">
                            <li class="py-3 text-center text-gray-500">No recent tenants.</li>
                        </template>
                    </ul>
                </div>

                <!-- Tenant Analytics -->
                <div class="dashboard-card p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Tenant Analytics</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Plan Distribution</span>
                            <span class="text-lg font-semibold text-gray-900">Premium 40%, Standard 60%</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Active Rate</span>
                            <span class="text-lg font-semibold text-gray-900">92%</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Average Revenue</span>
                            <span class="text-lg font-semibold text-gray-900">$3,750/month</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: 92%"></div>
                        </div>
                        <p class="text-xs text-gray-500">92% of tenants are active and paying</p>
                    </div>
                </div>
            </div>

            <!-- All Tenants Table -->
            <div class="dashboard-card p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">All Tenants</h3>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <input type="text" placeholder="Search tenants..." class="zena-input zena-input-sm" x-model="searchQuery">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <select x-model="planFilter" class="zena-select zena-select-sm">
                            <option value="">All Plans</option>
                            <option value="premium">Premium</option>
                            <option value="standard">Standard</option>
                            <option value="basic">Basic</option>
                        </select>
                        <select x-model="statusFilter" class="zena-select zena-select-sm">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="trial">Trial</option>
                            <option value="suspended">Suspended</option>
                        </select>
                        <button @click="resetFilters()" class="zena-btn zena-btn-outline zena-btn-sm">
                            <i class="fas fa-redo mr-2"></i>Reset
                        </button>
                    </div>
                </div>

                <!-- Tenants Table -->
                <div class="overflow-x-auto">
                    <table class="zena-table w-full">
                        <thead>
                            <tr>
                                <th class="text-left">Tenant</th>
                                <th class="text-left">Plan</th>
                                <th class="text-left">Status</th>
                                <th class="text-left">Created</th>
                                <th class="text-left">Revenue</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="tenant in filteredTenants" :key="tenant.id">
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="font-medium text-gray-900">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="getTenantAvatarColor(tenant.plan)">
                                                <span class="text-white text-sm font-medium" x-text="tenant.initials"></span>
                                            </div>
                                            <div>
                                                <p class="font-medium" x-text="tenant.name"></p>
                                                <p class="text-sm text-gray-600" x-text="tenant.email"></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="zena-badge zena-badge-sm" :class="getPlanColor(tenant.plan)" x-text="tenant.plan"></span>
                                    </td>
                                    <td>
                                        <span class="zena-badge" :class="{'zena-badge-success': tenant.status === 'active', 'zena-badge-warning': tenant.status === 'trial', 'zena-badge-danger': tenant.status === 'suspended'}" x-text="tenant.status"></span>
                                    </td>
                                    <td x-text="tenant.createdAt"></td>
                                    <td x-text="formatCurrency(tenant.revenue)"></td>
                                    <td class="text-right">
                                        <button @click="viewTenant(tenant.id)" class="zena-btn zena-btn-outline zena-btn-sm"><i class="fas fa-eye"></i></button>
                                        <button @click="editTenant(tenant.id)" class="zena-btn zena-btn-outline zena-btn-sm"><i class="fas fa-edit"></i></button>
                                        <button @click="suspendTenant(tenant.id)" class="zena-btn zena-btn-outline zena-btn-sm zena-btn-warning"><i class="fas fa-pause"></i></button>
                                        <button @click="deleteTenant(tenant.id)" class="zena-btn zena-btn-outline zena-btn-sm zena-btn-danger"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="filteredTenants.length === 0">
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-gray-500">No tenants found matching your criteria.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6 flex items-center justify-between">
                    <div class="flex items-center text-sm text-gray-700">
                        <span>Showing </span>
                        <select class="mx-2 border border-gray-300 rounded-md px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                        </select>
                        <span> of <span class="font-medium" x-text="mockTenants.length"></span> results</span>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <button class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-chevron-left mr-1"></i> Previous
                        </button>
                        <div class="flex items-center space-x-1">
                            <button class="px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-md hover:bg-blue-700">1</button>
                            <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">2</button>
                            <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">3</button>
                        </div>
                        <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">
                            Next <i class="fas fa-chevron-right ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function tenantsDashboard() {
        return {
            mockTenants: [
                {
                    id: '1',
                    name: 'Acme Corporation',
                    email: 'admin@acme.com',
                    plan: 'premium',
                    status: 'active',
                    createdAt: 'Jan 15, 2024',
                    revenue: 5000,
                    initials: 'AC'
                },
                {
                    id: '2',
                    name: 'TechCorp Solutions',
                    email: 'contact@techcorp.com',
                    plan: 'standard',
                    status: 'active',
                    createdAt: 'Feb 20, 2024',
                    revenue: 2500,
                    initials: 'TC'
                },
                {
                    id: '3',
                    name: 'StartupXYZ',
                    email: 'hello@startupxyz.com',
                    plan: 'basic',
                    status: 'trial',
                    createdAt: 'Mar 10, 2024',
                    revenue: 0,
                    initials: 'SX'
                },
                {
                    id: '4',
                    name: 'Global Enterprises',
                    email: 'info@global.com',
                    plan: 'premium',
                    status: 'active',
                    createdAt: 'Jan 5, 2024',
                    revenue: 7500,
                    initials: 'GE'
                },
                {
                    id: '5',
                    name: 'Small Business Inc',
                    email: 'admin@sbi.com',
                    plan: 'standard',
                    status: 'suspended',
                    createdAt: 'Dec 20, 2023',
                    revenue: 0,
                    initials: 'SB'
                }
            ],
            searchQuery: '',
            planFilter: '',
            statusFilter: '',
            stats: {
                totalTenants: 0,
                activeTenants: 0,
                monthlyRevenue: 0,
                growthRate: 0,
                storageUsed: 0,
                totalStorage: 0,
                newTenants: 0,
            },
            recentTenants: [],

            init() {
                this.calculateStats();
                this.recentTenants = this.mockTenants.slice(0, 4);
                this.$watch('searchQuery', () => this.updateFilteredTenants());
                this.$watch('planFilter', () => this.updateFilteredTenants());
                this.$watch('statusFilter', () => this.updateFilteredTenants());
            },

            calculateStats() {
                this.stats.totalTenants = this.mockTenants.length;
                this.stats.activeTenants = this.mockTenants.filter(tenant => tenant.status === 'active').length;
                this.stats.monthlyRevenue = this.mockTenants.reduce((sum, tenant) => sum + tenant.revenue, 0);
                this.stats.growthRate = 12;
                this.stats.storageUsed = 2.3 * 1024 * 1024 * 1024; // 2.3TB in bytes
                this.stats.totalStorage = 5 * 1024 * 1024 * 1024; // 5TB in bytes
                this.stats.newTenants = 3;
            },

            get filteredTenants() {
                return this.mockTenants.filter(tenant => {
                    const searchMatch = tenant.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                                       tenant.email.toLowerCase().includes(this.searchQuery.toLowerCase());
                    const planMatch = this.planFilter === '' || tenant.plan === this.planFilter;
                    const statusMatch = this.statusFilter === '' || tenant.status === this.statusFilter;
                    return searchMatch && planMatch && statusMatch;
                });
            },

            updateFilteredTenants() {
                // This function is primarily for reactivity
            },

            getTenantAvatarColor(plan) {
                switch (plan) {
                    case 'premium': return 'bg-purple-500';
                    case 'standard': return 'bg-blue-500';
                    case 'basic': return 'bg-green-500';
                    default: return 'bg-gray-500';
                }
            },

            getPlanColor(plan) {
                switch (plan) {
                    case 'premium': return 'zena-badge-purple';
                    case 'standard': return 'zena-badge-primary';
                    case 'basic': return 'zena-badge-success';
                    default: return 'zena-badge-neutral';
                }
            },

            formatCurrency(amount) {
                return new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: 'USD',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(amount);
            },

            formatBytes(bytes, decimals = 2) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const dm = decimals < 0 ? 0 : decimals;
                const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
            },

            createTenant() {
                alert('Create tenant functionality will be implemented here!');
            },

            viewTenant(tenantId) {
                alert(`Viewing tenant: ${tenantId}`);
            },

            editTenant(tenantId) {
                alert(`Editing tenant: ${tenantId}`);
            },

            suspendTenant(tenantId) {
                if (confirm(`Are you sure you want to suspend tenant ${tenantId}?`)) {
                    alert(`Suspending tenant: ${tenantId}`);
                }
            },

            deleteTenant(tenantId) {
                if (confirm(`Are you sure you want to delete tenant ${tenantId}?`)) {
                    alert(`Deleting tenant: ${tenantId}`);
                    this.mockTenants = this.mockTenants.filter(tenant => tenant.id !== tenantId);
                    this.calculateStats();
                }
            },

            exportTenants(format) {
                alert(`Exporting tenants in ${format} format.`);
            },

            resetFilters() {
                this.searchQuery = '';
                this.planFilter = '';
                this.statusFilter = '';
            }
        }
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/tenants/index.blade.php ENDPATH**/ ?>