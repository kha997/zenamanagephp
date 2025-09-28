
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <!-- Loading State -->
    <div x-show="isLoading" class="p-6">
        <div class="space-y-4">
            <template x-for="i in 8" :key="i">
                <div class="flex items-center space-x-4 animate-pulse">
                    <div class="w-4 h-4 bg-gray-200 rounded"></div>
                    <div class="w-8 h-8 bg-gray-200 rounded-full"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-4 bg-gray-200 rounded w-1/4"></div>
                        <div class="h-3 bg-gray-200 rounded w-1/6"></div>
                    </div>
                    <div class="h-4 bg-gray-200 rounded w-1/5"></div>
                    <div class="h-4 bg-gray-200 rounded w-1/6"></div>
                    <div class="h-4 bg-gray-200 rounded w-1/8"></div>
                    <div class="h-4 bg-gray-200 rounded w-1/8"></div>
                    <div class="h-4 bg-gray-200 rounded w-1/8"></div>
                </div>
            </template>
        </div>
    </div>
    
    <!-- Error State -->
    <div x-show="error && !isLoading" class="p-6 text-center">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>
            <h3 class="text-lg font-medium text-red-900 mb-2">Error loading tenants</h3>
            <p class="text-red-700 mb-4" x-text="error"></p>
            <button @click="loadTenants" 
                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                <i class="fas fa-retry mr-2"></i>Retry
            </button>
        </div>
    </div>
    
    <!-- Table Content -->
    <div x-show="!isLoading && !error" class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">
                        <input type="checkbox" @change="selectAllTenants" 
                               :checked="selectedTenants.length === filteredTenants.length && filteredTenants.length > 0"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               aria-label="Select all tenants">
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('name')"
                        :aria-label="sortBy === 'name' ? `Sorted by name ${sortOrder}` : 'Sort by name'">
                        Name
                        <i :class="sortBy === 'name' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('domain')"
                        :aria-label="sortBy === 'domain' ? `Sorted by domain ${sortOrder}` : 'Sort by domain'">
                        Domain
                        <i :class="sortBy === 'domain' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('owner')"
                        :aria-label="sortBy === 'owner' ? `Sorted by owner ${sortOrder}` : 'Sort by owner'">
                        Owner
                        <i :class="sortBy === 'owner' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('plan')"
                        :aria-label="sortBy === 'plan' ? `Sorted by plan ${sortOrder}` : 'Sort by plan'">
                        Plan
                        <i :class="sortBy === 'plan' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('status')"
                        :aria-label="sortBy === 'status' ? `Sorted by status ${sortOrder}` : 'Sort by status'">
                        Status
                        <i :class="sortBy === 'status' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('usersCount')"
                        :aria-label="sortBy === 'usersCount' ? `Sorted by users ${sortOrder}` : 'Sort by users'">
                        Users
                        <i :class="sortBy === 'usersCount' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('lastActiveAt')"
                        :aria-label="sortBy === 'lastActiveAt' ? `Sorted by last active ${sortOrder}` : 'Sort by last active'">
                        Last Active
                        <i :class="sortBy === 'lastActiveAt' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <template x-for="tenant in filteredTenants" :key="tenant.id">
                    <tr class="hover:bg-gray-50 h-12">
                        <td class="px-4 py-2 whitespace-nowrap">
                            <input type="checkbox" 
                                   :checked="selectedTenants.some(t => t.id === tenant.id)"
                                   @change="selectTenant(tenant)"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                   :aria-label="`Select tenant ${tenant.name}`">
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8">
                                    <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-building text-blue-600 text-sm"></i>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <a :href="`/admin/tenants/${tenant.id}`" 
                                       class="text-sm font-medium text-blue-600 hover:text-blue-900" 
                                       x-text="tenant.name"></a>
                                    <div class="text-xs text-gray-500">ID: <span x-text="tenant.id"></span></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900" x-text="tenant.domain"></div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900" x-text="tenant.owner"></div>
                            <div class="text-xs text-gray-500" x-text="tenant.ownerEmail"></div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <span :class="tenant.plan === 'Enterprise' ? 'bg-purple-100 text-purple-800' : tenant.plan === 'Professional' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'" 
                                  class="px-2 py-1 text-xs font-medium rounded-full" x-text="tenant.plan"></span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <span :class="tenant.status === 'active' ? 'bg-green-100 text-green-800' : tenant.status === 'suspended' ? 'bg-red-100 text-red-800' : tenant.status === 'trial' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800'" 
                                  class="px-2 py-1 text-xs font-medium rounded-full" x-text="tenant.status"></span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900" x-text="tenant.users || 0"></td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500" x-text="formatDate(tenant.lastActive)"></td>
                        <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                <button @click="viewTenant(tenant)" 
                                        class="text-blue-600 hover:text-blue-900"
                                        :aria-label="`View tenant ${tenant.name}`">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button @click="openEditModal(tenant)" 
                                        class="text-green-600 hover:text-green-900"
                                        :aria-label="`Edit tenant ${tenant.name}`">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button @click="toggleTenantStatus(tenant)" 
                                        :class="tenant.status === 'active' ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900'"
                                        :aria-label="`${tenant.status === 'active' ? 'Suspend' : 'Activate'} tenant ${tenant.name}`">
                                    <i :class="tenant.status === 'active' ? 'fas fa-pause' : 'fas fa-play'"></i>
                                </button>
                                <button @click="openDeleteModal(tenant)" 
                                        class="text-red-600 hover:text-red-900"
                                        :aria-label="`Delete tenant ${tenant.name}`">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    
    <!-- Empty State -->
    <div x-show="!isLoading && !error && filteredTenants.length === 0" class="text-center py-12">
        <i class="fas fa-building text-gray-400 text-4xl mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No tenants found</h3>
        <p class="text-gray-600 mb-4">Get started by creating your first tenant</p>
        <button @click="openCreateModal" 
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i>Create Tenant
        </button>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/tenants/_table.blade.php ENDPATH**/ ?>