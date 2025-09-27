
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left">
                        <input type="checkbox" @change="selectAllUsers" 
                               :checked="selectedUsers.length === filteredUsers.length && filteredUsers.length > 0"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('name')">
                        Name
                        <i :class="sortBy === 'name' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('email')">
                        Email
                        <i :class="sortBy === 'email' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('tenant')">
                        Tenant
                        <i :class="sortBy === 'tenant' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('role')">
                        Role
                        <i :class="sortBy === 'role' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('status')">
                        Status
                        <i :class="sortBy === 'status' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('lastLogin')">
                        Last Login
                        <i :class="sortBy === 'lastLogin' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
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
                            <input type="checkbox" 
                                   :checked="selectedUsers.some(u => u.id === user.id)"
                                   @change="selectUser(user)"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <img class="h-10 w-10 rounded-full" 
                                         :src="'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.name) + '&background=3b82f6&color=ffffff'" 
                                         :alt="user.name">
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900" x-text="user.name"></div>
                                    <div class="text-sm text-gray-500">ID: <span x-text="user.id"></span></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900" x-text="user.email"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900" x-text="user.tenant"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span :class="user.role === 'Admin' ? 'bg-red-100 text-red-800' : user.role === 'Project Manager' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'" 
                                  class="px-2 py-1 text-xs font-medium rounded-full" x-text="user.role"></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span :class="user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" 
                                  class="px-2 py-1 text-xs font-medium rounded-full" x-text="user.status"></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="user.lastLogin || 'Never'"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                <button @click="resetPassword(user)" 
                                        class="text-yellow-600 hover:text-yellow-900" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <button @click="openEditModal(user)" 
                                        class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button @click="openDeleteModal(user)" 
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
    
    <div x-show="filteredUsers.length === 0" class="text-center py-12">
        <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
        <p class="text-gray-600 mb-4">Get started by creating your first user</p>
        <button @click="openCreateModal" 
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i>Create User
        </button>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/users/_table.blade.php ENDPATH**/ ?>