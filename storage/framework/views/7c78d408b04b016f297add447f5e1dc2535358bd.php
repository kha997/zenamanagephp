<!-- Active Sessions Panel -->
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Active Sessions</h2>
            <p class="text-sm text-gray-600 mt-1">Monitor and manage active user sessions across all tenants</p>
        </div>
        <div class="flex space-x-3">
            <button @click="exportActiveSessions()" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-download mr-2"></i>
                Export CSV
            </button>
            <button @click="refreshSessions()" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh
            </button>
        </div>
    </div>

    <!-- Session Summary -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-blue-800">Total Sessions</p>
                    <p class="text-2xl font-bold text-blue-900" x-text="activeSessions.length">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-clock text-green-600 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">Recent (1h)</p>
                    <p class="text-2xl font-bold text-green-900" x-text="getRecentSessionsCount()">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-yellow-800">Long Sessions</p>
                    <p class="text-2xl font-bold text-yellow-900" x-text="getLongSessionsCount()">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-globe text-red-600 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">Suspicious</p>
                    <p class="text-2xl font-bold text-red-900" x-text="getSuspiciousSessionsCount()">0</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-4 flex flex-wrap items-center space-x-4">
        <div class="flex items-center space-x-2">
            <label for="user-filter" class="text-sm font-medium text-gray-700">User:</label>
            <input 
                type="text" 
                id="user-filter"
                x-model="filters.user"
                @input.debounce.250ms="loadActiveSessions()"
                placeholder="Filter by user..."
                class="block w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
        </div>
        
        <div class="flex items-center space-x-2">
            <label for="ip-filter" class="text-sm font-medium text-gray-700">IP:</label>
            <input 
                type="text" 
                id="ip-filter"
                x-model="filters.ip"
                @input.debounce.250ms="loadActiveSessions()"
                placeholder="Filter by IP..."
                class="block w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
        </div>
        
        <div class="flex items-center space-x-2">
            <label for="duration-filter" class="text-sm font-medium text-gray-700">Duration:</label>
            <select 
                id="duration-filter"
                x-model="filters.duration_gt"
                @change="loadActiveSessions()"
                class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
                <option value="">All</option>
                <option value="1h">> 1 hour</option>
                <option value="1d">> 1 day</option>
                <option value="7d">> 7 days</option>
                <option value="30d">> 30 days</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <input type="checkbox" @change="toggleSelectAllSessions()" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortSessions('user')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>User</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Device
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortSessions('ip')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>IP Address</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Location
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortSessions('lastSeenAt')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Last Seen</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortSessions('createdAt')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Created</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortSessions('durationSec')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Duration</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <!-- Loading State -->
                <tr x-show="loading">
                    <td colspan="9" class="px-6 py-4">
                        <div class="animate-pulse">
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        </div>
                    </td>
                </tr>

                <!-- Empty State -->
                <tr x-show="!loading && activeSessions.length === 0">
                    <td colspan="9" class="px-6 py-12 text-center">
                        <div class="text-gray-500">
                            <i class="fas fa-users text-4xl mb-4"></i>
                            <p class="text-lg font-medium">No active sessions</p>
                            <p class="text-sm mt-1">All users are currently offline.</p>
                        </div>
                    </td>
                </tr>

                <!-- Data Rows -->
                <template x-for="session in activeSessions" :key="session.id">
                    <tr class="hover:bg-gray-50" :class="getSessionRowClass(session)">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="checkbox" :value="session.id" x-model="selectedItems" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8">
                                    <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                        <i class="fas fa-user text-gray-600 text-sm"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900" x-text="session.user"></div>
                                    <div class="text-sm text-gray-500" x-text="session.tenantName || ''"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="session.device || 'Unknown'"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900" x-text="session.ip"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="session.location || 'Unknown'"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDateTime(session.lastSeenAt)"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDateTime(session.createdAt)"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm" :class="getDurationTextClass(session.durationSec)" x-text="formatDuration(session.durationSec)"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button @click="endSession(session.id)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-times mr-1"></i>
                                    End
                                </button>
                                <button @click="endAllUserSessions(session.user)" class="text-orange-600 hover:text-orange-900">
                                    <i class="fas fa-user-times mr-1"></i>
                                    End All
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
            <span class="text-sm text-red-800" x-text="`${selectedItems.length} sessions selected`"></span>
            <div class="flex space-x-2">
                <button @click="endSelectedSessions()" class="px-3 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <i class="fas fa-times mr-2"></i>
                    End Selected Sessions
                </button>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        <?php echo $__env->make('admin.security._pagination', ['panel' => 'sessions'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    </div>
</div>


<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/security/_panels/active_sessions.blade.php ENDPATH**/ ?>