{{-- Admin User Management Page --}}
@extends('layouts.universal-frame')

@section('title', 'User Management - Admin Dashboard')

@section('content')
<div class="user-management">
    <!-- Page Header -->
    <div class="page-header bg-white border-b border-gray-200 px-6 py-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
                <p class="text-gray-600 mt-1">Manage system users and permissions</p>
            </div>
            <div class="flex items-center space-x-3">
                <button 
                    @click="exportUsers()"
                    class="btn-secondary flex items-center gap-2"
                >
                    <i class="fas fa-download"></i>
                    <span>Export</span>
                </button>
                <a href="{{ route('admin.users.create') }}" class="btn-primary flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    <span>Add User</span>
                </a>
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
                        @input.debounce.300ms="searchUsers()"
                        placeholder="Search users by name, email, or role..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
            </div>
            
            <!-- Filters -->
            <div class="flex gap-3">
                <select 
                    x-model="statusFilter"
                    @change="filterUsers()"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
                
                <select 
                    x-model="roleFilter"
                    @change="filterUsers()"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Roles</option>
                    <option value="super_admin">Super Admin</option>
                    <option value="admin">Admin</option>
                    <option value="project_manager">Project Manager</option>
                    <option value="member">Member</option>
                    <option value="client">Client</option>
                </select>
                
                <select 
                    x-model="tenantFilter"
                    @change="filterUsers()"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Tenants</option>
                    <option value="1">Tenant A</option>
                    <option value="2">Tenant B</option>
                    <option value="3">Tenant C</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="main-content p-6">
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <!-- Table Header -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">
                        Users <span class="text-gray-500">(<span x-text="filteredUsers.length"></span>)</span>
                    </h2>
                    <div class="flex items-center space-x-2">
                        <button 
                            @click="selectAll()"
                            class="text-sm text-blue-600 hover:text-blue-800"
                        >
                            Select All
                        </button>
                        <button 
                            @click="bulkAction()"
                            :disabled="selectedUsers.length === 0"
                            class="btn-secondary text-sm"
                            :class="{ 'opacity-50 cursor-not-allowed': selectedUsers.length === 0 }"
                        >
                            Bulk Actions (<span x-text="selectedUsers.length"></span>)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input 
                                    type="checkbox" 
                                    x-model="selectAllUsers"
                                    @change="toggleSelectAll()"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Role
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tenant
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Last Login
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Created
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="user in filteredUsers" :key="user.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input 
                                        type="checkbox" 
                                        :value="user.id"
                                        x-model="selectedUsers"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-gray-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900" x-text="user.name"></div>
                                            <div class="text-sm text-gray-500" x-text="user.email"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span 
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                        :class="getRoleColor(user.role)"
                                        x-text="user.role"
                                    ></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="user.tenant_name"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span 
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                        :class="getStatusColor(user.status)"
                                        x-text="user.status"
                                    ></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDate(user.last_login)"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDate(user.created_at)"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <button 
                                            @click="viewUser(user.id)"
                                            class="text-blue-600 hover:text-blue-900"
                                        >
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button 
                                            @click="editUser(user.id)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button 
                                            @click="suspendUser(user.id)"
                                            :class="user.status === 'suspended' ? 'text-green-600 hover:text-green-900' : 'text-yellow-600 hover:text-yellow-900'"
                                        >
                                            <i :class="user.status === 'suspended' ? 'fas fa-unlock' : 'fas fa-lock'"></i>
                                        </button>
                                        <button 
                                            @click="deleteUser(user.id)"
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing <span x-text="(currentPage - 1) * perPage + 1"></span> to 
                        <span x-text="Math.min(currentPage * perPage, filteredUsers.length)"></span> of 
                        <span x-text="filteredUsers.length"></span> results
                    </div>
                    <div class="flex items-center space-x-2">
                        <button 
                            @click="previousPage()"
                            :disabled="currentPage === 1"
                            class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Previous
                        </button>
                        <span class="px-3 py-1 text-sm text-gray-700">
                            Page <span x-text="currentPage"></span> of <span x-text="totalPages"></span>
                        </span>
                        <button 
                            @click="nextPage()"
                            :disabled="currentPage === totalPages"
                            class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('userManagement', () => ({
        searchQuery: '',
        statusFilter: '',
        roleFilter: '',
        tenantFilter: '',
        selectedUsers: [],
        selectAllUsers: false,
        currentPage: 1,
        perPage: 10,
        
        users: [
            {
                id: 1,
                name: 'John Doe',
                email: 'john@example.com',
                role: 'super_admin',
                tenant_name: 'System',
                status: 'active',
                last_login: '2025-09-24T10:30:00Z',
                created_at: '2025-01-15T08:00:00Z'
            },
            {
                id: 2,
                name: 'Jane Smith',
                email: 'jane@example.com',
                role: 'admin',
                tenant_name: 'Acme Corp',
                status: 'active',
                last_login: '2025-09-24T09:15:00Z',
                created_at: '2025-02-20T10:30:00Z'
            },
            {
                id: 3,
                name: 'Bob Johnson',
                email: 'bob@example.com',
                role: 'project_manager',
                tenant_name: 'Tech Solutions',
                status: 'suspended',
                last_login: '2025-09-20T14:45:00Z',
                created_at: '2025-03-10T12:00:00Z'
            },
            {
                id: 4,
                name: 'Alice Brown',
                email: 'alice@example.com',
                role: 'member',
                tenant_name: 'Design Studio',
                status: 'active',
                last_login: '2025-09-24T08:30:00Z',
                created_at: '2025-04-05T15:20:00Z'
            },
            {
                id: 5,
                name: 'Charlie Wilson',
                email: 'charlie@example.com',
                role: 'client',
                tenant_name: 'Marketing Agency',
                status: 'inactive',
                last_login: '2025-09-15T16:00:00Z',
                created_at: '2025-05-12T09:45:00Z'
            }
        ],

        get filteredUsers() {
            let filtered = this.users;
            
            // Search filter
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(user => 
                    user.name.toLowerCase().includes(query) ||
                    user.email.toLowerCase().includes(query) ||
                    user.role.toLowerCase().includes(query)
                );
            }
            
            // Status filter
            if (this.statusFilter) {
                filtered = filtered.filter(user => user.status === this.statusFilter);
            }
            
            // Role filter
            if (this.roleFilter) {
                filtered = filtered.filter(user => user.role === this.roleFilter);
            }
            
            // Tenant filter
            if (this.tenantFilter) {
                filtered = filtered.filter(user => user.tenant_name === this.tenantFilter);
            }
            
            return filtered;
        },

        get totalPages() {
            return Math.ceil(this.filteredUsers.length / this.perPage);
        },

        searchUsers() {
            this.currentPage = 1;
        },

        filterUsers() {
            this.currentPage = 1;
        },

        selectAll() {
            this.selectAllUsers = !this.selectAllUsers;
            if (this.selectAllUsers) {
                this.selectedUsers = this.filteredUsers.map(user => user.id);
            } else {
                this.selectedUsers = [];
            }
        },

        toggleSelectAll() {
            if (this.selectAllUsers) {
                this.selectedUsers = this.filteredUsers.map(user => user.id);
            } else {
                this.selectedUsers = [];
            }
        },

        bulkAction() {
            if (this.selectedUsers.length === 0) return;
            
            const action = prompt('Bulk action (suspend, activate, delete):');
            if (action) {
                console.log(`Performing ${action} on users:`, this.selectedUsers);
                // Implement bulk action logic
            }
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
        },

        getRoleColor(role) {
            const colors = {
                'super_admin': 'bg-red-100 text-red-800',
                'admin': 'bg-blue-100 text-blue-800',
                'project_manager': 'bg-green-100 text-green-800',
                'member': 'bg-gray-100 text-gray-800',
                'client': 'bg-purple-100 text-purple-800'
            };
            return colors[role] || 'bg-gray-100 text-gray-800';
        },

        getStatusColor(status) {
            const colors = {
                'active': 'bg-green-100 text-green-800',
                'inactive': 'bg-gray-100 text-gray-800',
                'suspended': 'bg-red-100 text-red-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },

        viewUser(userId) {
            console.log('View user:', userId);
            // Implement view user logic
        },

        editUser(userId) {
            console.log('Edit user:', userId);
            // Implement edit user logic
        },

        suspendUser(userId) {
            const user = this.users.find(u => u.id === userId);
            if (user) {
                user.status = user.status === 'suspended' ? 'active' : 'suspended';
            }
        },

        deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                this.users = this.users.filter(u => u.id !== userId);
            }
        },

        exportUsers() {
            console.log('Exporting users...');
            // Implement export logic
        }
    }));
});
</script>

<style>
.user-management {
    min-height: 100vh;
    background-color: #f9fafb;
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

/* Responsive table */
@media (max-width: 768px) {
    .user-management .overflow-x-auto {
        font-size: 14px;
    }
    
    .user-management .px-6 {
        padding-left: 1rem;
        padding-right: 1rem;
    }
}
</style>
@endsection
