<!-- RBAC Overview Panel -->
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">RBAC Overview</h2>
            <p class="text-sm text-gray-600 mt-1">Role-based access control summary and recent changes</p>
        </div>
        <div class="flex space-x-3">
            <button @click="refreshRbacOverview()" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh
            </button>
            <button @click="manageRoles()" class="px-3 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-cog mr-2"></i>
                Manage Roles
            </button>
        </div>
    </div>

    <!-- RBAC Summary Cards -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-users-cog text-blue-600 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-blue-800">Total Roles</p>
                    <p class="text-2xl font-bold text-blue-900" x-text="rbacOverview.totalRoles || 0">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-key text-green-600 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">Total Permissions</p>
                    <p class="text-2xl font-bold text-green-900" x-text="rbacOverview.totalPermissions || 0">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-user-tag text-purple-600 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-purple-800">Active Assignments</p>
                    <p class="text-2xl font-bold text-purple-900" x-text="rbacOverview.activeAssignments || 0">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-history text-orange-600 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-orange-800">Recent Changes</p>
                    <p class="text-2xl font-bold text-orange-900" x-text="rbacOverview.recentChanges || 0">0</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Role Distribution Chart -->
    <div class="mb-6">
        <div class="bg-gray-50 rounded-lg p-4">
            <h3 class="text-sm font-medium text-gray-900 mb-4">Role Distribution</h3>
            <div class="h-64 flex items-center justify-center">
                <div class="text-center">
                    <i class="fas fa-chart-pie text-4xl text-gray-400 mb-2"></i>
                    <p class="text-gray-500">Role distribution chart will be displayed here</p>
                    <p class="text-xs text-gray-400 mt-1">Chart.js integration pending</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Roles Table -->
    <div class="mb-6">
        <h3 class="text-sm font-medium text-gray-900 mb-4">Role Details</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Role Name
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Users
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Permissions
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Last Modified
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <!-- Loading State -->
                    <tr x-show="loading">
                        <td colspan="5" class="px-6 py-4">
                            <div class="animate-pulse">
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty State -->
                    <tr x-show="!loading && (!rbacOverview.roles || rbacOverview.roles.length === 0)">
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="text-gray-500">
                                <i class="fas fa-users-cog text-4xl mb-4"></i>
                                <p class="text-lg font-medium">No roles found</p>
                                <p class="text-sm mt-1">RBAC data is being loaded.</p>
                            </div>
                        </td>
                    </tr>

                    <!-- Data Rows -->
                    <template x-for="role in (rbacOverview.roles || [])" :key="role.name">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8">
                                        <div class="h-8 w-8 rounded-full flex items-center justify-center" :class="getRoleIconClass(role.name)">
                                            <i :class="getRoleIcon(role.name)" class="text-white text-sm"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900" x-text="role.name"></div>
                                        <div class="text-sm text-gray-500" x-text="role.description || ''"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="role.userCount || 0"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="role.permissionCount || 0"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDateTime(role.lastModified)"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button @click="viewRoleDetails(role.name)" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-eye mr-1"></i>
                                    View
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Changes -->
    <div class="mb-6">
        <h3 class="text-sm font-medium text-gray-900 mb-4">Recent RBAC Changes</h3>
        <div class="bg-white border border-gray-200 rounded-lg">
            <div class="p-4">
                <div x-show="!rbacOverview.recentChanges || rbacOverview.recentChanges.length === 0" class="text-center py-8">
                    <i class="fas fa-history text-4xl text-gray-400 mb-2"></i>
                    <p class="text-gray-500">No recent changes</p>
                </div>
                
                <div x-show="rbacOverview.recentChanges && rbacOverview.recentChanges.length > 0" class="space-y-3">
                    <template x-for="change in (rbacOverview.recentChanges || [])" :key="change.id">
                        <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="getChangeIconClass(change.type)">
                                    <i :class="getChangeIcon(change.type)" class="text-white text-sm"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900" x-text="change.description"></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <span x-text="change.actor"></span> • 
                                    <span x-text="formatDateTime(change.timestamp)"></span>
                                </p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Permission Summary -->
    <div class="mb-6">
        <h3 class="text-sm font-medium text-gray-900 mb-4">Permission Summary</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="permission in (rbacOverview.permissions || [])" :key="permission.name">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900" x-text="permission.name"></p>
                            <p class="text-xs text-gray-500 mt-1" x-text="permission.description || ''"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-gray-900" x-text="permission.roleCount || 0"></p>
                            <p class="text-xs text-gray-500">roles</p>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

{{-- RBAC overview methods are now in the main securityPage component --}}
