<!-- MFA Adoption Panel -->
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">MFA Adoption</h2>
            <p class="text-sm text-gray-600 mt-1">Monitor multi-factor authentication usage across all tenants</p>
        </div>
        <div class="flex space-x-3">
            <button @click="exportMfaUsers()" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-download mr-2"></i>
                Export CSV
            </button>
            <button @click="showForceMfaModal = true" class="px-3 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-user-shield mr-2"></i>
                Force MFA
            </button>
        </div>
    </div>

    <!-- MFA Adoption Chart -->
    <div class="mb-6">
        <div class="bg-gray-50 rounded-lg p-4">
            <h3 class="text-sm font-medium text-gray-900 mb-4">Adoption Rate Over Time</h3>
            <div class="relative h-64 w-full">
                <canvas 
                    id="mfa-adoption-panel-chart"
                    width="100%" 
                    height="100%"
                    class="chart-canvas"
                    aria-label="MFA adoption percentage over time">
                </canvas>
                
                <!-- Loading State -->
                <div x-show="chartsLoading" class="absolute inset-0 flex items-center justify-center">
                    <div class="flex items-center space-x-2 text-gray-500">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                        <span class="text-sm">Loading...</span>
                    </div>
                </div>
                
                <!-- Error State -->
                <div x-show="chartError" class="absolute inset-0 flex flex-col items-center justify-center text-red-500">
                    <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                    <p class="text-sm">Chart Error</p>
                    <p class="text-xs mt-1" x-text="chartError"></p>
                    <button @click="initCharts()" class="mt-2 px-3 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600">
                        Retry
                    </button>
                </div>
                
                <!-- Empty State -->
                <div x-show="!chartError && !chartsLoading && !chartData.mfaAdoption" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400">
                    <i class="fas fa-chart-line text-3xl mb-2"></i>
                    <p class="text-sm">No data available</p>
                </div>
            </div>
        </div>
    </div>

    <!-- No-MFA Users Table -->
    <div class="mb-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-900">Users Without MFA</h3>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500" x-text="`${mfaUsers.length} users`"></span>
                <button @click="selectAllNoMfa()" class="text-xs text-blue-600 hover:text-blue-800">
                    Select All
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <input type="checkbox" @change="toggleSelectAll()" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        User
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Tenant
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Role
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Last Login
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <!-- Loading State -->
                <tr x-show="loading">
                    <td colspan="6" class="px-6 py-4">
                        <div class="animate-pulse">
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        </div>
                    </td>
                </tr>

                <!-- Empty State -->
                <tr x-show="!loading && mfaUsers.length === 0">
                    <td colspan="6" class="px-6 py-12 text-center">
                        <div class="text-gray-500">
                            <i class="fas fa-user-shield text-4xl mb-4"></i>
                            <p class="text-lg font-medium">All users have MFA enabled</p>
                            <p class="text-sm mt-1">Great job! Your security posture is strong.</p>
                        </div>
                    </td>
                </tr>

                <!-- Data Rows -->
                <template x-for="user in mfaUsers" :key="user.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="checkbox" :value="user.id" x-model="selectedItems" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8">
                                    <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                        <i class="fas fa-user text-gray-600 text-sm"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900" x-text="user.name"></div>
                                    <div class="text-sm text-gray-500" x-text="user.email"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="user.tenantName || 'N/A'"></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  :class="getRoleBadgeClass(user.role)" x-text="user.role"></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span x-text="formatDate(user.last_login_at)" title="Last login time in UTC"></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button @click="forceMfaForUser(user.id)" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-user-shield mr-1"></i>
                                    Force MFA
                                </button>
                                <button @click="sendMfaReminder(user.id)" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-envelope mr-1"></i>
                                    Remind
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Bulk Actions -->
    <div x-show="selectedItems.length > 0" class="mt-4 p-4 bg-blue-50 rounded-lg">
        <div class="flex items-center justify-between">
            <span class="text-sm text-blue-800" x-text="`${selectedItems.length} users selected`"></span>
            <div class="flex space-x-2">
                <button @click="forceMfaForSelected()" class="px-3 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <i class="fas fa-user-shield mr-2"></i>
                    Force MFA for Selected
                </button>
                <button @click="sendMfaReminderForSelected()" class="px-3 py-2 text-sm text-green-700 bg-green-100 border border-green-200 rounded-md hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <i class="fas fa-envelope mr-2"></i>
                    Send Reminders
                </button>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        @include('admin.security._pagination', ['panel' => 'mfa'])
    </div>
</div>

{{-- MFA-specific methods are now in the main securityPage component --}}
