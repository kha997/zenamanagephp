<!-- IP Access Panel -->
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">IP Access Control</h2>
            <p class="text-sm text-gray-600 mt-1">Manage IP allowlist and denylist for enhanced security</p>
        </div>
        <div class="flex space-x-3">
            <button @click="exportIpLists()" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-download mr-2"></i>
                Export CSV
            </button>
            <button @click="showBlockIpModal = true" class="px-3 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                <i class="fas fa-ban mr-2"></i>
                Block IP
            </button>
            <button @click="addToAllowlist()" class="px-3 py-2 text-sm text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                <i class="fas fa-check mr-2"></i>
                Allow IP
            </button>
        </div>
    </div>

    <!-- IP Lists Summary -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-red-800">Blocked IPs</p>
                    <p class="text-2xl font-bold text-red-900" x-text="ipDenylist.length">0</p>
                </div>
                <i class="fas fa-ban text-red-600 text-2xl"></i>
            </div>
        </div>
        
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-green-800">Allowed IPs</p>
                    <p class="text-2xl font-bold text-green-900" x-text="ipAllowlist.length">0</p>
                </div>
                <i class="fas fa-check text-green-600 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-6">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <button @click="activeIpTab = 'denylist'" 
                        :class="activeIpTab === 'denylist' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-ban mr-2"></i>
                    Blocked IPs (<span x-text="ipDenylist.length"></span>)
                </button>
                <button @click="activeIpTab = 'allowlist'" 
                        :class="activeIpTab === 'allowlist' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-check mr-2"></i>
                    Allowed IPs (<span x-text="ipAllowlist.length"></span>)
                </button>
            </nav>
        </div>
    </div>

    <!-- Denylist Tab -->
    <div x-show="activeIpTab === 'denylist'">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-900">Blocked IP Addresses</h3>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500" x-text="`${ipDenylist.length} entries`"></span>
                <button @click="selectAllDenylist()" class="text-xs text-blue-600 hover:text-blue-800">
                    Select All
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input type="checkbox" @change="toggleSelectAllDenylist()" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            CIDR Block
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Note
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Added By
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Added At
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
                    <tr x-show="!loading && ipDenylist.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="text-gray-500">
                                <i class="fas fa-ban text-4xl mb-4"></i>
                                <p class="text-lg font-medium">No blocked IPs</p>
                                <p class="text-sm mt-1">All IP addresses are currently allowed.</p>
                            </div>
                        </td>
                    </tr>

                    <!-- Data Rows -->
                    <template x-for="entry in ipDenylist" :key="entry.cidr">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" :value="entry.cidr" x-model="selectedItems" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900" x-text="entry.cidr"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="entry.note || '-'"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="entry.addedBy"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDateTime(entry.addedAt)"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button @click="unblockIp(entry.cidr)" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-check mr-1"></i>
                                        Unblock
                                    </button>
                                    <button @click="moveToAllowlist(entry.cidr)" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-arrow-right mr-1"></i>
                                        Allow
                                    </button>
                                    <button @click="deleteIpEntry(entry.cidr, 'denylist')" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash mr-1"></i>
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Allowlist Tab -->
    <div x-show="activeIpTab === 'allowlist'">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-900">Allowed IP Addresses</h3>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500" x-text="`${ipAllowlist.length} entries`"></span>
                <button @click="selectAllAllowlist()" class="text-xs text-blue-600 hover:text-blue-800">
                    Select All
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input type="checkbox" @change="toggleSelectAllAllowlist()" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            CIDR Block
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Note
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Added By
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Added At
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
                    <tr x-show="!loading && ipAllowlist.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="text-gray-500">
                                <i class="fas fa-check text-4xl mb-4"></i>
                                <p class="text-lg font-medium">No allowed IPs</p>
                                <p class="text-sm mt-1">All IP addresses are currently blocked by default.</p>
                            </div>
                        </td>
                    </tr>

                    <!-- Data Rows -->
                    <template x-for="entry in ipAllowlist" :key="entry.cidr">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" :value="entry.cidr" x-model="selectedItems" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900" x-text="entry.cidr"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="entry.note || '-'"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="entry.addedBy"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDateTime(entry.addedAt)"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button @click="blockIp(entry.cidr)" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-ban mr-1"></i>
                                        Block
                                    </button>
                                    <button @click="moveToDenylist(entry.cidr)" class="text-orange-600 hover:text-orange-900">
                                        <i class="fas fa-arrow-right mr-1"></i>
                                        Deny
                                    </button>
                                    <button @click="deleteIpEntry(entry.cidr, 'allowlist')" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash mr-1"></i>
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bulk Actions -->
    <div x-show="selectedItems.length > 0" class="mt-4 p-4 bg-gray-50 rounded-lg">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-800" x-text="`${selectedItems.length} entries selected`"></span>
            <div class="flex space-x-2">
                <button @click="bulkUnblock()" x-show="activeIpTab === 'denylist'" class="px-3 py-2 text-sm text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <i class="fas fa-check mr-2"></i>
                    Unblock Selected
                </button>
                <button @click="bulkBlock()" x-show="activeIpTab === 'allowlist'" class="px-3 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <i class="fas fa-ban mr-2"></i>
                    Block Selected
                </button>
                <button @click="bulkDelete()" class="px-3 py-2 text-sm text-white bg-gray-600 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    <i class="fas fa-trash mr-2"></i>
                    Delete Selected
                </button>
            </div>
        </div>
    </div>
</div>

{{-- IP access methods are now in the main securityPage component --}}
