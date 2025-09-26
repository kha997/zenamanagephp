@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header with Logo -->
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                @include('components.zena-logo', ['subtitle' => 'User Management'])
                
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
        <div x-data="usersDashboard()" x-init="init()">
            <!-- Users Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="dashboard-card metric-card blue p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Total Users</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.totalUsers || 0"></p>
                            <p class="text-white/80 text-sm">
                                <span x-text="stats?.activeUsers || 0"></span> active
                            </p>
                        </div>
                        <i class="fas fa-users text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card green p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">New This Month</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.newThisMonth || 0"></p>
                            <p class="text-white/80 text-sm">
                                <span x-text="stats?.growthRate || 0"></span>% growth
                            </p>
                        </div>
                        <i class="fas fa-user-plus text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card orange p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Pending Approval</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.pendingUsers || 0"></p>
                            <p class="text-white/80 text-sm">
                                Requires review
                            </p>
                        </div>
                        <i class="fas fa-hourglass-half text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card purple p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Online Now</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.onlineUsers || 0"></p>
                            <p class="text-white/80 text-sm">
                                Active sessions
                            </p>
                        </div>
                        <i class="fas fa-circle text-4xl text-white/60"></i>
                    </div>
                </div>
            </div>

            <!-- Users Overview Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Recent Users -->
                <div class="dashboard-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Users</h3>
                        <div class="flex items-center gap-2">
                            <button class="zena-btn zena-btn-primary zena-btn-sm" @click="createUser()">
                                <i class="fas fa-plus mr-2"></i>
                                Add User
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
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click.prevent="exportUsers('excel')">
                                            <i class="fas fa-file-excel mr-2 text-green-600"></i>
                                            Excel (.xlsx)
                                        </a>
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click.prevent="exportUsers('pdf')">
                                            <i class="fas fa-file-pdf mr-2 text-red-600"></i>
                                            PDF (.pdf)
                                        </a>
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click.prevent="exportUsers('csv')">
                                            <i class="fas fa-file-csv mr-2 text-blue-600"></i>
                                            CSV (.csv)
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <template x-for="user in recentUsers.slice(0, 4)" :key="user.id">
                            <li class="py-3 flex items-center justify-between hover:bg-gray-50 rounded-lg px-2 -mx-2 transition-colors">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="getUserAvatarColor(user.role)">
                                        <span class="text-white text-sm font-medium" x-text="user.initials"></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900" x-text="user.name"></p>
                                        <p class="text-sm text-gray-600">
                                            <span x-text="user.email"></span> • <span x-text="user.role"></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="zena-badge" :class="{'zena-badge-success': user.status === 'active', 'zena-badge-warning': user.status === 'pending', 'zena-badge-danger': user.status === 'inactive'}" x-text="user.status"></span>
                                    <p class="text-sm text-gray-600" x-text="user.lastLogin"></p>
                                </div>
                            </li>
                        </template>
                        <template x-if="recentUsers.length === 0">
                            <li class="py-3 text-center text-gray-500">No recent users.</li>
                        </template>
                    </ul>
                </div>

                <!-- User Analytics -->
                <div class="dashboard-card p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">User Analytics</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Role Distribution</span>
                            <span class="text-lg font-semibold text-gray-900">Admin 15%, PM 25%</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Active Rate</span>
                            <span class="text-lg font-semibold text-gray-900">85%</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Average Session</span>
                            <span class="text-lg font-semibold text-gray-900">2.5 hours</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: 85%"></div>
                        </div>
                        <p class="text-xs text-gray-500">85% of users are active daily</p>
                    </div>
                </div>
            </div>

            <!-- All Users Table -->
            <div class="dashboard-card p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">All Users</h3>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <input type="text" placeholder="Search users..." class="zena-input zena-input-sm" x-model="searchQuery">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <select x-model="roleFilter" class="zena-select zena-select-sm">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="project_manager">Project Manager</option>
                            <option value="designer">Designer</option>
                            <option value="engineer">Engineer</option>
                            <option value="client">Client</option>
                        </select>
                        <select x-model="statusFilter" class="zena-select zena-select-sm">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <button @click="resetFilters()" class="zena-btn zena-btn-outline zena-btn-sm">
                            <i class="fas fa-redo mr-2"></i>Reset
                        </button>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="overflow-x-auto">
                    <table class="zena-table w-full">
                        <thead>
                            <tr>
                                <th class="text-left">User</th>
                                <th class="text-left">Role</th>
                                <th class="text-left">Status</th>
                                <th class="text-left">Last Login</th>
                                <th class="text-left">Projects</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="user in filteredUsers" :key="user.id">
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="font-medium text-gray-900">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="getUserAvatarColor(user.role)">
                                                <span class="text-white text-sm font-medium" x-text="user.initials"></span>
                                            </div>
                                            <div>
                                                <p class="font-medium" x-text="user.name"></p>
                                                <p class="text-sm text-gray-600" x-text="user.email"></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="zena-badge zena-badge-sm" :class="getRoleColor(user.role)" x-text="user.role"></span>
                                    </td>
                                    <td>
                                        <span class="zena-badge" :class="{'zena-badge-success': user.status === 'active', 'zena-badge-warning': user.status === 'pending', 'zena-badge-danger': user.status === 'inactive'}" x-text="user.status"></span>
                                    </td>
                                    <td x-text="user.lastLogin"></td>
                                    <td x-text="user.projectCount"></td>
                                    <td class="text-right">
                                        <button @click="viewUser(user.id)" class="zena-btn zena-btn-outline zena-btn-sm"><i class="fas fa-eye"></i></button>
                                        <button @click="editUser(user.id)" class="zena-btn zena-btn-outline zena-btn-sm"><i class="fas fa-edit"></i></button>
                                        <button @click="deleteUser(user.id)" class="zena-btn zena-btn-outline zena-btn-sm zena-btn-danger"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="filteredUsers.length === 0">
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-gray-500">No users found matching your criteria.</td>
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
                        <span> of <span class="font-medium" x-text="mockUsers.length"></span> results</span>
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
    function usersDashboard() {
        return {
            mockUsers: [
                {
                    id: '1',
                    name: 'John Doe',
                    email: 'john.doe@company.com',
                    role: 'project_manager',
                    status: 'active',
                    lastLogin: '2 hours ago',
                    projectCount: 5,
                    initials: 'JD'
                },
                {
                    id: '2',
                    name: 'Jane Smith',
                    email: 'jane.smith@company.com',
                    role: 'designer',
                    status: 'active',
                    lastLogin: '1 day ago',
                    projectCount: 3,
                    initials: 'JS'
                },
                {
                    id: '3',
                    name: 'Mike Johnson',
                    email: 'mike.johnson@company.com',
                    role: 'engineer',
                    status: 'pending',
                    lastLogin: 'Never',
                    projectCount: 0,
                    initials: 'MJ'
                },
                {
                    id: '4',
                    name: 'Sarah Wilson',
                    email: 'sarah.wilson@company.com',
                    role: 'admin',
                    status: 'active',
                    lastLogin: '30 min ago',
                    projectCount: 8,
                    initials: 'SW'
                },
                {
                    id: '5',
                    name: 'David Brown',
                    email: 'david.brown@company.com',
                    role: 'client',
                    status: 'inactive',
                    lastLogin: '1 week ago',
                    projectCount: 2,
                    initials: 'DB'
                }
            ],
            searchQuery: '',
            roleFilter: '',
            statusFilter: '',
            stats: {
                totalUsers: 0,
                activeUsers: 0,
                newThisMonth: 0,
                growthRate: 0,
                pendingUsers: 0,
                onlineUsers: 0,
            },
            recentUsers: [],

            init() {
                this.calculateStats();
                this.recentUsers = this.mockUsers.slice(0, 4);
                this.$watch('searchQuery', () => this.updateFilteredUsers());
                this.$watch('roleFilter', () => this.updateFilteredUsers());
                this.$watch('statusFilter', () => this.updateFilteredUsers());
            },

            calculateStats() {
                this.stats.totalUsers = this.mockUsers.length;
                this.stats.activeUsers = this.mockUsers.filter(user => user.status === 'active').length;
                this.stats.newThisMonth = 8;
                this.stats.growthRate = 15;
                this.stats.pendingUsers = this.mockUsers.filter(user => user.status === 'pending').length;
                this.stats.onlineUsers = 12;
            },

            get filteredUsers() {
                return this.mockUsers.filter(user => {
                    const searchMatch = user.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                                       user.email.toLowerCase().includes(this.searchQuery.toLowerCase());
                    const roleMatch = this.roleFilter === '' || user.role === this.roleFilter;
                    const statusMatch = this.statusFilter === '' || user.status === this.statusFilter;
                    return searchMatch && roleMatch && statusMatch;
                });
            },

            updateFilteredUsers() {
                // This function is primarily for reactivity
            },

            getUserAvatarColor(role) {
                switch (role) {
                    case 'admin': return 'bg-red-500';
                    case 'project_manager': return 'bg-blue-500';
                    case 'designer': return 'bg-purple-500';
                    case 'engineer': return 'bg-green-500';
                    case 'client': return 'bg-orange-500';
                    default: return 'bg-gray-500';
                }
            },

            getRoleColor(role) {
                switch (role) {
                    case 'admin': return 'zena-badge-danger';
                    case 'project_manager': return 'zena-badge-primary';
                    case 'designer': return 'zena-badge-purple';
                    case 'engineer': return 'zena-badge-success';
                    case 'client': return 'zena-badge-warning';
                    default: return 'zena-badge-neutral';
                }
            },

            createUser() {
                alert('Create user functionality will be implemented here!');
            },

            viewUser(userId) {
                alert(`Viewing user: ${userId}`);
            },

            editUser(userId) {
                alert(`Editing user: ${userId}`);
            },

            deleteUser(userId) {
                if (confirm(`Are you sure you want to delete user ${userId}?`)) {
                    alert(`Deleting user: ${userId}`);
                    this.mockUsers = this.mockUsers.filter(user => user.id !== userId);
                    this.calculateStats();
                }
            },

            exportUsers(format) {
                alert(`Exporting users in ${format} format.`);
            },

            resetFilters() {
                this.searchQuery = '';
                this.roleFilter = '';
                this.statusFilter = '';
            }
        }
    }
</script>
@endsection
