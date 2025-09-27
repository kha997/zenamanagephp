{{-- Tenants Table --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">
                        <input type="checkbox" @change="selectAllTenants" 
                               :checked="selectedTenants.length === filteredTenants.length && filteredTenants.length > 0"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('name')">
                        Name
                        <i :class="sortBy === 'name' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('domain')">
                        Domain
                        <i :class="sortBy === 'domain' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('owner')">
                        Owner
                        <i :class="sortBy === 'owner' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('plan')">
                        Plan
                        <i :class="sortBy === 'plan' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('status')">
                        Status
                        <i :class="sortBy === 'status' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('users')">
                        Users
                        <i :class="sortBy === 'users' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="setSort('lastActive')">
                        Last Active
                        <i :class="sortBy === 'lastActive' ? (sortOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down') : 'fas fa-sort'"></i>
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
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
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8">
                                    <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-building text-blue-600 text-sm"></i>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900" x-text="tenant.name"></div>
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
                            <span :class="tenant.status === 'active' ? 'bg-green-100 text-green-800' : tenant.status === 'suspended' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'" 
                                  class="px-2 py-1 text-xs font-medium rounded-full" x-text="tenant.status"></span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900" x-text="tenant.users"></td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500" x-text="tenant.lastActive"></td>
                        <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                <button @click="openEditModal(tenant)" 
                                        class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button @click="openDeleteModal(tenant)" 
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
    
    <!-- Empty State -->
    <div x-show="filteredTenants.length === 0" class="text-center py-12">
        <i class="fas fa-building text-gray-400 text-4xl mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No tenants found</h3>
        <p class="text-gray-600 mb-4">Get started by creating your first tenant</p>
        <button @click="openCreateModal" 
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i>Create Tenant
        </button>
    </div>
</div>
