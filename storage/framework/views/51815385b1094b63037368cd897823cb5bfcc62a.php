
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
    <!-- Search Bar -->
    <div class="mb-4">
        <div class="relative">
            <input type="text" 
                   x-model="searchQuery"
                   @input="performServerSearch"
                   placeholder="Search users by name, email..."
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-400"></i>
            </div>
        </div>
    </div>

    <!-- Quick Filters (Presets) -->
    <div class="mb-4">
        <div class="flex flex-wrap gap-2">
            <button @click="applyPreset('active')" 
                    :class="activePreset === 'active' ? 'bg-green-100 text-green-800 border-green-300' : 'bg-gray-100 text-gray-700 border-gray-300'"
                    class="px-3 py-1 text-sm font-medium rounded-full border hover:bg-green-50 transition-colors">
                Active
            </button>
            <button @click="applyPreset('locked')" 
                    :class="activePreset === 'locked' ? 'bg-red-100 text-red-800 border-red-300' : 'bg-gray-100 text-gray-700 border-gray-300'"
                    class="px-3 py-1 text-sm font-medium rounded-full border hover:bg-red-50 transition-colors">
                Locked
            </button>
            <button @click="applyPreset('no-mfa')" 
                    :class="activePreset === 'no-mfa' ? 'bg-orange-100 text-orange-800 border-orange-300' : 'bg-gray-100 text-gray-700 border-gray-300'"
                    class="px-3 py-1 text-sm font-medium rounded-full border hover:bg-orange-50 transition-colors">
                No-MFA
            </button>
            <button @click="applyPreset('invited')" 
                    :class="activePreset === 'invited' ? 'bg-purple-100 text-purple-800 border-purple-300' : 'bg-gray-100 text-gray-700 border-gray-300'"
                    class="px-3 py-1 text-sm font-medium rounded-full border hover:bg-purple-50 transition-colors">
                Pending Invites
            </button>
            <button @click="applyPreset('disabled')" 
                    :class="activePreset === 'disabled' ? 'bg-gray-100 text-gray-800 border-gray-300' : 'bg-gray-100 text-gray-700 border-gray-300'"
                    class="px-3 py-1 text-sm font-medium rounded-full border hover:bg-gray-50 transition-colors">
                Disabled
            </button>
        </div>
    </div>

    <!-- Advanced Filters -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <!-- Tenant Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tenant</label>
            <select x-model="tenantFilter" 
                    @change="applyFilters"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Tenants</option>
                <option value="1">Acme Corp</option>
                <option value="2">TechStart</option>
                <option value="3">Enterprise Inc</option>
            </select>
        </div>

        <!-- Role Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select x-model="roleFilter" 
                    @change="applyFilters"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Roles</option>
                <option value="SuperAdmin">Super Admin</option>
                <option value="TenantAdmin">Tenant Admin</option>
                <option value="PM">Project Manager</option>
                <option value="Staff">Staff</option>
                <option value="Viewer">Viewer</option>
            </select>
        </div>

        <!-- Status Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select x-model="statusFilter" 
                    @change="applyFilters"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="disabled">Disabled</option>
                <option value="locked">Locked</option>
                <option value="invited">Invited</option>
            </select>
        </div>

        <!-- MFA Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">MFA</label>
            <select x-model="mfaFilter" 
                    @change="applyFilters"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All</option>
                <option value="true">Enabled</option>
                <option value="false">Not Enabled</option>
            </select>
        </div>
    </div>

    <!-- Date Range Filters -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <!-- Active Within -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Active Within</label>
            <select x-model="activeWithinFilter" 
                    @change="applyFilters"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Any Time</option>
                <option value="7d">Last 7 days</option>
                <option value="30d">Last 30 days</option>
                <option value="90d">Last 90 days</option>
            </select>
        </div>

        <!-- Last Login From -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Last Login From</label>
            <input type="date" 
                   x-model="lastLoginFrom" 
                   @change="applyFilters"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <!-- Last Login To -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Last Login To</label>
            <input type="date" 
                   x-model="lastLoginTo" 
                   @change="applyFilters"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <!-- Created From -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Created From</label>
            <input type="date" 
                   x-model="createdFrom" 
                   @change="applyFilters"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
    </div>

    <!-- Active Filters & Clear -->
    <div class="flex items-center justify-between">
        <div>
            <span x-show="hasActiveFilters" class="text-sm text-gray-600">Active filters:</span>
            <div x-show="hasActiveFilters" class="flex flex-wrap gap-2 mt-1">
                <span x-show="searchQuery" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    Search: <span x-text="searchQuery"></span>
                    <button @click="searchQuery = ''; applyFilters()" class="ml-1 text-blue-600 hover:text-blue-800">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
                <span x-show="tenantFilter" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Tenant: <span x-text="tenantFilter"></span>
                    <button @click="tenantFilter = ''; applyFilters()" class="ml-1 text-green-600 hover:text-green-800">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
                <span x-show="roleFilter" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    Role: <span x-text="roleFilter"></span>
                    <button @click="roleFilter = ''; applyFilters()" class="ml-1 text-purple-600 hover:text-purple-800">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
                <span x-show="statusFilter" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                    Status: <span x-text="statusFilter"></span>
                    <button @click="statusFilter = ''; applyFilters()" class="ml-1 text-orange-600 hover:text-orange-800">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
                <span x-show="mfaFilter" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                    MFA: <span x-text="mfaFilter"></span>
                    <button @click="mfaFilter = ''; applyFilters()" class="ml-1 text-red-600 hover:text-red-800">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            </div>
        </div>
        <button @click="clearFilters" 
                x-show="hasActiveFilters"
                class="text-sm text-gray-500 hover:text-gray-700 flex items-center">
            <i class="fas fa-times mr-1"></i>Clear All
        </button>
    </div>

    <!-- Bulk Actions -->
    <div x-show="selectedUsers.length > 0" class="mt-4 p-3 bg-blue-50 rounded-lg">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-blue-900">
                <span x-text="selectedUsers.length"></span> user(s) selected
            </span>
            <div class="flex items-center space-x-2">
                <button @click="bulkAction('enable')" 
                        class="text-xs bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 transition-colors">
                    Enable
                </button>
                <button @click="bulkAction('disable')" 
                        class="text-xs bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition-colors">
                    Disable
                </button>
                <button @click="bulkAction('unlock')" 
                        class="text-xs bg-yellow-600 text-white px-3 py-1 rounded hover:bg-yellow-700 transition-colors">
                    Unlock
                </button>
                <button @click="bulkAction('change-role')" 
                        class="text-xs bg-purple-600 text-white px-3 py-1 rounded hover:bg-purple-700 transition-colors">
                    Change Role
                </button>
                <button @click="bulkAction('force-mfa')" 
                        class="text-xs bg-orange-600 text-white px-3 py-1 rounded hover:bg-orange-700 transition-colors">
                    Force MFA
                </button>
                <button @click="bulkAction('send-reset')" 
                        class="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition-colors">
                    Send Reset
                </button>
                <button @click="bulkAction('delete')" 
                        class="text-xs bg-gray-600 text-white px-3 py-1 rounded hover:bg-gray-700 transition-colors">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/users/_filters.blade.php ENDPATH**/ ?>