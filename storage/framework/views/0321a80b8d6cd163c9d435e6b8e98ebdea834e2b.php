
<section id="users-table" 
         :data-loading="usersLoading ? 'users-table' : ''" 
         :aria-busy="usersLoading ? 'true' : 'false'"
         class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    
    <!-- Loading State - Scoped to table -->
    <div x-show="usersLoading" class="table-skeleton p-6">
        <div class="space-y-4">
            <template x-for="i in 8" :key="i">
                <div class="flex items-center space-x-4 skeleton-pulse">
                    <div class="w-4 h-4 bg-gray-200 rounded skeleton-circle"></div>
                    <div class="w-8 h-8 bg-gray-200 rounded-full skeleton-circle"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-4 bg-gray-200 rounded skeleton-line"></div>
                        <div class="h-3 bg-gray-200 rounded skeleton-line"></div>
                    </div>
                    <div class="h-4 bg-gray-200 rounded skeleton-cell"></div>
                    <div class="h-4 bg-gray-200 rounded skeleton-cell"></div>
                    <div class="h-4 bg-gray-200 rounded skeleton-cell"></div>
                    <div class="h-4 bg-gray-200 rounded skeleton-cell"></div>
                    <div class="h-4 bg-gray-200 rounded skeleton-cell"></div>
                </div>
            </template>
        </div>
    </div>
    
    <!-- Error State -->
    <div x-show="error && !usersLoading" class="p-6 text-center">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>
            <h3 class="text-lg font-medium text-red-900 mb-2">Error loading users</h3>
            <p class="text-red-700 mb-4" x-text="error"></p>
            <button @click="loadUsers" 
                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                <i class="fas fa-retry mr-2"></i>Retry
            </button>
        </div>
    </div>
    
    <!-- Table Content -->
    <div x-show="!usersLoading && !error" class="overflow-x-auto table-container" aria-live="polite">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">
                        <input type="checkbox" @change="selectAllUsers" 
                               :checked="selectedUsers.length === filteredUsers.length && filteredUsers.length > 0"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               aria-label="Select all users">
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('name')"
                        :aria-label="sortBy === 'name' ? `Sorted by name ${sortOrder}` : 'Sort by name'">
                        Name
                        <i :class="sortBy === 'name' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('tenantName')"
                        :aria-label="sortBy === 'tenantName' ? `Sorted by tenant ${sortOrder}` : 'Sort by tenant'">
                        Tenant
                        <i :class="sortBy === 'tenantName' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('role')"
                        :aria-label="sortBy === 'role' ? `Sorted by role ${sortOrder}` : 'Sort by role'">
                        Role
                        <i :class="sortBy === 'role' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('status')"
                        :aria-label="sortBy === 'status' ? `Sorted by status ${sortOrder}` : 'Sort by status'">
                        Status
                        <i :class="sortBy === 'status' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('mfaEnabled')"
                        :aria-label="sortBy === 'mfaEnabled' ? `Sorted by MFA ${sortOrder}` : 'Sort by MFA'">
                        MFA
                        <i :class="sortBy === 'mfaEnabled' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('lastLoginAt')"
                        :aria-label="sortBy === 'lastLoginAt' ? `Sorted by last login ${sortOrder}` : 'Sort by last login'">
                        Last Login
                        <i :class="sortBy === 'lastLoginAt' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('createdAt')"
                        :aria-label="sortBy === 'createdAt' ? `Sorted by created ${sortOrder}` : 'Sort by created'">
                        Created
                        <i :class="sortBy === 'createdAt' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <template x-for="user in filteredUsers" :key="user.id">
                    <tr class="hover:bg-gray-50 h-12">
                        <td class="px-4 py-2 whitespace-nowrap">
                            <input type="checkbox" 
                                   :checked="selectedUsers.some(u => u.id === user.id)"
                                   @change="selectUser(user)"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                   :aria-label="`Select user ${user.name}`">
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8">
                                    <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600 text-sm"></i>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <a :href="`/admin/users/${user.id}`" 
                                       class="text-sm font-medium text-blue-600 hover:text-blue-900" 
                                       x-text="user.name"></a>
                                    <div class="text-xs text-gray-500" x-text="user.email"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800" 
                                  x-text="user.tenantName"></span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <span :class="user.role === 'SuperAdmin' ? 'bg-purple-100 text-purple-800' : 
                                         user.role === 'TenantAdmin' ? 'bg-blue-100 text-blue-800' : 
                                         user.role === 'PM' ? 'bg-green-100 text-green-800' : 
                                         user.role === 'Staff' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'" 
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  x-text="user.role"></span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <span :class="user.status === 'active' ? 'bg-green-100 text-green-800' : 
                                         user.status === 'disabled' ? 'bg-red-100 text-red-800' : 
                                         user.status === 'locked' ? 'bg-orange-100 text-orange-800' : 
                                         user.status === 'invited' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'" 
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  x-text="user.status"></span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <span :class="user.mfaEnabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" 
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                <i :class="user.mfaEnabled ? 'fas fa-shield-alt' : 'fas fa-shield-alt'" class="mr-1"></i>
                                <span x-text="user.mfaEnabled ? 'Enabled' : 'Not Enabled'"></span>
                            </span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500" 
                            :title="user.lastLoginAt ? new Date(user.lastLoginAt).toLocaleString() : 'Never'"
                            x-text="formatTimeAgo(user.lastLoginAt)"></td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500" 
                            x-text="formatDate(user.createdAt)"></td>
                        <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-1">
                                <button @click="viewUser(user)" 
                                        class="text-blue-600 hover:text-blue-900 p-1"
                                        :aria-label="getAriaLabel('view', user)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button @click="openEditModal(user)" 
                                        class="text-green-600 hover:text-green-900 p-1"
                                        :aria-label="getAriaLabel('edit', user)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button @click="toggleUserStatus(user)" 
                                        :class="user.status === 'active' ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900'"
                                        class="p-1"
                                        :aria-label="getAriaLabel(user.status === 'active' ? 'disable' : 'enable', user)">
                                    <i :class="user.status === 'active' ? 'fas fa-pause' : 'fas fa-play'"></i>
                                </button>
                                <button x-show="user.status === 'locked'" 
                                        @click="unlockUser(user)" 
                                        class="text-orange-600 hover:text-orange-900 p-1"
                                        :aria-label="getAriaLabel('unlock', user)">
                                    <i class="fas fa-unlock"></i>
                                </button>
                                <button @click="openChangeRoleModal(user)" 
                                        class="text-purple-600 hover:text-purple-900 p-1"
                                        :aria-label="getAriaLabel('change-role', user)">
                                    <i class="fas fa-user-tag"></i>
                                </button>
                                <button @click="openForceMfaModal(user)" 
                                        class="text-orange-600 hover:text-orange-900 p-1"
                                        :aria-label="getAriaLabel('force-mfa', user)">
                                    <i class="fas fa-shield-alt"></i>
                                </button>
                                <button @click="sendResetLink(user)" 
                                        class="text-blue-600 hover:text-blue-900 p-1"
                                        :aria-label="getAriaLabel('send-reset', user)">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    
    <!-- Empty State -->
    <div x-show="!isLoading && !error && filteredUsers.length === 0" class="text-center py-12">
        <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
        <p class="text-gray-600 mb-4">Get started by inviting your first user</p>
        <button @click="openInviteModal" 
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-user-plus mr-2"></i>Invite User
        </button>
    </div>
</div><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/users/_table.blade.php ENDPATH**/ ?>