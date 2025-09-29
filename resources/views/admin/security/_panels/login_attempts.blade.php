<!-- Login Attempts Panel -->
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Login Attempts</h2>
            <p class="text-sm text-gray-600 mt-1">Monitor successful and failed login attempts across all tenants</p>
        </div>
        <div class="flex space-x-3">
            <button @click="exportLoginAttempts()" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-download mr-2"></i>
                Export CSV
            </button>
            <button @click="showBlockIpModal = true" class="px-3 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                <i class="fas fa-ban mr-2"></i>
                Block IP
            </button>
        </div>
    </div>

    <!-- Login Attempts Chart -->
    <div class="mb-6">
        <div class="bg-gray-50 rounded-lg p-4">
            <h3 class="text-sm font-medium text-gray-900 mb-4">Success vs Failed Logins Over Time</h3>
            <div class="h-64 flex items-center justify-center">
                <div class="text-center">
                    <i class="fas fa-chart-bar text-4xl text-gray-400 mb-2"></i>
                    <p class="text-gray-500">Login attempts chart will be displayed here</p>
                    <p class="text-xs text-gray-400 mt-1">Chart.js integration pending</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-4 flex flex-wrap items-center space-x-4">
        <div class="flex items-center space-x-2">
            <label for="outcome-filter" class="text-sm font-medium text-gray-700">Outcome:</label>
            <select 
                id="outcome-filter"
                x-model="filters.outcome"
                @change="loadLoginAttempts()"
                class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
                <option value="">All</option>
                <option value="success">Success</option>
                <option value="failed">Failed</option>
            </select>
        </div>
        
        <div class="flex items-center space-x-2">
            <label for="ip-filter" class="text-sm font-medium text-gray-700">IP:</label>
            <input 
                type="text" 
                id="ip-filter"
                x-model="filters.ip"
                @input.debounce.250ms="loadLoginAttempts()"
                placeholder="Filter by IP..."
                class="block w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
        </div>
        
        <div class="flex items-center space-x-2">
            <label for="user-filter" class="text-sm font-medium text-gray-700">User:</label>
            <input 
                type="text" 
                id="user-filter"
                x-model="filters.user"
                @input.debounce.250ms="loadLoginAttempts()"
                placeholder="Filter by user..."
                class="block w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <input type="checkbox" @change="toggleSelectAllLogins()" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortLoginAttempts('ts')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Time</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortLoginAttempts('userEmail')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>User</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortLoginAttempts('ip')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>IP Address</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Location
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortLoginAttempts('outcome')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Outcome</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Reason
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
                <tr x-show="!loading && loginAttempts.length === 0">
                    <td colspan="8" class="px-6 py-12 text-center">
                        <div class="text-gray-500">
                            <i class="fas fa-sign-in-alt text-4xl mb-4"></i>
                            <p class="text-lg font-medium">No login attempts found</p>
                            <p class="text-sm mt-1">Try adjusting your filters or time range.</p>
                        </div>
                    </td>
                </tr>

                <!-- Data Rows -->
                <template x-for="attempt in loginAttempts" :key="attempt.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="checkbox" :value="attempt.id" x-model="selectedItems" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="formatDateTime(attempt.ts)"></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900" x-text="attempt.userEmail || 'Unknown'"></div>
                            <div class="text-sm text-gray-500" x-text="attempt.tenantName || ''"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono" x-text="attempt.ip"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="attempt.country || 'Unknown'"></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  :class="attempt.outcome === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                  x-text="attempt.outcome === 'success' ? 'Success' : 'Failed'"></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="attempt.reason || '-'"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button @click="lockUser(attempt.userEmail)" x-show="attempt.outcome === 'failed'" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-lock mr-1"></i>
                                    Lock User
                                </button>
                                <button @click="blockIp(attempt.ip)" x-show="attempt.outcome === 'failed'" class="text-orange-600 hover:text-orange-900">
                                    <i class="fas fa-ban mr-1"></i>
                                    Block IP
                                </button>
                                <button @click="viewInAudit(attempt.id)" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-search mr-1"></i>
                                    Audit
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Bulk Actions -->
    <div x-show="selectedItems.length > 0" class="mt-4 p-4 bg-red-50 rounded-lg">
        <div class="flex items-center justify-between">
            <span class="text-sm text-red-800" x-text="`${selectedItems.length} attempts selected`"></span>
            <div class="flex space-x-2">
                <button @click="blockSelectedIps()" class="px-3 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <i class="fas fa-ban mr-2"></i>
                    Block Selected IPs
                </button>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        @include('admin.security._pagination', ['panel' => 'logins'])
    </div>
</div>

{{-- Login attempts methods are now in the main securityPage component --}}
