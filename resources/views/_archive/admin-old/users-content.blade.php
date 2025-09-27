<!-- Admin Users Content -->
<div x-data="adminUsers()" x-init="init()" class="space-y-6">
    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-2 text-gray-600">Loading users...</span>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="ml-3">
                <p class="font-bold">Error loading users</p>
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
                    <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
                    <p class="text-gray-600 mt-1">Manage system users across all tenants</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button @click="refreshData()" 
                            :disabled="refreshing"
                            class="flex items-center space-x-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 disabled:opacity-50">
                        <i class="fas fa-sync-alt" :class="{'animate-spin': refreshing}"></i>
                        <span>Refresh</span>
                    </button>
                    <button @click="showAddUserModal = true" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add User
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-blue-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-600">Total Users</p>
                            <p class="text-2xl font-bold text-blue-900" x-text="stats.totalUsers || 0"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-check text-green-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-600">Active Users</p>
                            <p class="text-2xl font-bold text-green-900" x-text="stats.activeUsers || 0"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-clock text-yellow-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-600">Pending</p>
                            <p class="text-2xl font-bold text-yellow-900" x-text="stats.pendingUsers || 0"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-red-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-times text-red-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-600">Suspended</p>
                            <p class="text-2xl font-bold text-red-900" x-text="stats.suspendedUsers || 0"></p>
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
                               @input="filterUsers()"
                               placeholder="Search users..." 
                               class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                    <select x-model="statusFilter" @change="filterUsers()" 
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="pending">Pending</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <select x-model="roleFilter" @change="filterUsers()" 
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Roles</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="admin">Admin</option>
                        <option value="pm">Project Manager</option>
                        <option value="designer">Designer</option>
                        <option value="site_engineer">Site Engineer</option>
                        <option value="qc">QC Inspector</option>
                        <option value="client">Client</option>
                    </select>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500" x-text="`Showing ${filteredUsers.length} of ${users.length} users`"></span>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" @change="toggleSelectAll()" x-model="selectAll" class="rounded">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Active</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="user in filteredUsers" :key="user.id">
                            <tr class="hover:bg-gray-50" :class="{'bg-blue-50': selectedUsers.includes(user.id)}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" 
                                           :value="user.id" 
                                           x-model="selectedUsers" 
                                           class="rounded">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                                            <span class="text-sm font-medium text-gray-700" x-text="getInitials(user.name)"></span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900" x-text="user.name"></div>
                                            <div class="text-sm text-gray-500" x-text="user.id"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="user.email"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full"
                                          :class="getRoleBadgeColor(user.role)"
                                          x-text="user.role"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="user.tenant || 'N/A'"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full"
                                          :class="getStatusBadgeColor(user.status)"
                                          x-text="user.status"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="user.lastActive"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <button @click="editUser(user)" 
                                                class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="viewUser(user)" 
                                                class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button @click="suspendUser(user)" 
                                                class="text-yellow-600 hover:text-yellow-900">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                        <button @click="deleteUser(user)" 
                                                class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Bulk Actions -->
            <div x-show="selectedUsers.length > 0" class="bg-gray-50 px-6 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-700" x-text="`${selectedUsers.length} users selected`"></span>
                    <div class="flex items-center space-x-2">
                        <button @click="bulkSuspend()" 
                                class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded text-sm hover:bg-yellow-200">
                            Suspend
                        </button>
                        <button @click="bulkActivate()" 
                                class="px-3 py-1 bg-green-100 text-green-800 rounded text-sm hover:bg-green-200">
                            Activate
                        </button>
                        <button @click="bulkDelete()" 
                                class="px-3 py-1 bg-red-100 text-red-800 rounded text-sm hover:bg-red-200">
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="bg-white px-6 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-700">Show</span>
                        <select x-model="perPage" @change="filterUsers()" 
                                class="px-2 py-1 border border-gray-300 rounded text-sm">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="text-sm text-gray-700">per page</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button @click="previousPage()" 
                                :disabled="currentPage === 1"
                                class="px-3 py-1 border border-gray-300 rounded text-sm disabled:opacity-50">
                            Previous
                        </button>
                        <span class="text-sm text-gray-700" x-text="`Page ${currentPage} of ${totalPages}`"></span>
                        <button @click="nextPage()" 
                                :disabled="currentPage === totalPages"
                                class="px-3 py-1 border border-gray-300 rounded text-sm disabled:opacity-50">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div x-show="showAddUserModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showAddUserModal = false"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form @submit.prevent="addUser()">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-user-plus text-blue-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Add New User</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                        <input type="text" x-model="newUser.name" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <input type="email" x-model="newUser.email" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                        <select x-model="newUser.role" required
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Role</option>
                                            <option value="admin">Admin</option>
                                            <option value="pm">Project Manager</option>
                                            <option value="designer">Designer</option>
                                            <option value="site_engineer">Site Engineer</option>
                                            <option value="qc">QC Inspector</option>
                                            <option value="client">Client</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tenant</label>
                                        <select x-model="newUser.tenant" required
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Tenant</option>
                                            <template x-for="tenant in tenants" :key="tenant.id">
                                                <option :value="tenant.name" x-text="tenant.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" 
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Add User
                        </button>
                        <button type="button" @click="showAddUserModal = false"
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
function adminUsers() {
    return {
        loading: true,
        error: null,
        refreshing: false,
        
        // Data
        users: [],
        filteredUsers: [],
        tenants: [],
        stats: {
            totalUsers: 0,
            activeUsers: 0,
            pendingUsers: 0,
            suspendedUsers: 0
        },
        
        // Filters
        searchQuery: '',
        statusFilter: '',
        roleFilter: '',
        
        // Selection
        selectedUsers: [],
        selectAll: false,
        
        // Pagination
        currentPage: 1,
        perPage: 25,
        totalPages: 1,
        
        // Modal
        showAddUserModal: false,
        newUser: {
            name: '',
            email: '',
            role: '',
            tenant: ''
        },

        async init() {
            await this.loadUsers();
            await this.loadTenants();
            this.calculateStats();
            this.filterUsers();
        },

        async loadUsers() {
            try {
                this.loading = true;
                this.error = null;
                
                // Mock data for demonstration
                this.users = [
                    {
                        id: 1,
                        name: 'John Doe',
                        email: 'john.doe@example.com',
                        role: 'admin',
                        tenant: 'Acme Corp',
                        status: 'active',
                        lastActive: '2 hours ago'
                    },
                    {
                        id: 2,
                        name: 'Jane Smith',
                        email: 'jane.smith@example.com',
                        role: 'pm',
                        tenant: 'TechStart Inc',
                        status: 'active',
                        lastActive: '1 hour ago'
                    },
                    {
                        id: 3,
                        name: 'Mike Johnson',
                        email: 'mike.johnson@example.com',
                        role: 'designer',
                        tenant: 'Global Solutions',
                        status: 'pending',
                        lastActive: 'Never'
                    },
                    {
                        id: 4,
                        name: 'Sarah Wilson',
                        email: 'sarah.wilson@example.com',
                        role: 'site_engineer',
                        tenant: 'Acme Corp',
                        status: 'suspended',
                        lastActive: '3 days ago'
                    },
                    {
                        id: 5,
                        name: 'David Brown',
                        email: 'david.brown@example.com',
                        role: 'qc',
                        tenant: 'TechStart Inc',
                        status: 'active',
                        lastActive: '30 minutes ago'
                    }
                ];
                
                this.loading = false;
                
            } catch (error) {
                console.error('Error loading users:', error);
                this.error = error.message;
                this.loading = false;
            }
        },

        async loadTenants() {
            this.tenants = [
                { id: 1, name: 'Acme Corp' },
                { id: 2, name: 'TechStart Inc' },
                { id: 3, name: 'Global Solutions' }
            ];
        },

        calculateStats() {
            this.stats = {
                totalUsers: this.users.length,
                activeUsers: this.users.filter(u => u.status === 'active').length,
                pendingUsers: this.users.filter(u => u.status === 'pending').length,
                suspendedUsers: this.users.filter(u => u.status === 'suspended').length
            };
        },

        filterUsers() {
            let filtered = this.users;
            
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(user => 
                    user.name.toLowerCase().includes(query) ||
                    user.email.toLowerCase().includes(query) ||
                    user.tenant.toLowerCase().includes(query)
                );
            }
            
            if (this.statusFilter) {
                filtered = filtered.filter(user => user.status === this.statusFilter);
            }
            
            if (this.roleFilter) {
                filtered = filtered.filter(user => user.role === this.roleFilter);
            }
            
            this.filteredUsers = filtered;
            this.totalPages = Math.ceil(filtered.length / this.perPage);
            this.currentPage = 1;
        },

        toggleSelectAll() {
            if (this.selectAll) {
                this.selectedUsers = this.filteredUsers.map(user => user.id);
            } else {
                this.selectedUsers = [];
            }
        },

        getInitials(name) {
            return name.split(' ').map(n => n[0]).join('').toUpperCase();
        },

        getRoleBadgeColor(role) {
            const colors = {
                'super_admin': 'bg-red-100 text-red-800',
                'admin': 'bg-blue-100 text-blue-800',
                'pm': 'bg-green-100 text-green-800',
                'designer': 'bg-purple-100 text-purple-800',
                'site_engineer': 'bg-yellow-100 text-yellow-800',
                'qc': 'bg-indigo-100 text-indigo-800',
                'client': 'bg-gray-100 text-gray-800'
            };
            return colors[role] || 'bg-gray-100 text-gray-800';
        },

        getStatusBadgeColor(status) {
            const colors = {
                'active': 'bg-green-100 text-green-800',
                'inactive': 'bg-gray-100 text-gray-800',
                'pending': 'bg-yellow-100 text-yellow-800',
                'suspended': 'bg-red-100 text-red-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        },

        editUser(user) {
            console.log('Edit user:', user);
            // Implement edit functionality
        },

        viewUser(user) {
            console.log('View user:', user);
            // Implement view functionality
        },

        suspendUser(user) {
            console.log('Suspend user:', user);
            // Implement suspend functionality
        },

        deleteUser(user) {
            if (confirm(`Are you sure you want to delete ${user.name}?`)) {
                console.log('Delete user:', user);
                // Implement delete functionality
            }
        },

        bulkSuspend() {
            console.log('Bulk suspend:', this.selectedUsers);
            // Implement bulk suspend
        },

        bulkActivate() {
            console.log('Bulk activate:', this.selectedUsers);
            // Implement bulk activate
        },

        bulkDelete() {
            if (confirm(`Are you sure you want to delete ${this.selectedUsers.length} users?`)) {
                console.log('Bulk delete:', this.selectedUsers);
                // Implement bulk delete
            }
        },

        async addUser() {
            try {
                console.log('Adding user:', this.newUser);
                // Implement add user functionality
                
                // Reset form
                this.newUser = { name: '', email: '', role: '', tenant: '' };
                this.showAddUserModal = false;
                
                // Refresh data
                await this.loadUsers();
                this.calculateStats();
                this.filterUsers();
                
            } catch (error) {
                console.error('Error adding user:', error);
            }
        },

        refreshData() {
            this.refreshing = true;
            setTimeout(() => {
                this.init();
                this.refreshing = false;
            }, 1000);
        },

        previousPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        }
    }
}
</script>