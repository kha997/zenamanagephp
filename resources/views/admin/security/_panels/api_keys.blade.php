<!-- API Keys Panel -->
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">API Keys & Service Accounts</h2>
            <p class="text-sm text-gray-600 mt-1">Manage API keys, service accounts, and monitor usage patterns</p>
        </div>
        <div class="flex space-x-3">
            <button @click="exportApiKeys()" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-download mr-2"></i>
                Export CSV
            </button>
            <button @click="createApiKey()" class="px-3 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-plus mr-2"></i>
                Create Key
            </button>
        </div>
    </div>

    <!-- Risk Summary -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">High Risk Keys</p>
                    <p class="text-2xl font-bold text-red-900" x-text="getHighRiskKeysCount()">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-yellow-800">Expiring Soon</p>
                    <p class="text-2xl font-bold text-yellow-900" x-text="getExpiringKeysCount()">0</p>
                </div>
            </div>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-key text-blue-600 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-blue-800">Total Keys</p>
                    <p class="text-2xl font-bold text-blue-900" x-text="apiKeys.length">0</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-4 flex flex-wrap items-center space-x-4">
        <div class="flex items-center space-x-2">
            <label for="risk-filter" class="text-sm font-medium text-gray-700">Risk Level:</label>
            <select 
                id="risk-filter"
                x-model="filters.risk"
                @change="loadApiKeys()"
                class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
                <option value="">All</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
            </select>
        </div>
        
        <div class="flex items-center space-x-2">
            <label for="owner-filter" class="text-sm font-medium text-gray-700">Owner:</label>
            <input 
                type="text" 
                id="owner-filter"
                x-model="filters.owner"
                @input.debounce.250ms="loadApiKeys()"
                placeholder="Filter by owner..."
                class="block w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
        </div>
        
        <div class="flex items-center space-x-2">
            <label for="scope-filter" class="text-sm font-medium text-gray-700">Scope:</label>
            <select 
                id="scope-filter"
                x-model="filters.scope"
                @change="loadApiKeys()"
                class="block w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
                <option value="">All</option>
                <option value="read">Read</option>
                <option value="write">Write</option>
                <option value="admin">Admin</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <input type="checkbox" @change="toggleSelectAllKeys()" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortApiKeys('id')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Key ID</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortApiKeys('owner')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Owner</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Scope
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortApiKeys('lastUsedAt')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Last Used</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortApiKeys('createdAt')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Created</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortApiKeys('expiresAt')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Expires</span>
                            <i class="fas fa-sort text-xs"></i>
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <button @click="sortApiKeys('risk')" class="flex items-center space-x-1 hover:text-gray-700">
                            <span>Risk</span>
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
                <tr x-show="!loading && apiKeys.length === 0">
                    <td colspan="9" class="px-6 py-12 text-center">
                        <div class="text-gray-500">
                            <i class="fas fa-key text-4xl mb-4"></i>
                            <p class="text-lg font-medium">No API keys found</p>
                            <p class="text-sm mt-1">Create your first API key to get started.</p>
                        </div>
                    </td>
                </tr>

                <!-- Data Rows -->
                <template x-for="key in apiKeys" :key="key.id">
                    <tr class="hover:bg-gray-50" :class="getKeyRowClass(key)">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="checkbox" :value="key.id" x-model="selectedItems" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-mono text-gray-900" x-text="maskKeyId(key.id)"></div>
                            <div class="text-xs text-gray-500" x-text="`Age: ${key.rotationAgeDays}d`"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="key.owner"></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex flex-wrap gap-1">
                                <template x-for="scope in key.scope" :key="scope">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800" x-text="scope"></span>
                                </template>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDate(key.lastUsedAt)"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDate(key.createdAt)"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm" :class="getExpiryTextClass(key.expiresAt)" x-text="formatDate(key.expiresAt)"></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  :class="getRiskBadgeClass(key.risk)" x-text="key.risk"></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button @click="viewKeyUsage(key.id)" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-chart-line mr-1"></i>
                                    Usage
                                </button>
                                <button @click="rotateKey(key.id)" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-sync-alt mr-1"></i>
                                    Rotate
                                </button>
                                <button @click="editKeyScope(key.id)" class="text-yellow-600 hover:text-yellow-900">
                                    <i class="fas fa-edit mr-1"></i>
                                    Edit
                                </button>
                                <button @click="revokeKey(key.id)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-ban mr-1"></i>
                                    Revoke
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Bulk Actions -->
    <div x-show="selectedItems.length > 0" class="mt-4 p-4 bg-orange-50 rounded-lg">
        <div class="flex items-center justify-between">
            <span class="text-sm text-orange-800" x-text="`${selectedItems.length} keys selected`"></span>
            <div class="flex space-x-2">
                <button @click="rotateSelectedKeys()" class="px-3 py-2 text-sm text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <i class="fas fa-sync-alt mr-2"></i>
                    Rotate Selected
                </button>
                <button @click="revokeSelectedKeys()" class="px-3 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <i class="fas fa-ban mr-2"></i>
                    Revoke Selected
                </button>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        @include('admin.security._pagination', ['panel' => 'keys'])
    </div>
</div>

{{-- API keys methods are now in the main securityPage component --}}
