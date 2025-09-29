<!-- Audit Logs Panel -->
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Audit Logs</h2>
            <p class="text-sm text-gray-600 mt-1">Real-time security and system activity across all tenants</p>
        </div>
        <div class="flex space-x-3">
            <button @click="exportAuditLogs()" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-download mr-2"></i>
                Export CSV
            </button>
            <button @click="refreshAuditLogs()" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh
            </button>
        </div>
    </div>

    <!-- Audit Logs Filters -->
    <div class="mb-4 flex flex-wrap items-center space-x-4">
        <div class="flex items-center space-x-2">
            <label for="action-filter" class="text-sm font-medium text-gray-700">Action:</label>
            <select 
                id="action-filter"
                x-model="filters.action"
                @change="loadAuditLogs()"
                class="block w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
                <option value="">All Actions</option>
                <option value="login">Login</option>
                <option value="logout">Logout</option>
                <option value="create">Create</option>
                <option value="update">Update</option>
                <option value="delete">Delete</option>
                <option value="export">Export</option>
                <option value="admin">Admin Action</option>
            </select>
        </div>
        
        <div class="flex items-center space-x-2">
            <label for="actor-filter" class="text-sm font-medium text-gray-700">Actor:</label>
            <input 
                type="text" 
                id="actor-filter"
                x-model="filters.actor"
                @input.debounce.250ms="loadAuditLogs()"
                placeholder="Filter by actor..."
                class="block w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
        </div>
        
        <div class="flex items-center space-x-2">
            <label for="target-type-filter" class="text-sm font-medium text-gray-700">Target Type:</label>
            <select 
                id="target-type-filter"
                x-model="filters.targetType"
                @change="loadAuditLogs()"
                class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
                <option value="">All</option>
                <option value="user">User</option>
                <option value="tenant">Tenant</option>
                <option value="project">Project</option>
                <option value="task">Task</option>
                <option value="file">File</option>
            </select>
        </div>
        
        <div class="flex items-center space-x-2">
            <label for="result-filter" class="text-sm font-medium text-gray-700">Result:</label>
            <select 
                id="result-filter"
                x-model="filters.result"
                @change="loadAuditLogs()"
                class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
                <option value="">All</option>
                <option value="success">Success</option>
                <option value="failed">Failed</option>
            </select>
        </div>
    </div>

    <!-- Real-time Status -->
    <div class="mb-4 flex items-center space-x-4">
        <div class="flex items-center space-x-2">
            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
            <span class="text-sm text-gray-600">Real-time updates enabled</span>
        </div>
        <div class="text-sm text-gray-500">
            Last updated: <span x-text="new Date().toLocaleTimeString()"></span>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortAuditLogs('ts')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Timestamp</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortAuditLogs('actor')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Actor</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortAuditLogs('action')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Action</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Target
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortAuditLogs('result')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Result</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        IP Address
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Tenant
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <!-- Loading State -->
                <tr x-show="loading">
                    <td colspan="8" class="px-6 py-4">
                        <div class="animate-pulse">
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        </div>
                    </td>
                </tr>

                <!-- Empty State -->
                <tr x-show="!loading && auditLogs.length === 0">
                    <td colspan="8" class="px-6 py-12 text-center">
                        <div class="text-gray-500">
                            <i class="fas fa-clipboard-list text-4xl mb-4"></i>
                            <p class="text-lg font-medium">No audit logs found</p>
                            <p class="text-sm mt-1">Try adjusting your filters or time range.</p>
                        </div>
                    </td>
                </tr>

                <!-- Data Rows -->
                <template x-for="log in auditLogs" :key="log.id">
                    <tr class="hover:bg-gray-50" :class="getSeverityRowClass(log.severity)">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="formatDateTime(log.ts)"></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900" x-text="log.actor"></div>
                            <div class="text-sm text-gray-500" x-text="log.ua || ''"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  :class="getActionBadgeClass(log.action)" x-text="log.action"></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="log.target || '-'"></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  :class="log.result === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                  x-text="log.result === 'success' ? 'Success' : 'Failed'"></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono" x-text="log.ip || '-'"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="log.tenantName || '-'"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button @click="viewAuditDetail(log.id)" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-eye mr-1"></i>
                                View Detail
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        <?php echo $__env->make('admin.security._pagination', ['panel' => 'audit'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    </div>
</div>


<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/security/_panels/audit_logs.blade.php ENDPATH**/ ?>